<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Support\Facades\Queue;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentCard;
use Modules\Enrollment\Services\StudentCardGeneratorService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentCardApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    private AcademicYear $academicYear;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->student = Student::factory()->create(['status' => 'Actif']);
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

    private function authPatchJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->patchJson($uri, $data);
    }

    #[Test]
    public function it_can_list_student_cards(): void
    {
        StudentCard::factory()->count(3)->create([
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authGetJson('/api/admin/enrollment/student-cards');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'card_number', 'status', 'print_status'],
            ],
            'meta' => ['current_page', 'total'],
        ]);
    }

    #[Test]
    public function it_can_filter_cards_by_status(): void
    {
        StudentCard::factory()->active()->create(['academic_year_id' => $this->academicYear->id]);
        StudentCard::factory()->expired()->create(['academic_year_id' => $this->academicYear->id]);

        $response = $this->authGetJson('/api/admin/enrollment/student-cards?status=Active');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_can_generate_student_card(): void
    {
        Queue::fake();

        $response = $this->authPostJson("/api/admin/enrollment/student-cards/generate/{$this->student->id}", [
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => ['id', 'card_number', 'status', 'qr_signature'],
        ]);

        $this->assertDatabaseHas('student_cards', [
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'Active',
        ], 'tenant');
    }

    #[Test]
    public function it_returns_existing_card_if_already_generated(): void
    {
        $existingCard = StudentCard::factory()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'is_duplicate' => false,
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/student-cards/generate/{$this->student->id}", [
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals($existingCard->id, $response->json('data.id'));
    }

    #[Test]
    public function it_can_batch_generate_cards(): void
    {
        Queue::fake();

        $students = Student::factory()->count(3)->create();

        $response = $this->authPostJson('/api/admin/enrollment/student-cards/batch-generate', [
            'student_ids' => $students->pluck('id')->toArray(),
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => ['generated', 'skipped', 'failed'],
        ]);
        $this->assertCount(3, $response->json('data.generated'));
    }

    #[Test]
    public function it_can_show_card_details(): void
    {
        $card = StudentCard::factory()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/student-cards/{$card->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'card_number', 'student', 'academic_year'],
        ]);
    }

    #[Test]
    public function it_can_generate_duplicate_card(): void
    {
        Queue::fake();

        $originalCard = StudentCard::factory()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'is_duplicate' => false,
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/student-cards/{$originalCard->id}/duplicate");

        $response->assertStatus(201);
        $this->assertTrue($response->json('data.is_duplicate'));
    }

    #[Test]
    public function it_can_update_card_status(): void
    {
        $card = StudentCard::factory()->active()->create([
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authPatchJson("/api/admin/enrollment/student-cards/{$card->id}/status", [
            'status' => 'Suspended',
        ]);

        $response->assertOk();
        $this->assertEquals('Suspended', $response->json('data.status'));
    }

    #[Test]
    public function it_validates_card_status_value(): void
    {
        $card = StudentCard::factory()->create([
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authPatchJson("/api/admin/enrollment/student-cards/{$card->id}/status", [
            'status' => 'InvalidStatus',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_can_update_print_status(): void
    {
        $card = StudentCard::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'print_status' => 'Pending',
        ]);

        $response = $this->authPatchJson("/api/admin/enrollment/student-cards/{$card->id}/print-status", [
            'print_status' => 'Printed',
        ]);

        $response->assertOk();
        $this->assertEquals('Printed', $response->json('data.print_status'));
        $this->assertNotNull($response->json('data.printed_at'));
    }

    #[Test]
    public function it_can_verify_valid_card(): void
    {
        // Fake queue to prevent job dispatch
        Queue::fake();

        // Generate card through service to get a valid signature
        $service = app(StudentCardGeneratorService::class);
        $card = $service->generate($this->student, $this->academicYear);

        $qrData = $card->getQrDataArray();
        $signature = $card->qr_signature;

        $response = $this->authPostJson('/api/admin/enrollment/student-cards/verify', [
            'qr_data' => json_encode($qrData),
            'signature' => $signature,
        ]);

        $response->assertOk();
        $response->assertJson(['data' => ['valid' => true]]);
    }

    #[Test]
    public function it_rejects_invalid_card_signature(): void
    {
        $card = StudentCard::factory()->active()->create([
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authPostJson('/api/admin/enrollment/student-cards/verify', [
            'qr_data' => json_encode(['card_number' => $card->card_number]),
            'signature' => 'invalid_signature',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_can_get_card_statistics(): void
    {
        StudentCard::factory()->count(5)->create([
            'academic_year_id' => $this->academicYear->id,
            'status' => 'Active',
        ]);

        StudentCard::factory()->count(2)->create([
            'academic_year_id' => $this->academicYear->id,
            'is_duplicate' => true,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/student-cards/statistics?academic_year_id={$this->academicYear->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['total', 'originals', 'duplicates', 'by_status', 'by_print_status'],
        ]);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/enrollment/student-cards');

        $response->assertStatus(401);
    }
}
