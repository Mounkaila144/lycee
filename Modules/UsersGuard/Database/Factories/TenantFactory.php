<?php

namespace Modules\UsersGuard\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\UsersGuard\Entities\Tenant;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * company_name / is_active are virtual columns stored in the "data" JSON.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->slug(2),
            'company_name' => fake()->company(),
            'is_active' => true,
        ];
    }
}
