<?php

namespace Modules\Documents\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documents\Entities\StudentCard;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;

class StudentCardFactory extends Factory
{
    protected $model = StudentCard::class;

    public function definition(): array
    {
        $issueDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $expiryDate = (clone $issueDate)->modify('+1 year');

        return [
            'student_id' => Student::factory(),
            'card_number' => 'CARD-'.strtoupper($this->faker->bothify('##-????-#####')),
            'card_type' => 'student_id',
            'issue_date' => $issueDate,
            'expiry_date' => $expiryDate,
            'academic_year_id' => AcademicYear::factory(),
            'photo_path' => 'students/photos/'.$this->faker->uuid.'.jpg',
            'qr_code' => json_encode(['card_number' => 'CARD-'.$this->faker->bothify('##-????-#####')]),
            'qr_code_path' => 'cards/qr/'.$this->faker->uuid.'.png',
            'barcode' => $this->faker->ean13(),
            'barcode_path' => 'cards/barcodes/'.$this->faker->uuid.'.png',
            'status' => 'active',
            'access_permissions' => ['library', 'computer_lab', 'cafeteria', 'main_building'],
            'is_printed' => false,
            'printed_at' => null,
            'printed_by' => null,
            'replaced_by_card_id' => null,
            'replacement_reason' => null,
            'document_id' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expiry_date' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    public function printed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_printed' => true,
            'printed_at' => now(),
        ]);
    }

    public function accessBadge(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => 'access_badge',
            'card_number' => 'BADGE-'.strtoupper($this->faker->bothify('##-????-#####')),
        ]);
    }
}
