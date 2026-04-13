<?php

namespace Modules\UsersGuard\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\UsersGuard\Entities\Tenant;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = [
            [
                'id' => 'lycee1',
                'domains' => [
                    ['domain' => 'jandoosecondaire.ptrniger.com'],
                ],
            ],
        ];

        foreach ($tenants as $tenantData) {
            $domains = $tenantData['domains'];
            unset($tenantData['domains']);

            $tenant = Tenant::updateOrCreate(
                ['id' => $tenantData['id']],
                $tenantData
            );

            foreach ($domains as $domainData) {
                $tenant->domains()->updateOrCreate(
                    ['domain' => $domainData['domain']],
                    $domainData
                );
            }

            $this->command->info("Tenant '{$tenant->id}' created successfully!");

            // Create database for this tenant if it doesn't exist
            try {
                \Artisan::call('tenants:migrate', [
                    '--tenants' => [$tenant->id],
                ]);
                $this->command->info("Database migrated for tenant '{$tenant->id}'");
            } catch (\Exception $e) {
                $this->command->warn("Could not migrate tenant database: {$e->getMessage()}");
            }
        }
    }
}
