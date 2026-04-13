<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentAuditLog;
use Modules\UsersGuard\Entities\User;

class StudentAuditLogFactory extends Factory
{
    protected $model = StudentAuditLog::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'user_id' => User::factory(),
            'event' => $this->faker->randomElement(['created', 'updated', 'deleted']),
            'field_name' => $this->faker->randomElement(['email', 'address', 'city', 'phone', 'status']),
            'old_value' => $this->faker->word(),
            'new_value' => $this->faker->word(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    public function created(): self
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'created',
            'field_name' => null,
            'old_value' => null,
            'new_value' => null,
        ]);
    }

    public function updated(): self
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'updated',
        ]);
    }

    public function deleted(): self
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'deleted',
            'field_name' => null,
            'old_value' => null,
            'new_value' => null,
        ]);
    }

    public function forField(string $fieldName, ?string $oldValue = null, ?string $newValue = null): self
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'updated',
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }
}
