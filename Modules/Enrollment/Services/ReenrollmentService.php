<?php

namespace Modules\Enrollment\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\PedagogicalEnrollment;
use Modules\Enrollment\Entities\Reenrollment;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\UsersGuard\Entities\User;

class ReenrollmentService
{
    /**
     * Level progression map
     */
    private const LEVEL_PROGRESSION = [
        'L1' => 'L2',
        'L2' => 'L3',
        'L3' => 'M1',
        'M1' => 'M2',
        'M2' => 'Diplômé',
    ];

    /**
     * Check student eligibility for reenrollment
     */
    public function checkEligibility(Student $student, ReenrollmentCampaign $campaign): array
    {
        $checks = [];

        // 1. Student status check
        $checks['is_active'] = $student->status === 'Actif';

        // 2. Previous enrollment check
        \Log::info('Checking eligibility', [
            'student_id' => $student->id,
            'academic_year_id' => $campaign->from_academic_year_id,
            'status_constant' => PedagogicalEnrollment::STATUS_VALIDATED,
            'status_hex' => bin2hex(PedagogicalEnrollment::STATUS_VALIDATED),
        ]);

        $previousEnrollment = PedagogicalEnrollment::on('tenant')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $campaign->from_academic_year_id)
            ->where('status', PedagogicalEnrollment::STATUS_VALIDATED)
            ->first();

        \Log::info('Enrollment found', ['enrollment' => $previousEnrollment ? $previousEnrollment->toArray() : null]);

        $checks['has_previous_enrollment'] = $previousEnrollment !== null;
        $checks['previous_enrollment'] = $previousEnrollment;

        // 3. ECTS credits check
        if ($previousEnrollment) {
            $validatedEcts = $this->calculateValidatedEcts($student->id, $campaign->from_academic_year_id);
            $checks['has_min_ects'] = $validatedEcts >= $campaign->min_ects_required;
            $checks['validated_ects'] = $validatedEcts;
            $checks['required_ects'] = $campaign->min_ects_required;
        } else {
            $checks['has_min_ects'] = false;
            $checks['validated_ects'] = 0;
            $checks['required_ects'] = $campaign->min_ects_required;
        }

        // 4. Financial clearance check (if required)
        if ($campaign->check_financial_clearance) {
            $checks['financial_clearance'] = $this->checkFinancialClearance($student->id);
        } else {
            $checks['financial_clearance'] = true;
        }

        // 5. No disciplinary exclusion
        $checks['no_disciplinary_exclusion'] = $student->status !== 'Exclu';

        // 6. Program eligibility
        if ($previousEnrollment) {
            $checks['program_eligible'] = $campaign->isProgramEligible($previousEnrollment->programme_id);
            $checks['level_eligible'] = $campaign->isLevelEligible($previousEnrollment->level);
        } else {
            $checks['program_eligible'] = false;
            $checks['level_eligible'] = false;
        }

        // Calculate overall eligibility
        $checks['is_eligible'] = collect($checks)
            ->except(['validated_ects', 'required_ects', 'previous_enrollment'])
            ->every(fn ($v) => $v === true);

        return $checks;
    }

    /**
     * Get list of eligible students for a campaign
     */
    public function getEligibleStudents(ReenrollmentCampaign $campaign): array
    {
        $students = Student::on('tenant')
            ->where('status', 'Actif')
            ->whereHas('enrollments', function ($q) use ($campaign) {
                $q->where('academic_year_id', $campaign->from_academic_year_id)
                    ->where('status', PedagogicalEnrollment::STATUS_VALIDATED);
            })
            ->get();

        $eligible = [];
        $notEligible = [];

        foreach ($students as $student) {
            $eligibility = $this->checkEligibility($student, $campaign);

            if ($eligibility['is_eligible']) {
                $eligible[] = [
                    'student' => $student,
                    'eligibility' => $eligibility,
                ];
            } else {
                $notEligible[] = [
                    'student' => $student,
                    'eligibility' => $eligibility,
                ];
            }
        }

        return [
            'eligible' => $eligible,
            'not_eligible' => $notEligible,
            'total_eligible' => count($eligible),
            'total_not_eligible' => count($notEligible),
        ];
    }

    /**
     * Create a reenrollment request
     */
    public function createReenrollment(Student $student, ReenrollmentCampaign $campaign, array $data): Reenrollment
    {
        // Check if already exists
        $existing = Reenrollment::on('tenant')
            ->where('campaign_id', $campaign->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            throw new \Exception('Reenrollment request already exists for this campaign');
        }

        // Check eligibility
        $eligibility = $this->checkEligibility($student, $campaign);

        $previousEnrollment = $eligibility['previous_enrollment'];

        if (! $previousEnrollment) {
            throw new \Exception('No previous enrollment found for this academic year');
        }

        $targetLevel = $this->calculateTargetLevel(
            $previousEnrollment->level,
            $data['is_redoing'] ?? false
        );

        $isReorientation = isset($data['target_program_id'])
            && $data['target_program_id'] != $previousEnrollment->programme_id;

        $reenrollment = Reenrollment::on('tenant')->create([
            'campaign_id' => $campaign->id,
            'student_id' => $student->id,
            'previous_enrollment_id' => $previousEnrollment->id,
            'previous_level' => $previousEnrollment->level,
            'target_level' => $targetLevel,
            'target_program_id' => $data['target_program_id'] ?? $previousEnrollment->programme_id,
            'is_redoing' => $data['is_redoing'] ?? false,
            'is_reorientation' => $isReorientation,
            'personal_data_updates' => $data['personal_data_updates'] ?? null,
            'uploaded_documents' => $data['uploaded_documents'] ?? null,
            'has_accepted_rules' => $data['has_accepted_rules'] ?? false,
            'eligibility_status' => $eligibility['is_eligible']
                ? Reenrollment::ELIGIBILITY_ELIGIBLE
                : Reenrollment::ELIGIBILITY_NOT_ELIGIBLE,
            'eligibility_notes' => $this->formatEligibilityNotes($eligibility),
            'status' => Reenrollment::STATUS_DRAFT,
        ]);

        return $reenrollment;
    }

    /**
     * Update a reenrollment request
     */
    public function updateReenrollment(Reenrollment $reenrollment, array $data): Reenrollment
    {
        if (! $reenrollment->isDraft()) {
            throw new \Exception('Only draft reenrollments can be updated');
        }

        $updates = [];

        if (isset($data['target_program_id'])) {
            $updates['target_program_id'] = $data['target_program_id'];
            $updates['is_reorientation'] = $data['target_program_id'] != $reenrollment->previousEnrollment->programme_id;
        }

        if (isset($data['is_redoing'])) {
            $updates['is_redoing'] = $data['is_redoing'];
            $updates['target_level'] = $this->calculateTargetLevel(
                $reenrollment->previous_level,
                $data['is_redoing']
            );
        }

        if (isset($data['personal_data_updates'])) {
            $updates['personal_data_updates'] = $data['personal_data_updates'];
        }

        if (isset($data['uploaded_documents'])) {
            $currentDocs = $reenrollment->uploaded_documents ?? [];
            $updates['uploaded_documents'] = array_merge($currentDocs, $data['uploaded_documents']);
        }

        if (isset($data['has_accepted_rules'])) {
            $updates['has_accepted_rules'] = $data['has_accepted_rules'];
        }

        $reenrollment->update($updates);

        return $reenrollment->fresh();
    }

    /**
     * Submit a reenrollment request
     */
    public function submitReenrollment(Reenrollment $reenrollment): Reenrollment
    {
        if (! $reenrollment->canBeSubmitted()) {
            throw new \Exception('Reenrollment cannot be submitted: check status, eligibility, and rules acceptance');
        }

        $reenrollment->update([
            'status' => Reenrollment::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        // Generate confirmation PDF
        $this->generateConfirmationPdf($reenrollment);

        return $reenrollment->fresh();
    }

    /**
     * Validate a reenrollment request (admin)
     */
    public function validateReenrollment(Reenrollment $reenrollment, User $validator): Reenrollment
    {
        if (! $reenrollment->canBeValidated()) {
            throw new \Exception('Reenrollment cannot be validated in current status');
        }

        return DB::connection('tenant')->transaction(function () use ($reenrollment, $validator) {
            // 1. Update reenrollment status
            $reenrollment->update([
                'status' => Reenrollment::STATUS_VALIDATED,
                'validated_by' => $validator->id,
                'validated_at' => now(),
            ]);

            // 2. Update student level and program if needed
            $studentUpdates = ['level' => $reenrollment->target_level];

            if ($reenrollment->is_reorientation) {
                $studentUpdates['program_id'] = $reenrollment->target_program_id;
            }

            // Apply personal data updates if any
            if ($reenrollment->personal_data_updates) {
                $allowedFields = ['email', 'phone', 'mobile', 'address', 'city', 'country'];
                foreach ($reenrollment->personal_data_updates as $field => $value) {
                    if (in_array($field, $allowedFields)) {
                        $studentUpdates[$field] = $value;
                    }
                }
            }

            $reenrollment->student->update($studentUpdates);

            // 3. Create new pedagogical enrollment
            $newEnrollment = PedagogicalEnrollment::on('tenant')->create([
                'student_id' => $reenrollment->student_id,
                'program_id' => $reenrollment->target_program_id,
                'level' => $reenrollment->target_level,
                'academic_year_id' => $reenrollment->campaign->to_academic_year_id,
                'status' => PedagogicalEnrollment::STATUS_DRAFT,
            ]);

            // 4. If redoing: copy failed modules
            if ($reenrollment->is_redoing) {
                $this->copyFailedModules($reenrollment, $newEnrollment);
            }

            // 5. Link new enrollment
            $reenrollment->update(['new_enrollment_id' => $newEnrollment->id]);

            return $reenrollment->fresh();
        });
    }

    /**
     * Reject a reenrollment request
     */
    public function rejectReenrollment(Reenrollment $reenrollment, User $validator, string $reason): Reenrollment
    {
        if (! $reenrollment->canBeRejected()) {
            throw new \Exception('Reenrollment cannot be rejected in current status');
        }

        $reenrollment->update([
            'status' => Reenrollment::STATUS_REJECTED,
            'validated_by' => $validator->id,
            'validated_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $reenrollment->fresh();
    }

    /**
     * Get reenrollment statistics for a campaign
     */
    public function getCampaignStatistics(ReenrollmentCampaign $campaign): array
    {
        $reenrollments = Reenrollment::on('tenant')->where('campaign_id', $campaign->id);

        $stats = [
            'total' => $reenrollments->count(),
            'by_status' => [
                'draft' => (clone $reenrollments)->where('status', 'Draft')->count(),
                'submitted' => (clone $reenrollments)->where('status', 'Submitted')->count(),
                'validated' => (clone $reenrollments)->where('status', 'Validated')->count(),
                'rejected' => (clone $reenrollments)->where('status', 'Rejected')->count(),
            ],
            'by_eligibility' => [
                'eligible' => (clone $reenrollments)->where('eligibility_status', 'Eligible')->count(),
                'not_eligible' => (clone $reenrollments)->where('eligibility_status', 'Not_Eligible')->count(),
                'pending' => (clone $reenrollments)->where('eligibility_status', 'Pending')->count(),
            ],
            'special_cases' => [
                'redoing' => (clone $reenrollments)->where('is_redoing', true)->count(),
                'reorientation' => (clone $reenrollments)->where('is_reorientation', true)->count(),
            ],
            'by_target_level' => Reenrollment::on('tenant')->where('campaign_id', $campaign->id)
                ->selectRaw('target_level, COUNT(*) as count')
                ->groupBy('target_level')
                ->pluck('count', 'target_level')
                ->toArray(),
        ];

        $stats['validation_rate'] = $stats['total'] > 0
            ? round(($stats['by_status']['validated'] / $stats['total']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Calculate target level based on current level and redoing status
     */
    private function calculateTargetLevel(string $currentLevel, bool $isRedoing): string
    {
        if ($isRedoing) {
            return $currentLevel;
        }

        return self::LEVEL_PROGRESSION[$currentLevel] ?? $currentLevel;
    }

    /**
     * Calculate validated ECTS for a student in an academic year
     */
    private function calculateValidatedEcts(int $studentId, int $academicYearId): int
    {
        // This would typically query grades/results table
        // For now, we'll use module enrollments with validated status
        return StudentModuleEnrollment::on('tenant')
            ->where('student_id', $studentId)
            ->whereHas('studentEnrollment', function ($q) use ($academicYearId) {
                $q->where('academic_year_id', $academicYearId);
            })
            ->where('status', 'Validated')
            ->join('modules', 'student_module_enrollments.module_id', '=', 'modules.id')
            ->sum('modules.credits_ects');
    }

    /**
     * Check financial clearance for a student
     */
    private function checkFinancialClearance(int $studentId): bool
    {
        // This would check against Finance module
        // For now, return true (no blocking debt)
        return true;
    }

    /**
     * Copy failed modules to new enrollment
     */
    private function copyFailedModules(Reenrollment $reenrollment, PedagogicalEnrollment $newEnrollment): void
    {
        $failedModules = StudentModuleEnrollment::on('tenant')
            ->where('student_id', $reenrollment->student_id)
            ->whereHas('studentEnrollment', function ($q) use ($reenrollment) {
                $q->where('academic_year_id', $reenrollment->campaign->from_academic_year_id);
            })
            ->where('status', 'Failed')
            ->pluck('module_id');

        foreach ($failedModules as $moduleId) {
            StudentModuleEnrollment::on('tenant')->create([
                'student_id' => $reenrollment->student_id,
                'student_enrollment_id' => $newEnrollment->id,
                'module_id' => $moduleId,
                'enrollment_type' => 'Redo',
                'status' => 'Enrolled',
            ]);
        }
    }

    /**
     * Format eligibility notes from checks
     */
    private function formatEligibilityNotes(array $eligibility): string
    {
        $notes = [];

        if (! $eligibility['is_active']) {
            $notes[] = 'Étudiant non actif';
        }

        if (! $eligibility['has_previous_enrollment']) {
            $notes[] = 'Aucune inscription précédente trouvée';
        }

        if (! $eligibility['has_min_ects']) {
            $notes[] = sprintf(
                'ECTS insuffisants: %d/%d',
                $eligibility['validated_ects'],
                $eligibility['required_ects']
            );
        }

        if (! $eligibility['financial_clearance']) {
            $notes[] = 'Apurement financier non validé';
        }

        if (! $eligibility['no_disciplinary_exclusion']) {
            $notes[] = 'Étudiant exclu';
        }

        if (! $eligibility['program_eligible']) {
            $notes[] = 'Programme non éligible pour cette campagne';
        }

        if (! $eligibility['level_eligible']) {
            $notes[] = 'Niveau non éligible pour cette campagne';
        }

        return empty($notes) ? 'Éligible' : implode('; ', $notes);
    }

    /**
     * Generate confirmation PDF
     */
    private function generateConfirmationPdf(Reenrollment $reenrollment): void
    {
        try {
            $pdf = Pdf::loadView('enrollment::reenrollments.confirmation', [
                'reenrollment' => $reenrollment->load(['student', 'campaign', 'targetProgram']),
            ]);

            $fileName = sprintf(
                'confirmation_reinscription_%s_%d.pdf',
                $reenrollment->student->matricule,
                $reenrollment->campaign_id
            );

            $path = "reenrollments/{$reenrollment->campaign_id}/{$fileName}";

            Storage::disk('tenant')->put($path, $pdf->output());

            $reenrollment->update(['confirmation_pdf_path' => $path]);
        } catch (\Exception $e) {
            // Log error but don't fail the submission
            logger()->error('Failed to generate reenrollment confirmation PDF: '.$e->getMessage());
        }
    }
}
