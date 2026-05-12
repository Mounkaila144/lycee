<?php

namespace Modules\PortailParent\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\PortailParent\Entities\ParentModel;

class ParentModelFactory extends Factory
{
    protected $model = ParentModel::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
            'relationship' => $this->faker->randomElement(['Père', 'Mère', 'Tuteur', 'Tutrice']),
            'phone' => $this->faker->phoneNumber(),
            'phone_secondary' => null,
            'email' => $this->faker->safeEmail(),
            'profession' => $this->faker->jobTitle(),
            'address' => $this->faker->address(),
        ];
    }
}
