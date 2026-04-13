<?php

namespace Modules\Documents\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documents\Entities\DocumentRequest;
use Modules\Enrollment\Entities\Student;

class DocumentRequestFactory extends Factory
{
    protected $model = DocumentRequest::class;

    public function definition(): array
    {
        $requestDate = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'student_id' => Student::factory(),
            'document_type' => $this->faker->randomElement([
                'certificate_enrollment',
                'certificate_status',
                'certificate_attendance',
                'certificate_schooling',
            ]),
            'quantity' => $this->faker->numberBetween(1, 3),
            'reason' => $this->faker->sentence(),
            'urgency' => $this->faker->randomElement(['normal', 'urgent']),
            'request_date' => $requestDate,
            'expected_delivery_date' => $this->faker->dateTimeBetween($requestDate, '+7 days'),
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'processing_notes' => null,
            'rejection_reason' => null,
            'generated_document_id' => null,
            'fee_amount' => $this->faker->randomFloat(2, 3000, 10000),
            'fee_paid' => false,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'processed_at' => now(),
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'urgency' => 'urgent',
        ]);
    }
}
