<?php

namespace Modules\UsersGuard\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\UsersGuard\Entities\Tenant;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $companyName = fake()->company();
        $domain = fake()->unique()->domainName();
        $database = 'tenant_'.fake()->unique()->slug();

        return [
            'data' => [
                'company_name' => $companyName,
                'domain' => $domain,
                'database' => $database,
                'is_active' => true,
            ],
        ];
    }
}
