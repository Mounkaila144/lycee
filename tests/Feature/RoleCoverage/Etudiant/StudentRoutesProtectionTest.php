<?php

namespace Tests\Feature\RoleCoverage\Etudiant;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Protection des routes Étudiant — couvre les Stories Étudiant 01-08 :
 * vérifie que l'Étudiant est bloqué sur les routes admin sensibles
 * (CRUD users, finance admin, paie, structure académique CRUD, etc.).
 *
 * Note : Les endpoints frontend Étudiant (`/api/frontend/student/*`) ne sont
 * pas encore créés. Quand ils le seront, ajouter des tests positifs ici.
 */
class StudentRoutesProtectionTest extends TestCase
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

    private function tokenForEtudiant(): string
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Étudiant');

        return $user->createToken('test-token')->plainTextToken;
    }

    #[Test]
    public function etudiant_can_view_own_profile(): void
    {
        $token = $this->tokenForEtudiant();

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertOk();
    }

    #[Test]
    public function etudiant_cannot_list_global_users(): void
    {
        $token = $this->tokenForEtudiant();

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_teacher_endpoints(): void
    {
        $token = $this->tokenForEtudiant();

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_finance_admin(): void
    {
        $token = $this->tokenForEtudiant();

        $this->withToken($token)
            ->getJson('/api/admin/finance/invoices')
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_create_student(): void
    {
        $token = $this->tokenForEtudiant();

        $this->withToken($token)
            ->postJson('/api/admin/students', [
                'firstname' => 'Test',
                'lastname' => 'Test',
                'birthdate' => '2010-01-01',
                'sex' => 'M',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_payroll(): void
    {
        $token = $this->tokenForEtudiant();

        $response = $this->withToken($token)->getJson('/api/admin/payroll/employees');

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertNotSame(200, $response->getStatusCode());
    }

    #[Test]
    public function etudiant_cannot_access_documents_admin(): void
    {
        $token = $this->tokenForEtudiant();

        $response = $this->withToken($token)->getJson('/api/admin/documents/diplomas');

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertNotSame(200, $response->getStatusCode());
    }
}
