<?php

namespace Modules\Documents\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\DocumentTemplate;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Semester;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Service for Epic 1: Génération Relevés (Stories 01-05)
 *
 * Story 01: Transcript generation per semester
 * Story 02: Semester-specific transcripts
 * Story 03: Global transcripts (all semesters)
 * Story 04: Provisional transcripts
 * Story 05: Batch transcript generation
 */
class TranscriptService
{
    public function __construct(
        private DocumentVerificationService $verificationService
    ) {}

    /**
     * Story 01 & 02: Generate semester transcript
     */
    public function generateSemesterTranscript(
        Student $student,
        Semester $semester,
        AcademicYear $academicYear,
        bool $isProvisional = false
    ): Document {
        $template = DocumentTemplate::active()
            ->ofType($isProvisional ? 'transcript_provisional' : 'transcript_semester')
            ->first();

        if (! $template) {
            throw new \Exception('No active transcript template found');
        }

        // Get student grades for the semester
        $grades = $this->getStudentGrades($student, $semester, $academicYear);
        $statistics = $this->calculateSemesterStatistics($grades);

        // Generate document number
        $documentNumber = $this->generateDocumentNumber('TS', $academicYear);

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_matricule' => $student->matricule,
            'semester_name' => $semester->name,
            'academic_year' => $academicYear->name,
            'document_number' => $documentNumber,
            'issue_date' => now()->format('d/m/Y'),
            'grades' => $grades,
            'average' => $statistics['average'],
            'credits_earned' => $statistics['credits_earned'],
            'credits_total' => $statistics['credits_total'],
            'is_provisional' => $isProvisional,
            'verification_code' => $verificationCode,
        ];

        // Render HTML
        $html = $template->renderTemplate($data);

        // Generate PDF
        $pdfPath = $this->generatePdf($html, $documentNumber, $template);

        // Generate QR code
        $qrCodePath = $this->generateQrCode($verificationCode, $documentNumber);

        // Create document record
        $document = Document::create([
            'student_id' => $student->id,
            'document_type' => $isProvisional ? 'transcript_provisional' : 'transcript_semester',
            'template_id' => $template->id,
            'document_number' => $documentNumber,
            'issue_date' => now(),
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'pdf_path' => $pdfPath,
            'verification_code' => $verificationCode,
            'qr_code_path' => $qrCodePath,
            'status' => 'issued',
            'metadata' => $statistics,
            'issued_by' => auth()->id(),
        ]);

        return $document;
    }

    /**
     * Story 03: Generate global transcript (all semesters)
     */
    public function generateGlobalTranscript(
        Student $student,
        ?AcademicYear $academicYear = null
    ): Document {
        $template = DocumentTemplate::active()
            ->ofType('transcript_global')
            ->first();

        if (! $template) {
            throw new \Exception('No active global transcript template found');
        }

        // Get all student grades
        $allGrades = $this->getAllStudentGrades($student, $academicYear);
        $statistics = $this->calculateGlobalStatistics($allGrades);

        // Generate document number
        $documentNumber = $this->generateDocumentNumber('TG', $academicYear);

        // Generate verification code
        $verificationCode = $this->verificationService->generateVerificationCode();

        // Prepare template data
        $data = [
            'student_name' => $student->full_name,
            'student_matricule' => $student->matricule,
            'academic_year' => $academicYear?->name ?? 'Toutes les années',
            'document_number' => $documentNumber,
            'issue_date' => now()->format('d/m/Y'),
            'semesters' => $allGrades,
            'cumulative_gpa' => $statistics['cumulative_gpa'],
            'total_credits_earned' => $statistics['total_credits_earned'],
            'verification_code' => $verificationCode,
        ];

        // Render HTML
        $html = $template->renderTemplate($data);

        // Generate PDF
        $pdfPath = $this->generatePdf($html, $documentNumber, $template);

        // Generate QR code
        $qrCodePath = $this->generateQrCode($verificationCode, $documentNumber);

        // Create document record
        $document = Document::create([
            'student_id' => $student->id,
            'document_type' => 'transcript_global',
            'template_id' => $template->id,
            'document_number' => $documentNumber,
            'issue_date' => now(),
            'academic_year_id' => $academicYear?->id,
            'pdf_path' => $pdfPath,
            'verification_code' => $verificationCode,
            'qr_code_path' => $qrCodePath,
            'status' => 'issued',
            'metadata' => $statistics,
            'issued_by' => auth()->id(),
        ]);

        return $document;
    }

    /**
     * Story 05: Batch transcript generation
     */
    public function generateBatchTranscripts(
        array $studentIds,
        Semester $semester,
        AcademicYear $academicYear,
        bool $isProvisional = false
    ): array {
        $documents = [];

        foreach ($studentIds as $studentId) {
            $student = Student::findOrFail($studentId);

            try {
                $document = $this->generateSemesterTranscript(
                    $student,
                    $semester,
                    $academicYear,
                    $isProvisional
                );

                $documents[] = [
                    'student_id' => $studentId,
                    'document_id' => $document->id,
                    'status' => 'success',
                ];
            } catch (\Exception $e) {
                $documents[] = [
                    'student_id' => $studentId,
                    'document_id' => null,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $documents;
    }

    /**
     * Get student grades for a specific semester
     */
    private function getStudentGrades(Student $student, Semester $semester, AcademicYear $academicYear): array
    {
        // This would integrate with NotesEvaluations module
        // For now, returning mock structure
        return [
            // ['module_code' => 'MAT101', 'module_name' => 'Mathematics', 'grade' => 15.5, 'credits' => 6, 'status' => 'passed']
        ];
    }

    /**
     * Get all student grades
     */
    private function getAllStudentGrades(Student $student, ?AcademicYear $academicYear): array
    {
        // This would integrate with NotesEvaluations module
        // Grouped by semester
        return [];
    }

    /**
     * Calculate semester statistics
     */
    private function calculateSemesterStatistics(array $grades): array
    {
        if (empty($grades)) {
            return [
                'average' => 0,
                'credits_earned' => 0,
                'credits_total' => 0,
            ];
        }

        $totalGrade = 0;
        $totalCredits = 0;
        $earnedCredits = 0;

        foreach ($grades as $grade) {
            $totalGrade += $grade['grade'] * $grade['credits'];
            $totalCredits += $grade['credits'];

            if ($grade['status'] === 'passed') {
                $earnedCredits += $grade['credits'];
            }
        }

        return [
            'average' => $totalCredits > 0 ? round($totalGrade / $totalCredits, 2) : 0,
            'credits_earned' => $earnedCredits,
            'credits_total' => $totalCredits,
        ];
    }

    /**
     * Calculate global statistics
     */
    private function calculateGlobalStatistics(array $semesterGrades): array
    {
        $totalGpa = 0;
        $totalCredits = 0;
        $earnedCredits = 0;
        $semesterCount = 0;

        foreach ($semesterGrades as $semesterData) {
            $semesterStats = $this->calculateSemesterStatistics($semesterData['grades'] ?? []);
            $totalGpa += $semesterStats['average'];
            $totalCredits += $semesterStats['credits_total'];
            $earnedCredits += $semesterStats['credits_earned'];
            $semesterCount++;
        }

        return [
            'cumulative_gpa' => $semesterCount > 0 ? round($totalGpa / $semesterCount, 2) : 0,
            'total_credits_earned' => $earnedCredits,
            'total_credits_required' => $totalCredits,
        ];
    }

    /**
     * Generate document number
     */
    private function generateDocumentNumber(string $prefix, ?AcademicYear $academicYear): string
    {
        $year = $academicYear?->name ?? date('Y');
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

        $filename = "transcripts/{$documentNumber}.pdf";
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
