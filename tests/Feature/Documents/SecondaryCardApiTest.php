<?php

namespace Tests\Feature\Documents;

use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SecondaryCardApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $year = AcademicYear::factory()->create();
        $level = Level::factory()->create();
        $classe = Classe::factory()->create([
            'academic_year_id' => $year->id,
            'level_id' => $level->id,
            'series_id' => null,
        ]);
        $this->student = Student::factory()->create([
            'class_id' => $classe->id,
            'matricule' => 'STU-CARD-1',
        ]);
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

    private function authGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->get($uri);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/documents/secondary-cards')->assertStatus(401);
    }

    #[Test]
    public function it_generates_and_lists_a_student_card(): void
    {
        $create = $this->authPostJson('/api/admin/documents/secondary-cards', [
            'student_id' => $this->student->id,
        ]);

        $create->assertStatus(201);
        $cardNumber = $create->json('data.document_number');
        $this->assertStringStartsWith('CS-', $cardNumber);

        $list = $this->authGetJson('/api/admin/documents/secondary-cards');
        $list->assertOk();
        $list->assertJsonFragment(['card_number' => $cardNumber]);
    }

    #[Test]
    public function it_downloads_a_card_pdf(): void
    {
        $create = $this->authPostJson('/api/admin/documents/secondary-cards', [
            'student_id' => $this->student->id,
        ]);
        $id = $create->json('data.id');

        $response = $this->authGet("/api/admin/documents/secondary-cards/{$id}/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function it_validates_the_student_id(): void
    {
        $this->authPostJson('/api/admin/documents/secondary-cards', [])->assertStatus(422);
    }
}
