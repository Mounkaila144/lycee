<?php

namespace Modules\Messaging\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Messaging\Entities\Announcement;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'audience' => Announcement::AUDIENCE_ALL,
            'classe_id' => null,
            'published_at' => now()->subHour(),
            'expires_at' => null,
            'author_id' => null,
            'is_pinned' => false,
        ];
    }

    public function forAudience(string $audience, ?int $classeId = null): static
    {
        return $this->state(fn (): array => ['audience' => $audience, 'classe_id' => $classeId]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->addDay()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
