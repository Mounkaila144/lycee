<?php

namespace Tests\Feature\RoleCoverage\Administrator;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Story Admin 13 — Réglages de l'établissement.
 *
 * Couvre :
 *   - CRUD complet sur tenant_settings (Admin uniquement)
 *   - Tous les autres rôles bloqués → 403
 */
class SettingsTest extends TestCase
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

    private function tokenFor(string $role): string
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user->createToken('test-token')->plainTextToken;
    }

    #[Test]
    public function admin_can_list_settings(): void
    {
        $token = $this->tokenFor('Administrator');

        $this->withToken($token)
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function admin_can_create_setting(): void
    {
        $token = $this->tokenFor('Administrator');

        $this->withToken($token)
            ->postJson('/api/admin/settings', [
                'key' => 'school.name',
                'value' => 'Lycée Test',
                'type' => 'string',
                'category' => 'general',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.key', 'school.name');
    }

    #[Test]
    public function admin_can_upsert_existing_setting(): void
    {
        $token = $this->tokenFor('Administrator');

        $this->withToken($token)->postJson('/api/admin/settings', [
            'key' => 'school.name',
            'value' => 'Old Name',
            'type' => 'string',
        ])->assertStatus(201);

        $this->withToken($token)->postJson('/api/admin/settings', [
            'key' => 'school.name',
            'value' => 'New Name',
            'type' => 'string',
        ])
            ->assertOk()
            ->assertJsonPath('data.value', 'New Name');
    }

    #[Test]
    public function admin_can_show_and_delete_setting(): void
    {
        $token = $this->tokenFor('Administrator');

        $this->withToken($token)->postJson('/api/admin/settings', [
            'key' => 'pdf.watermark',
            'value' => 'true',
            'type' => 'boolean',
        ])->assertStatus(201);

        $this->withToken($token)
            ->getJson('/api/admin/settings/pdf.watermark')
            ->assertOk()
            ->assertJsonPath('data.value', 'true');

        $this->withToken($token)
            ->deleteJson('/api/admin/settings/pdf.watermark')
            ->assertOk();
    }

    #[Test]
    public function manager_cannot_access_settings(): void
    {
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->getJson('/api/admin/settings')
            ->assertForbidden();
    }

    #[Test]
    public function comptable_cannot_modify_settings(): void
    {
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)
            ->postJson('/api/admin/settings', [
                'key' => 'finance.currency',
                'value' => 'XOF',
                'type' => 'string',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_settings(): void
    {
        $token = $this->tokenFor('Étudiant');

        $this->withToken($token)
            ->getJson('/api/admin/settings')
            ->assertForbidden();
    }
}
