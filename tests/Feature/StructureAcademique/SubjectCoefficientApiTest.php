<?php

namespace Tests\Feature\StructureAcademique;

use Modules\StructureAcademique\Database\Seeders\CycleAndLevelSeeder;
use Modules\StructureAcademique\Database\Seeders\SeriesSeeder;
use Modules\StructureAcademique\Database\Seeders\SubjectCoefficientSeeder;
use Modules\StructureAcademique\Database\Seeders\SubjectSeeder;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Series;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SubjectCoefficientApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        // Seed prerequisites
        (new CycleAndLevelSeeder)->run();
        (new SeriesSeeder)->run();
        (new SubjectSeeder)->run();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authGetJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function authPutJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    private function authDeleteJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri);
    }

    private function getLevel(string $code): Level
    {
        return Level::on('tenant')->where('code', $code)->firstOrFail();
    }

    private function getSeries(string $code): Series
    {
        return Series::on('tenant')->where('code', $code)->firstOrFail();
    }

    private function getSubject(string $code): Subject
    {
        return Subject::on('tenant')->where('code', $code)->firstOrFail();
    }

    // =============================================
    // Index
    // =============================================

    #[Test]
    public function it_can_list_coefficients_for_a_level(): void
    {
        $level = $this->getLevel('6E');
        $math = $this->getSubject('MATH');
        $fran = $this->getSubject('FRAN');

        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id, 'level_id' => $level->id,
            'series_id' => null, 'coefficient' => 4, 'hours_per_week' => 5,
        ]);
        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $fran->id, 'level_id' => $level->id,
            'series_id' => null, 'coefficient' => 4, 'hours_per_week' => 6,
        ]);

        $response = $this->authGetJson("/api/admin/subject-class-coefficients?level_id={$level->id}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('totals.total_coefficient', 8);
        $response->assertJsonPath('totals.total_hours', 11);
    }

    #[Test]
    public function it_can_list_coefficients_for_level_and_series(): void
    {
        $level = $this->getLevel('TLE');
        $seriesA = $this->getSeries('A');
        $seriesC = $this->getSeries('C');
        $math = $this->getSubject('MATH');

        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id, 'level_id' => $level->id,
            'series_id' => $seriesA->id, 'coefficient' => 2, 'hours_per_week' => 3,
        ]);
        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id, 'level_id' => $level->id,
            'series_id' => $seriesC->id, 'coefficient' => 5, 'hours_per_week' => 6,
        ]);

        $response = $this->authGetJson("/api/admin/subject-class-coefficients?level_id={$level->id}&series_id={$seriesA->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.coefficient', 2);
    }

    #[Test]
    public function it_requires_level_id_on_index(): void
    {
        $response = $this->authGetJson('/api/admin/subject-class-coefficients');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('level_id');
    }

    // =============================================
    // Create
    // =============================================

    #[Test]
    public function it_can_create_a_coefficient(): void
    {
        $level = $this->getLevel('6E');
        $math = $this->getSubject('MATH');

        $response = $this->authPostJson('/api/admin/subject-class-coefficients', [
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'series_id' => null,
            'coefficient' => 4,
            'hours_per_week' => 5,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.coefficient', 4);
        $response->assertJsonPath('data.hours_per_week', 5);
    }

    #[Test]
    public function it_prevents_duplicate_coefficients(): void
    {
        $level = $this->getLevel('6E');
        $math = $this->getSubject('MATH');

        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id, 'level_id' => $level->id,
            'series_id' => null, 'coefficient' => 4,
        ]);

        $response = $this->authPostJson('/api/admin/subject-class-coefficients', [
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'series_id' => null,
            'coefficient' => 3,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('subject_id');
    }

    #[Test]
    public function it_requires_series_for_1ere(): void
    {
        $level = $this->getLevel('1ERE');
        $math = $this->getSubject('MATH');

        $response = $this->authPostJson('/api/admin/subject-class-coefficients', [
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'series_id' => null,
            'coefficient' => 4,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('series_id');
    }

    #[Test]
    public function it_requires_series_for_tle(): void
    {
        $level = $this->getLevel('TLE');
        $math = $this->getSubject('MATH');

        $response = $this->authPostJson('/api/admin/subject-class-coefficients', [
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'series_id' => null,
            'coefficient' => 4,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('series_id');
    }

    #[Test]
    public function it_allows_no_series_for_college_levels(): void
    {
        $level = $this->getLevel('6E');
        $math = $this->getSubject('MATH');

        $response = $this->authPostJson('/api/admin/subject-class-coefficients', [
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'coefficient' => 4,
            'hours_per_week' => 5,
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function it_validates_coefficient_range(): void
    {
        $level = $this->getLevel('6E');
        $math = $this->getSubject('MATH');

        $response = $this->authPostJson('/api/admin/subject-class-coefficients', [
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'coefficient' => 10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('coefficient');
    }

    // =============================================
    // Update
    // =============================================

    #[Test]
    public function it_can_update_a_coefficient(): void
    {
        $level = $this->getLevel('6E');
        $math = $this->getSubject('MATH');

        $coeff = SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id, 'level_id' => $level->id,
            'series_id' => null, 'coefficient' => 4, 'hours_per_week' => 5,
        ]);

        $response = $this->authPutJson("/api/admin/subject-class-coefficients/{$coeff->id}", [
            'coefficient' => 5,
            'hours_per_week' => 6,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.coefficient', 5);
        $response->assertJsonPath('data.hours_per_week', 6);
    }

    // =============================================
    // Destroy
    // =============================================

    #[Test]
    public function it_can_delete_a_coefficient_without_grades(): void
    {
        $level = $this->getLevel('6E');
        $math = $this->getSubject('MATH');

        $coeff = SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id, 'level_id' => $level->id,
            'series_id' => null, 'coefficient' => 4,
        ]);

        $response = $this->authDeleteJson("/api/admin/subject-class-coefficients/{$coeff->id}");

        $response->assertOk();
        $this->assertNull(SubjectClassCoefficient::on('tenant')->find($coeff->id));
    }

    // =============================================
    // Compare (Story 3.3)
    // =============================================

    #[Test]
    public function it_can_compare_coefficients_for_tle(): void
    {
        (new SubjectCoefficientSeeder)->run();

        $tle = $this->getLevel('TLE');

        $response = $this->authGetJson("/api/admin/subject-class-coefficients/compare?level_id={$tle->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'subjects' => [['code', 'name', 'coefficients']],
            'totals',
            'series',
        ]);

        $series = $response->json('series');
        $this->assertContains('A', $series);
        $this->assertContains('C', $series);
        $this->assertContains('D', $series);
    }

    #[Test]
    public function it_returns_422_for_compare_on_college_level(): void
    {
        $sixieme = $this->getLevel('6E');

        $response = $this->authGetJson("/api/admin/subject-class-coefficients/compare?level_id={$sixieme->id}");

        $response->assertStatus(422);
    }

    #[Test]
    public function it_can_export_comparison_pdf(): void
    {
        (new SubjectCoefficientSeeder)->run();

        $tle = $this->getLevel('TLE');

        $response = $this->withToken($this->token)
            ->get("/api/admin/subject-class-coefficients/compare/export?level_id={$tle->id}");

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    #[Test]
    public function it_returns_422_for_pdf_export_on_college_level(): void
    {
        $sixieme = $this->getLevel('6E');

        $response = $this->withToken($this->token)
            ->get("/api/admin/subject-class-coefficients/compare/export?level_id={$sixieme->id}");

        $response->assertStatus(422);
    }

    // =============================================
    // Duplicate (Story 3.4)
    // =============================================

    #[Test]
    public function it_can_duplicate_with_replace_strategy(): void
    {
        $level6e = $this->getLevel('6E');
        $level5e = $this->getLevel('5E');
        $math = $this->getSubject('MATH');
        $fran = $this->getSubject('FRAN');

        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id, 'level_id' => $level6e->id,
            'series_id' => null, 'coefficient' => 4, 'hours_per_week' => 5,
        ]);
        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $fran->id, 'level_id' => $level6e->id,
            'series_id' => null, 'coefficient' => 4, 'hours_per_week' => 6,
        ]);

        // Pre-existing in target
        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id, 'level_id' => $level5e->id,
            'series_id' => null, 'coefficient' => 2, 'hours_per_week' => 3,
        ]);

        $response = $this->authPostJson('/api/admin/subject-class-coefficients/duplicate', [
            'source_level_id' => $level6e->id,
            'target_level_id' => $level5e->id,
            'strategy' => 'replace',
        ]);

        $response->assertOk();
        $response->assertJsonPath('report.created_count', 2);
        $response->assertJsonPath('report.replaced_count', 1);

        $count = SubjectClassCoefficient::on('tenant')
            ->where('level_id', $level5e->id)
            ->count();
        $this->assertEquals(2, $count);
    }

    #[Test]
    public function it_can_duplicate_with_merge_strategy(): void
    {
        $level6e = $this->getLevel('6E');
        $level5e = $this->getLevel('5E');
        $math = $this->getSubject('MATH');
        $fran = $this->getSubject('FRAN');

        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id, 'level_id' => $level6e->id,
            'series_id' => null, 'coefficient' => 4, 'hours_per_week' => 5,
        ]);
        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $fran->id, 'level_id' => $level6e->id,
            'series_id' => null, 'coefficient' => 4, 'hours_per_week' => 6,
        ]);

        // Pre-existing - should be skipped
        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id, 'level_id' => $level5e->id,
            'series_id' => null, 'coefficient' => 2, 'hours_per_week' => 3,
        ]);

        $response = $this->authPostJson('/api/admin/subject-class-coefficients/duplicate', [
            'source_level_id' => $level6e->id,
            'target_level_id' => $level5e->id,
            'strategy' => 'merge',
        ]);

        $response->assertOk();
        $response->assertJsonPath('report.created_count', 1);
        $response->assertJsonPath('report.skipped_count', 1);

        // Original math coefficient should be unchanged
        $mathCoeff = SubjectClassCoefficient::on('tenant')
            ->where('level_id', $level5e->id)
            ->where('subject_id', $math->id)
            ->first();
        $this->assertEquals(2, (float) $mathCoeff->coefficient);
    }

    #[Test]
    public function it_returns_error_for_empty_source(): void
    {
        $level6e = $this->getLevel('6E');
        $level5e = $this->getLevel('5E');

        $response = $this->authPostJson('/api/admin/subject-class-coefficients/duplicate', [
            'source_level_id' => $level6e->id,
            'target_level_id' => $level5e->id,
            'strategy' => 'replace',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_prevents_duplicate_to_same_target(): void
    {
        $level6e = $this->getLevel('6E');

        $response = $this->authPostJson('/api/admin/subject-class-coefficients/duplicate', [
            'source_level_id' => $level6e->id,
            'target_level_id' => $level6e->id,
            'strategy' => 'replace',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('target_level_id');
    }

    // =============================================
    // Seeder
    // =============================================

    #[Test]
    public function seeder_creates_coefficients_for_all_levels(): void
    {
        (new SubjectCoefficientSeeder)->run();

        $sixieme = $this->getLevel('6E');
        $this->assertGreaterThan(0, SubjectClassCoefficient::on('tenant')->where('level_id', $sixieme->id)->count());

        $tle = $this->getLevel('TLE');
        $seriesC = $this->getSeries('C');
        $tleC = SubjectClassCoefficient::on('tenant')
            ->where('level_id', $tle->id)
            ->where('series_id', $seriesC->id)
            ->count();
        $this->assertGreaterThan(0, $tleC);
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        (new SubjectCoefficientSeeder)->run();
        $countBefore = SubjectClassCoefficient::on('tenant')->count();

        (new SubjectCoefficientSeeder)->run();
        $countAfter = SubjectClassCoefficient::on('tenant')->count();

        $this->assertEquals($countBefore, $countAfter);
    }

    // =============================================
    // Auth
    // =============================================

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/subject-class-coefficients?level_id=1');
        $response->assertStatus(401);
    }
}
