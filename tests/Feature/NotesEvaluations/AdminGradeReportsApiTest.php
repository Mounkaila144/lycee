<?php

namespace Tests\Feature\NotesEvaluations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\TenantUser as User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Admin read-only grade reports must not crash on the lycée schema (no programmes on students, no semesters.order).
 */
class AdminGradeReportsApiTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as runBaseMigrationsOnce;
    }

    private string $token;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $admin = User::factory()->create();
        $this->assignRole($admin, 'Administrator');
        $this->token = $admin->createToken('test-token')->plainTextToken;

        $this->semester = Semester::factory()->create();
    }

    /**
     * Run the shared tenant migrations, then the NotesEvaluations tables (not part of the shared set).
     */
    protected function runMigrationsOnce(): void
    {
        if (self::$migrationsRan) {
            return;
        }

        $this->runBaseMigrationsOnce();

        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => base_path('Modules/NotesEvaluations/Database/Migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
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

    #[Test]
    public function final_results_can_publish_check_works(): void
    {
        $this->authGetJson("/api/admin/semesters/{$this->semester->id}/final-results/can-publish")
            ->assertOk()
            ->assertJsonPath('data.can_publish', false);
    }

    #[Test]
    public function programme_statistics_return_empty_list_without_programmes(): void
    {
        $this->authGetJson("/api/admin/statistics/semesters/{$this->semester->id}/programmes")
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function academic_year_comparisons_do_not_rely_on_semester_order_column(): void
    {
        $academicYearId = $this->semester->academic_year_id;

        $this->authGetJson("/api/admin/analytics/academic-years/{$academicYearId}/historical-comparison")
            ->assertOk();

        $this->authGetJson("/api/admin/statistics/academic-years/{$academicYearId}/comparison")
            ->assertOk();
    }

    #[Test]
    public function grade_reports_are_forbidden_to_teachers(): void
    {
        $teacher = User::factory()->create();
        $this->assignRole($teacher, 'Professeur');

        $this->withToken($teacher->createToken('test-token')->plainTextToken)
            ->getJson("/api/admin/semesters/{$this->semester->id}/ranking")
            ->assertForbidden();
    }
}
