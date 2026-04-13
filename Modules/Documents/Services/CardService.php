<?php

namespace Modules\Documents\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\DocumentTemplate;
use Modules\Documents\Entities\StudentCard;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Service for Epic 4: Certificats (Stories 19-20)
 *
 * Story 19: Student ID cards
 * Story 20: Access badges
 */
class CardService
{
    public function __construct(
        private DocumentVerificationService $verificationService
    ) {}

    /**
     * Story 19: Generate student ID card
     */
    public function generateStudentCard(
        Student $student,
        AcademicYear $academicYear,
        ?array $accessPermissions = null
    ): StudentCard {
        // Check if student already has an active card for this academic year
        $existingCard = StudentCard::forStudent($student->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('card_type', 'student_id')
            ->active()
            ->first();

        if ($existingCard) {
            throw new \Exception('Student already has an active ID card for this academic year');
        }

        // Generate card number
        $cardNumber = $this->generateCardNumber($student, $academicYear);

        // Generate QR code content
        $qrCodeContent = $this->generateQrCodeContent($student, $cardNumber);

        // Generate barcode
        $barcode = $this->generateBarcode($student, $cardNumber);

        // Calculate expiry date (end of academic year)
        $expiryDate = $this->calculateExpiryDate($academicYear);

        // Create student card record
        $studentCard = StudentCard::create([
            'student_id' => $student->id,
            'card_number' => $cardNumber,
            'card_type' => 'student_id',
            'issue_date' => now(),
            'expiry_date' => $expiryDate,
            'academic_year_id' => $academicYear->id,
            'photo_path' => $student->photo,
            'qr_code' => $qrCodeContent,
            'barcode' => $barcode,
            'status' => 'active',
            'access_permissions' => $accessPermissions ?? $this->getDefaultAccessPermissions(),
            'is_printed' => false,
        ]);

        // Generate QR code image
        $qrCodePath = $this->generateQrCodeImage($qrCodeContent, $cardNumber);
        $studentCard->update(['qr_code_path' => $qrCodePath]);

        // Generate barcode image
        $barcodePath = $this->generateBarcodeImage($barcode, $cardNumber);
        $studentCard->update(['barcode_path' => $barcodePath]);

        // Generate card PDF for printing
        $document = $this->generateCardPdf($student, $studentCard);

        $studentCard->update(['document_id' => $document->id]);

        return $studentCard;
    }

    /**
     * Story 20: Generate access badge
     */
    public function generateAccessBadge(
        Student $student,
        AcademicYear $academicYear,
        array $accessPermissions
    ): StudentCard {
        // Generate card number
        $cardNumber = $this->generateCardNumber($student, $academicYear, 'BADGE');

        // Generate QR code content
        $qrCodeContent = $this->generateQrCodeContent($student, $cardNumber, 'access_badge');

        // Calculate expiry date
        $expiryDate = $this->calculateExpiryDate($academicYear);

        // Create access badge record
        $accessBadge = StudentCard::create([
            'student_id' => $student->id,
            'card_number' => $cardNumber,
            'card_type' => 'access_badge',
            'issue_date' => now(),
            'expiry_date' => $expiryDate,
            'academic_year_id' => $academicYear->id,
            'photo_path' => $student->photo,
            'qr_code' => $qrCodeContent,
            'status' => 'active',
            'access_permissions' => $accessPermissions,
            'is_printed' => false,
        ]);

        // Generate QR code image
        $qrCodePath = $this->generateQrCodeImage($qrCodeContent, $cardNumber);
        $accessBadge->update(['qr_code_path' => $qrCodePath]);

        // Generate badge PDF for printing
        $document = $this->generateBadgePdf($student, $accessBadge);

        $accessBadge->update(['document_id' => $document->id]);

        return $accessBadge;
    }

    /**
     * Batch generate student cards
     */
    public function batchGenerateStudentCards(
        array $studentIds,
        AcademicYear $academicYear,
        ?array $accessPermissions = null
    ): array {
        $results = [];

        foreach ($studentIds as $studentId) {
            $student = Student::findOrFail($studentId);

            try {
                $card = $this->generateStudentCard($student, $academicYear, $accessPermissions);

                $results[] = [
                    'student_id' => $studentId,
                    'card_id' => $card->id,
                    'status' => 'success',
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'student_id' => $studentId,
                    'card_id' => null,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Replace lost or stolen card
     */
    public function replaceCard(
        StudentCard $oldCard,
        string $replacementReason
    ): StudentCard {
        // Mark old card as lost/stolen/replaced
        $status = match ($replacementReason) {
            'lost' => 'lost',
            'stolen' => 'stolen',
            default => 'replaced',
        };

        $oldCard->update([
            'status' => $status,
            'replacement_reason' => $replacementReason,
        ]);

        // Generate new card
        $newCard = $this->generateStudentCard(
            $oldCard->student,
            $oldCard->academicYear,
            $oldCard->access_permissions
        );

        // Link replacement
        $oldCard->update(['replaced_by_card_id' => $newCard->id]);

        return $newCard;
    }

    /**
     * Print card (mark as printed)
     */
    public function printCard(StudentCard $card, int $printedBy): bool
    {
        return $card->markAsPrinted($printedBy);
    }

    /**
     * Batch print cards
     */
    public function batchPrintCards(array $cardIds, int $printedBy): array
    {
        $results = [];

        foreach ($cardIds as $cardId) {
            $card = StudentCard::findOrFail($cardId);

            try {
                $this->printCard($card, $printedBy);

                $results[] = [
                    'card_id' => $cardId,
                    'status' => 'success',
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'card_id' => $cardId,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Verify card access permission
     */
    public function verifyCardAccess(StudentCard $card, string $permission): bool
    {
        if (! $card->isActive()) {
            return false;
        }

        return $card->hasAccessPermission($permission);
    }

    /**
     * Update card access permissions
     */
    public function updateAccessPermissions(StudentCard $card, array $permissions): bool
    {
        return $card->update(['access_permissions' => $permissions]);
    }

    /**
     * Generate card number
     */
    private function generateCardNumber(Student $student, AcademicYear $academicYear, string $prefix = 'CARD'): string
    {
        $year = substr($academicYear->name, -2);
        $sequence = StudentCard::where('card_number', 'like', "{$prefix}-{$year}-%")->count() + 1;

        return sprintf('%s-%s-%s-%05d', $prefix, $year, strtoupper(substr($student->matricule, -4)), $sequence);
    }

    /**
     * Generate QR code content
     */
    private function generateQrCodeContent(Student $student, string $cardNumber, string $cardType = 'student_id'): string
    {
        $data = [
            'card_number' => $cardNumber,
            'card_type' => $cardType,
            'student_id' => $student->id,
            'student_matricule' => $student->matricule,
            'timestamp' => now()->timestamp,
        ];

        return json_encode($data);
    }

    /**
     * Generate barcode
     */
    private function generateBarcode(Student $student, string $cardNumber): string
    {
        // Simple barcode format: student matricule + card sequence
        return $student->matricule.'-'.substr($cardNumber, -5);
    }

    /**
     * Calculate expiry date
     */
    private function calculateExpiryDate(AcademicYear $academicYear): \Carbon\Carbon
    {
        // End of academic year + 3 months grace period
        if ($academicYear->end_date) {
            return $academicYear->end_date->copy()->addMonths(3);
        }

        // Default: end of current year + 1
        return now()->endOfYear()->addYear();
    }

    /**
     * Get default access permissions
     */
    private function getDefaultAccessPermissions(): array
    {
        return [
            'library',
            'computer_lab',
            'cafeteria',
            'main_building',
        ];
    }

    /**
     * Generate card PDF
     */
    private function generateCardPdf(Student $student, StudentCard $card): Document
    {
        $template = DocumentTemplate::active()
            ->ofType('student_card')
            ->first();

        if (! $template) {
            throw new \Exception('No active student card template found');
        }

        // Generate document number
        $documentNumber = $card->card_number;

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Get enrollment details
        $enrollmentDetails = $this->getEnrollmentDetails($student, $card->academicYear);

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_matricule' => $student->matricule,
            'student_photo_url' => $card->photo_url,
            'card_number' => $card->card_number,
            'issue_date' => $card->issue_date->format('d/m/Y'),
            'expiry_date' => $card->expiry_date->format('d/m/Y'),
            'academic_year' => $card->academicYear->name,
            'programme_name' => $enrollmentDetails['programme_name'] ?? '',
            'qr_code_url' => $card->qr_code_url,
            'barcode_url' => $card->barcode_url,
            'verification_code' => $verificationCode,
        ];

        // Render HTML
        $html = $template->renderTemplate($data);

        // Generate PDF (ID card size: 85.6mm x 53.98mm)
        $pdf = Pdf::loadHTML($html)
            ->setPaper([0, 0, 242.65, 153], 'landscape');

        $filename = "cards/{$documentNumber}.pdf";
        Storage::disk('tenant')->put($filename, $pdf->output());

        // Create document record
        $document = Document::create([
            'student_id' => $student->id,
            'document_type' => 'student_card',
            'template_id' => $template->id,
            'document_number' => $documentNumber,
            'issue_date' => now(),
            'academic_year_id' => $card->academic_year_id,
            'pdf_path' => $filename,
            'verification_code' => $verificationCode,
            'qr_code_path' => $card->qr_code_path,
            'status' => 'issued',
            'metadata' => [
                'card_id' => $card->id,
                'card_type' => $card->card_type,
            ],
            'issued_by' => auth()->id(),
        ]);

        return $document;
    }

    /**
     * Generate badge PDF
     */
    private function generateBadgePdf(Student $student, StudentCard $badge): Document
    {
        $template = DocumentTemplate::active()
            ->ofType('access_badge')
            ->first();

        if (! $template) {
            throw new \Exception('No active access badge template found');
        }

        // Generate document number
        $documentNumber = $badge->card_number;

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_matricule' => $student->matricule,
            'student_photo_url' => $badge->photo_url,
            'badge_number' => $badge->card_number,
            'issue_date' => $badge->issue_date->format('d/m/Y'),
            'expiry_date' => $badge->expiry_date->format('d/m/Y'),
            'qr_code_url' => $badge->qr_code_url,
            'access_level' => $this->formatAccessPermissions($badge->access_permissions),
            'verification_code' => $verificationCode,
        ];

        // Render HTML
        $html = $template->renderTemplate($data);

        // Generate PDF (Badge size)
        $pdf = Pdf::loadHTML($html)
            ->setPaper([0, 0, 242.65, 153], 'portrait');

        $filename = "badges/{$documentNumber}.pdf";
        Storage::disk('tenant')->put($filename, $pdf->output());

        // Create document record
        $document = Document::create([
            'student_id' => $student->id,
            'document_type' => 'access_badge',
            'template_id' => $template->id,
            'document_number' => $documentNumber,
            'issue_date' => now(),
            'academic_year_id' => $badge->academic_year_id,
            'pdf_path' => $filename,
            'verification_code' => $verificationCode,
            'qr_code_path' => $badge->qr_code_path,
            'status' => 'issued',
            'metadata' => [
                'card_id' => $badge->id,
                'card_type' => $badge->card_type,
                'access_permissions' => $badge->access_permissions,
            ],
            'issued_by' => auth()->id(),
        ]);

        return $document;
    }

    /**
     * Generate QR code image
     */
    private function generateQrCodeImage(string $content, string $cardNumber): string
    {
        $qrCode = QrCode::format('png')
            ->size(200)
            ->generate($content);

        $filename = "cards/qr/{$cardNumber}.png";
        Storage::disk('tenant')->put($filename, $qrCode);

        return $filename;
    }

    /**
     * Generate barcode image
     */
    private function generateBarcodeImage(string $barcode, string $cardNumber): string
    {
        // This would use a barcode generation library
        // For now, returning a placeholder path
        $filename = "cards/barcodes/{$cardNumber}.png";

        // Placeholder implementation
        // You would use a library like picqer/php-barcode-generator
        // Storage::disk('tenant')->put($filename, $barcodeImage);

        return $filename;
    }

    /**
     * Get enrollment details
     */
    private function getEnrollmentDetails(Student $student, AcademicYear $academicYear): array
    {
        // This would integrate with Enrollment module
        return [
            'programme_name' => '',
        ];
    }

    /**
     * Format access permissions for display
     */
    private function formatAccessPermissions(?array $permissions): string
    {
        if (! $permissions) {
            return 'No access';
        }

        return implode(', ', array_map(function ($permission) {
            return ucfirst(str_replace('_', ' ', $permission));
        }, $permissions));
    }
}
