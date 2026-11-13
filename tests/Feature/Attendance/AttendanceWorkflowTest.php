<?php

namespace Tests\Feature\Attendance;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Attendance\Entities\AbsenceJustification;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Attendance\Entities\AttendanceSession;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\PortailParent\Entities\ParentModel;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Présences : appel par le Professeur (sa classe uniquement), justificatifs
 * soumis par Parent / Élève (leurs enfants / eux-mêmes uniquement) et
 * validés ou refusés par l'administration.
 */
class AttendanceWorkflowTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as runBaseMigrationsOnce;
    }

    private TenantUser $admin;

    private TenantUser $teacher;

    private TenantUser $otherTeacher;

    private Classe $teacherClasse;

    private Classe $otherClasse;

    private Semester $semester;

    private Student $child;

    private Student $otherChild;

    private TenantUser $childUser;

    private TenantUser $otherChildUser;

    private TenantUser $parentUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->admin = $this->userWithRole('Administrator');
        $this->teacher = $this->userWithRole('Professeur');
        $this->otherTeacher = $this->userWithRole('Professeur');

        $year = AcademicYear::factory()->create([
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'is_active' => true,
        ]);
        $this->semester = Semester::on('tenant')->create([
            'academic_year_id' => $year->id,
            'name' => 'S1',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);

        $this->teacherClasse = Classe::factory()->create(['academic_year_id' => $year->id, 'head_teacher_id' => $this->teacher->id]);
        $this->otherClasse = Classe::factory()->create(['academic_year_id' => $year->id, 'head_teacher_id' => $this->otherTeacher->id]);

        [$this->child, $this->childUser] = $this->enrolledStudent($this->teacherClasse, $year);
        [$this->otherChild, $this->otherChildUser] = $this->enrolledStudent($this->otherClasse, $year);

        $this->parentUser = $this->userWithRole('Parent');
        $parent = ParentModel::factory()->create(['user_id' => $this->parentUser->id]);
        $parent->students()->attach($this->child->id, [
            'is_primary_contact' => true,
            'is_financial_responsible' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    // ───────────────────────── Appel (Professeur) ─────────────────────────

    #[Test]
    public function teacher_can_open_attendance_session_for_own_class(): void
    {
        $response = $this->withUserToken($this->teacher)->postJson('/api/admin/attendance/sessions', [
            'classe_id' => $this->teacherClasse->id,
            'session_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('classe_id', $this->teacherClasse->id)
            ->assertJsonPath('created_by', $this->teacher->id)
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.student_id', $this->childUser->id)
            ->assertJsonPath('records.0.status', 'absent');
    }

    #[Test]
    public function teacher_cannot_open_attendance_session_for_another_class(): void
    {
        $this->withUserToken($this->teacher)->postJson('/api/admin/attendance/sessions', [
            'classe_id' => $this->otherClasse->id,
            'session_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, AttendanceSession::query()->count());
    }

    #[Test]
    public function subject_teacher_with_a_timetable_slot_in_the_class_can_take_attendance(): void
    {
        $subjectTeacher = $this->userWithRole('Professeur');
        TimetableSlot::factory()->create([
            'teacher_id' => $subjectTeacher->id,
            'classe_id' => $this->teacherClasse->id,
            'module_id' => null,
            'group_id' => null,
            'room_id' => null,
            'semester_id' => $this->semester->id,
        ]);

        $this->withUserToken($subjectTeacher)->postJson('/api/admin/attendance/sessions', [
            'classe_id' => $this->teacherClasse->id,
            'session_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonCount(1, 'records');

        $this->withUserToken($subjectTeacher)->postJson('/api/admin/attendance/sessions', [
            'classe_id' => $this->otherClasse->id,
            'session_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    #[Test]
    public function session_creation_requires_a_class_or_a_slot(): void
    {
        $this->withUserToken($this->teacher)->postJson('/api/admin/attendance/sessions', [
            'session_date' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['classe_id']);
    }

    #[Test]
    public function teacher_can_take_attendance_and_complete_own_session(): void
    {
        $session = $this->openSession($this->teacher, $this->teacherClasse);

        $this->withUserToken($this->teacher)->postJson('/api/admin/attendance/record', [
            'session_id' => $session->id,
            'student_id' => $this->childUser->id,
            'status' => 'late',
            'arrival_time' => '08:20',
        ])->assertOk()->assertJsonPath('status', 'late')->assertJsonPath('delay_minutes', 20);

        $this->withUserToken($this->teacher)->getJson("/api/admin/attendance/sessions/{$session->id}/sheet")
            ->assertOk()
            ->assertJsonPath('summary.late', 1)
            ->assertJsonPath('records.0.student_profile.id', $this->child->id);

        $this->withUserToken($this->teacher)->postJson("/api/admin/attendance/sessions/{$session->id}/complete")
            ->assertOk()
            ->assertJsonPath('session.status', 'completed')
            ->assertJsonPath('session.completed_by', $this->teacher->id);
    }

    #[Test]
    public function teacher_cannot_touch_another_teachers_session(): void
    {
        $session = $this->openSession($this->otherTeacher, $this->otherClasse);
        $record = AttendanceRecord::query()->where('attendance_session_id', $session->id)->firstOrFail();

        $this->withUserToken($this->teacher)->getJson("/api/admin/attendance/sessions/{$session->id}/sheet")->assertForbidden();
        $this->withUserToken($this->teacher)->postJson('/api/admin/attendance/record', [
            'session_id' => $session->id,
            'student_id' => $this->otherChildUser->id,
            'status' => 'present',
        ])->assertForbidden();
        $this->withUserToken($this->teacher)->putJson("/api/admin/attendance/records/{$record->id}", [
            'status' => 'present',
            'reason' => 'Erreur de saisie',
        ])->assertForbidden();
        $this->withUserToken($this->teacher)->postJson("/api/admin/attendance/sessions/{$session->id}/complete")->assertForbidden();

        $this->assertSame('absent', $record->fresh()->status);
    }

    #[Test]
    public function teacher_only_lists_own_sessions_while_admin_lists_all(): void
    {
        $own = $this->openSession($this->teacher, $this->teacherClasse);
        $this->openSession($this->otherTeacher, $this->otherClasse);

        $this->withUserToken($this->teacher)->getJson('/api/admin/attendance/sessions')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $own->id);

        $this->withUserToken($this->admin)->getJson('/api/admin/attendance/sessions')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->withUserToken($this->teacher)->getJson('/api/admin/attendance/classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->teacherClasse->id);
    }

    // ─────────────────── Justificatifs (Parent / Élève) ───────────────────

    #[Test]
    public function parent_can_submit_justification_for_own_child(): void
    {
        $this->withUserToken($this->parentUser)->postJson('/api/admin/attendance/my/justifications', [
            'student_id' => $this->child->id,
            'absence_date_from' => now()->toDateString(),
            'absence_date_to' => now()->toDateString(),
            'type' => 'medical',
            'reason' => 'Paludisme',
        ])->assertCreated()
            ->assertJsonPath('data.student_id', $this->childUser->id)
            ->assertJsonPath('data.submitted_by', $this->parentUser->id)
            ->assertJsonPath('data.status', 'pending');

        $this->withUserToken($this->parentUser)->getJson('/api/admin/attendance/my/justifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function parent_cannot_submit_justification_for_another_child(): void
    {
        $this->withUserToken($this->parentUser)->postJson('/api/admin/attendance/my/justifications', [
            'student_id' => $this->otherChild->id,
            'absence_date_from' => now()->toDateString(),
            'absence_date_to' => now()->toDateString(),
            'type' => 'family',
            'reason' => 'Voyage',
        ])->assertForbidden();

        $this->withUserToken($this->parentUser)->getJson('/api/admin/attendance/my/absences?student_id='.$this->otherChild->id)
            ->assertForbidden();

        $this->assertSame(0, AbsenceJustification::query()->count());
    }

    #[Test]
    public function student_can_submit_justification_for_himself_only(): void
    {
        $this->withUserToken($this->childUser)->postJson('/api/admin/attendance/my/justifications', [
            'absence_date_from' => now()->toDateString(),
            'absence_date_to' => now()->toDateString(),
            'type' => 'administrative',
            'reason' => 'Convocation',
        ])->assertCreated()->assertJsonPath('data.student_id', $this->childUser->id);

        $this->withUserToken($this->childUser)->postJson('/api/admin/attendance/my/justifications', [
            'student_id' => $this->otherChild->id,
            'absence_date_from' => now()->toDateString(),
            'absence_date_to' => now()->toDateString(),
            'type' => 'other',
            'reason' => 'Fraude',
        ])->assertForbidden();
    }

    #[Test]
    public function families_cannot_use_staff_attendance_endpoints(): void
    {
        $this->withUserToken($this->parentUser)->getJson('/api/admin/justifications')->assertForbidden();
        $this->withUserToken($this->parentUser)->postJson('/api/admin/justifications/1/validate', ['decision' => 'approved'])->assertForbidden();
        $this->withUserToken($this->childUser)->getJson('/api/admin/attendance/sessions')->assertForbidden();
        $this->withUserToken($this->teacher)->getJson('/api/admin/attendance/my/justifications')->assertForbidden();
    }

    // ─────────────────────── Validation (Administration) ───────────────────────

    #[Test]
    public function admin_approves_justification_and_absences_become_excused(): void
    {
        $session = $this->openSession($this->teacher, $this->teacherClasse);
        $justification = $this->pendingJustification();

        $this->withUserToken($this->admin)->getJson('/api/admin/justifications/pending')
            ->assertOk()
            ->assertJsonCount(1);

        $this->withUserToken($this->admin)->postJson("/api/admin/justifications/{$justification->id}/validate", [
            'decision' => 'approved',
            'notes' => 'Certificat médical conforme',
        ])->assertOk()
            ->assertJsonPath('justification.status', 'approved')
            ->assertJsonPath('justification.validated_by', $this->admin->id);

        $record = AttendanceRecord::query()
            ->where('attendance_session_id', $session->id)
            ->where('student_id', $this->childUser->id)
            ->firstOrFail();
        $this->assertSame('excused', $record->status);
        $this->assertSame($this->admin->id, (int) $record->modified_by);
    }

    #[Test]
    public function admin_rejects_justification_and_absences_stay_unjustified(): void
    {
        $session = $this->openSession($this->teacher, $this->teacherClasse);
        $justification = $this->pendingJustification();

        $this->withUserToken($this->admin)->postJson("/api/admin/justifications/{$justification->id}/validate", [
            'decision' => 'rejected',
            'notes' => 'Document illisible',
        ])->assertOk()->assertJsonPath('justification.status', 'rejected');

        $this->assertSame('absent', AttendanceRecord::query()
            ->where('attendance_session_id', $session->id)
            ->value('status'));

        $this->withUserToken($this->admin)->postJson("/api/admin/justifications/{$justification->id}/validate", [
            'decision' => 'approved',
        ])->assertUnprocessable();
    }

    #[Test]
    public function teacher_cannot_validate_justification(): void
    {
        $justification = $this->pendingJustification();

        $this->withUserToken($this->teacher)->postJson("/api/admin/justifications/{$justification->id}/validate", [
            'decision' => 'approved',
        ])->assertForbidden();

        $this->assertSame('pending', $justification->fresh()->status);
    }

    #[Test]
    public function validation_requires_a_valid_decision(): void
    {
        $justification = $this->pendingJustification();

        $this->withUserToken($this->admin)->postJson("/api/admin/justifications/{$justification->id}/validate", [
            'decision' => 'maybe',
        ])->assertUnprocessable()->assertJsonValidationErrors(['decision']);
    }

    #[Test]
    public function teacher_only_sees_justifications_of_own_class_students(): void
    {
        $this->pendingJustification();
        AbsenceJustification::query()->create([
            'student_id' => $this->otherChildUser->id,
            'absence_date_from' => now()->toDateString(),
            'absence_date_to' => now()->toDateString(),
            'type' => 'other',
            'reason' => 'Autre',
            'status' => 'pending',
            'submitted_by' => $this->otherChildUser->id,
        ]);

        $this->withUserToken($this->teacher)->getJson('/api/admin/justifications')->assertOk()->assertJsonPath('total', 1);
        $this->withUserToken($this->teacher)->getJson("/api/admin/justifications/students/{$this->otherChildUser->id}")->assertForbidden();
        $this->withUserToken($this->admin)->getJson("/api/admin/justifications/students/{$this->otherChildUser->id}")->assertOk()->assertJsonCount(1);
    }

    // ─────────────────────────── Rapports / suivi ───────────────────────────

    #[Test]
    public function admin_can_read_reports_for_a_semester(): void
    {
        $this->openSession($this->teacher, $this->teacherClasse);
        $semesterId = $this->semester->id;

        $this->withUserToken($this->admin)->getJson("/api/admin/attendance/reports/rates?semester_id={$semesterId}")
            ->assertOk()->assertJsonPath('total_sessions', 1)->assertJsonPath('absent_count', 1);
        $this->withUserToken($this->admin)->getJson("/api/admin/attendance/reports/absentees?semester_id={$semesterId}")
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.student_id', $this->childUser->id);
        $this->withUserToken($this->admin)->getJson("/api/admin/attendance/reports/statistics?semester_id={$semesterId}")
            ->assertOk()->assertJsonPath('global.absent_count', 1);
        $this->withUserToken($this->admin)->getJson("/api/admin/attendance/reports/export?semester_id={$semesterId}&type=students")
            ->assertOk()->assertJsonCount(2, 'data');
        $this->withUserToken($this->admin)->getJson("/api/admin/attendance/monitoring/students/{$this->childUser->id}/stats?semester_id={$semesterId}")
            ->assertOk()->assertJsonPath('absent_count', 1);
        $this->withUserToken($this->teacher)->getJson("/api/admin/attendance/monitoring/students/{$this->otherChildUser->id}/history?semester_id={$semesterId}")
            ->assertForbidden();
    }

    #[Test]
    public function requests_without_token_are_rejected(): void
    {
        $this->getJson('/api/admin/attendance/sessions')->assertUnauthorized();
        $this->getJson('/api/admin/attendance/my/justifications')->assertUnauthorized();
    }

    // ─────────────────────────────── Helpers ───────────────────────────────

    private function withUserToken(TenantUser $user): self
    {
        return $this->withToken($user->createToken('test-token')->plainTextToken);
    }

    private function userWithRole(string $role): TenantUser
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user;
    }

    /**
     * @return array{0: Student, 1: TenantUser}
     */
    private function enrolledStudent(Classe $classe, AcademicYear $year): array
    {
        $user = $this->userWithRole('Élève');
        $student = Student::factory()->create(['user_id' => $user->id]);

        ClassEnrollment::on('tenant')->create([
            'student_id' => $student->id,
            'classe_id' => $classe->id,
            'academic_year_id' => $year->id,
            'enrolled_at' => now()->toDateString(),
            'assignment_type' => 'Nouvelle inscription',
            'status' => 'Actif',
        ]);

        return [$student, $user];
    }

    private function openSession(TenantUser $teacher, Classe $classe): AttendanceSession
    {
        $response = $this->withUserToken($teacher)->postJson('/api/admin/attendance/sessions', [
            'classe_id' => $classe->id,
            'session_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        $response->assertCreated();

        return AttendanceSession::query()->findOrFail($response->json('id'));
    }

    private function pendingJustification(): AbsenceJustification
    {
        $response = $this->withUserToken($this->parentUser)->postJson('/api/admin/attendance/my/justifications', [
            'student_id' => $this->child->id,
            'absence_date_from' => now()->toDateString(),
            'absence_date_to' => now()->toDateString(),
            'type' => 'medical',
            'reason' => 'Malade',
        ]);
        $response->assertCreated();

        return AbsenceJustification::query()->findOrFail($response->json('data.id'));
    }

    /**
     * Les migrations Timetable / Attendance ne font pas partie du socle de InteractsWithTenancy :
     * elles sont jouées juste après, avant l'ouverture de la transaction de test.
     */
    protected function runMigrationsOnce(): void
    {
        $alreadyMigrated = self::$migrationsRan;
        $this->runBaseMigrationsOnce();

        if ($alreadyMigrated && Schema::connection('tenant')->hasTable('attendance_justifications')) {
            return;
        }

        foreach (['Timetable', 'Attendance'] as $module) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path("Modules/{$module}/Database/Migrations/tenant"),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }
}
