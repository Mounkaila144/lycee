<?php

namespace Tests\Feature\UsersGuard;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\UsersGuard\Entities\SuperAdmin;
use Modules\UsersGuard\Entities\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Super-admin › Sites : persistance des colonnes virtuelles du tenant (nom, statut),
 * blocage des écoles désactivées, identification par domaine et activation d'un site.
 * Base de test = base centrale (tables users/tokens centrales + tenants/domains).
 */
class SuperadminTenantManagementTest extends TestCase
{
    private const PROVISIONED_TENANT = 'a9-ecole-provisionnee';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        DB::purge('mysql');
        Artisan::call('db:wipe', ['--database' => 'mysql', '--force' => true]);

        foreach ([
            'Modules/UsersGuard/Database/Migrations/2024_01_01_000001_create_central_users_table.php',
            'Modules/UsersGuard/Database/Migrations/2025_12_16_091944_create_central_personal_access_tokens_table.php',
            'Modules/UsersGuard/Database/Migrations/2024_01_01_000002_create_tenants_table.php',
            'Modules/UsersGuard/Database/Migrations/2024_01_01_000003_create_domains_table.php',
        ] as $path) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        $superAdmin = SuperAdmin::query()->create([
            'username' => 'root.admin',
            'email' => 'root.admin@example.test',
            'password' => Hash::make('secret'),
            'application' => 'superadmin',
            'is_active' => true,
        ]);
        $this->token = $superAdmin->createToken('superadmin-token', ['role:superadmin'])->plainTextToken;
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        DB::connection('mysql')->statement('DROP DATABASE IF EXISTS `tenant_'.self::PROVISIONED_TENANT.'`');
        DB::purge('mysql');
        Artisan::call('db:wipe', ['--database' => 'mysql', '--force' => true]);
        parent::tearDown();
    }

    /**
     * Tenant shaped like production "lycee1": data JSON without company_name/is_active.
     */
    private function insertLegacyTenant(string $id, string $domain): void
    {
        DB::connection('mysql')->table('tenants')->insert([
            'id' => $id,
            'data' => json_encode(['created_at' => '2026-04-13 09:35:04', 'updated_at' => '2026-04-13 09:35:04']),
            'created_at' => '2026-04-13 09:35:04',
            'updated_at' => '2026-04-13 09:35:04',
        ]);
        DB::connection('mysql')->table('domains')->insert([
            'domain' => $domain,
            'tenant_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function it_persists_name_and_status_through_the_superadmin_api(): void
    {
        $this->withToken($this->token)->postJson('/api/superadmin/tenants', [
            'id' => 'ecole-test',
            'company_name' => 'Lycée Test',
            'company_email' => 'contact@ecole-test.test',
            'domains' => [['domain' => 'ecole-test.test']],
        ])->assertCreated()
            ->assertJsonPath('tenant.company_name', 'Lycée Test')
            ->assertJsonPath('tenant.is_active', true);

        $tenant = Tenant::query()->findOrFail('ecole-test');
        $this->assertSame('Lycée Test', $tenant->company_name);
        $this->assertSame('contact@ecole-test.test', $tenant->company_email);
        $rawData = json_decode(DB::connection('mysql')->table('tenants')->where('id', 'ecole-test')->value('data'), true);
        $this->assertSame('Lycée Test', $rawData['company_name']);

        $this->withToken($this->token)->putJson('/api/superadmin/tenants/ecole-test', [
            'company_name' => 'Lycée Renommé',
            'domains' => [['domain' => 'ecole-test.test']],
        ])->assertOk()
            ->assertJsonPath('tenant.company_name', 'Lycée Renommé');
        $this->assertSame('Lycée Renommé', Tenant::query()->findOrFail('ecole-test')->company_name);

        $this->withToken($this->token)->postJson('/api/superadmin/tenants/ecole-test/toggle-active')
            ->assertOk()
            ->assertJsonPath('tenant.is_active', false);
        $this->assertFalse(Tenant::query()->findOrFail('ecole-test')->isActive());

        $this->withToken($this->token)->postJson('/api/superadmin/tenants/ecole-test/toggle-active')
            ->assertOk()
            ->assertJsonPath('tenant.is_active', true);
        $this->assertTrue(Tenant::query()->findOrFail('ecole-test')->isActive());

        $this->withToken($this->token)->getJson('/api/superadmin/tenants?search=Renommé')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'ecole-test')
            ->assertJsonPath('data.0.company_name', 'Lycée Renommé');
    }

    #[Test]
    public function active_scope_treats_a_missing_status_as_active(): void
    {
        $this->insertLegacyTenant('legacy', 'legacy.test');
        Tenant::query()->create(['id' => 'inactive', 'company_name' => 'Fermée', 'is_active' => false]);
        Tenant::query()->create(['id' => 'active', 'company_name' => 'Ouverte', 'is_active' => true]);

        $this->assertEqualsCanonicalizing(['legacy', 'active'], Tenant::query()->active()->pluck('id')->all());
        $this->assertTrue(Tenant::query()->findOrFail('legacy')->isActive());
        $this->assertNull(Tenant::query()->findOrFail('legacy')->company_name);
    }

    #[Test]
    public function an_existing_tenant_is_still_identified_by_its_domain(): void
    {
        $this->insertLegacyTenant('legacy', 'legacy.test');

        $this->getJson('http://legacy.test/api/admin/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
        $this->getJson('http://legacy.test/admin/auth/me')->assertUnauthorized();

        $this->getJson('http://unknown-school.test/api/admin/auth/me')
            ->assertServerError();
    }

    #[Test]
    public function a_deactivated_school_is_blocked_and_reopened_when_reactivated(): void
    {
        $this->insertLegacyTenant('legacy', 'legacy.test');

        $this->withToken($this->token)->postJson('/api/superadmin/tenants/legacy/toggle-active')
            ->assertOk()
            ->assertJsonPath('tenant.is_active', false);

        $this->withoutToken()->getJson('http://legacy.test/api/admin/auth/me')
            ->assertForbidden()
            ->assertJsonPath('error', 'TENANT_INACTIVE')
            ->assertJsonPath('message', 'Établissement désactivé. Contactez l\'administrateur de la plateforme.');
        $this->postJson('http://legacy.test/admin/auth/login', ['username' => 'x', 'password' => 'y'])
            ->assertForbidden();
        // Each HTTP request boots tenancy afresh in production; reset it between test requests.
        tenancy()->end();

        $this->withToken($this->token)->postJson('/api/superadmin/tenants/legacy/toggle-active')
            ->assertOk()
            ->assertJsonPath('tenant.is_active', true);

        tenancy()->end();
        $this->withoutToken()->getJson('http://legacy.test/api/admin/auth/me')->assertUnauthorized();
    }

    #[Test]
    public function activating_a_site_with_an_existing_database_only_marks_it_active(): void
    {
        $tenant = Tenant::query()->create(['id' => 'deja-provisionnee', 'company_name' => 'Déjà', 'is_active' => false]);
        $tenant->setInternal('db_name', config('database.connections.mysql.database'));
        $tenant->save();

        $this->withToken($this->token)->getJson('/api/superadmin/tenants/deja-provisionnee')
            ->assertOk()
            ->assertJsonPath('tenant.is_uptodate', true);

        $this->withToken($this->token)->postJson('/api/superadmin/sites/deja-provisionnee/activate')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('output', null);

        $this->assertTrue(Tenant::query()->findOrFail('deja-provisionnee')->isActive());
    }

    #[Test]
    public function activating_a_new_site_creates_and_migrates_its_database(): void
    {
        Tenant::query()->create(['id' => self::PROVISIONED_TENANT, 'company_name' => 'Nouvelle école', 'is_active' => false]);
        $databaseName = 'tenant_'.self::PROVISIONED_TENANT;

        $this->withToken($this->token)->getJson('/api/superadmin/tenants/'.self::PROVISIONED_TENANT)
            ->assertJsonPath('tenant.is_uptodate', false);

        $this->withToken($this->token)->postJson('/api/superadmin/sites/'.self::PROVISIONED_TENANT.'/activate')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_uptodate', true);

        $tables = collect(DB::connection('mysql')->select(
            'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = ?',
            [$databaseName]
        ))->pluck('name')->all();

        $this->assertContains('users', $tables);
        $this->assertContains('students', $tables);
        $this->assertContains('roles', $tables);
        $this->assertFalse(tenancy()->initialized);
    }

    #[Test]
    public function activating_an_unknown_site_returns_404(): void
    {
        $this->withToken($this->token)->postJson('/api/superadmin/sites/inconnu/activate')->assertNotFound();
    }

    #[Test]
    public function site_activation_requires_authentication(): void
    {
        $this->postJson('/api/superadmin/sites/inconnu/activate')->assertUnauthorized();
    }
}
