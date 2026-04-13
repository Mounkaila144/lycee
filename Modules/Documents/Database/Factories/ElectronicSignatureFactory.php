<?php

namespace Modules\Documents\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\ElectronicSignature;

class ElectronicSignatureFactory extends Factory
{
    protected $model = ElectronicSignature::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'signer_name' => $this->faker->name(),
            'signer_title' => $this->faker->randomElement(['Directeur', 'Secrétaire Général', 'Chef de Service']),
            'signer_role' => $this->faker->randomElement(['director', 'secretary_general', 'department_head']),
            'signature_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'signature_image_path' => 'signatures/'.$this->faker->uuid.'.png',
            'certificate_path' => null,
            'signature_hash' => $this->faker->sha256(),
            'is_valid' => true,
            'expires_at' => $this->faker->dateTimeBetween('+6 months', '+1 year'),
            'signed_by' => null,
            'signature_metadata' => null,
        ];
    }

    public function valid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_valid' => true,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }
}
