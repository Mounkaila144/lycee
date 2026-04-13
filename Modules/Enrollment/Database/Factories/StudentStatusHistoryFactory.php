<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentStatusHistory;

class StudentStatusHistoryFactory extends Factory
{
    protected $model = StudentStatusHistory::class;

    public function definition(): array
    {
        $statuses = ['Actif', 'Suspendu', 'Exclu', 'Diplômé', 'Abandon', 'Transféré'];
        $oldStatus = $this->faker->randomElement(['Actif', 'Suspendu']);
        $newStatus = $this->faker->randomElement(array_diff($statuses, [$oldStatus]));

        return [
            'student_id' => Student::factory(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $this->faker->sentence(10),
            'effective_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'changed_by' => null,
            'document_path' => null,
        ];
    }

    /**
     * Transition from Actif to Suspendu.
     */
    public function suspension(): self
    {
        return $this->state(fn (array $attributes) => [
            'old_status' => 'Actif',
            'new_status' => 'Suspendu',
            'reason' => 'Suspension temporaire - '.$this->faker->randomElement([
                'Raisons financières',
                'Raisons médicales',
                'Raisons disciplinaires',
            ]),
        ]);
    }

    /**
     * Transition from Suspendu to Actif (reactivation).
     */
    public function reactivation(): self
    {
        return $this->state(fn (array $attributes) => [
            'old_status' => 'Suspendu',
            'new_status' => 'Actif',
            'reason' => 'Réactivation du dossier - '.$this->faker->sentence(5),
        ]);
    }

    /**
     * Transition to Exclu.
     */
    public function exclusion(): self
    {
        return $this->state(fn (array $attributes) => [
            'old_status' => $this->faker->randomElement(['Actif', 'Suspendu']),
            'new_status' => 'Exclu',
            'reason' => 'Exclusion définitive - '.$this->faker->randomElement([
                'Échec académique répété',
                'Faute disciplinaire grave',
                'Non-respect du règlement',
            ]),
        ]);
    }

    /**
     * Transition to Diplômé.
     */
    public function graduation(): self
    {
        return $this->state(fn (array $attributes) => [
            'old_status' => 'Actif',
            'new_status' => 'Diplômé',
            'reason' => 'Obtention du diplôme - Formation terminée avec succès',
        ]);
    }

    /**
     * Transition to Abandon.
     */
    public function abandonment(): self
    {
        return $this->state(fn (array $attributes) => [
            'old_status' => $this->faker->randomElement(['Actif', 'Suspendu']),
            'new_status' => 'Abandon',
            'reason' => 'Abandon volontaire - '.$this->faker->sentence(5),
        ]);
    }

    /**
     * Transition to Transféré.
     */
    public function transfer(): self
    {
        return $this->state(fn (array $attributes) => [
            'old_status' => 'Actif',
            'new_status' => 'Transféré',
            'reason' => 'Transfert vers autre établissement - '.$this->faker->company(),
        ]);
    }

    /**
     * With a document attached.
     */
    public function withDocument(): self
    {
        return $this->state(fn (array $attributes) => [
            'document_path' => 'students/'.$this->faker->uuid().'/status-docs/'.$this->faker->uuid().'.pdf',
        ]);
    }
}
