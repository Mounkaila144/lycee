<?php

namespace Tests\Feature;

use Database\Seeders\DemoSuperAdminSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\UsersGuard\Entities\SuperAdmin;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Compte super-admin de démo, en base centrale : la base de test est réinitialisée avec
 * les seules tables centrales nécessaires (users, personal_access_tokens).
 */
class DemoSuperAdminSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::purge('mysql');
        Artisan::call('db:wipe', ['--database' => 'mysql', '--force' => true]);

        foreach ([
            'Modules/UsersGuard/Database/Migrations/2024_01_01_000001_create_central_users_table.php',
            'Modules/UsersGuard/Database/Migrations/2025_12_16_091944_create_central_personal_access_tokens_table.php',
        ] as $path) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Artisan::call('db:wipe', ['--database' => 'mysql', '--force' => true]);
        parent::tearDown();
    }

    #[Test]
    public function it_creates_the_demo_superadmin_idempotently_without_touching_other_superadmins(): void
    {
        $existing = SuperAdmin::query()->create([
            'username' => 'superadmin',
            'email' => 'superadmin@crm.com',
            'password' => Hash::make('original-secret'),
            'application' => 'superadmin',
            'is_active' => true,
        ]);
        $originalHash = $existing->fresh()->password;

        $this->seed(DemoSuperAdminSeeder::class);
        $this->seed(DemoSuperAdminSeeder::class);

        $this->assertSame(1, SuperAdmin::query()->where('username', DemoSuperAdminSeeder::USERNAME)->count());
        $demo = SuperAdmin::query()->where('username', DemoSuperAdminSeeder::USERNAME)->firstOrFail();
        $this->assertSame(DemoSuperAdminSeeder::EMAIL, $demo->email);
        $this->assertSame('superadmin', $demo->application);
        $this->assertTrue($demo->is_active);
        $this->assertTrue(Hash::check(DemoSuperAdminSeeder::PASSWORD, $demo->password));
        $this->assertSame($originalHash, $existing->fresh()->password);
        $this->assertSame(2, SuperAdmin::query()->count());
    }

    #[Test]
    public function the_demo_superadmin_can_log_in(): void
    {
        $this->seed(DemoSuperAdminSeeder::class);

        $this->postJson('/api/superadmin/auth/login', [
            'username' => DemoSuperAdminSeeder::USERNAME,
            'password' => DemoSuperAdminSeeder::PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.username', DemoSuperAdminSeeder::USERNAME)
            ->assertJsonPath('data.user.application', 'superadmin');

        $this->postJson('/api/superadmin/auth/login', [
            'username' => DemoSuperAdminSeeder::USERNAME,
            'password' => 'password',
        ])->assertUnprocessable();
    }

    #[Test]
    public function it_reactivates_a_soft_deleted_demo_superadmin(): void
    {
        $this->seed(DemoSuperAdminSeeder::class);
        SuperAdmin::query()->where('username', DemoSuperAdminSeeder::USERNAME)->firstOrFail()->delete();

        $this->seed(DemoSuperAdminSeeder::class);

        $this->assertNotNull(SuperAdmin::query()->where('username', DemoSuperAdminSeeder::USERNAME)->first());
        $this->assertSame(1, SuperAdmin::query()->withTrashed()->where('username', DemoSuperAdminSeeder::USERNAME)->count());
    }
}
