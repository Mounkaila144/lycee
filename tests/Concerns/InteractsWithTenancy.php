<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\UsersGuard\Entities\Tenant;

trait InteractsWithTenancy
{
    protected static ?Tenant $testTenant = null;

    protected static bool $migrationsRan = false;

    /**
     * Reset state before each test file (critical for multi-file test runs)
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Reset static state to ensure clean migrations for each test file
        self::$migrationsRan = false;
        self::$testTenant = null;
    }

    /**
     * Setup tenant for testing
     */
    protected function setUpTenancy(): void
    {
        $this->setUpTenant();
    }

    /**
     * Setup tenant for testing (alias)
     */
    protected function setUpTenant(): void
    {
        // Clean test database (once per test suite)
        $this->cleanTestDatabase();

        // Run all migrations once per test suite
        $this->runMigrationsOnce();

        // Create or reuse test tenant
        if (self::$testTenant === null) {
            $this->createTestTenant();
        }

        // ⚠️ IMPORTANT: Do NOT call tenancy()->initialize() in tests
        // Reason: In tests, both 'mysql' and 'tenant' connections already point to 'crm_test'
        // Calling initialize() would try to switch to a database named 'tenant_test-tenant'
        // which doesn't exist. We don't need tenancy switching in tests since it's a single DB.

        // Start transaction for test isolation (rollback in tearDown = instant cleanup)
        DB::connection('tenant')->beginTransaction();
    }

    /**
     * Clean MySQL test database and run migrations (once per test file)
     */
    protected function cleanTestDatabase(): void
    {
        // Purge cached connections to ensure fresh state
        DB::purge('mysql');
        DB::purge('tenant');
    }

    /**
     * Run ALL migrations with migrate:fresh (once per test file)
     */
    protected function runMigrationsOnce(): void
    {
        if (self::$migrationsRan) {
            return;
        }

        // CRITICAL: In tests, both 'mysql' and 'tenant' point to the same DB (crm_test)
        // So we use migrate:fresh ONCE and only run TENANT migrations (skip central)

        $basePath = base_path();

        // STEP 0: Wipe ALL tables first to ensure clean slate
        Artisan::call('db:wipe', [
            '--database' => 'mysql',
            '--force' => true,
        ]);

        // STEP 1: Install migration table
        Artisan::call('migrate:install', [
            '--database' => 'mysql',
        ]);

        // STEP 2: Run tenancy migrations (creates tenants and domains tables)
        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => $basePath.'/vendor/stancl/tenancy/assets/migrations',
            '--realpath' => true,
            '--force' => true,
        ]);

        // STEP 3: Skip central users table (conflicts with tenant users in tests)
        // Central users table is NOT needed for tenant tests

        // STEP 4: Add UsersGuard TENANT tables (users, permissions, tokens, cache)
        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => $basePath.'/Modules/UsersGuard/Database/Migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);

        // STEP 5: Add StructureAcademique TENANT tables
        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => $basePath.'/Modules/StructureAcademique/Database/Migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);

        // STEP 6: Enrollment TENANT tables (Story 7.1 secondaire alignment included)
        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => $basePath.'/Modules/Enrollment/Database/Migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);

        // STEP 7: PortailParent TENANT tables (parents + parent_student)
        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => $basePath.'/Modules/PortailParent/Database/Migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);

        // STEP 8: Finance TENANT tables (notamment cashier_close_records — Story Caissier 05)
        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => $basePath.'/Modules/Finance/Database/Migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);

        // STEP 9: Settings TENANT table (Story Admin 13)
        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => $basePath.'/Modules/Settings/Database/Migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);

        // STEP 10: Messaging TENANT table (Story Parent 07)
        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => $basePath.'/Modules/Messaging/Database/Migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);

        // STEP 11: Re-run Finance migrations to pick up parent_online_payments
        // (idempotent — migrations déjà passées sont skip).
        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => $basePath.'/Modules/Finance/Database/Migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);

        self::$migrationsRan = true;
    }

    /**
     * Create test tenant without triggering events
     */
    protected function createTestTenant(): void
    {
        // Check if tenant exists (reuse between tests)
        $existingTenant = Tenant::find('test-tenant');
        if ($existingTenant) {
            self::$testTenant = $existingTenant;

            return;
        }

        // Create tenant without events (no DB creation attempt)
        self::$testTenant = Tenant::withoutEvents(function () {
            return Tenant::create([
                'id' => 'test-tenant',
            ]);
        });

        // Create domain for tenant
        self::$testTenant->domains()->create([
            'domain' => 'localhost',
        ]);
    }

    /**
     * Clean up tenant after testing
     */
    protected function tearDownTenancy(): void
    {
        // Rollback transaction (instant cleanup, no TRUNCATE needed!)
        try {
            if (DB::connection('tenant')->transactionLevel() > 0) {
                DB::connection('tenant')->rollBack();
            }
        } catch (\Exception $e) {
            // Ignore rollback errors
        }

        // Note: No need to call tenancy()->end() since we don't call initialize() in tests
    }

    /**
     * Reset static state between test files (for ParaTest)
     */
    public static function tearDownAfterClass(): void
    {
        self::$testTenant = null;
        self::$migrationsRan = false;

        parent::tearDownAfterClass();
    }

    /**
     * Seed the role/permission catalog for the current tenant connection.
     * Idempotent: safe to call multiple times within a test.
     */
    protected function seedRolesAndPermissions(): void
    {
        $hierarchy = config('role-routes.hierarchy', []);

        $permissions = [
            'view dashboard', 'view users', 'create users', 'edit users', 'delete users',
            'view roles', 'view students', 'manage grades', 'view timetable',
            'view own grades', 'view own timetable',
            'view invoices', 'create invoices', 'edit invoices',
            'view payments', 'create payments', 'generate receipts',
            'view financial reports', 'export financial data',
            'manage payment plans', 'manage refunds', 'manage bank reconciliation',
            'manage collection',
        ];

        foreach ($permissions as $name) {
            \Spatie\Permission\Models\Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'tenant'],
                ['display_name' => ucfirst($name)]
            );
        }

        foreach ($hierarchy as $roleName) {
            \Spatie\Permission\Models\Role::updateOrCreate(
                ['name' => $roleName, 'guard_name' => 'tenant'],
                ['display_name' => $roleName]
            );
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Assign a Spatie role to the test user (creates it if missing).
     */
    protected function assignRole(\Modules\UsersGuard\Entities\TenantUser $user, string $roleName): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'tenant'],
            ['display_name' => $roleName]
        );

        $user->assignRole($roleName);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
