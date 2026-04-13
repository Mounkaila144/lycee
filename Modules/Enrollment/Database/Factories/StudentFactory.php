<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'matricule' => $this->faker->unique()->numerify('####-TEST-###'),
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
            'birthdate' => $this->faker->dateTimeBetween('-25 years', '-18 years'),
            'birthplace' => $this->faker->city(),
            'sex' => $this->faker->randomElement(['M', 'F', 'O']),
            'nationality' => 'Niger',
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '+227'.$this->faker->numerify('########'),
            'mobile' => '+227'.$this->faker->numerify('########'),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'country' => 'Niger',
            'photo' => null,
            'status' => 'Actif',
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => '+227'.$this->faker->numerify('########'),
        ];
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Actif',
        ]);
    }

    public function suspended(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Suspendu',
        ]);
    }

    public function excluded(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Exclu',
        ]);
    }

    public function graduated(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Diplômé',
        ]);
    }

    public function male(): self
    {
        return $this->state(fn (array $attributes) => [
            'sex' => 'M',
        ]);
    }

    public function female(): self
    {
        return $this->state(fn (array $attributes) => [
            'sex' => 'F',
        ]);
    }

    public function withPhoto(): self
    {
        return $this->state(fn (array $attributes) => [
            'photo' => 'students/photos/'.fake()->uuid().'.jpg',
        ]);
    }
}
