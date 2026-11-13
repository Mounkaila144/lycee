<?php

namespace Tests\Feature\Timetable;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\PortailParent\Entities\ParentModel;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\Timetable\Entities\Room;
use Modules\Timetable\Entities\TeacherPreference;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Emplois du temps (lycée) : créneaux par classe/matière, conflits, accès par rôle.
 */
class TimetableApiTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as baseRunMigrationsOnce;
    }

    private TenantUser $admin;

    private string $adminToken;

    private TenantUser $teacher;

    private string $teacherToken;

    private TenantUser $otherTeacher;

    private AcademicYear $academicYear;

    private Semester $semester;

    private Classe $classe;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->admin = $this->userWithRole('Administrator');
        $this->adminToken = $this->admin->createToken('test-token')->plainTextToken;

        $this->teacher = $this->userWithRole('Professeur');
        $this->teacherToken = $this->teacher->createToken('test-token')->plainTextToken;

        $this->otherTeacher = $this->userWithRole('Professeur');

        $this->academicYear = AcademicYear::factory()->create(['is_active' => true]);
        $this->semester = Semester::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S1',
            'is_active' => true,
        ]);
        $this->classe = Classe::factory()->create(['academic_year_id' => $this->academicYear->id, 'classroom' => 'Salle 12']);
        $this->subject = Subject::factory()->create(['name' => 'Mathématiques', 'code' => 'MATHS']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Les tables Timetable ne font pas partie des migrations communes des tests.
     */
    protected function runMigrationsOnce(): void
    {
        $alreadyMigrated = self::$migrationsRan;

        $this->baseRunMigrationsOnce();

        if (! $alreadyMigrated) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path('Modules/Timetable/Database/Migrations/tenant'),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    private function userWithRole(string $role): TenantUser
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function slotPayload(array $overrides = []): array
    {
        return array_merge([
            'classe_id' => $this->classe->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'semester_id' => $this->semester->id,
            'day_of_week' => 'Lundi',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSlot(array $overrides = []): TimetableSlot
    {
        return TimetableSlot::create(array_merge([
            'classe_id' => $this->classe->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'semester_id' => $this->semester->id,
            'day_of_week' => 'Lundi',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'type' => 'CM',
            'is_recurring' => true,
        ], $overrides));
    }

    private function authGetJson(string $token, string $uri): TestResponse
    {
        return $this->withToken($token)->getJson($uri);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authPostJson(string $token, string $uri, array $data = []): TestResponse
    {
        return $this->withToken($token)->postJson($uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authPutJson(string $token, string $uri, array $data = []): TestResponse
    {
        return $this->withToken($token)->putJson($uri, $data);
    }

    private function authDeleteJson(string $token, string $uri): TestResponse
    {
        return $this->withToken($token)->deleteJson($uri);
    }

    // ── Création de créneau ─────────────────────────────────────────────

    #[Test]
    public function admin_can_create_a_slot_for_a_classe_and_subject_without_room(): void
    {
        $response = $this->authPostJson($this->adminToken, '/api/admin/timetable/slots', $this->slotPayload());

        $response->assertCreated()
            ->assertJsonPath('data.classe.id', $this->classe->id)
            ->assertJsonPath('data.subject.code', 'MATHS')
            ->assertJsonPath('data.teacher.id', $this->teacher->id)
            ->assertJsonPath('data.start_time', '08:00')
            ->assertJsonPath('data.type', 'CM');

        $slot = TimetableSlot::firstOrFail();
        $this->assertSame($this->classe->id, (int) $slot->classe_id);
        $this->assertNull($slot->room_id);
        $this->assertSame($this->admin->id, (int) $slot->changes()->first()->user_id);
    }

    #[Test]
    public function slot_creation_requires_classe_subject_and_valid_times(): void
    {
        $this->authPostJson($this->adminToken, '/api/admin/timetable/slots', [
            'teacher_id' => $this->teacher->id,
            'semester_id' => $this->semester->id,
            'day_of_week' => 'Dimanche',
            'start_time' => '10:00',
            'end_time' => '09:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['classe_id', 'subject_id', 'day_of_week', 'end_time']);
    }

    // ── Détection de conflits ───────────────────────────────────────────

    #[Test]
    public function creating_a_slot_detects_teacher_conflict(): void
    {
        $otherClasse = Classe::factory()->create(['academic_year_id' => $this->academicYear->id]);
        $this->createSlot();

        $response = $this->authPostJson($this->adminToken, '/api/admin/timetable/slots', $this->slotPayload([
            'classe_id' => $otherClasse->id,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]));

        $response->assertUnprocessable()->assertJsonPath('conflicts.0.type', 'teacher');
        $this->assertSame(1, TimetableSlot::count());
    }

    #[Test]
    public function creating_a_slot_detects_classe_conflict(): void
    {
        $this->createSlot();

        $response = $this->authPostJson($this->adminToken, '/api/admin/timetable/slots', $this->slotPayload([
            'teacher_id' => $this->otherTeacher->id,
        ]));

        $response->assertUnprocessable()->assertJsonPath('conflicts.0.type', 'classe');
    }

    #[Test]
    public function creating_a_slot_detects_room_conflict(): void
    {
        $room = Room::factory()->create();
        $otherClasse = Classe::factory()->create(['academic_year_id' => $this->academicYear->id]);
        $this->createSlot(['room_id' => $room->id]);

        $response = $this->authPostJson($this->adminToken, '/api/admin/timetable/slots', $this->slotPayload([
            'classe_id' => $otherClasse->id,
            'teacher_id' => $this->otherTeacher->id,
            'room_id' => $room->id,
        ]));

        $response->assertUnprocessable()->assertJsonPath('conflicts.0.type', 'room');
    }

    #[Test]
    public function adjacent_slots_and_other_semesters_are_not_conflicts(): void
    {
        $this->createSlot();
        $otherSemester = Semester::factory()->create(['academic_year_id' => $this->academicYear->id, 'name' => 'S2']);

        $this->authPostJson($this->adminToken, '/api/admin/timetable/check-conflicts', $this->slotPayload([
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]))->assertOk()->assertJsonPath('has_conflicts', false);

        $this->authPostJson($this->adminToken, '/api/admin/timetable/check-conflicts', $this->slotPayload([
            'semester_id' => $otherSemester->id,
        ]))->assertOk()->assertJsonPath('has_conflicts', false);

        $this->authPostJson($this->adminToken, '/api/admin/timetable/check-conflicts', $this->slotPayload())
            ->assertOk()
            ->assertJsonPath('has_conflicts', true);
    }

    #[Test]
    public function updating_a_slot_ignores_itself_but_detects_other_conflicts(): void
    {
        $slot = $this->createSlot();
        $this->createSlot(['day_of_week' => 'Mardi', 'teacher_id' => $this->otherTeacher->id]);

        $this->authPutJson($this->adminToken, "/api/admin/timetable/slots/{$slot->id}", ['start_time' => '08:00', 'end_time' => '09:00'])
            ->assertOk()
            ->assertJsonPath('data.end_time', '09:00');

        $this->authPutJson($this->adminToken, "/api/admin/timetable/slots/{$slot->id}", ['day_of_week' => 'Mardi'])
            ->assertUnprocessable()
            ->assertJsonPath('conflicts.0.type', 'classe');
    }

    #[Test]
    public function slot_routes_resolve_real_records_and_return_404_for_unknown_ids(): void
    {
        $slot = $this->createSlot();

        $this->authGetJson($this->adminToken, '/api/admin/timetable/slots/999999')->assertNotFound();
        $this->authGetJson($this->adminToken, '/api/admin/rooms/999999')->assertNotFound();
        $this->authGetJson($this->adminToken, "/api/admin/timetable/slots/{$slot->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $slot->id);

        $this->authDeleteJson($this->adminToken, "/api/admin/timetable/slots/{$slot->id}")->assertOk();
        $this->assertSoftDeleted('timetable_slots', ['id' => $slot->id], 'tenant');
    }

    // ── Vues ────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_view_classe_timetable_grid_with_default_active_semester(): void
    {
        $this->createSlot();
        $this->createSlot(['day_of_week' => 'Mercredi', 'start_time' => '14:00:00', 'end_time' => '16:00:00']);

        $this->authGetJson($this->adminToken, "/api/admin/timetable/classes/{$this->classe->id}")
            ->assertOk()
            ->assertJsonPath('total_slots', 2)
            ->assertJsonPath('semester_id', $this->semester->id)
            ->assertJsonPath('classe.id', $this->classe->id)
            ->assertJsonPath('data.Lundi.0.subject.name', 'Mathématiques')
            ->assertJsonPath('data.Mercredi.0.start_time', '14:00');
    }

    #[Test]
    public function admin_can_view_student_timetable_through_active_class_enrollment(): void
    {
        $this->createSlot();
        $student = $this->studentInClasse();

        $this->authGetJson($this->adminToken, "/api/admin/timetable/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('total_slots', 1)
            ->assertJsonPath('classe.id', $this->classe->id);
    }

    // ── Accès par rôle ──────────────────────────────────────────────────

    #[Test]
    public function teacher_cannot_create_update_or_delete_slots(): void
    {
        $slot = $this->createSlot();

        $this->authPostJson($this->teacherToken, '/api/admin/timetable/slots', $this->slotPayload(['day_of_week' => 'Jeudi']))
            ->assertForbidden();
        $this->authPutJson($this->teacherToken, "/api/admin/timetable/slots/{$slot->id}", ['day_of_week' => 'Jeudi'])
            ->assertForbidden();
        $this->authDeleteJson($this->teacherToken, "/api/admin/timetable/slots/{$slot->id}")->assertForbidden();
        $this->authPostJson($this->teacherToken, '/api/admin/rooms', ['code' => 'X1', 'name' => 'X', 'type' => 'Salle', 'capacity' => 30])
            ->assertForbidden();
    }

    #[Test]
    public function teacher_only_sees_own_slots(): void
    {
        $ownSlot = $this->createSlot();
        $otherSlot = $this->createSlot(['day_of_week' => 'Mardi', 'teacher_id' => $this->otherTeacher->id]);

        $this->authGetJson($this->teacherToken, '/api/admin/timetable/slots?teacher_id='.$this->otherTeacher->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownSlot->id);

        $this->authGetJson($this->teacherToken, "/api/admin/timetable/slots/{$otherSlot->id}")->assertForbidden();

        $this->authGetJson($this->teacherToken, "/api/admin/timetable/teachers/{$this->otherTeacher->id}?semester_id={$this->semester->id}")
            ->assertForbidden();

        $this->authGetJson($this->teacherToken, "/api/admin/timetable/teachers/{$this->teacher->id}?semester_id={$this->semester->id}")
            ->assertOk()
            ->assertJsonPath('total_slots', 1);
    }

    #[Test]
    public function teacher_can_view_own_timetable_via_me_endpoint(): void
    {
        $this->createSlot();
        $this->createSlot(['day_of_week' => 'Mardi', 'teacher_id' => $this->otherTeacher->id]);

        $this->authGetJson($this->teacherToken, '/api/admin/timetable/me')
            ->assertOk()
            ->assertJsonPath('total_slots', 1)
            ->assertJsonPath('teacher.id', $this->teacher->id)
            ->assertJsonPath('data.Lundi.0.classe.id', $this->classe->id);
    }

    #[Test]
    public function teacher_can_only_manage_own_preferences(): void
    {
        $payload = ['preferences' => [[
            'day_of_week' => 'Lundi',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'is_preferred' => true,
        ]]];

        $this->authPutJson($this->teacherToken, "/api/admin/teachers/{$this->otherTeacher->id}/preferences", $payload)
            ->assertForbidden();
        $this->authGetJson($this->teacherToken, "/api/admin/teachers/{$this->otherTeacher->id}/preferences")
            ->assertForbidden();

        $this->authPutJson($this->teacherToken, "/api/admin/teachers/{$this->teacher->id}/preferences", $payload)
            ->assertOk();
        $this->assertSame(1, TeacherPreference::where('teacher_id', $this->teacher->id)->count());

        $this->authGetJson($this->adminToken, "/api/admin/teachers/{$this->otherTeacher->id}/preferences")->assertOk();
    }

    #[Test]
    public function teacher_cannot_access_management_views(): void
    {
        $student = $this->studentInClasse();

        $this->authGetJson($this->teacherToken, "/api/admin/timetable/students/{$student->id}")->assertForbidden();
        $this->authGetJson($this->teacherToken, '/api/admin/reports/occupation-stats?semester_id='.$this->semester->id)->assertForbidden();
        $this->authGetJson($this->teacherToken, '/api/admin/notifications/statistics')->assertForbidden();
    }

    #[Test]
    public function notifications_endpoint_uses_the_tenant_user(): void
    {
        $this->authGetJson($this->teacherToken, '/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    #[Test]
    public function students_and_parents_cannot_use_admin_timetable_endpoints(): void
    {
        $studentToken = $this->userWithRole('Élève')->createToken('test-token')->plainTextToken;
        $parentToken = $this->userWithRole('Parent')->createToken('test-token')->plainTextToken;

        foreach ([$studentToken, $parentToken] as $token) {
            $this->authGetJson($token, '/api/admin/timetable/slots')->assertForbidden();
            $this->authGetJson($token, "/api/admin/timetable/classes/{$this->classe->id}")->assertForbidden();
            $this->authPostJson($token, '/api/admin/timetable/slots', $this->slotPayload())->assertForbidden();
        }
    }

    #[Test]
    public function unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/admin/timetable/slots')->assertUnauthorized();
    }

    #[Test]
    public function parent_sees_the_timetable_of_own_child_class_only(): void
    {
        $this->createSlot();
        $child = $this->studentInClasse();

        Permission::firstOrCreate(['name' => 'view children timetable', 'guard_name' => 'tenant']);
        Permission::firstOrCreate(['name' => 'view children', 'guard_name' => 'tenant']);
        Role::where('name', 'Parent')->where('guard_name', 'tenant')->first()
            ?->givePermissionTo(['view children', 'view children timetable']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $parentUser = $this->userWithRole('Parent');
        $parent = ParentModel::factory()->create(['user_id' => $parentUser->id]);
        $parent->students()->attach($child->id, ['is_primary_contact' => true, 'is_financial_responsible' => true]);
        $parentToken = $parentUser->createToken('test-token')->plainTextToken;

        $this->authGetJson($parentToken, "/api/admin/parent/children/{$child->id}/timetable")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Mathématiques')
            ->assertJsonPath('data.0.classroom', 'Salle 12')
            ->assertJsonPath('meta.classe.id', $this->classe->id);

        $stranger = $this->studentInClasse();
        $this->authGetJson($parentToken, "/api/admin/parent/children/{$stranger->id}/timetable")->assertForbidden();
    }

    private function studentInClasse(): Student
    {
        $student = Student::factory()->create();

        ClassEnrollment::create([
            'student_id' => $student->id,
            'classe_id' => $this->classe->id,
            'academic_year_id' => $this->academicYear->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'Actif',
        ]);

        return $student;
    }
}
