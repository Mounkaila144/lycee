<?php

namespace Tests\Feature\UsersGuard;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class AuthLoginPayloadTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function loginPayload(TenantUser $user, string $rawPassword = 'password'): array
    {
        $response = $this->postJson('/api/admin/auth/login', [
            'username' => $user->username,
            'password' => $rawPassword,
            'application' => 'admin',
        ]);

        $response->assertOk();

        return $response->json('data.user');
    }

    #[Test]
    public function administrator_login_returns_dashboard_home_route(): void
    {
        $user = TenantUser::factory()->create([
            'application' => 'admin',
            'password' => bcrypt('password'),
        ]);
        $this->assignRole($user, 'Administrator');

        $payload = $this->loginPayload($user);

        $this->assertSame('Administrator', $payload['primary_role']);
        $this->assertSame('/admin/dashboard', $payload['home_route']);
        $this->assertContains('Administrator', $payload['roles']);
    }

    #[Test]
    public function professeur_login_returns_teacher_home_route(): void
    {
        $user = TenantUser::factory()->create([
            'application' => 'admin',
            'password' => bcrypt('password'),
        ]);
        $this->assignRole($user, 'Professeur');

        $payload = $this->loginPayload($user);

        $this->assertSame('Professeur', $payload['primary_role']);
        $this->assertSame('/admin/teacher/home', $payload['home_route']);
    }

    #[Test]
    public function etudiant_login_returns_student_home_route(): void
    {
        $user = TenantUser::factory()->create([
            'application' => 'admin',
            'password' => bcrypt('password'),
        ]);
        $this->assignRole($user, 'Étudiant');

        $payload = $this->loginPayload($user);

        $this->assertSame('Étudiant', $payload['primary_role']);
        $this->assertSame('/admin/student/home', $payload['home_route']);
    }

    #[Test]
    public function caissier_login_returns_encaissements_home_route(): void
    {
        $user = TenantUser::factory()->create([
            'application' => 'admin',
            'password' => bcrypt('password'),
        ]);
        $this->assignRole($user, 'Caissier');

        $payload = $this->loginPayload($user);

        $this->assertSame('Caissier', $payload['primary_role']);
        $this->assertSame('/admin/finance/payments', $payload['home_route']);
    }

    #[Test]
    public function comptable_login_returns_finance_dashboard_home_route(): void
    {
        $user = TenantUser::factory()->create([
            'application' => 'admin',
            'password' => bcrypt('password'),
        ]);
        $this->assignRole($user, 'Comptable');

        $payload = $this->loginPayload($user);

        $this->assertSame('Comptable', $payload['primary_role']);
        $this->assertSame('/admin/finance/reports', $payload['home_route']);
    }

    #[Test]
    public function multiple_roles_respect_hierarchy(): void
    {
        $user = TenantUser::factory()->create([
            'application' => 'admin',
            'password' => bcrypt('password'),
        ]);
        $this->assignRole($user, 'Caissier');
        $this->assignRole($user, 'Comptable'); // Comptable is more prioritary

        $payload = $this->loginPayload($user);

        $this->assertSame('Comptable', $payload['primary_role']);
        $this->assertSame('/admin/finance/reports', $payload['home_route']);
    }

    #[Test]
    public function user_with_frontend_application_can_still_login(): void
    {
        // Regression: many real users have application='frontend' but should still
        // be able to login since roles drive the post-login redirect now.
        $user = TenantUser::factory()->create([
            'application' => 'frontend',
            'password' => bcrypt('password'),
        ]);
        $this->assignRole($user, 'Professeur');

        $payload = $this->loginPayload($user);

        $this->assertSame('Professeur', $payload['primary_role']);
        $this->assertSame('/admin/teacher/home', $payload['home_route']);
    }

    #[Test]
    public function user_without_role_falls_back_to_default_home(): void
    {
        $user = TenantUser::factory()->create([
            'application' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $payload = $this->loginPayload($user);

        $this->assertNull($payload['primary_role']);
        $this->assertSame('/admin/dashboard', $payload['home_route']);
    }
}
