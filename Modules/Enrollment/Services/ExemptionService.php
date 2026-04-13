<?php

namespace Modules\Enrollment\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\ModuleExemption;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\TenantUser;

class ExemptionService
{
    /**
     * Create an exemption request
     */
    public function createExemptionRequest(
        Student $student,
        Module $module,
        AcademicYear $year,
        array $data
    ): ModuleExemption {
        // Check for existing active exemption
        $existing = ModuleExemption::where('student_id', $student->id)
            ->where('module_id', $module->id)
            ->where('academic_year_id', $year->id)
            ->whereIn('status', [
                ModuleExemption::STATUS_PENDING,
                ModuleExemption::STATUS_UNDER_REVIEW,
                ModuleExemption::STATUS_APPROVED,
                ModuleExemption::STATUS_PARTIALLY_APPROVED,
            ])
            ->first();

        if ($existing) {
            throw new \Exception('An active exemption request already exists for this module');
        }

        $exemptionNumber = ModuleExemption::generateExemptionNumber($year->id);

        $exemption = ModuleExemption::create([
            'exemption_number' => $exemptionNumber,
            'student_id' => $student->id,
            'module_id' => $module->id,
            'academic_year_id' => $year->id,
            'exemption_type' => $data['exemption_type'],
            'reason_category' => $data['reason_category'],
            'reason_details' => $data['reason_details'],
            'uploaded_documents' => $data['uploaded_documents'] ?? null,
            'status' => ModuleExemption::STATUS_PENDING,
        ]);

        return $exemption;
    }

    /**
     * Teacher review of exemption request
     */
    public function teacherReview(
        ModuleExemption $exemption,
        TenantUser $teacher,
        string $opinion
    ): ModuleExemption {
        if (! $exemption->canBeReviewed()) {
            throw new \Exception('Exemption not in pending status');
        }

        $exemption->update([
            'status' => ModuleExemption::STATUS_UNDER_REVIEW,
            'reviewed_by_teacher' => $teacher->id,
            'teacher_opinion' => $opinion,
            'teacher_reviewed_at' => now(),
        ]);

        return $exemption->fresh();
    }

    /**
     * Validate (approve/reject) exemption
     */
    public function validateExemption(
        ModuleExemption $exemption,
        TenantUser $validator,
        string $decision,
        array $options = []
    ): ModuleExemption {
        if (! $exemption->canBeValidated()) {
            throw new \Exception('Exemption cannot be validated in current status');
        }

        if (! in_array($decision, [
            ModuleExemption::STATUS_APPROVED,
            ModuleExemption::STATUS_PARTIALLY_APPROVED,
            ModuleExemption::STATUS_REJECTED,
        ])) {
            throw new \Exception('Invalid decision');
        }

        return DB::transaction(function () use ($exemption, $validator, $decision, $options) {
            $updates = [
                'status' => $decision,
                'validated_by' => $validator->id,
                'validated_at' => now(),
                'validation_notes' => $options['notes'] ?? null,
            ];

            if ($decision === ModuleExemption::STATUS_APPROVED) {
                $module = $exemption->module;

                $updates['grants_ects'] = $exemption->exemption_type === ModuleExemption::TYPE_FULL;
                $updates['ects_granted'] = $updates['grants_ects'] ? $module->credits_ects : 0;
                $updates['grade_granted'] = $options['grade'] ?? 12.0;

                // Generate certificate
                $this->generateExemptionCertificate($exemption);
            } elseif ($decision === ModuleExemption::STATUS_PARTIALLY_APPROVED) {
                $updates['grants_ects'] = false;
                $updates['ects_granted'] = 0;
                $updates['grade_granted'] = null;
            } elseif ($decision === ModuleExemption::STATUS_REJECTED) {
                $updates['rejection_reason'] = $options['rejection_reason'] ?? 'Non spécifié';
            }

            $exemption->update($updates);

            return $exemption->fresh();
        });
    }

    /**
     * Revoke an approved exemption
     */
    public function revokeExemption(
        ModuleExemption $exemption,
        TenantUser $revokedBy,
        string $reason
    ): ModuleExemption {
        if (! $exemption->canBeRevoked()) {
            throw new \Exception('Only approved exemptions can be revoked');
        }

        return DB::transaction(function () use ($exemption, $revokedBy, $reason) {
            $exemption->update([
                'status' => ModuleExemption::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $revokedBy->id,
                'revocation_reason' => $reason,
            ]);

            // Re-enroll student in module if semester is active
            $currentSemester = Semester::where('academic_year_id', $exemption->academic_year_id)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            if ($currentSemester) {
                // Check if enrollment already exists
                $existingEnrollment = StudentModuleEnrollment::where('student_id', $exemption->student_id)
                    ->where('module_id', $exemption->module_id)
                    ->whereHas('studentEnrollment', function ($q) use ($exemption) {
                        $q->where('academic_year_id', $exemption->academic_year_id);
                    })
                    ->first();

                if (! $existingEnrollment) {
                    // Find student's pedagogical enrollment for this year
                    $studentEnrollment = $exemption->student->enrollments()
                        ->where('academic_year_id', $exemption->academic_year_id)
                        ->first();

                    if ($studentEnrollment) {
                        StudentModuleEnrollment::create([
                            'student_id' => $exemption->student_id,
                            'student_enrollment_id' => $studentEnrollment->id,
                            'module_id' => $exemption->module_id,
                            'enrollment_type' => 'Normal',
                            'status' => 'Enrolled',
                        ]);
                    }
                }
            }

            return $exemption->fresh();
        });
    }

    /**
     * Get student's exemption requests
     */
    public function getStudentExemptions(int $studentId, ?int $academicYearId = null): array
    {
        $query = ModuleExemption::where('student_id', $studentId)
            ->with(['module', 'academicYear']);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query->orderByDesc('created_at')->get()->toArray();
    }

    /**
     * Get exemption statistics
     */
    public function getStatistics(?int $academicYearId = null): array
    {
        $query = ModuleExemption::query();

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        $total = $query->count();

        return [
            'total' => $total,
            'by_status' => [
                'pending' => (clone $query)->where('status', 'Pending')->count(),
                'under_review' => (clone $query)->where('status', 'Under_Review')->count(),
                'approved' => (clone $query)->where('status', 'Approved')->count(),
                'partially_approved' => (clone $query)->where('status', 'Partially_Approved')->count(),
                'rejected' => (clone $query)->where('status', 'Rejected')->count(),
                'revoked' => (clone $query)->where('status', 'Revoked')->count(),
            ],
            'by_type' => [
                'full' => (clone $query)->where('exemption_type', 'Full')->count(),
                'partial' => (clone $query)->where('exemption_type', 'Partial')->count(),
                'exemption' => (clone $query)->where('exemption_type', 'Exemption')->count(),
            ],
            'by_reason' => ModuleExemption::query()
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->selectRaw('reason_category, COUNT(*) as count')
                ->groupBy('reason_category')
                ->pluck('count', 'reason_category')
                ->toArray(),
            'acceptance_rate' => $total > 0
                ? round(((clone $query)->whereIn('status', ['Approved', 'Partially_Approved'])->count() / $total) * 100, 2)
                : 0,
            'total_ects_granted' => ModuleExemption::query()
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->where('status', 'Approved')
                ->sum('ects_granted'),
            'pending_over_15_days' => (clone $query)
                ->where('status', 'Pending')
                ->where('created_at', '<', now()->subDays(15))
                ->count(),
        ];
    }

    /**
     * Get pending exemptions requiring attention
     */
    public function getPendingExemptions(?int $academicYearId = null): array
    {
        $query = ModuleExemption::whereIn('status', [
            ModuleExemption::STATUS_PENDING,
            ModuleExemption::STATUS_UNDER_REVIEW,
        ])
            ->with(['student', 'module', 'academicYear']);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query->orderBy('created_at')->get()->toArray();
    }

    /**
     * Check if student has exemption for module
     */
    public function hasActiveExemption(int $studentId, int $moduleId, int $academicYearId): bool
    {
        return ModuleExemption::where('student_id', $studentId)
            ->where('module_id', $moduleId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('status', [
                ModuleExemption::STATUS_APPROVED,
                ModuleExemption::STATUS_PARTIALLY_APPROVED,
            ])
            ->exists();
    }

    /**
     * Generate exemption certificate PDF
     */
    private function generateExemptionCertificate(ModuleExemption $exemption): void
    {
        try {
            $pdf = Pdf::loadView('enrollment::exemptions.certificate', [
                'exemption' => $exemption->load(['student', 'module', 'academicYear', 'validator']),
            ]);

            $fileName = "attestation_dispense_{$exemption->exemption_number}.pdf";
            $path = "exemptions/{$exemption->academic_year_id}/{$fileName}";

            Storage::disk('tenant')->put($path, $pdf->output());

            $exemption->update(['certificate_path' => $path]);
        } catch (\Exception $e) {
            logger()->error('Failed to generate exemption certificate: '.$e->getMessage());
        }
    }
}
