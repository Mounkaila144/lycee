<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentDocument;

class StudentDocumentFactory extends Factory
{
    protected $model = StudentDocument::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([
            'certificat_naissance',
            'releve_baccalaureat',
            'photo_identite',
            'cni_passeport',
            'autre',
        ]);

        $filename = $this->faker->uuid().'.'.$this->faker->randomElement(['pdf', 'jpg', 'png']);

        return [
            'student_id' => Student::factory(),
            'type' => $type,
            'filename' => $filename,
            'original_filename' => $this->faker->word().'_document.'.$this->faker->randomElement(['pdf', 'jpg']),
            'file_path' => 'students/documents/'.$filename,
            'mime_type' => $this->faker->randomElement(['application/pdf', 'image/jpeg', 'image/png']),
            'file_size' => $this->faker->numberBetween(10000, 2000000), // 10KB to 2MB
            'description' => $this->faker->optional()->sentence(),
            'is_validated' => false,
            'validated_by' => null,
            'validated_at' => null,
        ];
    }

    public function validated(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_validated' => true,
            'validated_at' => now(),
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_validated' => false,
            'validated_by' => null,
            'validated_at' => null,
        ]);
    }

    public function pdf(): self
    {
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->uuid().'.pdf',
            'original_filename' => $this->faker->word().'_document.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function image(): self
    {
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->uuid().'.jpg',
            'original_filename' => $this->faker->word().'_photo.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }

    public function certificatNaissance(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'certificat_naissance',
        ]);
    }

    public function releveBaccalaureat(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'releve_baccalaureat',
        ]);
    }

    public function photoIdentite(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'photo_identite',
            'mime_type' => 'image/jpeg',
        ]);
    }

    public function cniPasseport(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cni_passeport',
        ]);
    }
}
