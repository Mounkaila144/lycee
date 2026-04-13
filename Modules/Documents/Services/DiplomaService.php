<?php

namespace Modules\Documents\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Entities\DiplomaRegister;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\DocumentTemplate;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Service for Epic 2: Diplômes (Stories 06-10)
 *
 * Story 06: Diploma generation with honors
 * Story 07: Diploma register
 * Story 08: Honor mentions on diplomas
 * Story 09: Duplicate diplomas
 * Story 10: Diploma supplements (European standard)
 */
class DiplomaService
{
    public function __construct(
        private DocumentVerificationService $verificationService
    ) {}

    /**
     * Story 06 & 08: Generate diploma with honors
     */
    public function generateDiploma(
        Student $student,
        Programme $programme,
        AcademicYear $academicYear,
        \DateTime $graduationDate,
        float $finalGpa,
        ?string $specialization = null
    ): DiplomaRegister {
        // Calculate honors based on GPA
        $honors = $this->calculateHonors($finalGpa);

        $template = DocumentTemplate::active()
            ->ofType('diploma')
            ->first();

        if (! $template) {
            throw new \Exception('No active diploma template found');
        }

        // Generate diploma number
        $diplomaNumber = $this->generateDiplomaNumber($programme, $academicYear);
        $registerNumber = $this->generateRegisterNumber($academicYear);

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_birthdate' => $student->birthdate->format('d/m/Y'),
            'student_birthplace' => $student->birthplace,
            'programme_name' => $programme->name,
            'diploma_type' => $this->getDiplomaType($programme),
            'specialization' => $specialization,
            'graduation_date' => $graduationDate->format('d/m/Y'),
            'academic_year' => $academicYear->name,
            'final_gpa' => $finalGpa,
            'honors' => $this->getHonorsLabel($honors),
            'diploma_number' => $diplomaNumber,
            'register_number' => $registerNumber,
            'issue_date' => now()->format('d/m/Y'),
            'verification_code' => $verificationCode,
        ];

        // Render HTML
        $html = $template->renderTemplate($data);

        // Generate PDF
        $pdfPath = $this->generatePdf($html, $diplomaNumber, $template);

        // Generate QR code
        $qrCodePath = $this->generateQrCode($verificationCode, $diplomaNumber);

        // Create document record
        $document = Document::create([
            'student_id' => $student->id,
            'document_type' => 'diploma',
            'template_id' => $template->id,
            'document_number' => $diplomaNumber,
            'issue_date' => now(),
            'academic_year_id' => $academicYear->id,
            'programme_id' => $programme->id,
            'pdf_path' => $pdfPath,
            'verification_code' => $verificationCode,
            'qr_code_path' => $qrCodePath,
            'status' => 'issued',
            'metadata' => [
                'final_gpa' => $finalGpa,
                'honors' => $honors,
                'graduation_date' => $graduationDate->format('Y-m-d'),
            ],
            'issued_by' => auth()->id(),
        ]);

        // Story 07: Create diploma register entry
        $diplomaRegister = DiplomaRegister::create([
            'student_id' => $student->id,
            'programme_id' => $programme->id,
            'diploma_number' => $diplomaNumber,
            'register_number' => $registerNumber,
            'issue_date' => now(),
            'graduation_date' => $graduationDate,
            'academic_year_id' => $academicYear->id,
            'honors' => $honors,
            'final_gpa' => $finalGpa,
            'diploma_type' => $this->getDiplomaType($programme),
            'specialization' => $specialization,
            'document_id' => $document->id,
        ]);

        return $diplomaRegister;
    }

    /**
     * Story 09: Generate duplicate diploma
     */
    public function generateDuplicate(
        DiplomaRegister $originalDiploma,
        string $reason
    ): DiplomaRegister {
        $template = DocumentTemplate::active()
            ->ofType('diploma_duplicate')
            ->first();

        if (! $template) {
            throw new \Exception('No active diploma duplicate template found');
        }

        // Generate new diploma number for duplicate
        $diplomaNumber = $this->generateDiplomaNumber(
            $originalDiploma->programme,
            $originalDiploma->academicYear,
            'DUP'
        );
        $registerNumber = $this->generateRegisterNumber($originalDiploma->academicYear, 'DUP');

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Prepare template data (same as original but marked as duplicate)
        $data = [
            'student_name' => $originalDiploma->student->full_name,
            'student_birthdate' => $originalDiploma->student->birthdate->format('d/m/Y'),
            'student_birthplace' => $originalDiploma->student->birthplace,
            'programme_name' => $originalDiploma->programme->name,
            'diploma_type' => $originalDiploma->diploma_type,
            'specialization' => $originalDiploma->specialization,
            'graduation_date' => $originalDiploma->graduation_date->format('d/m/Y'),
            'academic_year' => $originalDiploma->academicYear->name,
            'final_gpa' => $originalDiploma->final_gpa,
            'honors' => $this->getHonorsLabel($originalDiploma->honors),
            'diploma_number' => $diplomaNumber,
            'register_number' => $registerNumber,
            'issue_date' => now()->format('d/m/Y'),
            'original_diploma_number' => $originalDiploma->diploma_number,
            'is_duplicate' => true,
            'duplicate_reason' => $reason,
            'verification_code' => $verificationCode,
        ];

        // Render HTML
        $html = $template->renderTemplate($data);

        // Generate PDF
        $pdfPath = $this->generatePdf($html, $diplomaNumber, $template);

        // Generate QR code
        $qrCodePath = $this->generateQrCode($verificationCode, $diplomaNumber);

        // Create document record
        $document = Document::create([
            'student_id' => $originalDiploma->student_id,
            'document_type' => 'diploma_duplicate',
            'template_id' => $template->id,
            'document_number' => $diplomaNumber,
            'issue_date' => now(),
            'academic_year_id' => $originalDiploma->academic_year_id,
            'programme_id' => $originalDiploma->programme_id,
            'pdf_path' => $pdfPath,
            'verification_code' => $verificationCode,
            'qr_code_path' => $qrCodePath,
            'status' => 'issued',
            'metadata' => [
                'final_gpa' => $originalDiploma->final_gpa,
                'honors' => $originalDiploma->honors,
                'graduation_date' => $originalDiploma->graduation_date->format('Y-m-d'),
                'is_duplicate' => true,
                'original_diploma_id' => $originalDiploma->id,
                'duplicate_reason' => $reason,
            ],
            'issued_by' => auth()->id(),
        ]);

        // Create diploma register entry for duplicate
        $duplicateRegister = DiplomaRegister::create([
            'student_id' => $originalDiploma->student_id,
            'programme_id' => $originalDiploma->programme_id,
            'diploma_number' => $diplomaNumber,
            'register_number' => $registerNumber,
            'issue_date' => now(),
            'graduation_date' => $originalDiploma->graduation_date,
            'academic_year_id' => $originalDiploma->academic_year_id,
            'honors' => $originalDiploma->honors,
            'final_gpa' => $originalDiploma->final_gpa,
            'diploma_type' => $originalDiploma->diploma_type,
            'specialization' => $originalDiploma->specialization,
            'document_id' => $document->id,
            'is_duplicate' => true,
            'original_diploma_id' => $originalDiploma->id,
            'duplicate_reason' => $reason,
        ]);

        return $duplicateRegister;
    }

    /**
     * Story 10: Generate diploma supplement (European standard)
     */
    public function generateSupplement(DiplomaRegister $diploma): Document
    {
        $template = DocumentTemplate::active()
            ->ofType('diploma_supplement')
            ->first();

        if (! $template) {
            throw new \Exception('No active diploma supplement template found');
        }

        // Generate document number
        $documentNumber = $this->generateSupplementNumber($diploma);

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Get comprehensive academic record
        $academicRecord = $this->getComprehensiveAcademicRecord($diploma);

        // Prepare template data (European Diploma Supplement format)
        $data = [
            // Section 1: Holder of the qualification
            'student_name' => $diploma->student->full_name,
            'student_birthdate' => $diploma->student->birthdate->format('d/m/Y'),
            'student_id' => $diploma->student->matricule,

            // Section 2: Qualification
            'qualification_name' => $diploma->programme->name,
            'qualification_title' => $this->getQualificationTitle($diploma),
            'main_field' => $diploma->specialization,
            'institution_name' => config('app.name'),
            'institution_status' => 'Public/Private',
            'language_instruction' => 'Français',

            // Section 3: Level of qualification
            'level' => $this->getEQFLevel($diploma),
            'duration' => $this->getProgrammeDuration($diploma->programme),

            // Section 4: Contents and results
            'mode_of_study' => 'Full-time',
            'programme_requirements' => $academicRecord['requirements'],
            'programme_details' => $academicRecord['courses'],
            'grading_scheme' => $this->getGradingScheme(),
            'overall_classification' => $this->getHonorsLabel($diploma->honors),
            'final_gpa' => $diploma->final_gpa,

            // Section 5: Function of qualification
            'access_to_further_study' => $this->getAccessRights($diploma),
            'professional_status' => $this->getProfessionalStatus($diploma),

            // Section 6: Additional information
            'additional_info' => $this->getAdditionalInfo($diploma),

            // Section 7: Certification
            'certification_date' => now()->format('d/m/Y'),
            'document_number' => $documentNumber,
            'verification_code' => $verificationCode,

            // Section 8: Information on national higher education system
            'higher_education_system' => $this->getHigherEducationSystemInfo(),
        ];

        // Render HTML
        $html = $template->renderTemplate($data);

        // Generate PDF
        $pdfPath = $this->generatePdf($html, $documentNumber, $template);

        // Generate QR code
        $qrCodePath = $this->generateQrCode($verificationCode, $documentNumber);

        // Create document record
        $document = Document::create([
            'student_id' => $diploma->student_id,
            'document_type' => 'diploma_supplement',
            'template_id' => $template->id,
            'document_number' => $documentNumber,
            'issue_date' => now(),
            'academic_year_id' => $diploma->academic_year_id,
            'programme_id' => $diploma->programme_id,
            'pdf_path' => $pdfPath,
            'verification_code' => $verificationCode,
            'qr_code_path' => $qrCodePath,
            'status' => 'issued',
            'metadata' => [
                'diploma_register_id' => $diploma->id,
                'european_standard' => true,
            ],
            'issued_by' => auth()->id(),
        ]);

        // Update diploma register
        $diploma->update([
            'supplement_generated' => true,
            'supplement_document_id' => $document->id,
        ]);

        return $document;
    }

    /**
     * Calculate honors based on GPA
     */
    private function calculateHonors(float $gpa): string
    {
        return match (true) {
            $gpa >= 16 => 'excellent',
            $gpa >= 14 => 'tres_bien',
            $gpa >= 12 => 'bien',
            $gpa >= 10 => 'assez_bien',
            default => 'passable',
        };
    }

    /**
     * Get honors label
     */
    private function getHonorsLabel(string $honors): string
    {
        return match ($honors) {
            'excellent' => 'Excellent',
            'tres_bien' => 'Très Bien',
            'bien' => 'Bien',
            'assez_bien' => 'Assez Bien',
            'passable' => 'Passable',
            default => 'Sans mention',
        };
    }

    /**
     * Generate diploma number
     */
    private function generateDiplomaNumber(Programme $programme, AcademicYear $academicYear, string $prefix = ''): string
    {
        $year = $academicYear->name;
        $programmeCode = strtoupper(substr($programme->code, 0, 3));
        $prefix = $prefix ? "{$prefix}-" : '';
        $sequence = DiplomaRegister::where('diploma_number', 'like', "{$prefix}DIP-{$programmeCode}-{$year}-%")->count() + 1;

        return sprintf('%sDIP-%s-%s-%05d', $prefix, $programmeCode, $year, $sequence);
    }

    /**
     * Generate register number
     */
    private function generateRegisterNumber(AcademicYear $academicYear, string $prefix = ''): string
    {
        $year = $academicYear->name;
        $prefix = $prefix ? "{$prefix}-" : '';
        $sequence = DiplomaRegister::where('register_number', 'like', "{$prefix}REG-{$year}-%")->count() + 1;

        return sprintf('%sREG-%s-%05d', $prefix, $year, $sequence);
    }

    /**
     * Generate supplement number
     */
    private function generateSupplementNumber(DiplomaRegister $diploma): string
    {
        return "SUP-{$diploma->diploma_number}";
    }

    /**
     * Get diploma type
     */
    private function getDiplomaType(Programme $programme): string
    {
        // This would be based on programme level
        return 'Licence'; // or 'Master', 'Doctorat'
    }

    /**
     * Get comprehensive academic record for supplement
     */
    private function getComprehensiveAcademicRecord(DiplomaRegister $diploma): array
    {
        // This would integrate with StructureAcademique and NotesEvaluations modules
        return [
            'requirements' => 'Completion of 180 ECTS credits',
            'courses' => [], // Detailed course list with grades
        ];
    }

    /**
     * Get EQF (European Qualifications Framework) level
     */
    private function getEQFLevel(DiplomaRegister $diploma): string
    {
        return match ($diploma->diploma_type) {
            'Licence' => 'Level 6 (Bachelor)',
            'Master' => 'Level 7 (Master)',
            'Doctorat' => 'Level 8 (Doctorate)',
            default => 'Level 6',
        };
    }

    /**
     * Get programme duration
     */
    private function getProgrammeDuration(Programme $programme): string
    {
        // This would be from programme configuration
        return '3 years (180 ECTS)';
    }

    /**
     * Get grading scheme
     */
    private function getGradingScheme(): string
    {
        return '0-20 scale. Pass mark: 10/20';
    }

    /**
     * Get access rights for further study
     */
    private function getAccessRights(DiplomaRegister $diploma): string
    {
        return match ($diploma->diploma_type) {
            'Licence' => 'Access to Master programmes',
            'Master' => 'Access to Doctoral programmes',
            default => '',
        };
    }

    /**
     * Get professional status
     */
    private function getProfessionalStatus(DiplomaRegister $diploma): string
    {
        return 'Professional qualification in the field of study';
    }

    /**
     * Get additional information
     */
    private function getAdditionalInfo(DiplomaRegister $diploma): string
    {
        return '';
    }

    /**
     * Get qualification title
     */
    private function getQualificationTitle(DiplomaRegister $diploma): string
    {
        return "{$diploma->diploma_type} in {$diploma->programme->name}";
    }

    /**
     * Get higher education system information
     */
    private function getHigherEducationSystemInfo(): string
    {
        return 'Information about the national higher education system...';
    }

    /**
     * Generate PDF
     */
    private function generatePdf(string $html, string $documentNumber, DocumentTemplate $template): string
    {
        $settings = $template->settings ?? [];

        $pdf = Pdf::loadHTML($html)
            ->setPaper($settings['paper_size'] ?? 'a4', $settings['orientation'] ?? 'landscape')
            ->setOption('margin-top', $settings['margin_top'] ?? 10)
            ->setOption('margin-bottom', $settings['margin_bottom'] ?? 10)
            ->setOption('margin-left', $settings['margin_left'] ?? 10)
            ->setOption('margin-right', $settings['margin_right'] ?? 10);

        $filename = "diplomas/{$documentNumber}.pdf";
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
