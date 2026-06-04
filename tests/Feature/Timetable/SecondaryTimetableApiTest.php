<?php

namespace Tests\Feature\Timetable;

use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\TeacherAssignment;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Scheduling a teacher on a timetable slot is constrained by their teaching
 * assignments: the (teacher, class, subject) triple must exist.
 */
class SecondaryTimetableApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $admin;

    private string $token;

    private TenantUser $teacher;

    private Classe $classe;

    private Subject $assignedSubject;

    private Subject $otherSubject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->seedRolesAndPermissions();

        $this->admin = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->admin, 'Administrator');
        $this->token = $this->admin->createToken('test-token')->plainTextToken;

        $this->teacher = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->teacher, 'Professeur');

        $year = AcademicYear::factory()->create();
        $level = Level::factory()->create();
        $this->classe = Classe::factory()->create([
            'academic_year_id' => $year->id,
            'level_id' => $level->id,
            'series_id' => null,
        ]);
        $this->assignedSubject = Subject::factory()->create(['code' => 'MATH']);
        $this->otherSubject = Subject::factory()->create(['code' => 'PHYS']);

        TeacherAssignment::on('tenant')->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->classe->id,
            'subject_id' => $this->assignedSubject->id,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function slotPayload(array $overrides = []): array
    {
        return array_merge([
            'class_id' => $this->classe->id,
            'subject_id' => $this->assignedSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 'Lundi',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room_name' => 'Salle 1',
        ], $overrides);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->postJson('/api/admin/timetable/class-slots', $this->slotPayload())
            ->assertStatus(401);
    }

    #[Test]
    public function it_creates_a_slot_for_an_assigned_teacher_subject_class(): void
    {
        $this->authPostJson('/api/admin/timetable/class-slots', $this->slotPayload())
            ->assertStatus(201);

        $this->assertSame(1, TimetableSlot::on('tenant')->count());
    }

    #[Test]
    public function it_rejects_a_slot_when_teacher_not_assigned_to_subject_in_class(): void
    {
        $response = $this->authPostJson('/api/admin/timetable/class-slots', $this->slotPayload([
            'subject_id' => $this->otherSubject->id,
        ]));

        $response->assertStatus(422);
        $this->assertSame(0, TimetableSlot::on('tenant')->count());
    }
}
