<?php

namespace Tests\Traits;

use Modules\UsersGuard\Entities\User;
use Tests\Concerns\InteractsWithTenancy;

trait TenantTestTrait
{
    use InteractsWithTenancy;

    protected ?User $tenantUser = null;

    /**
     * Initialize tenant for testing (alias for setUpTenant)
     */
    protected function initializeTenant(): void
    {
        $this->setUpTenant();

        // Clean tenant data before each test
        if (self::$testTenant) {
            $this->cleanTenantData();
        }

        $this->createTenantUser();
    }

    /**
     * Create a test user in the tenant context
     */
    protected function createTenantUser(): void
    {
        if ($this->tenantUser === null) {
            $this->tenantUser = User::firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'username' => 'testuser',
                    'password' => bcrypt('password'),
                ]
            );
        }
    }
}
