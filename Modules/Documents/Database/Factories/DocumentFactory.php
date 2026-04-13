<?php

namespace Modules\Documents\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\DocumentTemplate;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([
            'transcript_semester',
            'transcript_global',
            'diploma',
            'certificate_enrollment',
            'certificate_status',
            'student_card',
        ]);

        return [
            'student_id' => Student::factory(),
            'document_type' => $type,
            'template_id' => DocumentTemplate::factory()->ofType($type),
            'document_number' => strtoupper($this->faker->bothify('DOC-####-????')),
            'issue_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'academic_year_id' => AcademicYear::factory(),
            'semester_id' => null,
            'programme_id' => null,
            'pdf_path' => 'documents/'.$this->faker->uuid.'.pdf',
            'verification_code' => strtoupper($this->faker->bothify('??????????##########')),
            'qr_code_path' => 'qr_codes/'.$this->faker->uuid.'.png',
            'status' => 'issued',
            'metadata' => [],
            'issued_by' => null,
            'replaced_by_document_id' => null,
            'cancellation_reason' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'issued',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancellation_reason' => $this->faker->sentence(),
        ]);
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => $type,
        ]);
    }
}
