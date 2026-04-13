<?php

namespace Modules\Timetable\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Timetable\Entities\Room;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        $types = Room::VALID_TYPES;
        $buildings = ['Bâtiment A', 'Bâtiment B', 'Bâtiment C', 'Annexe'];
        $floors = ['RDC', '1er', '2ème', '3ème'];

        return [
            'code' => strtoupper($this->faker->unique()->bothify('S-###')),
            'name' => 'Salle '.$this->faker->numberBetween(100, 999),
            'type' => $this->faker->randomElement($types),
            'building' => $this->faker->randomElement($buildings),
            'floor' => $this->faker->randomElement($floors),
            'capacity' => $this->faker->numberBetween(20, 200),
            'equipment' => $this->faker->randomElements(['Vidéoprojecteur', 'Tableau blanc', 'Wifi', 'Climatisation', 'Ordinateurs'], 3),
            'is_active' => true,
            'description' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * État: Amphithéâtre
     */
    public function amphi(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Amphi',
            'capacity' => $this->faker->numberBetween(100, 500),
            'name' => 'Amphi '.$this->faker->randomLetter(),
        ]);
    }

    /**
     * État: Laboratoire
     */
    public function labo(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Labo',
            'capacity' => $this->faker->numberBetween(15, 30),
            'name' => 'Labo '.$this->faker->numberBetween(1, 10),
            'equipment' => ['Équipement de laboratoire', 'Postes de travail', 'Matériel scientifique'],
        ]);
    }

    /**
     * État: Salle informatique
     */
    public function salleInfo(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Salle_Info',
            'capacity' => $this->faker->numberBetween(20, 40),
            'name' => 'Info '.$this->faker->numberBetween(1, 10),
            'equipment' => ['Ordinateurs', 'Vidéoprojecteur', 'Wifi', 'Imprimante'],
        ]);
    }

    /**
     * État: Salle inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
