<?php

namespace Tests\Feature\RoleCoverage\Parent;

use Illuminate\Support\Facades\Artisan;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Attendance\Entities\AttendanceSession;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\InvoiceItem;
use Modules\Finance\Entities\Payment;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ClassEvaluationGrade;
use Modules\PortailParent\Entities\ParentModel;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Stories Parent 02 (Notes), 03 (Présences), 05 (Factures).
 *
 * Couvre :
 *   - Endpoints `/api/admin/parent/children/{student}/grades|attendance|invoices`
 *   - ChildPolicy::viewGrades / viewAttendance / viewInvoices appliquée
 *   - Cross-parent : un Parent ne peut PAS voir les notes/présences/factures
 *     de l'enfant d'un autre Parent → 403
 */
class ChildDataTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as baseRunMigrationsOnce;
    }

    protected static bool $childDataMigrationsRan = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$childDataMigrationsRan = false;
    }

    /**
     * Migrations partagées + tables Notes (grades), Timetable et Attendance.
     */
    protected function runMigrationsOnce(): void
    {
        $this->baseRunMigrationsOnce();

        if (self::$childDataMigrationsRan) {
            return;
        }

        foreach ([
            'Modules/NotesEvaluations/Database/Migrations/tenant',
            'Modules/Timetable/Database/Migrations/tenant',
            'Modules/Attendance/Database/Migrations/tenant',
        ] as $path) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        self::$childDataMigrationsRan = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        // Permissions Parent requises par ChildPolicy (Stories 02, 03, 05)
        foreach (['view children', 'view children grades', 'view children attendance', 'view children invoices'] as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'tenant']);
        }
        $parentRole = \Spatie\Permission\Models\Role::where('name', 'Parent')->where('guard_name', 'tenant')->first();
        $parentRole?->givePermissionTo([
            'view children', 'view children grades', 'view children attendance', 'view children invoices',
        ]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

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
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $parent, $child, $token];
    }

    #[Test]
    public function parent_can_view_own_child_grades(): void
    {
        [, , $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/grades")
            ->assertOk()
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function parent_can_view_own_child_attendance(): void
    {
        [, , $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/attendance")
            ->assertOk()
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function parent_can_view_own_child_invoices(): void
    {
        [, , $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/invoices")
            ->assertOk()
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function parent_cannot_view_other_parents_child_grades(): void
    {
        [, , , $token] = $this->parentWithChild();

        // Enfant d'un autre Parent
        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}/grades")
            ->assertForbidden();
    }

    #[Test]
    public function parent_cannot_view_other_parents_child_attendance(): void
    {
        [, , , $token] = $this->parentWithChild();

        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}/attendance")
            ->assertForbidden();
    }

    #[Test]
    public function parent_cannot_view_other_parents_child_invoices(): void
    {
        [, , , $token] = $this->parentWithChild();

        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}/invoices")
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_parent_child_data(): void
    {
        $student = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($student, 'Étudiant');
        $token = $student->createToken('test-token')->plainTextToken;

        $someChild = Student::factory()->create();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$someChild->id}/grades")
            ->assertForbidden();
    }

    #[Test]
    public function parent_without_specific_permission_is_blocked(): void
    {
        // Parent qui a `view children` mais PAS `view children grades`
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');

        // On retire la permission spécifique de ce rôle pour ce test
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $child = Student::factory()->create();
        $parent->students()->attach($child->id);

        // Retire view children grades du rôle Parent temporairement
        \Spatie\Permission\Models\Role::where('name', 'Parent')->where('guard_name', 'tenant')->first()
            ?->revokePermissionTo('view children grades');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/grades")
            ->assertForbidden();
    }

    #[Test]
    public function grades_return_only_published_grades_of_the_child(): void
    {
        [, , $child, $token] = $this->parentWithChild();
        $year = AcademicYear::factory()->active()->create();
        $classe = Classe::factory()->create(['academic_year_id' => $year->id]);
        $classmate = Student::factory()->create();

        foreach ([$child, $classmate] as $student) {
            ClassEnrollment::on('tenant')->create([
                'student_id' => $student->id,
                'classe_id' => $classe->id,
                'academic_year_id' => $year->id,
                'enrolled_at' => '2026-10-01',
                'assignment_type' => 'Nouvelle inscription',
                'status' => 'Actif',
            ]);
        }

        $semester = Semester::factory()->create(['academic_year_id' => $year->id, 'name' => 'S1', 'is_active' => true]);
        $subject = Subject::factory()->create(['name' => 'Mathématiques']);
        $teacher = TenantUser::factory()->create();
        $published = ClassEvaluation::factory()->published()->create([
            'classe_id' => $classe->id, 'subject_id' => $subject->id, 'semester_id' => $semester->id,
            'teacher_id' => $teacher->id, 'title' => 'Devoir 1', 'evaluation_date' => '2026-10-15',
        ]);
        $draft = ClassEvaluation::factory()->create([
            'classe_id' => $classe->id, 'subject_id' => $subject->id, 'semester_id' => $semester->id, 'teacher_id' => $teacher->id,
        ]);
        ClassEvaluationGrade::factory()->create(['class_evaluation_id' => $published->id, 'student_id' => $child->id, 'score' => 14.5]);
        ClassEvaluationGrade::factory()->create(['class_evaluation_id' => $draft->id, 'student_id' => $child->id, 'score' => 2]);
        ClassEvaluationGrade::factory()->create(['class_evaluation_id' => $published->id, 'student_id' => $classmate->id, 'score' => 16]);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/grades")
            ->assertOk()
            ->assertJsonPath('data.student.id', $child->id)
            ->assertJsonCount(1, 'data.subjects')
            ->assertJsonPath('data.subjects.0.subject.name', 'Mathématiques')
            ->assertJsonCount(1, 'data.subjects.0.evaluations')
            ->assertJsonPath('data.subjects.0.evaluations.0.id', $published->id)
            ->assertJsonPath('data.subjects.0.evaluations.0.title', 'Devoir 1')
            ->assertJsonPath('data.subjects.0.evaluations.0.score', 14.5)
            ->assertJsonPath('data.subjects.0.evaluations.0.max_score', 20)
            ->assertJsonPath('data.subjects.0.evaluations.0.date', '2026-10-15')
            ->assertJsonPath('data.subjects.0.class_average', 15.25)
            ->assertJsonPath('data.general_average', 14.5)
            ->assertJsonPath('data.rank', 2)
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function attendance_returns_child_records_resolved_through_user_account(): void
    {
        [$parentUser, , $child, $token] = $this->parentWithChild();
        $childUser = TenantUser::factory()->create(['application' => 'admin']);
        $child->update(['user_id' => $childUser->id]);
        $otherUser = TenantUser::factory()->create(['application' => 'admin']);

        $session = AttendanceSession::query()->create([
            'classe_id' => Classe::factory()->create()->id,
            'session_date' => '2026-11-02',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => 'completed',
            'method' => 'manual',
            'created_by' => $parentUser->id,
        ]);

        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $childUser->id,
            'status' => 'absent',
            'recorded_by' => $parentUser->id,
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $otherUser->id,
            'status' => 'late',
            'recorded_by' => $parentUser->id,
        ]);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/attendance")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'Absent')
            ->assertJsonPath('data.0.date', '2026-11-02')
            ->assertJsonPath('meta.absences_count', 1);
    }

    #[Test]
    public function attendance_is_empty_when_child_has_no_user_account(): void
    {
        [, , $child, $token] = $this->parentWithChild();
        $child->update(['user_id' => null]);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/attendance")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function invoices_return_child_invoices_with_items_and_payments(): void
    {
        [, , $child, $token] = $this->parentWithChild();
        $academicYearId = AcademicYear::factory()->create()->id;

        $invoice = Invoice::query()->create([
            'student_id' => $child->id,
            'invoice_number' => 'INV-TEST-001',
            'academic_year_id' => $academicYearId,
            'invoice_date' => '2026-10-01',
            'due_date' => '2026-11-30',
            'total_amount' => 72500,
            'paid_amount' => 25000,
            'status' => 'partial',
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Scolarité annuelle',
            'quantity' => 1,
            'unit_price' => 72500,
            'amount' => 72500,
        ]);
        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'student_id' => $child->id,
            'payment_date' => '2026-10-10',
            'amount' => 25000,
            'payment_method' => 'mobile_money',
            'receipt_number' => 'REC-TEST-001',
        ]);
        Invoice::query()->create([
            'student_id' => $child->id,
            'invoice_number' => 'INV-TEST-DRAFT',
            'academic_year_id' => $academicYearId,
            'invoice_date' => '2026-10-01',
            'due_date' => '2026-11-30',
            'total_amount' => 1000,
            'paid_amount' => 0,
            'status' => 'draft',
        ]);
        Invoice::query()->create([
            'student_id' => Student::factory()->create()->id,
            'invoice_number' => 'INV-TEST-OTHER',
            'academic_year_id' => $academicYearId,
            'invoice_date' => '2026-10-01',
            'due_date' => '2026-11-30',
            'total_amount' => 5000,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/invoices")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_number', 'INV-TEST-001')
            ->assertJsonPath('data.0.description', 'Scolarité annuelle')
            ->assertJsonPath('data.0.amount', 72500)
            ->assertJsonPath('data.0.balance', 47500)
            ->assertJsonPath('data.0.status', 'partial')
            ->assertJsonPath('data.0.payments.0.receipt_number', 'REC-TEST-001')
            ->assertJsonPath('meta.total_balance', 47500);
    }

    #[Test]
    public function unknown_child_returns_404(): void
    {
        [, , , $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson('/api/admin/parent/children/999999/grades')
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/admin/parent/children/abc/invoices')
            ->assertNotFound();
    }
}
