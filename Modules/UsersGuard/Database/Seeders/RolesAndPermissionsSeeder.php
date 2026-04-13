<?php

namespace Modules\UsersGuard\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\UsersGuard\Entities\Tenant;
use Modules\UsersGuard\Entities\TenantUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
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

            $this->command->info("Seeding roles and permissions for tenant: {$tenant->company_name}");

            // Reset cached roles and permissions
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            // Create Permissions
            $permissions = [
                // User Management
                'view users',
                'create users',
                'edit users',
                'delete users',

                // Role Management
                'view roles',
                'create roles',
                'edit roles',
                'delete roles',

                // Settings
                'view settings',
                'edit settings',

                // Reports
                'view reports',
                'export reports',

                // Dashboard
                'view dashboard',

                // Academic - Teacher Permissions
                'view students',
                'manage grades',
                'view timetable',

                // Student Permissions
                'view own grades',
                'view own timetable',
                'upload documents',
                'request attestations',
                'view own attendance',
            ];

            foreach ($permissions as $permission) {
                Permission::updateOrCreate(
                    ['name' => $permission, 'guard_name' => 'tenant'],
                    ['display_name' => ucfirst($permission)]
                );
            }

            $this->command->info('  - Permissions created');

            // Create Roles
            $adminRole = Role::updateOrCreate(
                ['name' => 'Administrator', 'guard_name' => 'tenant'],
                ['display_name' => 'Administrator', 'description' => 'Full access to all features']
            );
            $adminRole->syncPermissions(Permission::all());

            $managerRole = Role::updateOrCreate(
                ['name' => 'Manager', 'guard_name' => 'tenant'],
                ['display_name' => 'Manager', 'description' => 'Manage users and view reports']
            );
            $managerRole->syncPermissions([
                'view users',
                'create users',
                'edit users',
                'view reports',
                'view dashboard',
            ]);

            $userRole = Role::updateOrCreate(
                ['name' => 'User', 'guard_name' => 'tenant'],
                ['display_name' => 'User', 'description' => 'Basic user access']
            );
            $userRole->syncPermissions([
                'view dashboard',
            ]);

            $teacherRole = Role::updateOrCreate(
                ['name' => 'Professeur', 'guard_name' => 'tenant'],
                ['display_name' => 'Professeur', 'description' => 'Enseignant - Gestion des notes et cours']
            );
            $teacherRole->syncPermissions([
                'view dashboard',
                'view students',
                'manage grades',
                'view timetable',
            ]);

            $studentRole = Role::updateOrCreate(
                ['name' => 'Étudiant', 'guard_name' => 'tenant'],
                ['display_name' => 'Étudiant', 'description' => 'Étudiant - Accès portail étudiant']
            );
            $studentRole->syncPermissions([
                'view dashboard',
                'view own grades',
                'view own timetable',
                'upload documents',
                'request attestations',
                'view own attendance',
            ]);

            // Create Financial Roles (Story 2)
            $financialPermissions = [
                // Invoicing
                'view invoices',
                'create invoices',
                'edit invoices',
                'delete invoices',

                // Payments
                'view payments',
                'create payments',
                'edit payments',
                'delete payments',
                'generate receipts',

                // Financial Management
                'manage payment plans',
                'manage late fees',
                'manage refunds',
                'manage bank reconciliation',
                'manage collection',

                // Reporting
                'view financial reports',
                'export financial data',
            ];

            foreach ($financialPermissions as $permission) {
                Permission::updateOrCreate(
                    ['name' => $permission, 'guard_name' => 'tenant'],
                    ['display_name' => ucfirst(str_replace('_', ' ', $permission))]
                );
            }

            // Create Cashier Role
            $cashierRole = Role::updateOrCreate(
                ['name' => 'Caissier', 'guard_name' => 'tenant'],
                ['display_name' => 'Caissier', 'description' => 'Caissier - Encaissement et gestion des paiements']
            );
            $cashierRole->syncPermissions([
                'view dashboard',
                'view students',
                'view invoices',
                'create payments',
                'generate receipts',
                'view financial reports',
            ]);

            // Create Accounting Clerk Role
            $agentComptableRole = Role::updateOrCreate(
                ['name' => 'Agent Comptable', 'guard_name' => 'tenant'],
                ['display_name' => 'Agent Comptable', 'description' => 'Agent Comptable - Facturation et suivi impayés']
            );
            $agentComptableRole->syncPermissions([
                'view dashboard',
                'view students',
                'view invoices',
                'create invoices',
                'edit invoices',
                'manage payment plans',
                'manage late fees',
                'manage collection',
                'view financial reports',
            ]);

            // Create Accountant Role
            $comptableRole = Role::updateOrCreate(
                ['name' => 'Comptable', 'guard_name' => 'tenant'],
                ['display_name' => 'Comptable', 'description' => 'Comptable - Gestion comptable et rapports financiers']
            );
            $comptableRole->syncPermissions([
                'view dashboard',
                'view students',
                'view invoices',
                'view payments',
                'create payments',
                'generate receipts',
                'manage payment plans',
                'manage refunds',
                'manage bank reconciliation',
                'view financial reports',
                'export financial data',
                'manage collection',
            ]);

            $this->command->info('  - Roles created');

            // Assign roles to users
            $admin = TenantUser::where('username', 'admin')->first();
            if ($admin) {
                $admin->assignRole('Administrator');
                $this->command->info('  - Administrator role assigned to admin user');
            }

            $manager = TenantUser::where('username', 'manager')->first();
            if ($manager) {
                $manager->assignRole('Manager');
                $this->command->info('  - Manager role assigned to manager user');
            }

            $user1 = TenantUser::where('username', 'user1')->first();
            if ($user1) {
                $user1->assignRole('User');
                $this->command->info('  - User role assigned to user1');
            }

            $user2 = TenantUser::where('username', 'user2')->first();
            if ($user2) {
                $user2->assignRole('User');
                $this->command->info('  - User role assigned to user2');
            }

            // End tenant context
            tenancy()->end();
        }

        $this->command->info('All roles and permissions seeded successfully!');
    }
}
