<?php

namespace Modules\Documents\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\DocumentRequest;
use Modules\Documents\Entities\DocumentTemplate;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Service for Epic 3: Attestations (Stories 11-16)
 *
 * Story 11: Enrollment certificates
 * Story 12: Student status certificates
 * Story 13: Achievement certificates
 * Story 14: Attendance certificates
 * Story 15: Certificate request workflow
 * Story 16: Validation workflow
 */
class CertificateService
{
    public function __construct(
        private DocumentVerificationService $verificationService
    ) {}

    /**
     * Story 11: Generate enrollment certificate
     */
    public function generateEnrollmentCertificate(
        Student $student,
        AcademicYear $academicYear,
        ?DocumentRequest $request = null
    ): Document {
        $template = DocumentTemplate::active()
            ->ofType('certificate_enrollment')
            ->first();

        if (! $template) {
            throw new \Exception('No active enrollment certificate template found');
        }

        // Generate document number
        $documentNumber = $this->generateDocumentNumber('CE', $academicYear);

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Get enrollment details
        $enrollmentDetails = $this->getEnrollmentDetails($student, $academicYear);

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_matricule' => $student->matricule,
            'student_birthdate' => $student->birthdate->format('d/m/Y'),
            'student_birthplace' => $student->birthplace,
            'academic_year' => $academicYear->name,
            'programme_name' => $enrollmentDetails['programme_name'] ?? '',
            'level' => $enrollmentDetails['level'] ?? '',
            'document_number' => $documentNumber,
            'issue_date' => now()->format('d/m/Y'),
            'purpose' => $request?->reason ?? 'À la demande de l\'intéressé(e)',
            'verification_code' => $verificationCode,
        ];

        // Render and create document
        return $this->createCertificate(
            $student,
            'certificate_enrollment',
            $template,
            $documentNumber,
            $verificationCode,
            $data,
            $academicYear,
            $request
        );
    }

    /**
     * Story 12: Generate student status certificate
     */
    public function generateStatusCertificate(
        Student $student,
        AcademicYear $academicYear,
        ?DocumentRequest $request = null
    ): Document {
        $template = DocumentTemplate::active()
            ->ofType('certificate_status')
            ->first();

        if (! $template) {
            throw new \Exception('No active status certificate template found');
        }

        // Generate document number
        $documentNumber = $this->generateDocumentNumber('CS', $academicYear);

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_matricule' => $student->matricule,
            'student_birthdate' => $student->birthdate->format('d/m/Y'),
            'status' => $this->getStatusLabel($student->status),
            'academic_year' => $academicYear->name,
            'document_number' => $documentNumber,
            'issue_date' => now()->format('d/m/Y'),
            'purpose' => $request?->reason ?? 'À la demande de l\'intéressé(e)',
            'verification_code' => $verificationCode,
        ];

        return $this->createCertificate(
            $student,
            'certificate_status',
            $template,
            $documentNumber,
            $verificationCode,
            $data,
            $academicYear,
            $request
        );
    }

    /**
     * Story 13: Generate achievement certificate
     */
    public function generateAchievementCertificate(
        Student $student,
        AcademicYear $academicYear,
        array $achievementData,
        ?DocumentRequest $request = null
    ): Document {
        $template = DocumentTemplate::active()
            ->ofType('certificate_achievement')
            ->first();

        if (! $template) {
            throw new \Exception('No active achievement certificate template found');
        }

        // Generate document number
        $documentNumber = $this->generateDocumentNumber('CA', $academicYear);

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_matricule' => $student->matricule,
            'academic_year' => $academicYear->name,
            'achievement_title' => $achievementData['title'],
            'achievement_description' => $achievementData['description'],
            'achievement_date' => $achievementData['date'],
            'document_number' => $documentNumber,
            'issue_date' => now()->format('d/m/Y'),
            'verification_code' => $verificationCode,
        ];

        return $this->createCertificate(
            $student,
            'certificate_achievement',
            $template,
            $documentNumber,
            $verificationCode,
            $data,
            $academicYear,
            $request
        );
    }

    /**
     * Story 14: Generate attendance certificate
     */
    public function generateAttendanceCertificate(
        Student $student,
        AcademicYear $academicYear,
        ?DocumentRequest $request = null
    ): Document {
        $template = DocumentTemplate::active()
            ->ofType('certificate_attendance')
            ->first();

        if (! $template) {
            throw new \Exception('No active attendance certificate template found');
        }

        // Generate document number
        $documentNumber = $this->generateDocumentNumber('CAT', $academicYear);

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Get attendance statistics
        $attendanceStats = $this->getAttendanceStatistics($student, $academicYear);

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_matricule' => $student->matricule,
            'academic_year' => $academicYear->name,
            'attendance_rate' => $attendanceStats['rate'],
            'total_sessions' => $attendanceStats['total_sessions'],
            'attended_sessions' => $attendanceStats['attended_sessions'],
            'document_number' => $documentNumber,
            'issue_date' => now()->format('d/m/Y'),
            'purpose' => $request?->reason ?? 'À la demande de l\'intéressé(e)',
            'verification_code' => $verificationCode,
        ];

        return $this->createCertificate(
            $student,
            'certificate_attendance',
            $template,
            $documentNumber,
            $verificationCode,
            $data,
            $academicYear,
            $request
        );
    }

    /**
     * Story 15: Create certificate request
     */
    public function createCertificateRequest(
        Student $student,
        string $certificateType,
        string $reason,
        int $quantity = 1,
        bool $urgent = false
    ): DocumentRequest {
        $requestDate = now();
        $expectedDeliveryDate = $urgent ? $requestDate->copy()->addBusinessDays(2) : $requestDate->copy()->addBusinessDays(5);

        // Calculate fee based on certificate type and urgency
        $feeAmount = $this->calculateCertificateFee($certificateType, $quantity, $urgent);

        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => $certificateType,
            'quantity' => $quantity,
            'reason' => $reason,
            'urgency' => $urgent ? 'urgent' : 'normal',
            'request_date' => $requestDate,
            'expected_delivery_date' => $expectedDeliveryDate,
            'status' => 'pending',
            'fee_amount' => $feeAmount,
            'fee_paid' => false,
        ]);

        return $request;
    }

    /**
     * Story 16: Approve certificate request and generate certificate
     */
    public function approveAndGenerateCertificate(
        DocumentRequest $request,
        int $approvedBy,
        ?string $notes = null
    ): Document {
        // Approve the request
        $request->approve($approvedBy, $notes);

        // Mark as processing
        $request->markAsProcessing();

        // Get student and academic year
        $student = $request->student;
        $academicYear = AcademicYear::where('is_active', true)->first();

        // Generate the appropriate certificate
        $document = match ($request->document_type) {
            'certificate_enrollment' => $this->generateEnrollmentCertificate($student, $academicYear, $request),
            'certificate_status' => $this->generateStatusCertificate($student, $academicYear, $request),
            'certificate_attendance' => $this->generateAttendanceCertificate($student, $academicYear, $request),
            default => throw new \Exception('Unknown certificate type'),
        };

        // Mark request as completed
        $request->markAsCompleted($document->id);

        return $document;
    }

    /**
     * Story 16: Reject certificate request
     */
    public function rejectCertificateRequest(
        DocumentRequest $request,
        int $rejectedBy,
        string $reason
    ): bool {
        return $request->reject($rejectedBy, $reason);
    }

    /**
     * Story 17: Generate schooling certificate (Epic 4)
     */
    public function generateSchoolingCertificate(
        Student $student,
        AcademicYear $academicYear,
        ?DocumentRequest $request = null
    ): Document {
        $template = DocumentTemplate::active()
            ->ofType('certificate_schooling')
            ->first();

        if (! $template) {
            throw new \Exception('No active schooling certificate template found');
        }

        // Generate document number
        $documentNumber = $this->generateDocumentNumber('CSC', $academicYear);

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Get schooling history
        $schoolingHistory = $this->getSchoolingHistory($student);

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_matricule' => $student->matricule,
            'student_birthdate' => $student->birthdate->format('d/m/Y'),
            'schooling_years' => $schoolingHistory,
            'current_academic_year' => $academicYear->name,
            'document_number' => $documentNumber,
            'issue_date' => now()->format('d/m/Y'),
            'purpose' => $request?->reason ?? 'À la demande de l\'intéressé(e)',
            'verification_code' => $verificationCode,
        ];

        return $this->createCertificate(
            $student,
            'certificate_schooling',
            $template,
            $documentNumber,
            $verificationCode,
            $data,
            $academicYear,
            $request
        );
    }

    /**
     * Story 18: Generate transfer certificate (exeat)
     */
    public function generateTransferCertificate(
        Student $student,
        string $transferDestination,
        string $transferReason,
        AcademicYear $academicYear
    ): Document {
        $template = DocumentTemplate::active()
            ->ofType('certificate_transfer')
            ->first();

        if (! $template) {
            throw new \Exception('No active transfer certificate template found');
        }

        // Generate document number
        $documentNumber = $this->generateDocumentNumber('CT', $academicYear);

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Get student academic record
        $academicRecord = $this->getAcademicRecordSummary($student, $academicYear);

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_matricule' => $student->matricule,
            'student_birthdate' => $student->birthdate->format('d/m/Y'),
            'transfer_destination' => $transferDestination,
            'transfer_reason' => $transferReason,
            'academic_record' => $academicRecord,
            'current_academic_year' => $academicYear->name,
            'document_number' => $documentNumber,
            'issue_date' => now()->format('d/m/Y'),
            'verification_code' => $verificationCode,
        ];

        return $this->createCertificate(
            $student,
            'certificate_transfer',
            $template,
            $documentNumber,
            $verificationCode,
            $data,
            $academicYear,
            null
        );
    }

    /**
     * Common certificate creation logic
     */
    private function createCertificate(
        Student $student,
        string $certificateType,
        DocumentTemplate $template,
        string $documentNumber,
        string $verificationCode,
        array $data,
        AcademicYear $academicYear,
        ?DocumentRequest $request
    ): Document {
        // Render HTML
        $html = $template->renderTemplate($data);

        // Generate PDF
        $pdfPath = $this->generatePdf($html, $documentNumber, $template);

        // Generate QR code
        $qrCodePath = $this->generateQrCode($verificationCode, $documentNumber);

        // Create document record
        $document = Document::create([
            'student_id' => $student->id,
            'document_type' => $certificateType,
            'template_id' => $template->id,
            'document_number' => $documentNumber,
            'issue_date' => now(),
            'academic_year_id' => $academicYear->id,
            'pdf_path' => $pdfPath,
            'verification_code' => $verificationCode,
            'qr_code_path' => $qrCodePath,
            'status' => 'issued',
            'metadata' => [
                'request_id' => $request?->id,
            ],
            'issued_by' => auth()->id(),
        ]);

        return $document;
    }

    /**
     * Get enrollment details
     */
    private function getEnrollmentDetails(Student $student, AcademicYear $academicYear): array
    {
        // This would integrate with Enrollment module
        return [
            'programme_name' => '',
            'level' => '',
        ];
    }

    /**
     * Get status label
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'Actif' => 'Étudiant régulièrement inscrit',
            'Diplômé' => 'Étudiant diplômé',
            'Suspendu' => 'Étudiant suspendu',
            default => $status,
        };
    }

    /**
     * Get attendance statistics
     */
    private function getAttendanceStatistics(Student $student, AcademicYear $academicYear): array
    {
        // This would integrate with Attendance module
        return [
            'rate' => 95.5,
            'total_sessions' => 200,
            'attended_sessions' => 191,
        ];
    }

    /**
     * Get schooling history
     */
    private function getSchoolingHistory(Student $student): array
    {
        // This would get all academic years the student was enrolled
        return [];
    }

    /**
     * Get academic record summary
     */
    private function getAcademicRecordSummary(Student $student, AcademicYear $academicYear): array
    {
        // This would integrate with NotesEvaluations module
        return [];
    }

    /**
     * Calculate certificate fee
     */
    private function calculateCertificateFee(string $certificateType, int $quantity, bool $urgent): float
    {
        $baseFee = config('documents.certificate_fees.'.$certificateType, 5000);
        $urgentMultiplier = $urgent ? 1.5 : 1;

        return $baseFee * $quantity * $urgentMultiplier;
    }

    /**
     * Generate document number
     */
    private function generateDocumentNumber(string $prefix, AcademicYear $academicYear): string
    {
        $year = $academicYear->name;
        $sequence = Document::where('document_number', 'like', "{$prefix}-{$year}-%")->count() + 1;

        return sprintf('%s-%s-%05d', $prefix, $year, $sequence);
    }

    /**
     * Generate PDF
     */
    private function generatePdf(string $html, string $documentNumber, DocumentTemplate $template): string
    {
        $settings = $template->settings ?? [];

        $pdf = Pdf::loadHTML($html)
            ->setPaper($settings['paper_size'] ?? 'a4', $settings['orientation'] ?? 'portrait')
            ->setOption('margin-top', $settings['margin_top'] ?? 10)
            ->setOption('margin-bottom', $settings['margin_bottom'] ?? 10)
            ->setOption('margin-left', $settings['margin_left'] ?? 10)
            ->setOption('margin-right', $settings['margin_right'] ?? 10);

        $filename = "certificates/{$documentNumber}.pdf";
        Storage::disk('tenant')->put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Generate QR Code
     */
    private function generateQrCode(string $verificationCode, string $documentNumber): string
    {
        $qrCode = QrCode::format('png')
            ->size(300)
            ->generate($verificationCode);

        $filename = "qr_codes/{$documentNumber}.png";
        Storage::disk('tenant')->put($filename, $qrCode);

        return $filename;
    }
}
