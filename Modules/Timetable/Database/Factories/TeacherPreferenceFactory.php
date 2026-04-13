<?php

namespace Modules\Timetable\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Timetable\Entities\TeacherPreference;
use Modules\UsersGuard\Entities\User;

class TeacherPreferenceFactory extends Factory
{
    protected $model = TeacherPreference::class;

    public function definition(): array
    {
        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $startHour = $this->faker->numberBetween(8, 16);

        return [
            'teacher_id' => User::factory(),
            'day_of_week' => $this->faker->randomElement($days),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $startHour + 2),
            'is_preferred' => $this->faker->boolean(70), // 70% préférences positives
            'priority' => $this->faker->numberBetween(1, 10),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function preferred(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_preferred' => true,
            'priority' => $this->faker->numberBetween(7, 10),
        ]);
    }

    public function toAvoid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_preferred' => false,
            'priority' => $this->faker->numberBetween(1, 5),
        ]);
    }
}
