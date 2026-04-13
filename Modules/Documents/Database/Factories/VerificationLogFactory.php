<?php

namespace Modules\Documents\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\VerificationLog;

class VerificationLogFactory extends Factory
{
    protected $model = VerificationLog::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'verified_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'verification_method' => $this->faker->randomElement(['qr_code', 'document_number', 'api', 'manual']),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'country' => $this->faker->country(),
            'city' => $this->faker->city(),
            'verified_by' => null,
            'verification_successful' => true,
            'verification_notes' => null,
            'request_data' => [],
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_successful' => true,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_successful' => false,
        ]);
    }
}
