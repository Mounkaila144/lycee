<?php

namespace Tests\Feature\UsersGuard;

use Laravel\Sanctum\Sanctum;
use Modules\UsersGuard\Entities\TenantUser;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentsEndpointTest extends TestCase
{
    use InteractsWithTenancy;

    protected TenantUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        // Create roles
        Role::create(['name' => 'Administrator', 'guard_name' => 'tenant']);
        Role::create(['name' => 'Étudiant', 'guard_name' => 'tenant']);

        // Create admin user
        $this->admin = $this->createUser(['username' => 'admin']);
        $this->admin->assignRole('Administrator');

        Sanctum::actingAs($this->admin, guard: 'sanctum');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function createUser(array $attributes = []): TenantUser
    {
        return TenantUser::create(array_merge([
            'username' => 'user-'.uniqid(),
            'email' => 'test-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'firstname' => 'Test',
            'lastname' => 'User',
            'is_active' => true,
        ], $attributes));
    }

    public function test_students_endpoint_returns_only_students(): void
    {
        // Create students
        $student1 = $this->createUser(['firstname' => 'Aïssata', 'lastname' => 'Diallo']);
        $student1->assignRole('Étudiant');

        $student2 = $this->createUser(['firstname' => 'Moussa', 'lastname' => 'Traoré']);
        $student2->assignRole('Étudiant');

        // Create non-student user
        $admin2 = $this->createUser();
        $admin2->assignRole('Administrator');

        // Call endpoint
        $response = $this->getJson('/api/admin/students');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['firstname' => 'Aïssata']);
        $response->assertJsonFragment(['firstname' => 'Moussa']);
    }

    public function test_students_endpoint_returns_only_active_students(): void
    {
        // Create active student
        $activeStudent = $this->createUser(['firstname' => 'Active', 'is_active' => true]);
        $activeStudent->assignRole('Étudiant');

        // Create inactive student
        $inactiveStudent = $this->createUser(['firstname' => 'Inactive', 'is_active' => false]);
        $inactiveStudent->assignRole('Étudiant');

        // Call endpoint
        $response = $this->getJson('/api/admin/students');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['firstname' => 'Active']);
    }

    public function test_students_endpoint_search_works(): void
    {
        // Create students
        $student1 = $this->createUser(['firstname' => 'Aïssata', 'lastname' => 'Diallo']);
        $student1->assignRole('Étudiant');

        $student2 = $this->createUser(['firstname' => 'Moussa', 'lastname' => 'Traoré']);
        $student2->assignRole('Étudiant');

        // Search by firstname
        $response = $this->getJson('/api/admin/students?search=Aïssata');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['firstname' => 'Aïssata']);

        // Search by lastname
        $response = $this->getJson('/api/admin/students?search=Traoré');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['lastname' => 'Traoré']);
    }

    public function test_students_endpoint_pagination_works(): void
    {
        // Create 20 students
        for ($i = 1; $i <= 20; $i++) {
            $student = $this->createUser();
            $student->assignRole('Étudiant');
        }

        // Default pagination (15 per page)
        $response = $this->getJson('/api/admin/students');

        $response->assertStatus(200);
        $response->assertJsonCount(15, 'data');
        $response->assertJsonPath('meta.total', 20);
        $response->assertJsonPath('meta.per_page', 15);

        // Custom per_page
        $response = $this->getJson('/api/admin/students?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.per_page', 10);
    }

    public function test_students_endpoint_requires_authentication(): void
    {
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/admin/students');

        $response->assertStatus(401);
    }
}
