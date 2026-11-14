<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\UsersGuard\Entities\SuperAdmin;

/**
 * Compte super-administrateur de démonstration (base CENTRALE, table users, application = superadmin).
 *
 * Idempotent ; ne modifie aucun autre compte super-admin.
 * Usage : php artisan db:seed --class="Database\Seeders\DemoSuperAdminSeeder" --force
 */
class DemoSuperAdminSeeder extends Seeder
{
    public const USERNAME = 'demo.superadmin';

    public const PASSWORD = 'Jandoo-Demo-2026!';

    public const EMAIL = 'demo.superadmin@jandoo.test';

    public function run(): void
    {
        $superAdmin = SuperAdmin::query()->withTrashed()->firstOrNew(['username' => self::USERNAME]);

        $superAdmin->fill([
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'firstname' => 'Démo',
            'lastname' => 'Super-admin',
            'application' => 'superadmin',
            'is_active' => true,
            'sex' => 'O',
        ]);
        $superAdmin->deleted_at = null;
        $superAdmin->save();

        $this->command?->info('Super-admin de démo prêt : '.self::USERNAME);
    }
}
