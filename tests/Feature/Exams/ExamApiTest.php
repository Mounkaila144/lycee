<?php

namespace Tests\Feature\Exams;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\Student;
use Modules\Exams\Entities\ExamAttendanceSheet;
use Modules\Exams\Entities\ExamRoomAssignment;
use Modules\Exams\Entities\ExamSession;
use Modules\Exams\Entities\ExamSupervisor;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module as AcademicModule;
use Modules\Timetable\Entities\Room;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Examens : planification des sessions, salles, gestion des épreuves,
 * surveillance (présences, incidents), rapports et accès par rôle.
 */
class ExamApiTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as baseRunMigrationsOnce;
    }

    protected static bool $examMigrationsRan = false;

    private TenantUser $admin;

    private string $adminToken;

    private AcademicModule $module;

    private AcademicYear $academicYear;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$examMigrationsRan = false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->admin = $this->userWithRole('Administrator');
        $this->adminToken = $this->admin->createToken('test-token')->plainTextToken;

        $this->academicYear = AcademicYear::factory()->create();
        $this->module = AcademicModule::query()->create([
            'code' => 'MATH-'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Mathématiques',
            'credits_ects' => 2,
            'coefficient' => 2,
            'type' => 'Obligatoire',
            'semester' => 'S1',
            'level' => 'L1',
            'hours_cm' => 10,
            'hours_td' => 10,
            'hours_tp' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Run the shared tenant migrations, then the Timetable (rooms) and Exams module migrations.
     */
    protected function runMigrationsOnce(): void
    {
        $this->baseRunMigrationsOnce();

        if (self::$examMigrationsRan) {
            return;
        }

        foreach (['Modules/Timetable/Database/Migrations/tenant', 'Modules/Exams/Database/Migrations/tenant'] as $path) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        self::$examMigrationsRan = true;
    }

    private function userWithRole(string $role): TenantUser
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user;
    }

    private function tokenFor(string $role): string
    {
        return $this->userWithRole($role)->createToken('test-token')->plainTextToken;
    }

    private function authGetJson(string $uri, ?string $token = null): TestResponse
    {
        return $this->withToken($token ?? $this->adminToken)->getJson($uri);
    }

    private function authPostJson(string $uri, array $data = [], ?string $token = null): TestResponse
    {
        return $this->withToken($token ?? $this->adminToken)->postJson($uri, $data);
    }

    private function authPutJson(string $uri, array $data = [], ?string $token = null): TestResponse
    {
        return $this->withToken($token ?? $this->adminToken)->putJson($uri, $data);
    }

    private function authDeleteJson(string $uri, ?string $token = null): TestResponse
    {
        return $this->withToken($token ?? $this->adminToken)->deleteJson($uri);
    }

    private function createSession(array $overrides = []): ExamSession
    {
        return ExamSession::query()->create(array_merge([
            'module_id' => $this->module->id,
            'academic_year_id' => $this->academicYear->id,
            'title' => 'Composition de mathématiques',
            'type' => 'normal',
            'exam_date' => '2026-12-10',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'duration_minutes' => 120,
            'status' => 'draft',
        ], $overrides));
    }

    private function createRoomAssignment(ExamSession $session, int $capacity = 30): ExamRoomAssignment
    {
        $room = Room::factory()->create(['capacity' => $capacity]);

        return ExamRoomAssignment::query()->create([
            'exam_session_id' => $session->id,
            'room_id' => $room->id,
            'capacity' => $capacity,
            'assigned_students' => 0,
        ]);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/exams/sessions')->assertUnauthorized();
    }

    #[Test]
    public function eleve_cannot_access_exams(): void
    {
        $session = $this->createSession();
        $token = $this->tokenFor('Élève');

        $this->authGetJson('/api/admin/exams/sessions', $token)->assertForbidden();
        $this->authGetJson("/api/admin/exams/sessions/{$session->id}", $token)->assertForbidden();
        $this->authGetJson('/api/admin/exams/reports/statistics', $token)->assertForbidden();
    }

    #[Test]
    public function admin_can_list_sessions_with_relations(): void
    {
        $this->createSession();

        $this->authGetJson('/api/admin/exams/sessions')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.module.name', 'Mathématiques')
            ->assertJsonPath('data.0.evaluation_period', null);
    }

    #[Test]
    public function admin_can_show_a_session(): void
    {
        $session = $this->createSession();

        $this->authGetJson("/api/admin/exams/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('title', 'Composition de mathématiques');
    }

    #[Test]
    public function showing_unknown_session_returns_404(): void
    {
        $this->authGetJson('/api/admin/exams/sessions/999999')->assertNotFound();
    }

    #[Test]
    public function available_rooms_route_is_not_captured_by_show_route(): void
    {
        Room::factory()->create(['name' => 'Salle libre', 'is_active' => true]);
        $session = $this->createSession();
        $busyAssignment = $this->createRoomAssignment($session);

        $response = $this->authGetJson('/api/admin/exams/sessions/available-rooms?date=2026-12-10&start_time=08:30&end_time=09:30');

        $response->assertOk();
        $roomIds = collect($response->json())->pluck('id');
        $this->assertNotContains($busyAssignment->room_id, $roomIds);
        $this->assertContains('Salle libre', collect($response->json())->pluck('name'));
    }

    #[Test]
    public function admin_can_create_a_session(): void
    {
        $response = $this->authPostJson('/api/admin/exams/sessions', [
            'module_id' => $this->module->id,
            'academic_year_id' => $this->academicYear->id,
            'title' => 'Bac blanc',
            'type' => 'normal',
            'exam_date' => '2026-12-15',
            'start_time' => '08:00',
            'end_time' => '11:00',
        ]);

        $response->assertCreated()->assertJsonPath('title', 'Bac blanc');
        $this->assertDatabaseHas('exam_sessions', [
            'title' => 'Bac blanc',
            'duration_minutes' => 180,
            'created_by' => $this->admin->id,
        ], 'tenant');
    }

    #[Test]
    public function creating_a_session_validates_input(): void
    {
        $this->authPostJson('/api/admin/exams/sessions', ['type' => 'unknown'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['module_id', 'academic_year_id', 'title', 'type', 'exam_date']);
    }

    #[Test]
    public function admin_can_update_publish_duplicate_cancel_and_delete_a_session(): void
    {
        $session = $this->createSession();
        $this->createRoomAssignment($session);

        $this->authPutJson("/api/admin/exams/sessions/{$session->id}", ['title' => 'Nouveau titre', 'end_time' => '11:00'])
            ->assertOk()
            ->assertJsonPath('title', 'Nouveau titre')
            ->assertJsonPath('duration_minutes', 180);

        $this->authPostJson("/api/admin/exams/sessions/{$session->id}/publish")
            ->assertOk()
            ->assertJsonPath('status', 'planned')
            ->assertJsonPath('is_published', true);

        $duplicate = $this->authPostJson("/api/admin/exams/sessions/{$session->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('status', 'draft');
        $this->assertCount(1, $duplicate->json('room_assignments'));

        $this->authPostJson("/api/admin/exams/sessions/{$session->id}/cancel", ['reason' => 'Grève'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->authDeleteJson("/api/admin/exams/sessions/{$session->id}")->assertNoContent();
        $this->assertSoftDeleted('exam_sessions', ['id' => $session->id], 'tenant');
    }

    #[Test]
    public function admin_can_assign_and_remove_rooms(): void
    {
        $session = $this->createSession();
        $room = Room::factory()->create(['capacity' => 40]);

        $response = $this->authPostJson("/api/admin/exams/sessions/{$session->id}/rooms", [
            'rooms' => [['room_id' => $room->id]],
        ]);

        $response->assertCreated()->assertJsonPath('0.capacity', 40);
        $this->assertSame(40, $session->fresh()->total_capacity);

        $assignmentId = $response->json('0.id');
        $this->authDeleteJson("/api/admin/exams/sessions/{$session->id}/rooms/{$assignmentId}")->assertNoContent();
        $this->assertDatabaseMissing('exam_room_assignments', ['id' => $assignmentId], 'tenant');
    }

    #[Test]
    public function removing_a_room_of_another_session_returns_404(): void
    {
        $session = $this->createSession();
        $otherSession = $this->createSession(['title' => 'Autre']);
        $assignment = $this->createRoomAssignment($otherSession);

        $this->authDeleteJson("/api/admin/exams/sessions/{$session->id}/rooms/{$assignment->id}")->assertNotFound();
    }

    #[Test]
    public function admin_can_assign_students_and_gets_422_without_rooms(): void
    {
        $session = $this->createSession();
        $students = Student::factory()->count(2)->create();

        $this->authPostJson("/api/admin/exams/management/sessions/{$session->id}/students/assign", [
            'student_ids' => $students->pluck('id')->all(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['rooms']);

        $assignment = $this->createRoomAssignment($session, 1);

        $this->authPostJson("/api/admin/exams/management/sessions/{$session->id}/students/assign", [
            'student_ids' => $students->pluck('id')->all(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['student_ids']);

        $assignment->update(['capacity' => 10]);

        $this->authPostJson("/api/admin/exams/management/sessions/{$session->id}/students/assign", [
            'student_ids' => $students->pluck('id')->all(),
        ])->assertCreated()->assertJsonCount(2);

        $this->assertSame(2, $assignment->fresh()->assigned_students);
    }

    #[Test]
    public function eligible_students_and_checklist_do_not_crash(): void
    {
        $session = $this->createSession();

        $this->authGetJson("/api/admin/exams/management/sessions/{$session->id}/eligible-students")
            ->assertOk()
            ->assertJsonCount(0);

        $this->authGetJson("/api/admin/exams/management/sessions/{$session->id}/preparation-checklist")
            ->assertOk()
            ->assertJsonPath('session_details.title', 'Composition de mathématiques')
            ->assertJsonPath('supervisors_needed', 1);
    }

    #[Test]
    public function admin_can_assign_supervisors_and_professeur_can_mark_presence(): void
    {
        $session = $this->createSession();
        $teacher = $this->userWithRole('Professeur');
        $teacherToken = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->authPostJson("/api/admin/exams/supervision/sessions/{$session->id}/supervisors", [
            'supervisors' => [['teacher_id' => $teacher->id, 'role' => 'principal']],
        ]);
        $response->assertCreated()->assertJsonPath('0.teacher_id', $teacher->id);

        $supervisorId = $response->json('0.id');

        $this->authPutJson("/api/admin/exams/supervision/supervisors/{$supervisorId}/present", [], $teacherToken)
            ->assertOk()
            ->assertJsonPath('status', 'present');

        $this->authGetJson("/api/admin/exams/supervision/teachers/{$teacher->id}/schedule", $teacherToken)
            ->assertOk()
            ->assertJsonCount(1);

        $this->authGetJson('/api/admin/exams/reports/supervisor-workload')
            ->assertOk()
            ->assertJsonPath('0.teacher_id', $teacher->id)
            ->assertJsonPath('0.total_supervisions', 1);
    }

    #[Test]
    public function admin_can_replace_a_supervisor(): void
    {
        $session = $this->createSession();
        $supervisor = ExamSupervisor::query()->create([
            'exam_session_id' => $session->id,
            'teacher_id' => $this->userWithRole('Professeur')->id,
            'role' => 'principal',
            'status' => 'assigned',
        ]);
        $replacement = $this->userWithRole('Professeur');

        $this->authPostJson("/api/admin/exams/supervision/supervisors/{$supervisor->id}/replace", [
            'replacement_teacher_id' => $replacement->id,
        ])->assertCreated()->assertJsonPath('teacher_id', $replacement->id);

        $this->assertSame('replaced', $supervisor->fresh()->status);
    }

    #[Test]
    public function professeur_can_record_attendance_submission_and_verify(): void
    {
        $session = $this->createSession();
        $assignment = $this->createRoomAssignment($session);
        $student = Student::factory()->create();
        $sheet = ExamAttendanceSheet::query()->create([
            'exam_session_id' => $session->id,
            'exam_room_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'seat_number' => '1',
            'status' => 'present',
        ]);
        $teacher = $this->userWithRole('Professeur');
        $token = $teacher->createToken('test-token')->plainTextToken;

        $this->authPutJson("/api/admin/exams/supervision/attendance-sheets/{$sheet->id}/status", ['status' => 'late'], $token)
            ->assertOk()
            ->assertJsonPath('status', 'late');

        $this->authPostJson("/api/admin/exams/supervision/attendance-sheets/{$sheet->id}/submit", [], $token)
            ->assertOk()
            ->assertJsonPath('has_submitted', true);

        $this->authPostJson("/api/admin/exams/supervision/attendance-sheets/{$sheet->id}/verify", [], $token)
            ->assertOk()
            ->assertJsonPath('verified_by', $teacher->id);

        $this->authGetJson("/api/admin/exams/supervision/sessions/{$session->id}/attendance-stats", $token)
            ->assertOk()
            ->assertJsonPath('total_students', 1)
            ->assertJsonPath('late', 1);

        $this->authGetJson("/api/admin/exams/reports/sessions/{$session->id}/attendance")
            ->assertOk()
            ->assertJsonPath('summary.total_registered', 1)
            ->assertJsonPath('students.0.student_name', $student->full_name);
    }

    #[Test]
    public function professeur_can_report_and_manage_an_incident(): void
    {
        $session = $this->createSession();
        $teacher = $this->userWithRole('Professeur');
        $token = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->authPostJson('/api/admin/exams/supervision/incidents', [
            'exam_session_id' => $session->id,
            'type' => 'cheating',
            'title' => 'Antisèche',
            'description' => 'Antisèche trouvée',
            'severity' => 'high',
            'occurred_at_time' => '09:15',
        ], $token);

        $response->assertCreated()->assertJsonPath('status', 'reported');
        $this->assertDatabaseHas('exam_incidents', ['title' => 'Antisèche', 'reported_by' => $teacher->id], 'tenant');

        $incidentId = $response->json('id');

        $this->authPutJson("/api/admin/exams/supervision/incidents/{$incidentId}/status", ['status' => 'resolved'], $token)
            ->assertOk()
            ->assertJsonPath('reviewed_by', $teacher->id);

        $this->authPostJson("/api/admin/exams/supervision/incidents/{$incidentId}/escalate", ['reason' => 'Récidive'], $token)
            ->assertOk()
            ->assertJsonPath('status', 'escalated');

        $this->authGetJson("/api/admin/exams/supervision/sessions/{$session->id}/incidents/summary", $token)
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->authGetJson("/api/admin/exams/reports/sessions/{$session->id}/incidents")
            ->assertOk()
            ->assertJsonPath('incidents.0.reported_by', trim("{$teacher->firstname} {$teacher->lastname}"));
    }

    #[Test]
    public function reports_statistics_and_room_utilization_work(): void
    {
        $session = $this->createSession();
        $this->createRoomAssignment($session);

        $this->authGetJson('/api/admin/exams/reports/statistics')
            ->assertOk()
            ->assertJsonPath('overview.total_sessions', 1)
            ->assertJsonPath('by_module.0.module_name', 'Mathématiques');

        $this->authGetJson('/api/admin/exams/reports/room-utilization')
            ->assertOk()
            ->assertJsonCount(1);
    }

    #[Test]
    public function professeur_can_read_but_cannot_plan_sessions(): void
    {
        $session = $this->createSession();
        $token = $this->tokenFor('Professeur');

        $this->authGetJson('/api/admin/exams/sessions', $token)->assertOk();
        $this->authGetJson("/api/admin/exams/sessions/{$session->id}", $token)->assertOk();

        $this->authPostJson('/api/admin/exams/sessions', ['title' => 'x'], $token)->assertForbidden();
        $this->authPutJson("/api/admin/exams/sessions/{$session->id}", ['title' => 'x'], $token)->assertForbidden();
        $this->authDeleteJson("/api/admin/exams/sessions/{$session->id}", $token)->assertForbidden();
        $this->authPostJson("/api/admin/exams/sessions/{$session->id}/publish", [], $token)->assertForbidden();
        $this->authPostJson("/api/admin/exams/sessions/{$session->id}/cancel", [], $token)->assertForbidden();
        $this->authPostJson("/api/admin/exams/sessions/{$session->id}/rooms", ['rooms' => []], $token)->assertForbidden();
        $this->authPostJson("/api/admin/exams/management/sessions/{$session->id}/students/assign", ['student_ids' => [1]], $token)->assertForbidden();
        $this->authPostJson("/api/admin/exams/supervision/sessions/{$session->id}/supervisors", ['supervisors' => []], $token)->assertForbidden();

        $this->assertDatabaseHas('exam_sessions', ['id' => $session->id, 'deleted_at' => null], 'tenant');
    }

    #[Test]
    public function manager_can_plan_sessions(): void
    {
        $session = $this->createSession();
        $token = $this->tokenFor('Manager');

        $this->authPostJson("/api/admin/exams/sessions/{$session->id}/publish", [], $token)
            ->assertOk()
            ->assertJsonPath('status', 'planned');
    }
}
