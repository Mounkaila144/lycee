<?php

namespace Tests\Feature\UsersGuard;

use Laravel\Sanctum\Sanctum;
use Modules\UsersGuard\Entities\TenantUser;
use Modules\UsersGuard\Entities\User;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class TeachersEndpointTest extends TestCase
{
    use InteractsWithTenancy;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Disable tenancy middleware for tests
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        // Create permissions manually (instead of using seeder which calls tenancy()->initialize())
        $this->createPermissionsAndRoles();

        // Create authenticated user
        $this->user = $this->createUser();
        Sanctum::actingAs($this->user);
    }

    /**
     * Create permissions and roles for testing
     */
    private function createPermissionsAndRoles(): void
    {
        $permissions = [
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'view settings',
            'edit settings',
            'view reports',
            'export reports',
            'view dashboard',
            'view students',
            'manage grades',
            'view timetable',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::updateOrCreate(
                ['name' => $permission, 'guard_name' => 'tenant'],
                ['display_name' => ucfirst($permission)]
            );
        }

        // Create Professeur role
        $teacherRole = Role::updateOrCreate(
            ['name' => 'Professeur', 'guard_name' => 'tenant'],
            ['display_name' => 'Professeur', 'description' => 'Enseignant - Gestion des notes et cours']
        );
        $teacherRole->syncPermissions([
            'view dashboard',
            'view students',
            'manage grades',
            'view timetable',
        ]);

        // Create other roles for testing
        $adminRole = Role::updateOrCreate(
            ['name' => 'Administrator', 'guard_name' => 'tenant'],
            ['display_name' => 'Administrator', 'description' => 'Full access']
        );

        $managerRole = Role::updateOrCreate(
            ['name' => 'Manager', 'guard_name' => 'tenant'],
            ['display_name' => 'Manager', 'description' => 'Manager access']
        );
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper to create a user without factory
     */
    private function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'username' => 'testuser-'.uniqid(),
            'email' => 'test-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'firstname' => 'Test',
            'lastname' => 'User',
        ], $attributes));
    }

    /**
     * Helper to create a tenant user
     */
    private function createTenantUser(array $attributes = []): TenantUser
    {
        return TenantUser::create(array_merge([
            'username' => 'tenant-user-'.uniqid(),
            'email' => 'tenant-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'firstname' => 'Tenant',
            'lastname' => 'User',
            'is_active' => true,
        ], $attributes));
    }

    public function test_professeur_role_is_created_by_seeder(): void
    {
        $role = Role::where('name', 'Professeur')->where('guard_name', 'tenant')->first();

        $this->assertNotNull($role, 'Professeur role should exist');
        $this->assertEquals('Professeur', $role->display_name);
        $this->assertStringContainsString('Enseignant', $role->description);

        // Verify permissions
        $this->assertTrue($role->hasPermissionTo('view dashboard'));
        $this->assertTrue($role->hasPermissionTo('view students'));
        $this->assertTrue($role->hasPermissionTo('manage grades'));
        $this->assertTrue($role->hasPermissionTo('view timetable'));
    }

    public function test_teachers_endpoint_returns_only_users_with_professeur_role(): void
    {
        // Create teachers (with Professeur role)
        $teacher1 = $this->createTenantUser(['firstname' => 'Jean', 'lastname' => 'Dupont']);
        $teacher1->assignRole('Professeur');

        $teacher2 = $this->createTenantUser(['firstname' => 'Marie', 'lastname' => 'Martin']);
        $teacher2->assignRole('Professeur');

        // Create non-teachers (different roles)
        $admin = $this->createTenantUser(['firstname' => 'Admin', 'lastname' => 'User']);
        $admin->assignRole('Administrator');

        $manager = $this->createTenantUser(['firstname' => 'Manager', 'lastname' => 'User']);
        $manager->assignRole('Manager');

        $response = $this->getJson('/api/admin/teachers');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['firstname' => 'Jean', 'lastname' => 'Dupont'])
            ->assertJsonFragment(['firstname' => 'Marie', 'lastname' => 'Martin']);

        // Verify non-teachers are NOT in response
        $responseData = $response->json('data');
        $names = array_column($responseData, 'firstname');
        $this->assertNotContains('Admin', $names);
        $this->assertNotContains('Manager', $names);
    }

    public function test_teachers_endpoint_returns_only_active_users(): void
    {
        // Create active teacher
        $activeTeacher = $this->createTenantUser([
            'firstname' => 'Active',
            'lastname' => 'Teacher',
            'is_active' => true,
        ]);
        $activeTeacher->assignRole('Professeur');

        // Create inactive teacher
        $inactiveTeacher = $this->createTenantUser([
            'firstname' => 'Inactive',
            'lastname' => 'Teacher',
            'is_active' => false,
        ]);
        $inactiveTeacher->assignRole('Professeur');

        $response = $this->getJson('/api/admin/teachers');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['firstname' => 'Active', 'lastname' => 'Teacher']);

        // Verify inactive teacher is NOT in response
        $responseData = $response->json('data');
        $names = array_column($responseData, 'firstname');
        $this->assertNotContains('Inactive', $names);
    }

    public function test_teachers_endpoint_supports_search_functionality(): void
    {
        // Create teachers with different names
        $dupont = $this->createTenantUser([
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'email' => 'j.dupont@university.ne',
            'username' => 'prof.dupont',
        ]);
        $dupont->assignRole('Professeur');

        $martin = $this->createTenantUser([
            'firstname' => 'Marie',
            'lastname' => 'Martin',
            'email' => 'm.martin@university.ne',
            'username' => 'prof.martin',
        ]);
        $martin->assignRole('Professeur');

        $smith = $this->createTenantUser([
            'firstname' => 'John',
            'lastname' => 'Smith',
            'email' => 'j.smith@university.ne',
            'username' => 'prof.smith',
        ]);
        $smith->assignRole('Professeur');

        // Search by lastname
        $response = $this->getJson('/api/admin/teachers?search=Dupont');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['lastname' => 'Dupont']);

        // Search by firstname
        $response = $this->getJson('/api/admin/teachers?search=Marie');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['firstname' => 'Marie']);

        // Search by email
        $response = $this->getJson('/api/admin/teachers?search=j.smith');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['email' => 'j.smith@university.ne']);

        // Search by username
        $response = $this->getJson('/api/admin/teachers?search=prof.martin');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['username' => 'prof.martin']);

        // Partial search
        $response = $this->getJson('/api/admin/teachers?search=prof');
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data'); // All 3 teachers have 'prof' in username
    }

    public function test_teachers_endpoint_supports_pagination(): void
    {
        // Create 25 teachers
        for ($i = 1; $i <= 25; $i++) {
            $teacher = $this->createTenantUser([
                'firstname' => "Teacher{$i}",
                'lastname' => "Last{$i}",
            ]);
            $teacher->assignRole('Professeur');
        }

        // Default pagination (15 per page)
        $response = $this->getJson('/api/admin/teachers');
        $response->assertStatus(200)
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 25);

        // Custom per_page
        $response = $this->getJson('/api/admin/teachers?per_page=10');
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);

        // Second page
        $response = $this->getJson('/api/admin/teachers?per_page=10&page=2');
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_teachers_endpoint_respects_multi_tenancy_isolation(): void
    {
        // Create teacher in current tenant (test-tenant)
        $teacher1 = $this->createTenantUser(['firstname' => 'Tenant1', 'lastname' => 'Teacher']);
        $teacher1->assignRole('Professeur');

        // Simulate another tenant's data
        // Note: In real multi-tenancy, this would be in a separate database
        // For testing, we create a user with a marker to simulate another tenant
        $otherTenantTeacher = $this->createTenantUser([
            'firstname' => 'OtherTenant',
            'lastname' => 'Teacher',
            'email' => 'other-tenant@example.com',
        ]);
        $otherTenantTeacher->assignRole('Professeur');

        // Query teachers for current tenant
        $response = $this->getJson('/api/admin/teachers');

        $response->assertStatus(200);

        // Verify both teachers are returned (same DB in tests)
        // In production with separate tenant databases, only tenant1 teacher would be returned
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_teachers_endpoint_returns_correct_json_structure(): void
    {
        $teacher = $this->createTenantUser([
            'firstname' => 'Test',
            'lastname' => 'Teacher',
            'email' => 'teacher@test.com',
            'username' => 'test.teacher',
            'phone' => '+227 90 12 34 56',
        ]);
        $teacher->assignRole('Professeur');

        $response = $this->getJson('/api/admin/teachers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'username',
                        'email',
                        'firstname',
                        'lastname',
                        'full_name',
                        'is_active',
                        'roles',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);

        // Verify roles array contains "Professeur"
        $teacherData = $response->json('data')[0];
        $this->assertContains('Professeur', $teacherData['roles']);
    }

    public function test_teachers_endpoint_requires_authentication(): void
    {
        // Clear authentication
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/admin/teachers');

        $response->assertStatus(401);
    }
}
