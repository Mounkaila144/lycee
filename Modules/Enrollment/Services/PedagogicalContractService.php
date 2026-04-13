<?php

namespace Modules\Enrollment\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\PedagogicalEnrollment;
use Modules\Enrollment\Entities\StudentModuleEnrollment;

class PedagogicalContractService
{
    /**
     * Generate pedagogical contract PDF
     */
    public function generate(PedagogicalEnrollment $enrollment): string
    {
        $student = $enrollment->student;
        $program = $enrollment->program;
        $academicYear = $enrollment->academicYear;

        // Get enrolled modules with groups (via student_enrollments for academic_year filtering)
        $modules = StudentModuleEnrollment::where('student_module_enrollments.student_id', $student->id)
            ->join('student_enrollments', 'student_module_enrollments.student_enrollment_id', '=', 'student_enrollments.id')
            ->where('student_enrollments.academic_year_id', $enrollment->academic_year_id)
            ->select('student_module_enrollments.*')
            ->with(['module'])
            ->get();

        $data = [
            'enrollment' => $enrollment,
            'student' => $student,
            'program' => $program,
            'academicYear' => $academicYear,
            'modules' => $modules,
            'validator' => $enrollment->validator,
            'generatedAt' => now(),
        ];

        $pdf = Pdf::loadView('enrollment::contracts.pedagogical', $data);
        $pdf->setPaper('a4', 'portrait');

        $fileName = "contrat_pedagogique_{$student->matricule}_{$enrollment->academic_year_id}.pdf";
        $path = "contracts/pedagogical/{$enrollment->academic_year_id}/{$fileName}";

        // Store in tenant disk
        $disk = Storage::disk('tenant');
        $disk->put($path, $pdf->output());

        return $path;
    }

    /**
     * Get contract PDF path
     */
    public function getContractPath(PedagogicalEnrollment $enrollment): ?string
    {
        if (! $enrollment->contract_pdf_path) {
            return null;
        }

        $disk = Storage::disk('tenant');

        if (! $disk->exists($enrollment->contract_pdf_path)) {
            return null;
        }

        return $disk->path($enrollment->contract_pdf_path);
    }

    /**
     * Download contract
     */
    public function downloadContract(PedagogicalEnrollment $enrollment)
    {
        $path = $this->getContractPath($enrollment);

        if (! $path) {
            throw new \Exception('Contract not found');
        }

        $student = $enrollment->student;
        $fileName = "contrat_pedagogique_{$student->matricule}.pdf";

        return response()->download($path, $fileName);
    }
}
