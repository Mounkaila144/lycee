<?php

namespace Modules\Documents\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\DocumentArchive;

class DocumentArchiveFactory extends Factory
{
    protected $model = DocumentArchive::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'archived_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'archive_location' => 'archives/'.$this->faker->uuid.'.pdf',
            'archive_format' => 'pdf',
            'checksum' => $this->faker->sha256(),
            'file_size' => $this->faker->numberBetween(100000, 5000000),
            'storage_tier' => 'hot',
            'last_accessed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'access_count' => $this->faker->numberBetween(0, 100),
            'archived_by' => null,
            'archive_notes' => null,
            'is_encrypted' => false,
            'encryption_method' => null,
            'metadata' => [],
        ];
    }

    public function coldStorage(): static
    {
        return $this->state(fn (array $attributes) => [
            'storage_tier' => 'cold',
        ]);
    }

    public function encrypted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_encrypted' => true,
            'encryption_method' => 'AES-256-CBC',
        ]);
    }
}
