<?php

namespace Modules\UsersGuard\Database\Seeders;

use Illuminate\Database\Seeder;

class UsersGuardDatabaseSeeder extends Seeder
{
    /**
     * Run the module database seeds.
     */
    public function run(): void
    {
        $this->command->info('========================================');
        $this->command->info('Seeding UsersGuard Module');
        $this->command->info('========================================');

        // Seed Super Admins (Central Database)
        $this->call(SuperAdminSeeder::class);

        // Seed Tenants (Central Database)
        $this->call(TenantSeeder::class);

        // Seed Tenant Users (Tenant Databases)
        $this->call(TenantUserSeeder::class);

        // Seed Roles and Permissions (Tenant Databases)
        $this->call(RolesAndPermissionsSeeder::class);

        $this->command->info('========================================');
        $this->command->info('UsersGuard Module Seeded Successfully!');
        $this->command->info('========================================');
        $this->command->newLine();
        $this->command->info('Login Credentials:');
        $this->command->info('----------------------------------------');
        $this->command->info('Super Admin:');
        $this->command->info('  Username: superadmin');
        $this->command->info('  Password: password');
        $this->command->newLine();
        $this->command->info('Tenant Admin (company1):');
        $this->command->info('  Username: admin');
        $this->command->info('  Password: password');
        $this->command->newLine();
        $this->command->info('Tenant Manager (company1):');
        $this->command->info('  Username: manager');
        $this->command->info('  Password: password');
        $this->command->newLine();
        $this->command->info('Tenant User (company1):');
        $this->command->info('  Username: user1 / user2');
        $this->command->info('  Password: password');
        $this->command->info('========================================');
    }
}
