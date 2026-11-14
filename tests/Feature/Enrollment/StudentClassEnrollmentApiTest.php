<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Inscription d'un élève en classe, changement de classe (transfert interne), annulation, listes.
 */
class StudentClassEnrollmentApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $admin;

    private AcademicYear $year;

    private Classe $classeA;

    private Classe $classeB;

    private Student $student;

    /**
     * @var array<int, string>
     */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->admin = $this->userWithRole('Administrator');
        $this->year = AcademicYear::factory()->create(['name' => '2090-2091', 'is_active' => true]);
        $this->classeA = Classe::factory()->create(['academic_year_id' => $this->year->id, 'name' => '6ème A', 'max_capacity' => 2]);
        $this->classeB = Classe::factory()->create(['academic_year_id' => $this->year->id, 'level_id' => $this->classeA->level_id, 'name' => '6ème B', 'max_capacity' => 1]);
        $this->student = Student::factory()->withMatricule('MAT-0001')->create(['firstname' => 'Awa', 'lastname' => 'Issa']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function userWithRole(string $role): TenantUser
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user;
    }

    private function as(TenantUser $user): self
    {
        $this->tokens[$user->id] ??= $user->createToken('test-token')->plainTextToken;

        return $this->withToken($this->tokens[$user->id]);
    }

    private function enrollUri(?Student $student = null): string
    {
        return '/api/admin/enrollment/students/'.($student ?? $this->student)->id.'/class-enrollments';
    }

    private function enroll(Student $student, Classe $classe): TestResponse
    {
        return $this->as($this->admin)->postJson($this->enrollUri($student), ['classe_id' => $classe->id]);
    }

    #[Test]
    public function it_enrolls_a_student_in_the_year_of_the_class(): void
    {
        $response = $this->enroll($this->student, $this->classeA);

        $response->assertCreated()
            ->assertJsonPath('data.classe.id', $this->classeA->id)
            ->assertJsonPath('data.academic_year.id', $this->year->id)
            ->assertJsonPath('data.status', 'Actif')
            ->assertJsonPath('data.assignment_type', 'Nouvelle inscription');

        $this->assertDatabaseHas('class_enrollments', [
            'student_id' => $this->student->id,
            'classe_id' => $this->classeA->id,
            'academic_year_id' => $this->year->id,
            'status' => 'Actif',
            'assigned_by' => $this->admin->id,
        ], 'tenant');
        $this->assertDatabaseHas('student_audit_logs', ['student_id' => $this->student->id, 'event' => 'class_enrolled', 'new_value' => '6ème A'], 'tenant');

        $this->as($this->admin)->getJson('/api/admin/enrollment/students/'.$this->student->id)
            ->assertOk()
            ->assertJsonPath('data.current_class.classe_id', $this->classeA->id)
            ->assertJsonPath('data.current_class.classe_name', '6ème A');
    }

    #[Test]
    public function it_refuses_a_second_active_enrollment_for_the_same_year(): void
    {
        $this->enroll($this->student, $this->classeA)->assertCreated();

        $this->enroll($this->student, $this->classeB)
            ->assertStatus(422)
            ->assertJsonValidationErrors('classe_id');

        $this->assertSame(1, ClassEnrollment::query()->where('student_id', $this->student->id)->count());
    }

    #[Test]
    public function it_refuses_enrollment_in_a_full_class(): void
    {
        $this->enroll(Student::factory()->create(), $this->classeB)->assertCreated();

        $this->enroll($this->student, $this->classeB)
            ->assertStatus(422)
            ->assertJsonValidationErrors('classe_id');
    }

    #[Test]
    public function it_validates_the_class(): void
    {
        $this->as($this->admin)->postJson($this->enrollUri(), [])->assertStatus(422)->assertJsonValidationErrors('classe_id');
        $this->as($this->admin)->postJson($this->enrollUri(), ['classe_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('classe_id');
        $this->as($this->admin)->postJson('/api/admin/enrollment/students/999999/class-enrollments', ['classe_id' => $this->classeA->id])->assertNotFound();
    }

    #[Test]
    public function it_reuses_a_cancelled_enrollment_of_the_same_year(): void
    {
        $enrollmentId = $this->enroll($this->student, $this->classeA)->json('data.id');

        $this->as($this->admin)->postJson("/api/admin/enrollment/class-enrollments/{$enrollmentId}/cancel", ['reason' => 'Erreur'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Annulé');

        $this->enroll($this->student, $this->classeB)
            ->assertCreated()
            ->assertJsonPath('data.id', $enrollmentId)
            ->assertJsonPath('data.classe.id', $this->classeB->id)
            ->assertJsonPath('data.status', 'Actif');
    }

    #[Test]
    public function it_cannot_cancel_an_inactive_enrollment(): void
    {
        $enrollmentId = $this->enroll($this->student, $this->classeA)->json('data.id');
        $this->as($this->admin)->postJson("/api/admin/enrollment/class-enrollments/{$enrollmentId}/cancel")->assertOk();

        $this->as($this->admin)->postJson("/api/admin/enrollment/class-enrollments/{$enrollmentId}/cancel")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    #[Test]
    public function it_changes_class_as_an_internal_transfer(): void
    {
        $enrollmentId = $this->enroll($this->student, $this->classeA)->json('data.id');

        $response = $this->as($this->admin)->postJson('/api/admin/enrollment/students/'.$this->student->id.'/change-class', [
            'classe_id' => $this->classeB->id,
            'reason' => 'Demande des parents',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $enrollmentId)
            ->assertJsonPath('data.classe.id', $this->classeB->id)
            ->assertJsonPath('data.status', 'Actif');

        $this->assertStringContainsString('Transfert interne', (string) $response->json('data.notes'));
        $this->assertStringContainsString('6ème A → 6ème B (Demande des parents)', (string) $response->json('data.notes'));
        $this->assertDatabaseHas('student_audit_logs', [
            'student_id' => $this->student->id,
            'event' => 'class_changed',
            'old_value' => '6ème A',
            'new_value' => '6ème B',
        ], 'tenant');
        $this->assertSame(0, ClassEnrollment::query()->where('classe_id', $this->classeA->id)->where('status', 'Actif')->count());
    }

    #[Test]
    public function it_refuses_invalid_class_changes(): void
    {
        $changeUri = '/api/admin/enrollment/students/'.$this->student->id.'/change-class';

        $this->as($this->admin)->postJson($changeUri, ['classe_id' => $this->classeB->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('classe_id');

        $this->enroll($this->student, $this->classeA)->assertCreated();

        $this->as($this->admin)->postJson($changeUri, ['classe_id' => $this->classeA->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('classe_id');

        $this->enroll(Student::factory()->create(), $this->classeB)->assertCreated();

        $this->as($this->admin)->postJson($changeUri, ['classe_id' => $this->classeB->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('classe_id');

        $otherYearClasse = Classe::factory()->create(['name' => '5ème A']);

        $this->as($this->admin)->postJson($changeUri, ['classe_id' => $otherYearClasse->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('classe_id');
    }

    #[Test]
    public function it_lists_enrollments_of_a_student_and_of_a_class(): void
    {
        $this->enroll($this->student, $this->classeA)->assertCreated();
        $other = Student::factory()->create(['firstname' => 'Bachir', 'lastname' => 'Abdou']);
        $otherEnrollmentId = $this->enroll($other, $this->classeA)->json('data.id');
        $this->as($this->admin)->postJson("/api/admin/enrollment/class-enrollments/{$otherEnrollmentId}/cancel")->assertOk();

        $this->as($this->admin)->getJson($this->enrollUri())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.classe.name', '6ème A');

        $this->as($this->admin)->getJson('/api/admin/enrollment/classes/'.$this->classeA->id.'/class-enrollments')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.student.lastname', 'Abdou')
            ->assertJsonPath('meta.active_count', 1)
            ->assertJsonPath('meta.classe.academic_year.id', $this->year->id);

        $this->as($this->admin)->getJson('/api/admin/enrollment/classes/'.$this->classeA->id.'/class-enrollments?status=Actif')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.id', $this->student->id);
    }

    #[Test]
    public function a_manager_can_enroll_and_change_class(): void
    {
        $manager = $this->userWithRole('Manager');

        $this->as($manager)->postJson($this->enrollUri(), ['classe_id' => $this->classeA->id])->assertCreated();
        $this->as($manager)->postJson('/api/admin/enrollment/students/'.$this->student->id.'/change-class', ['classe_id' => $this->classeB->id])->assertOk();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forbiddenRoles(): array
    {
        return [
            'Professeur' => ['Professeur'],
            'Élève' => ['Élève'],
            'Parent' => ['Parent'],
            'Caissier' => ['Caissier'],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenRoles')]
    public function other_roles_cannot_manage_class_enrollments(string $role): void
    {
        $user = $this->userWithRole($role);
        $enrollmentId = $this->enroll(Student::factory()->create(), $this->classeA)->json('data.id');

        $this->as($user)->postJson($this->enrollUri(), ['classe_id' => $this->classeA->id])->assertForbidden();
        $this->as($user)->postJson('/api/admin/enrollment/students/'.$this->student->id.'/change-class', ['classe_id' => $this->classeB->id])->assertForbidden();
        $this->as($user)->getJson($this->enrollUri())->assertForbidden();
        $this->as($user)->getJson('/api/admin/enrollment/classes/'.$this->classeA->id.'/class-enrollments')->assertForbidden();
        $this->as($user)->postJson("/api/admin/enrollment/class-enrollments/{$enrollmentId}/cancel")->assertForbidden();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->postJson($this->enrollUri(), ['classe_id' => $this->classeA->id])->assertUnauthorized();
    }
}
