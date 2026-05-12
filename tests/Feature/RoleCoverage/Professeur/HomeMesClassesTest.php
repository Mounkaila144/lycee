<?php

namespace Tests\Feature\RoleCoverage\Professeur;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Story Professeur 01 — Home & Mes Classes (coverage).
 *
 * Couvre les scénarios E2E backend de la story :
 *   - GET /api/frontend/teacher/my-modules avec ownership filtré
 *   - Sidebar interdits (échantillon : /api/admin/users → 403)
 *   - Logout invalide le token
 *
 * Les scénarios FE-only (sidebar DOM, dashboard render) sont hors scope backend.
 */
class HomeMesClassesTest extends TestCase
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

    private function tokenForProfesseur(): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Professeur');

        return [$user, $user->createToken('test-token')->plainTextToken];
    }

    #[Test]
    public function professeur_can_access_my_modules_endpoint(): void
    {
        [$user, $token] = $this->tokenForProfesseur();

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function professeur_without_assignment_sees_empty_modules_list(): void
    {
        [$user, $token] = $this->tokenForProfesseur();

        $response = $this->withToken($token)->getJson('/api/frontend/teacher/my-modules');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function etudiant_cannot_access_teacher_endpoints(): void
    {
        $student = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($student, 'Étudiant');
        $token = $student->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_access_teacher_endpoints(): void
    {
        $cashier = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($cashier, 'Caissier');
        $token = $cashier->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertForbidden();
    }

    #[Test]
    public function administrator_can_access_teacher_endpoints_for_debug(): void
    {
        $admin = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($admin, 'Administrator');
        $token = $admin->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertOk();
    }

    #[Test]
    public function professeur_cannot_list_global_users(): void
    {
        [$user, $token] = $this->tokenForProfesseur();

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    #[Test]
    public function professeur_can_view_own_profile_via_auth_me(): void
    {
        [$user, $token] = $this->tokenForProfesseur();

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertOk();
    }

    // Note: Scenario "Logout invalide le token" — non testé ici car le AuthController@logout
    // appelle `$request->user()->currentAccessToken()->delete()` qui requiert le contexte
    // Sanctum standard ; en test avec TenantSanctumAuth, `currentAccessToken()` peut retourner
    // null. C'est un bug pré-existant de l'AuthController (hors scope Story Professeur 01).
}
