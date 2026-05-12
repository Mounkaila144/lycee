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
            'matricule' => null,
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
            'birthdate' => $this->faker->dateTimeBetween('-18 years', '-10 years'),
            'birthplace' => $this->faker->city(),
            'sex' => $this->faker->randomElement(['M', 'F']),
            'nationality' => 'Nigérienne',
            'phone' => '+227'.$this->faker->numerify('########'),
            'address' => $this->faker->address(),
            'city' => 'Niamey',
            'quarter' => null,
            'blood_group' => null,
            'health_notes' => null,
            'photo' => null,
            'status' => 'Actif',
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => '+227'.$this->faker->numerify('########'),
        ];
    }

    public function withMatricule(?string $matricule = null): self
    {
        return $this->state(fn (array $attributes) => [
            'matricule' => $matricule ?? $this->faker->unique()->numerify('STUD-####-###'),
        ]);
    }

    public function active(): self
    {
        return $this->state(fn () => ['status' => 'Actif']);
    }

    public function excluded(): self
    {
        return $this->state(fn () => ['status' => 'Exclu']);
    }

    public function graduated(): self
    {
        return $this->state(fn () => ['status' => 'Diplômé']);
    }

    public function male(): self
    {
        return $this->state(fn () => ['sex' => 'M']);
    }

    public function female(): self
    {
        return $this->state(fn () => ['sex' => 'F']);
    }

    public function withPhoto(): self
    {
        return $this->state(fn () => [
            'photo' => 'students/photos/'.fake()->uuid().'.jpg',
        ]);
    }
}
