<?php

namespace Modules\UsersGuard\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\UsersGuard\Entities\SuperAdmin;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmins = [
            [
                'username' => 'superadmin',
                'email' => 'superadmin@crm.com',
                'password' => bcrypt('password'),
                'firstname' => 'Super',
                'lastname' => 'Admin',
                'application' => 'superadmin',
                'is_active' => true,
                'sex' => 'M',
                'phone' => '+1234567890',
                'mobile' => '+1234567891',
            ],
            [
                'username' => 'admin',
                'email' => 'admin@crm.com',
                'password' => bcrypt('password'),
                'firstname' => 'Admin',
                'lastname' => 'User',
                'application' => 'superadmin',
                'is_active' => true,
                'sex' => 'F',
            ],
        ];

        foreach ($superAdmins as $admin) {
            SuperAdmin::updateOrCreate(
                ['username' => $admin['username']],
                $admin
            );
        }

        $this->command->info('Super admins created successfully!');
    }
}
