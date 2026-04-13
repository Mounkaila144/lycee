<?php

namespace Modules\UsersGuard\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\UsersGuard\Entities\Tenant;
use Modules\UsersGuard\Entities\TenantUser;

class TenantUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Please run TenantSeeder first.');

            return;
        }

        foreach ($tenants as $tenant) {
            // Initialize tenant context
            tenancy()->initialize($tenant);

            $this->command->info("Seeding users for tenant: {$tenant->company_name}");

            // Admin users for this tenant
            $adminUsers = [
                [
                    'username' => 'admin',
                    'email' => "admin@{$tenant->site_id}.com",
                    'password' => bcrypt('password'),
                    'firstname' => 'Admin',
                    'lastname' => $tenant->company_name,
                    'application' => 'admin',
                    'is_active' => true,
                    'sex' => 'M',
                    'phone' => '+1111111111',
                ],
                [
                    'username' => 'manager',
                    'email' => "manager@{$tenant->site_id}.com",
                    'password' => bcrypt('password'),
                    'firstname' => 'Manager',
                    'lastname' => $tenant->company_name,
                    'application' => 'admin',
                    'is_active' => true,
                    'sex' => 'F',
                ],
            ];

            foreach ($adminUsers as $userData) {
                TenantUser::updateOrCreate(
                    ['username' => $userData['username']],
                    $userData
                );
                $this->command->info("  - Created admin user: {$userData['username']}");
            }

            // Frontend users for this tenant
            $frontendUsers = [
                [
                    'username' => 'user1',
                    'email' => "user1@{$tenant->site_id}.com",
                    'password' => bcrypt('password'),
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'application' => 'frontend',
                    'is_active' => true,
                    'sex' => 'M',
                ],
                [
                    'username' => 'user2',
                    'email' => "user2@{$tenant->site_id}.com",
                    'password' => bcrypt('password'),
                    'firstname' => 'Jane',
                    'lastname' => 'Smith',
                    'application' => 'frontend',
                    'is_active' => true,
                    'sex' => 'F',
                ],
            ];

            foreach ($frontendUsers as $userData) {
                TenantUser::updateOrCreate(
                    ['username' => $userData['username']],
                    $userData
                );
                $this->command->info("  - Created frontend user: {$userData['username']}");
            }

            // End tenant context
            tenancy()->end();
        }

        $this->command->info('All tenant users created successfully!');
    }
}
