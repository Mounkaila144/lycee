<?php

namespace Modules\Documents\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documents\Entities\DiplomaRegister;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;

class DiplomaRegisterFactory extends Factory
{
    protected $model = DiplomaRegister::class;

    public function definition(): array
    {
        $finalGpa = $this->faker->randomFloat(2, 10, 18);

        return [
            'student_id' => Student::factory(),
            'programme_id' => Programme::factory(),
            'diploma_number' => 'DIP-'.strtoupper($this->faker->bothify('###-####-#####')),
            'register_number' => 'REG-'.strtoupper($this->faker->bothify('####-#####')),
            'issue_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'graduation_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'academic_year_id' => AcademicYear::factory(),
            'honors' => $this->calculateHonors($finalGpa),
            'final_gpa' => $finalGpa,
            'diploma_type' => $this->faker->randomElement(['Licence', 'Master', 'Doctorat']),
            'specialization' => $this->faker->words(2, true),
            'document_id' => null,
            'supplement_generated' => false,
            'supplement_document_id' => null,
            'is_duplicate' => false,
            'original_diploma_id' => null,
            'duplicate_reason' => null,
            'delivered_by' => null,
            'delivered_at' => null,
            'recipient_name' => null,
            'recipient_id_type' => null,
            'recipient_id_number' => null,
            'delivery_notes' => null,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivered_at' => now(),
            'recipient_name' => $this->faker->name(),
            'recipient_id_type' => 'cni',
            'recipient_id_number' => $this->faker->numerify('########'),
        ]);
    }

    public function duplicate(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_duplicate' => true,
            'duplicate_reason' => 'lost',
        ]);
    }

    private function calculateHonors(float $gpa): string
    {
        return match (true) {
            $gpa >= 16 => 'excellent',
            $gpa >= 14 => 'tres_bien',
            $gpa >= 12 => 'bien',
            $gpa >= 10 => 'assez_bien',
            default => 'passable',
        };
    }
}
