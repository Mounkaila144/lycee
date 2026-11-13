<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Validation des inscriptions (table student_enrollments, statuts Actif → Validé / Rejeté).
 */
class EnrollmentValidationApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    private AcademicYear $academicYear;

    private Programme $programme;

    private Semester $semester;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = TenantUser::factory()->create();
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->programme = Programme::factory()->create();
        $this->semester = Semester::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S1',
        ]);
        $this->student = Student::factory()->create(['status' => 'Actif']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authGetJson(string $uri): TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    private function authPostJson(string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEnrollment(array $attributes = []): StudentEnrollment
    {
        return StudentEnrollment::factory()->create(array_merge([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'level' => '6E',
            'status' => 'Actif',
        ], $attributes));
    }

    #[Test]
    public function it_can_list_pending_enrollments(): void
    {
        $this->createEnrollment();
        $this->createEnrollment(['student_id' => Student::factory()->create()->id]);
        $this->createEnrollment(['student_id' => Student::factory()->create()->id, 'status' => 'Validé']);

        $response = $this->authGetJson('/api/admin/enrollment/validation/pending');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'student_id', 'programme_id', 'level', 'status'],
                ],
                'current_page', 'last_page', 'per_page', 'total',
            ]);
        $this->assertSame(2, $response->json('total'));
    }

    #[Test]
    public function it_can_filter_pending_enrollments_by_academic_year(): void
    {
        $otherYear = AcademicYear::factory()->create(['name' => '1999-2000']);
        $otherSemester = Semester::factory()->create(['academic_year_id' => $otherYear->id, 'name' => 'S1']);

        $this->createEnrollment();
        $this->createEnrollment([
            'student_id' => Student::factory()->create()->id,
            'academic_year_id' => $otherYear->id,
            'semester_id' => $otherSemester->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/validation/pending?academic_year_id={$this->academicYear->id}");

        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
    }

    #[Test]
    public function it_searches_pending_enrollments_by_student_name_only(): void
    {
        $matching = Student::factory()->create(['firstname' => 'Zeinabou', 'lastname' => 'Issoufou']);
        $other = Student::factory()->create(['firstname' => 'Moussa', 'lastname' => 'Abdou']);
        $this->createEnrollment(['student_id' => $matching->id]);
        $this->createEnrollment(['student_id' => $other->id]);

        $response = $this->authGetJson('/api/admin/enrollment/validation/pending?search=Issoufou');

        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
        $this->assertSame($matching->id, $response->json('data.0.student_id'));
    }

    #[Test]
    public function it_can_check_enrollment_completeness(): void
    {
        $enrollment = $this->createEnrollment();

        $response = $this->authGetJson("/api/admin/enrollment/validation/{$enrollment->id}/check");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'administrative', 'modules_check', 'ects_check', 'groups_check',
                    'options_check', 'prerequisites_check', 'details', 'is_complete',
                ],
            ])
            ->assertJsonPath('data.administrative', true);
    }

    #[Test]
    public function it_can_validate_an_active_enrollment(): void
    {
        $enrollment = $this->createEnrollment();

        $response = $this->authPostJson("/api/admin/enrollment/validation/{$enrollment->id}/validate", [
            'validation_notes' => 'Dossier complet',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'Validé');
        $this->assertDatabaseHas('student_enrollments', ['id' => $enrollment->id, 'status' => 'Validé'], 'tenant');
    }

    #[Test]
    public function it_cannot_validate_an_already_validated_enrollment(): void
    {
        $enrollment = $this->createEnrollment(['status' => 'Validé']);

        $response = $this->authPostJson("/api/admin/enrollment/validation/{$enrollment->id}/validate");

        $response->assertStatus(422)->assertJsonStructure(['message']);
    }

    #[Test]
    public function it_can_reject_an_enrollment_with_reason(): void
    {
        $enrollment = $this->createEnrollment();

        $response = $this->authPostJson("/api/admin/enrollment/validation/{$enrollment->id}/reject", [
            'rejection_reason' => 'Les documents justificatifs sont incomplets. Veuillez fournir les pièces manquantes.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'Rejeté');
        $this->assertStringContainsString('[Rejet]', (string) $response->json('data.notes'));
    }

    #[Test]
    public function it_requires_rejection_reason_when_rejecting(): void
    {
        $enrollment = $this->createEnrollment();

        $response = $this->authPostJson("/api/admin/enrollment/validation/{$enrollment->id}/reject", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['rejection_reason']);
    }

    #[Test]
    public function it_requires_minimum_length_for_rejection_reason(): void
    {
        $enrollment = $this->createEnrollment();

        $response = $this->authPostJson("/api/admin/enrollment/validation/{$enrollment->id}/reject", [
            'rejection_reason' => 'Too short',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['rejection_reason']);
    }

    #[Test]
    public function it_can_batch_validate_multiple_enrollments(): void
    {
        $first = $this->createEnrollment();
        $second = $this->createEnrollment(['student_id' => Student::factory()->create()->id]);
        $alreadyValidated = $this->createEnrollment(['student_id' => Student::factory()->create()->id, 'status' => 'Validé']);

        $response = $this->authPostJson('/api/admin/enrollment/validation/batch-validate', [
            'enrollment_ids' => [$first->id, $second->id, $alreadyValidated->id],
        ]);

        $response->assertOk()->assertJsonStructure(['message', 'data' => ['validated', 'failed']]);
        $this->assertCount(2, $response->json('data.validated'));
        $this->assertCount(1, $response->json('data.failed'));
    }

    #[Test]
    public function it_can_get_validation_statistics(): void
    {
        $this->createEnrollment(['status' => 'Validé']);
        $this->createEnrollment(['student_id' => Student::factory()->create()->id]);
        $this->createEnrollment(['student_id' => Student::factory()->create()->id, 'status' => 'Rejeté']);

        $response = $this->authGetJson("/api/admin/enrollment/validation/stats?academic_year_id={$this->academicYear->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['total', 'by_status', 'validation_rate', 'rejection_rate', 'pending_count'],
            ])
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.pending_count', 1);
    }

    #[Test]
    public function it_can_get_validation_statistics_without_filters(): void
    {
        $this->createEnrollment();

        $this->authGetJson('/api/admin/enrollment/validation/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    #[Test]
    public function it_can_show_enrollment_details(): void
    {
        $enrollment = $this->createEnrollment();

        $response = $this->authGetJson("/api/admin/enrollment/validation/{$enrollment->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'student_id', 'programme_id', 'level', 'status']]);
    }

    #[Test]
    public function manager_can_access_validation_endpoints(): void
    {
        $manager = TenantUser::factory()->create();
        $managerRole = Role::on('tenant')->firstOrCreate(
            ['name' => 'Manager', 'guard_name' => 'tenant'],
            ['display_name' => 'Manager']
        );
        $manager->roles()->attach($managerRole->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $token = $manager->createToken('manager-token')->plainTextToken;
        $this->createEnrollment();

        $this->withToken($token)->getJson('/api/admin/enrollment/validation/pending')->assertOk();
        $this->withToken($token)->getJson('/api/admin/enrollment/validation/stats')->assertOk();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/enrollment/validation/pending');

        $response->assertStatus(401);
    }
}
