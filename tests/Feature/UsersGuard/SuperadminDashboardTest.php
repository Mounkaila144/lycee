<?php

namespace Tests\Feature\UsersGuard;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\UsersGuard\Entities\SuperAdmin;
use Modules\UsersGuard\Entities\Tenant;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tableau de bord super-admin : la base de test sert à la fois de base centrale
 * et de base d'une école (tenancy_db_name pointé dessus) ; une seconde école
 * pointe vers une base inexistante pour vérifier la gestion d'erreur.
 */
class SuperadminDashboardTest extends TestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        DB::purge('mysql');
        Artisan::call('db:wipe', ['--database' => 'mysql', '--force' => true]);

        foreach ([
            'Modules/UsersGuard/Database/Migrations/2024_01_01_000001_create_central_users_table.php',
            'Modules/UsersGuard/Database/Migrations/2025_12_16_091944_create_central_personal_access_tokens_table.php',
            'vendor/stancl/tenancy/assets/migrations',
            'Modules/UsersGuard/Database/Migrations/tenant/2024_01_01_100002_create_permission_tables.php',
        ] as $path) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        Schema::connection('mysql')->create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

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
        DB::purge('mysql');
        Artisan::call('db:wipe', ['--database' => 'mysql', '--force' => true]);
        parent::tearDown();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/superadmin/dashboard')->assertUnauthorized();
    }

    #[Test]
    public function it_returns_global_and_per_school_statistics(): void
    {
        $school = Tenant::query()->create(['id' => 'ecole-ok']);
        $school->setInternal('db_name', config('database.connections.mysql.database'));
        $school->save();
        $school->domains()->create(['domain' => 'ecole-ok.test']);
        $school->domains()->create(['domain' => 'www.ecole-ok.test']);

        $missing = Tenant::query()->create(['id' => 'ecole-sans-base']);
        $missing->domains()->create(['domain' => 'sans-base.test']);

        $teacher = SuperAdmin::query()->create([
            'username' => 'prof.test',
            'email' => 'prof.test@example.test',
            'password' => Hash::make('secret'),
            'application' => 'superadmin',
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Professeur',
            'guard_name' => 'tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => (new TenantUser)->getMorphClass(),
            'model_id' => $teacher->id,
        ]);
        DB::table('students')->insert(array_fill(0, 3, ['status' => 'Actif', 'created_at' => now()]));

        $response = $this->withToken($this->token)->getJson('/api/superadmin/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenants.total', 2)
            ->assertJsonPath('data.tenants.active', 2)
            ->assertJsonPath('data.tenants.inactive', 0)
            ->assertJsonPath('data.domains.total', 3)
            ->assertJsonPath('data.totals.students', 3)
            ->assertJsonPath('data.totals.teachers', 1)
            ->assertJsonPath('data.totals.users', 2)
            ->assertJsonPath('data.totals.unavailable_schools', 1)
            ->assertJsonCount(2, 'data.recent_tenants');

        $schools = collect($response->json('data.schools'))->keyBy('id');

        $this->assertTrue($schools['ecole-ok']['available']);
        $this->assertSame(3, $schools['ecole-ok']['students']);
        $this->assertSame(1, $schools['ecole-ok']['teachers']);
        $this->assertSame(2, $schools['ecole-ok']['users']);
        $this->assertEqualsCanonicalizing(['ecole-ok.test', 'www.ecole-ok.test'], $schools['ecole-ok']['domains']);
        $this->assertNull($schools['ecole-ok']['error']);

        $this->assertFalse($schools['ecole-sans-base']['available']);
        $this->assertNull($schools['ecole-sans-base']['students']);
        $this->assertSame('Base de données introuvable.', $schools['ecole-sans-base']['error']);
        $this->assertSame('tenant_ecole-sans-base', $schools['ecole-sans-base']['database']);

        $this->assertFalse(tenancy()->initialized);
    }

    #[Test]
    public function it_returns_empty_statistics_when_no_school_exists(): void
    {
        $this->withToken($this->token)->getJson('/api/superadmin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.tenants.total', 0)
            ->assertJsonPath('data.domains.total', 0)
            ->assertJsonPath('data.totals.students', 0)
            ->assertJsonPath('data.schools', [])
            ->assertJsonPath('data.recent_tenants', []);
    }
}
