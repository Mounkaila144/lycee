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

        // STEP 6: Enrollment TENANT tables - DISABLED (LMD module, will be rebuilt for secondaire)
        // Artisan::call('migrate', [
        //     '--database' => 'mysql',
        //     '--path' => $basePath.'/Modules/Enrollment/Database/Migrations/tenant',
        //     '--realpath' => true,
        //     '--force' => true,
        // ]);

        // STEP 7: NotesEvaluations TENANT tables - DISABLED (LMD module, will be rebuilt for secondaire)
        // Artisan::call('migrate', [
        //     '--database' => 'mysql',
        //     '--path' => $basePath.'/Modules/NotesEvaluations/Database/Migrations/tenant',
        //     '--realpath' => true,
        //     '--force' => true,
        // ]);

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
}
