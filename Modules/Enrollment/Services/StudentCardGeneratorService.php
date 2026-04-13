<?php

namespace Modules\Enrollment\Services;

use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentCard;
use Modules\Enrollment\Jobs\GenerateCardPdfJob;
use Modules\StructureAcademique\Entities\AcademicYear;

class StudentCardGeneratorService
{
    /**
     * Generate a new student card
     */
    public function generate(Student $student, AcademicYear $year, bool $isDuplicate = false): StudentCard
    {
        $existingCard = StudentCard::where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->where('is_duplicate', false)
            ->first();

        if ($existingCard && ! $isDuplicate) {
            return $existingCard;
        }

        $cardNumber = $this->generateCardNumber($year);
        $qrData = $this->generateQRData($student, $cardNumber, $year);
        $signature = $this->signQRData($qrData);

        $card = StudentCard::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'card_number' => $cardNumber,
            'qr_code_data' => json_encode($qrData),
            'qr_signature' => $signature,
            'status' => StudentCard::STATUS_ACTIVE,
            'issued_at' => now(),
            'valid_until' => $year->end_date ?? now()->addYear(),
            'is_duplicate' => $isDuplicate,
            'original_card_id' => $isDuplicate ? $existingCard?->id : null,
        ]);

        // Dispatch PDF generation job
        dispatch(new GenerateCardPdfJob($card));

        return $card;
    }

    /**
     * Generate a duplicate card
     */
    public function generateDuplicate(Student $student, AcademicYear $year): StudentCard
    {
        return $this->generate($student, $year, true);
    }

    /**
     * Batch generate cards for multiple students
     */
    public function batchGenerate(array $studentIds, AcademicYear $year): array
    {
        $results = [
            'generated' => [],
            'skipped' => [],
            'failed' => [],
        ];

        foreach ($studentIds as $studentId) {
            try {
                $student = Student::find($studentId);

                if (! $student) {
                    $results['failed'][] = [
                        'student_id' => $studentId,
                        'error' => 'Student not found',
                    ];

                    continue;
                }

                $existingCard = StudentCard::where('student_id', $studentId)
                    ->where('academic_year_id', $year->id)
                    ->where('is_duplicate', false)
                    ->first();

                if ($existingCard) {
                    $results['skipped'][] = [
                        'student_id' => $studentId,
                        'card_id' => $existingCard->id,
                        'reason' => 'Card already exists',
                    ];

                    continue;
                }

                $card = $this->generate($student, $year);
                $results['generated'][] = [
                    'student_id' => $studentId,
                    'card_id' => $card->id,
                    'card_number' => $card->card_number,
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'student_id' => $studentId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Verify a card using QR data
     */
    public function verifyCard(string $qrDataJson, string $signature): array
    {
        $qrData = json_decode($qrDataJson, true);

        if (! $qrData) {
            throw new \Exception('Invalid QR data format');
        }

        $expectedSignature = $this->signQRData($qrData);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new \Exception('Invalid card signature - possible counterfeit');
        }

        $card = StudentCard::where('card_number', $qrData['card_number'] ?? '')->first();

        if (! $card) {
            throw new \Exception('Card not found');
        }

        if ($card->status !== StudentCard::STATUS_ACTIVE) {
            throw new \Exception("Card is {$card->status}");
        }

        if ($card->isExpired()) {
            throw new \Exception('Card has expired');
        }

        return [
            'valid' => true,
            'card' => $card,
            'student' => $card->student,
        ];
    }

    /**
     * Update card status
     */
    public function updateStatus(StudentCard $card, string $status): StudentCard
    {
        if (! in_array($status, StudentCard::STATUSES)) {
            throw new \Exception("Invalid status: {$status}");
        }

        $card->update(['status' => $status]);

        return $card->fresh();
    }

    /**
     * Update print status
     */
    public function updatePrintStatus(StudentCard $card, string $printStatus): StudentCard
    {
        if (! in_array($printStatus, StudentCard::PRINT_STATUSES)) {
            throw new \Exception("Invalid print status: {$printStatus}");
        }

        $data = ['print_status' => $printStatus];

        if ($printStatus === StudentCard::PRINT_STATUS_PRINTED) {
            $data['printed_at'] = now();
        } elseif ($printStatus === StudentCard::PRINT_STATUS_DELIVERED) {
            $data['delivered_at'] = now();
            if (! $card->printed_at) {
                $data['printed_at'] = now();
            }
        }

        $card->update($data);

        return $card->fresh();
    }

    /**
     * Generate unique card number
     */
    private function generateCardNumber(AcademicYear $year): string
    {
        $prefix = 'CARD';
        $yearCode = $year->start_date?->format('Y') ?? date('Y');
        $pattern = "{$prefix}-{$yearCode}-";

        // Get the maximum card number for this year pattern (including soft-deleted cards)
        $lastCard = StudentCard::withTrashed()
            ->where('card_number', 'like', $pattern.'%')
            ->orderByRaw('CAST(SUBSTRING(card_number, -6) AS UNSIGNED) DESC')
            ->first();

        if ($lastCard) {
            // Extract the sequence number from the last card
            $lastSequence = (int) substr($lastCard->card_number, -6);
            $sequence = $lastSequence + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%06d', $prefix, $yearCode, $sequence);
    }

    /**
     * Generate QR code data
     */
    private function generateQRData(Student $student, string $cardNumber, AcademicYear $year): array
    {
        return [
            'card_number' => $cardNumber,
            'matricule' => $student->matricule,
            'student_id' => $student->id,
            'firstname' => $student->firstname,
            'lastname' => $student->lastname,
            'program' => $student->program?->code ?? 'N/A',
            'level' => $student->level ?? 'N/A',
            'valid_until' => ($year->end_date ?? now()->addYear())->format('Y-m-d'),
            'issued_at' => now()->format('Y-m-d'),
        ];
    }

    /**
     * Sign QR data with HMAC
     */
    private function signQRData(array $data): string
    {
        $secret = config('app.key');

        return hash_hmac('sha256', json_encode($data), $secret);
    }

    /**
     * Get card statistics
     */
    public function getStatistics(int $academicYearId): array
    {
        $query = StudentCard::where('academic_year_id', $academicYearId);

        $total = $query->count();
        $byStatus = (clone $query)->get()->groupBy('status')->map->count();
        $byPrintStatus = (clone $query)->get()->groupBy('print_status')->map->count();
        $duplicates = (clone $query)->where('is_duplicate', true)->count();

        return [
            'total' => $total,
            'originals' => $total - $duplicates,
            'duplicates' => $duplicates,
            'by_status' => $byStatus->toArray(),
            'by_print_status' => $byPrintStatus->toArray(),
            'pending_print' => $byPrintStatus->get(StudentCard::PRINT_STATUS_PENDING, 0),
            'printed' => $byPrintStatus->get(StudentCard::PRINT_STATUS_PRINTED, 0),
            'delivered' => $byPrintStatus->get(StudentCard::PRINT_STATUS_DELIVERED, 0),
        ];
    }
}
