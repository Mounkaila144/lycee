<?php

namespace Tests\Feature\Enrollment;

use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Classe;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Covers the secondary "filter students by class" feature on the students list
 * (GET /api/admin/enrollment/students?class_id=...).
 */
class StudentClassFilterApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;
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

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/enrollment/students')->assertStatus(401);
    }

    #[Test]
    public function it_filters_students_by_class(): void
    {
        $classA = Classe::factory()->create();
        $classB = Classe::factory()->create();

        Student::factory()->count(2)->create(['class_id' => $classA->id]);
        Student::factory()->count(3)->create(['class_id' => $classB->id]);

        $response = $this->authGetJson('/api/admin/enrollment/students?class_id='.$classA->id);

        $response->assertOk()->assertJsonCount(2, 'data');
        foreach ($response->json('data') as $student) {
            $this->assertSame($classA->id, $student['class_id']);
        }
    }

    #[Test]
    public function it_returns_all_students_without_a_class_filter(): void
    {
        $classA = Classe::factory()->create();
        Student::factory()->count(2)->create(['class_id' => $classA->id]);
        Student::factory()->count(3)->create(['class_id' => Classe::factory()->create()->id]);

        $this->authGetJson('/api/admin/enrollment/students')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    #[Test]
    public function it_exposes_the_class_on_each_student(): void
    {
        $classe = Classe::factory()->create();
        Student::factory()->create(['class_id' => $classe->id]);

        $response = $this->authGetJson('/api/admin/enrollment/students?class_id='.$classe->id);

        $response->assertOk()
            ->assertJsonPath('data.0.classe.id', $classe->id)
            ->assertJsonPath('data.0.classe.name', $classe->name);
    }
}
