<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentDocument;
use Modules\UsersGuard\Entities\TenantUser as User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        // Disable tenancy middleware (required for tests)
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        // Create user and REAL token (TenantSanctumAuth requires Bearer token)
        $this->seedRolesAndPermissions();
        $this->user = User::factory()->create();
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        Storage::fake('tenant');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Make an authenticated JSON GET request
     */
    private function authGetJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    /**
     * Make an authenticated JSON POST request
     */
    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    /**
     * Make an authenticated JSON PUT request
     */
    private function authPutJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    /**
     * Make an authenticated JSON DELETE request
     */
    private function authDeleteJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri);
    }

    /**
     * Make an authenticated POST request with files
     */
    private function authPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->post($uri, $data);
    }

    #[Test]
    public function it_lists_students_with_pagination(): void
    {
        Student::factory()->count(20)->create();

        $response = $this->authGetJson('/api/admin/enrollment/students');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'matricule', 'firstname', 'lastname', 'phone', 'status'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(15, 'data'); // Default pagination is 15
    }

    #[Test]
    public function it_searches_students(): void
    {
        Student::factory()->create([
            'firstname' => 'John',
            'lastname' => 'Zeta',
        ]);
        Student::factory()->create([
            'firstname' => 'Jane',
            'lastname' => 'Alpha',
        ]);

        $response = $this->authGetJson('/api/admin/enrollment/students?search=john');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.firstname', 'John');
    }

    #[Test]
    public function it_filters_students_by_status(): void
    {
        Student::factory()->count(3)->active()->create();
        Student::factory()->count(2)->suspended()->create();

        $response = $this->authGetJson('/api/admin/enrollment/students?status=Actif');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_creates_student_with_auto_generated_matricule(): void
    {
        $data = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'birthdate' => '2010-01-01',
            'sex' => 'M',
            'nationality' => 'Nigérienne',
            'phone' => '+22798765432',
            'quarter' => 'Plateau',
        ];

        $response = $this->authPostJson('/api/admin/enrollment/students', $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'matricule', 'firstname', 'lastname'],
            ]);

        $student = Student::on('tenant')->where('lastname', 'Doe')->first();
        $this->assertNotNull($student);
        $this->assertSame('+22798765432', $student->phone);
        $this->assertSame('Plateau', $student->quarter);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $student->matricule);
    }

    #[Test]
    public function it_validates_required_fields_when_creating_student(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/students', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'firstname',
                'lastname',
                'birthdate',
                'sex',
            ])
            ->assertJsonMissingValidationErrors(['email', 'mobile', 'programme_id']);
    }

    #[Test]
    public function it_ignores_legacy_university_fields_when_creating_student(): void
    {
        // Les colonnes email/mobile/country ont été retirées de `students` (migration align_students_to_secondaire).
        $response = $this->authPostJson('/api/admin/enrollment/students', [
            'firstname' => 'Legacy',
            'lastname' => 'Payload',
            'birthdate' => '2011-05-05',
            'sex' => 'F',
            'email' => 'legacy@example.com',
            'mobile' => '+22798765432',
            'country' => 'Niger',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('students', ['lastname' => 'Payload'], 'tenant');
    }

    #[Test]
    public function it_rejects_birthdate_in_the_future(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/students', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'birthdate' => now()->addDay()->format('Y-m-d'),
            'sex' => 'M',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['birthdate']);
    }

    #[Test]
    public function it_shows_student_details(): void
    {
        $student = Student::factory()->create();
        StudentDocument::factory()->count(2)->create(['student_id' => $student->id]);

        $response = $this->authGetJson("/api/admin/enrollment/students/{$student->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'matricule',
                    'full_name',
                    'documents',
                ],
            ])
            ->assertJsonCount(2, 'data.documents');
    }

    #[Test]
    public function it_updates_student_information(): void
    {
        $student = Student::factory()->create([
            'phone' => '+22790000000',
            'city' => 'Zinder',
        ]);

        $response = $this->authPutJson("/api/admin/enrollment/students/{$student->id}", [
            'phone' => '+22712345678',
            'city' => 'Niamey',
            'quarter' => 'Lazaret',
            'email' => 'ignored@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.phone', '+22712345678')
            ->assertJsonPath('data.quarter', 'Lazaret');

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'phone' => '+22712345678',
            'city' => 'Niamey',
        ], 'tenant');
    }

    #[Test]
    public function it_soft_deletes_student(): void
    {
        $student = Student::factory()->create();

        $response = $this->authDeleteJson("/api/admin/enrollment/students/{$student->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Dossier élève supprimé avec succès']);

        $this->assertSoftDeleted('students', ['id' => $student->id], 'tenant');
    }

    #[Test]
    public function it_checks_document_completeness(): void
    {
        $student = Student::factory()->create();

        // Add only 2 of 4 required documents
        StudentDocument::factory()->certificatNaissance()->create(['student_id' => $student->id]);
        StudentDocument::factory()->photoIdentite()->create(['student_id' => $student->id]);

        $response = $this->authGetJson("/api/admin/enrollment/students/{$student->id}/check-completeness");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'is_complete',
                    'completeness_percentage',
                    'missing_documents',
                    'uploaded_documents',
                ],
            ])
            ->assertJsonPath('data.is_complete', false)
            ->assertJsonPath('data.completeness_percentage', 50);
    }

    #[Test]
    public function it_checks_for_duplicate_students(): void
    {
        Student::factory()->count(2)->create([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'birthdate' => '2000-01-01',
        ]);

        $response = $this->authPostJson('/api/admin/enrollment/students/check-duplicates', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'birthdate' => '2000-01-01',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'has_duplicates',
                    'count',
                    'duplicates',
                ],
            ])
            ->assertJsonPath('data.has_duplicates', true)
            ->assertJsonPath('data.count', 2);
    }

    #[Test]
    public function it_uploads_document_for_student(): void
    {
        $student = Student::factory()->create();
        $file = UploadedFile::fake()->create('certificat.pdf', 1024); // 1MB

        $response = $this->authPost("/api/admin/enrollment/students/{$student->id}/documents", [
            'type' => 'certificat_naissance',
            'file' => $file,
            'description' => 'Certificat de naissance original',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'type', 'filename'],
            ]);

        $this->assertDatabaseHas('student_documents', [
            'student_id' => $student->id,
            'type' => 'certificat_naissance',
        ], 'tenant');

        Storage::disk('tenant')->assertExists(
            StudentDocument::on('tenant')->where('student_id', $student->id)->first()->file_path
        );
    }

    #[Test]
    public function it_validates_document_upload(): void
    {
        $student = Student::factory()->create();

        // Missing file
        $response = $this->authPostJson("/api/admin/enrollment/students/{$student->id}/documents", [
            'type' => 'certificat_naissance',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        // Invalid file type
        $invalidFile = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->authPost("/api/admin/enrollment/students/{$student->id}/documents", [
            'type' => 'certificat_naissance',
            'file' => $invalidFile,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function it_returns_student_statistics(): void
    {
        Student::factory()->count(10)->active()->create();
        Student::factory()->count(3)->suspended()->create();
        Student::factory()->count(2)->male()->create();
        Student::factory()->count(3)->female()->create();

        $response = $this->authGetJson('/api/admin/enrollment/students/statistics/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'by_status',
                    'by_sex',
                    'average_age',
                    'with_complete_documents',
                ],
            ]);
    }

    #[Test]
    public function professeur_can_read_but_cannot_modify_students(): void
    {
        $teacher = User::factory()->create();
        $this->assignRole($teacher, 'Professeur');
        $teacherToken = $teacher->createToken('test-token')->plainTextToken;
        $student = Student::factory()->create();

        $this->withToken($teacherToken)->getJson('/api/admin/enrollment/students')->assertOk();
        $this->withToken($teacherToken)->getJson("/api/admin/enrollment/students/{$student->id}")->assertOk();

        $this->withToken($teacherToken)->postJson('/api/admin/enrollment/students', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'birthdate' => '2010-01-01',
            'sex' => 'M',
        ])->assertForbidden();
        $this->withToken($teacherToken)->putJson("/api/admin/enrollment/students/{$student->id}", ['city' => 'Dosso'])->assertForbidden();
        $this->withToken($teacherToken)->deleteJson("/api/admin/enrollment/students/{$student->id}")->assertForbidden();
        $this->withToken($teacherToken)->postJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Suspendu',
            'reason' => 'Test',
        ])->assertForbidden();
    }

    #[Test]
    public function manager_can_update_student(): void
    {
        $manager = User::factory()->create();
        $this->assignRole($manager, 'Manager');
        $managerToken = $manager->createToken('test-token')->plainTextToken;
        $student = Student::factory()->create();

        $this->withToken($managerToken)
            ->putJson("/api/admin/enrollment/students/{$student->id}", ['city' => 'Dosso'])
            ->assertOk()
            ->assertJsonPath('data.city', 'Dosso');
    }

    #[Test]
    public function autocomplete_searches_by_name_and_matricule(): void
    {
        Student::factory()->withMatricule('2026-0042')->create(['firstname' => 'Aïcha', 'lastname' => 'Moussa']);
        Student::factory()->create(['firstname' => 'Ibrahim', 'lastname' => 'Sani']);

        $this->authGetJson('/api/admin/enrollment/students/search/autocomplete?q=Moussa')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.matricule', '2026-0042');

        $this->authGetJson('/api/admin/enrollment/students/search/autocomplete?q=2026-00')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function finance_roles_can_list_students_but_cannot_modify_them(): void
    {
        $student = Student::factory()->withMatricule('2026-0099')->create();

        foreach (['Comptable', 'Caissier'] as $roleName) {
            $financeUser = User::factory()->create();
            $this->assignRole($financeUser, $roleName);
            $financeToken = $financeUser->createToken('test-token')->plainTextToken;

            $this->withToken($financeToken)
                ->getJson('/api/admin/enrollment/students?search=2026-0099')
                ->assertOk()
                ->assertJsonPath('data.0.matricule', '2026-0099');

            $this->withToken($financeToken)->postJson('/api/admin/enrollment/students', [
                'firstname' => 'John',
                'lastname' => 'Doe',
                'birthdate' => '2010-01-01',
                'sex' => 'M',
            ])->assertForbidden();
            $this->withToken($financeToken)->getJson("/api/admin/enrollment/students/{$student->id}")->assertForbidden();
            $this->withToken($financeToken)->putJson("/api/admin/enrollment/students/{$student->id}", ['city' => 'Dosso'])->assertForbidden();
            $this->withToken($financeToken)->deleteJson("/api/admin/enrollment/students/{$student->id}")->assertForbidden();
        }
    }
}
