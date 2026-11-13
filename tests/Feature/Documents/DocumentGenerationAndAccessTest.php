<?php

namespace Tests\Feature\Documents;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\DocumentRequest;
use Modules\Documents\Entities\DocumentTemplate;
use Modules\Enrollment\Entities\Student;
use Modules\PortailParent\Entities\ParentModel;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Documents officiels : génération (attestations, certificats de scolarité,
 * transfert, relevés) et accès par rôle au téléchargement PDF.
 */
class DocumentGenerationAndAccessTest extends TestCase
{
    use InteractsWithTenancy;

    private static bool $documentsMigrated = false;

    private string $adminToken;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->migrateDocumentTables();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        Storage::fake('tenant');

        Permission::firstOrCreate(['name' => 'view children documents', 'guard_name' => 'tenant']);
        foreach (['Administrator', 'Manager', 'Élève', 'Parent'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'tenant'], ['display_name' => $roleName]);
        }
        Role::where('name', 'Parent')->where('guard_name', 'tenant')->first()
            ->givePermissionTo('view children documents');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($admin, 'Administrator');
        $this->adminToken = $admin->createToken('test-token')->plainTextToken;

        AcademicYear::query()->update(['is_active' => false]);
        $this->academicYear = AcademicYear::factory()->active()->create(['name' => '2090-2091']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Documents (and Attendance, used by attendance certificates) tables are not part of the
     * shared test migrations: run them once, outside the per-test transaction (DDL auto-commits).
     */
    private function migrateDocumentTables(): void
    {
        if (self::$documentsMigrated) {
            return;
        }

        DB::connection('tenant')->rollBack();

        foreach (['Timetable', 'Attendance', 'Documents'] as $module) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path("Modules/{$module}/Database/Migrations/tenant"),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        self::$documentsMigrated = true;

        DB::connection('tenant')->beginTransaction();
    }

    /**
     * @return array{0: Student, 1: string}
     */
    private function studentWithAccount(): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Élève');
        $student = Student::factory()->create(['user_id' => $user->id]);

        return [$student, $user->createToken('test-token')->plainTextToken];
    }

    /**
     * @return array{0: Student, 1: string}
     */
    private function parentWithChild(): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $child = Student::factory()->create();
        $parent->students()->attach($child->id, [
            'is_primary_contact' => true,
            'is_financial_responsible' => true,
        ]);

        return [$child, $user->createToken('test-token')->plainTextToken];
    }

    private function generateSchoolingCertificate(Student $student): Document
    {
        $response = $this->withToken($this->adminToken)->postJson('/api/admin/documents/certificates/schooling', [
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response->assertCreated();

        return Document::findOrFail($response->json('document.id'));
    }

    #[Test]
    public function admin_generates_schooling_certificate_with_default_template(): void
    {
        $student = Student::factory()->create(['firstname' => 'Aïcha', 'lastname' => 'Moussa']);

        $document = $this->generateSchoolingCertificate($student);

        $this->assertSame('certificate_schooling', $document->document_type);
        $this->assertSame('issued', $document->status);
        $this->assertStringStartsWith('CSC-2090-2091-', $document->document_number);
        $this->assertNotNull($document->issued_by);
        Storage::disk('tenant')->assertExists($document->pdf_path);
        Storage::disk('tenant')->assertExists($document->qr_code_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('tenant')->get($document->pdf_path));
        $this->assertTrue(DocumentTemplate::where('type', 'certificate_schooling')->where('is_active', true)->exists());
    }

    #[Test]
    public function document_numbers_are_sequential(): void
    {
        $student = Student::factory()->create();

        $first = $this->generateSchoolingCertificate($student);
        $second = $this->generateSchoolingCertificate($student);

        $this->assertNotSame($first->document_number, $second->document_number);
        $this->assertSame(1, DocumentTemplate::where('type', 'certificate_schooling')->count());
    }

    #[Test]
    public function admin_generates_enrollment_status_and_attendance_certificates(): void
    {
        $student = Student::factory()->create(['birthplace' => null]);

        foreach (['enrollment', 'status', 'attendance'] as $type) {
            $this->withToken($this->adminToken)->postJson("/api/admin/documents/certificates/{$type}", [
                'student_id' => $student->id,
                'academic_year_id' => $this->academicYear->id,
            ])->assertCreated()->assertJsonPath('document.document_type', "certificate_{$type}");
        }

        $attendance = Document::where('document_type', 'certificate_attendance')->firstOrFail();
        $this->assertSame($student->id, $attendance->student_id);
    }

    #[Test]
    public function admin_generates_transfer_certificate(): void
    {
        $student = Student::factory()->create();

        $this->withToken($this->adminToken)->postJson('/api/admin/documents/certificates/transfer', [
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'transfer_destination' => 'Lycée Issa Korombé',
            'transfer_reason' => 'Mutation des parents',
        ])
            ->assertCreated()
            ->assertJsonPath('document.document_type', 'certificate_transfer')
            ->assertJsonPath('document.metadata.transfer_destination', 'Lycée Issa Korombé');
    }

    #[Test]
    public function transfer_certificate_requires_destination_and_reason(): void
    {
        $student = Student::factory()->create();

        $this->withToken($this->adminToken)->postJson('/api/admin/documents/certificates/transfer', [
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['transfer_destination', 'transfer_reason']);
    }

    #[Test]
    public function admin_generates_global_transcript(): void
    {
        $student = Student::factory()->create();

        $this->withToken($this->adminToken)->postJson('/api/admin/documents/transcripts/global', [
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
        ])->assertCreated()->assertJsonPath('document.document_type', 'transcript_global');
    }

    #[Test]
    public function admin_approves_request_and_certificate_is_generated(): void
    {
        $student = Student::factory()->create();
        $requestId = $this->withToken($this->adminToken)->postJson('/api/admin/documents/certificates/requests', [
            'student_id' => $student->id,
            'document_type' => 'certificate_schooling',
            'reason' => 'Constitution du dossier de bourse',
        ])->assertCreated()->json('request.id');

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/documents/certificates/requests/{$requestId}/approve", ['notes' => 'OK'])
            ->assertOk()
            ->assertJsonPath('request.status', 'completed');

        $this->assertSame(1, Document::where('student_id', $student->id)->count());

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/documents/certificates/requests/{$requestId}/approve")
            ->assertUnprocessable();
    }

    #[Test]
    public function approving_request_needing_extra_data_is_rejected(): void
    {
        $student = Student::factory()->create();
        $documentRequest = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'certificate_transfer',
            'quantity' => 1,
            'reason' => 'Départ vers un autre lycée',
            'urgency' => 'normal',
            'request_date' => now(),
            'status' => 'pending',
            'fee_amount' => 0,
            'fee_paid' => false,
        ]);

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/documents/certificates/requests/{$documentRequest->id}/approve")
            ->assertUnprocessable();

        $this->assertSame('pending', $documentRequest->fresh()->status);
    }

    #[Test]
    public function admin_rejects_request(): void
    {
        $student = Student::factory()->create();
        $requestId = $this->withToken($this->adminToken)->postJson('/api/admin/documents/certificates/requests', [
            'student_id' => $student->id,
            'document_type' => 'certificate_enrollment',
            'reason' => 'Demande de visa pour un voyage',
        ])->json('request.id');

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/documents/certificates/requests/{$requestId}/reject", ['reason' => 'Dossier incomplet, pièces manquantes'])
            ->assertOk()
            ->assertJsonPath('request.status', 'rejected');
    }

    #[Test]
    public function admin_downloads_document_pdf(): void
    {
        $document = $this->generateSchoolingCertificate(Student::factory()->create());

        $response = $this->withToken($this->adminToken)->get("/api/admin/documents/files/{$document->id}/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function download_returns_404_when_pdf_file_is_missing(): void
    {
        $document = $this->generateSchoolingCertificate(Student::factory()->create());
        Storage::disk('tenant')->delete($document->pdf_path);

        $this->withToken($this->adminToken)
            ->getJson("/api/admin/documents/files/{$document->id}/download")
            ->assertNotFound();
    }

    #[Test]
    public function student_lists_and_downloads_own_documents(): void
    {
        [$student, $token] = $this->studentWithAccount();
        $document = $this->generateSchoolingCertificate($student);

        $this->withToken($token)
            ->getJson("/api/admin/documents/students/{$student->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $document->id)
            ->assertJsonPath('data.0.download_url', "/admin/documents/files/{$document->id}/download");

        $this->withToken($token)
            ->get("/api/admin/documents/files/{$document->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function student_cannot_access_another_students_documents(): void
    {
        [, $token] = $this->studentWithAccount();
        $otherStudent = Student::factory()->create();
        $document = $this->generateSchoolingCertificate($otherStudent);

        $this->withToken($token)
            ->getJson("/api/admin/documents/students/{$otherStudent->id}")
            ->assertForbidden();

        $this->withToken($token)
            ->getJson("/api/admin/documents/files/{$document->id}/download")
            ->assertForbidden();
    }

    #[Test]
    public function parent_downloads_own_child_document(): void
    {
        [$child, $token] = $this->parentWithChild();
        $document = $this->generateSchoolingCertificate($child);

        $this->withToken($token)
            ->getJson("/api/admin/documents/students/{$child->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $document->id);

        $this->withToken($token)
            ->get("/api/admin/documents/files/{$document->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function parent_cannot_download_another_students_document(): void
    {
        [, $token] = $this->parentWithChild();
        $otherStudent = Student::factory()->create();
        $document = $this->generateSchoolingCertificate($otherStudent);

        $this->withToken($token)
            ->getJson("/api/admin/documents/files/{$document->id}/download")
            ->assertForbidden();

        $this->withToken($token)
            ->getJson("/api/admin/documents/students/{$otherStudent->id}")
            ->assertForbidden();
    }

    #[Test]
    public function parent_cannot_download_cancelled_document_of_own_child(): void
    {
        [$child, $token] = $this->parentWithChild();
        $document = $this->generateSchoolingCertificate($child);
        $document->cancel('Erreur de saisie');

        $this->withToken($token)
            ->getJson("/api/admin/documents/files/{$document->id}/download")
            ->assertForbidden();

        $this->withToken($token)
            ->getJson("/api/admin/documents/students/{$child->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function parent_and_student_cannot_generate_documents(): void
    {
        [$student, $studentToken] = $this->studentWithAccount();
        [, $parentToken] = $this->parentWithChild();

        foreach ([$studentToken, $parentToken] as $token) {
            $this->withToken($token)->postJson('/api/admin/documents/certificates/schooling', [
                'student_id' => $student->id,
                'academic_year_id' => $this->academicYear->id,
            ])->assertForbidden();
        }
    }

    #[Test]
    public function other_roles_cannot_download_documents(): void
    {
        $document = $this->generateSchoolingCertificate(Student::factory()->create());
        $cashier = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($cashier, 'Caissier');

        $this->withToken($cashier->createToken('test-token')->plainTextToken)
            ->getJson("/api/admin/documents/files/{$document->id}/download")
            ->assertForbidden();
    }

    #[Test]
    public function register_filters_by_document_category(): void
    {
        $student = Student::factory()->create();
        $certificate = $this->generateSchoolingCertificate($student);
        $this->withToken($this->adminToken)->postJson('/api/admin/documents/transcripts/global', [
            'student_id' => $student->id,
        ])->assertCreated();

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/documents/verification/register?document_category=certificate')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $certificate->id);
    }

    #[Test]
    public function generated_document_can_be_verified_by_code_and_number(): void
    {
        $document = $this->generateSchoolingCertificate(Student::factory()->create());

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/documents/verification/qr-code', ['verification_code' => $document->verification_code])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('document.document_number', $document->document_number);

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/documents/verification/document-number', ['document_number' => 'INCONNU-0001'])
            ->assertOk()
            ->assertJsonPath('valid', false);
    }

    #[Test]
    public function download_requires_authentication(): void
    {
        $this->getJson('/api/admin/documents/files/1/download')->assertUnauthorized();
    }
}
