<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\Reenrollment;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ReenrollmentAdminApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    private ReenrollmentCampaign $campaign;

    private Programme $programme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = TenantUser::factory()->create();
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $fromYear = AcademicYear::factory()->create(['name' => '2090-2091']);
        $toYear = AcademicYear::factory()->create(['name' => '2091-2092']);
        $this->programme = Programme::factory()->create();

        $this->campaign = ReenrollmentCampaign::on('tenant')->create([
            'from_academic_year_id' => $fromYear->id,
            'to_academic_year_id' => $toYear->id,
            'name' => 'Campagne test',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
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

    private function authPostJson(string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function createSubmittedReenrollment(): Reenrollment
    {
        return Reenrollment::on('tenant')->create([
            'campaign_id' => $this->campaign->id,
            'student_id' => Student::factory()->create()->id,
            'previous_level' => '6E',
            'target_level' => '5E',
            'target_program_id' => $this->programme->id,
            'status' => Reenrollment::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    #[Test]
    public function it_can_list_reenrollments_and_statistics(): void
    {
        $this->createSubmittedReenrollment();

        $this->authGetJson('/api/admin/enrollment/reenrollments')->assertOk()->assertJsonCount(1, 'data');
        $this->authGetJson('/api/admin/enrollment/reenrollments/statistics')->assertOk();
    }

    #[Test]
    public function it_rejects_a_submitted_reenrollment_and_records_the_validator(): void
    {
        $reenrollment = $this->createSubmittedReenrollment();

        $response = $this->authPostJson("/api/admin/enrollment/reenrollments/{$reenrollment->id}/reject", [
            'reason' => 'Dossier incomplet : bulletin manquant.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('reenrollments', [
            'id' => $reenrollment->id,
            'status' => Reenrollment::STATUS_REJECTED,
            'validated_by' => $this->user->id,
        ], 'tenant');
    }

    #[Test]
    public function it_requires_a_reason_to_reject(): void
    {
        $reenrollment = $this->createSubmittedReenrollment();

        $this->authPostJson("/api/admin/enrollment/reenrollments/{$reenrollment->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }
}
