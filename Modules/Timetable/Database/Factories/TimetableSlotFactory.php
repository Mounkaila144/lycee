<?php

namespace Modules\Timetable\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Group;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;
use Modules\Timetable\Entities\Room;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\UsersGuard\Entities\User;

class TimetableSlotFactory extends Factory
{
    protected $model = TimetableSlot::class;

    public function definition(): array
    {
        $days = TimetableSlot::VALID_DAYS;
        $types = TimetableSlot::VALID_TYPES;
        $standardSlots = TimetableSlot::STANDARD_SLOTS;
        $timeSlot = $this->faker->randomElement($standardSlots);

        return [
            'module_id' => Module::factory(),
            'teacher_id' => User::factory(),
            'group_id' => Group::factory(),
            'room_id' => Room::factory(),
            'semester_id' => Semester::factory(),
            'day_of_week' => $this->faker->randomElement($days),
            'start_time' => $timeSlot[0].':00',
            'end_time' => $timeSlot[1].':00',
            'type' => $this->faker->randomElement($types),
            'is_recurring' => true,
            'specific_date' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    /**
     * État: Cours magistral
     */
    public function cm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'CM',
        ]);
    }

    /**
     * État: Travaux dirigés
     */
    public function td(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'TD',
        ]);
    }

    /**
     * État: Travaux pratiques
     */
    public function tp(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'TP',
        ]);
    }

    /**
     * État: Séance non récurrente
     */
    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => false,
            'specific_date' => $this->faker->dateTimeBetween('now', '+3 months'),
        ]);
    }

    /**
     * État: Créneau du matin
     */
    public function morning(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);
    }

    /**
     * État: Créneau de l'après-midi
     */
    public function afternoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
        ]);
    }

    /**
     * État: Jour spécifique
     */
    public function onDay(string $day): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => $day,
        ]);
    }
}
