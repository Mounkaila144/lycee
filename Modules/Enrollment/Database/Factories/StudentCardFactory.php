<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentCard;
use Modules\StructureAcademique\Entities\AcademicYear;

class StudentCardFactory extends Factory
{
    protected $model = StudentCard::class;

    public function definition(): array
    {
        $year = date('Y');

        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'card_number' => sprintf('CARD-%s-%06d', $year, $this->faker->unique()->numberBetween(1, 999999)),
            'qr_code_data' => json_encode([
                'matricule' => $this->faker->unique()->numerify('MAT-####'),
                'student_id' => $this->faker->randomNumber(5),
            ]),
            'qr_signature' => hash('sha256', $this->faker->uuid()),
            'pdf_path' => null,
            'status' => StudentCard::STATUS_ACTIVE,
            'issued_at' => now(),
            'valid_until' => now()->addYear(),
            'is_duplicate' => false,
            'original_card_id' => null,
            'print_status' => StudentCard::PRINT_STATUS_PENDING,
            'printed_at' => null,
            'delivered_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentCard::STATUS_ACTIVE,
            'valid_until' => now()->addYear(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentCard::STATUS_EXPIRED,
            'valid_until' => now()->subMonth(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentCard::STATUS_SUSPENDED,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentCard::STATUS_REVOKED,
        ]);
    }

    public function duplicate(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_duplicate' => true,
        ]);
    }

    public function printed(): static
    {
        return $this->state(fn (array $attributes) => [
            'print_status' => StudentCard::PRINT_STATUS_PRINTED,
            'printed_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'print_status' => StudentCard::PRINT_STATUS_DELIVERED,
            'printed_at' => now()->subDay(),
            'delivered_at' => now(),
        ]);
    }
}
