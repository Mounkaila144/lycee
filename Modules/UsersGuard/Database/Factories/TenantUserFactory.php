<?php

namespace Modules\UsersGuard\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\UsersGuard\Entities\TenantUser;

class TenantUserFactory extends Factory
{
    protected $model = TenantUser::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$password ??= Hash::make('password'),
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'application' => 'admin',
            'is_active' => true,
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->phoneNumber(),
            'sex' => fake()->randomElement(['M', 'F', 'O']),
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
