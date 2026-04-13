<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\NotesEvaluations\Entities\GradeConfig;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\NotesEvaluations\Events\SemesterResultsPublished;
use Modules\NotesEvaluations\Jobs\RecalculateSemesterAveragesJob;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleSemesterAssignment;

class SemesterAverageService
{
    public function __construct(
        protected ?EliminatoryRulesService $eliminatoryService = null
    ) {
        $this->eliminatoryService = $eliminatoryService ?? app(EliminatoryRulesService::class);
    }

    /**
     * Calculate semester average for a student
     */
    public function calculate(int $studentId, int $semesterId, bool $finalOnly = false): ?float
    {
        // Get all modules for this semester
        $moduleIds = ModuleSemesterAssignment::where('semester_id', $semesterId)
            ->where('is_active', true)
            ->pluck('module_id')
            ->unique();

        $modules = Module::whereIn('id', $moduleIds)->get();

        if ($modules->isEmpty()) {
            return null;
        }

        $totalWeightedScore = 0;
        $totalCredits = 0;
        $missingCount = 0;
        $allAbsent = true;

        foreach ($modules as $module) {
            $moduleGrade = ModuleGrade::where([
                'student_id' => $studentId,
                'module_id' => $module->id,
                'semester_id' => $semesterId,
            ])->first();

            // Module without grade (unpublished)
            if (! $moduleGrade || $moduleGrade->average === null) {
                $missingCount++;

                continue;
            }

            // Module ABS (student absent from all evaluations)
            if ($moduleGrade->average === null) {
                continue; // Exclude from calculation
            }

            $totalWeightedScore += $moduleGrade->average * $module->credits_ects;
            $totalCredits += $module->credits_ects;
            $allAbsent = false;
        }

        // If all modules absent
        if ($allAbsent) {
            $this->storeResult($studentId, $semesterId, null, true, $missingCount, $modules->sum('credits_ects'));

            return null;
        }

        // If provisional average and finalOnly=true
        if ($finalOnly && $missingCount > 0) {
            return null;
        }

        // Final calculation
        $average = $totalCredits > 0
            ? round($totalWeightedScore / $totalCredits, 2)
            : null;

        // Store result
        $this->storeResult($studentId, $semesterId, $average, $missingCount === 0, $missingCount, $modules->sum('credits_ects'));

        // Cache
        $this->cacheResult($studentId, $semesterId, $average);

        return $average;
    }

    /**
     * Calculate semester average with eliminatory checks
     */
    public function calculateWithEliminatories(int $studentId, int $semesterId): void
    {
        // Calculate normal average
        $average = $this->calculate($studentId, $semesterId);

        // Check eliminatory rules
        $validation = $this->eliminatoryService->canValidateSemester($studentId, $semesterId);

        // Update semester result
        SemesterResult::updateOrCreate(
            [
                'student_id' => $studentId,
                'semester_id' => $semesterId,
            ],
            [
                'is_validated' => $validation['can_validate'],
                'validation_blocked_by_eliminatory' => ! $validation['can_validate'] && $validation['failed_eliminatory_count'] > 0,
                'blocking_reasons' => $validation['reasons'],
            ]
        );
    }

    /**
     * Store calculation result in database
     */
    protected function storeResult(
        int $studentId,
        int $semesterId,
        ?float $average,
        bool $isFinal,
        int $missingCount,
        int $totalCredits
    ): SemesterResult {
        $config = GradeConfig::getConfig();
        $isValidated = $average !== null && $average >= $config->min_semester_average;

        // Count modules by status
        $moduleCounts = $this->countModulesByStatus($studentId, $semesterId);

        // Calculate acquired credits
        $acquiredCredits = $this->calculateAcquiredCredits($studentId, $semesterId);

        // Determine global status
        $globalStatus = $this->determineGlobalStatus(
            $isValidated && $isFinal,
            $moduleCounts['validated'],
            $moduleCounts['compensated'],
            $moduleCounts['failed'],
            $moduleCounts['total']
        );

        // Calculate success rate
        $successRate = $totalCredits > 0
            ? round(($acquiredCredits / $totalCredits) * 100, 2)
            : 0;

        return SemesterResult::updateOrCreate(
            [
                'student_id' => $studentId,
                'semester_id' => $semesterId,
            ],
            [
                'average' => $average,
                'is_final' => $isFinal,
                'is_validated' => $isValidated && $isFinal,
                'global_status' => $globalStatus,
                'validated_modules_count' => $moduleCounts['validated'],
                'compensated_modules_count' => $moduleCounts['compensated'],
                'failed_modules_count' => $moduleCounts['failed'],
                'total_credits' => $totalCredits,
                'acquired_credits' => $acquiredCredits,
                'missing_credits' => $totalCredits - $acquiredCredits,
                'success_rate' => $successRate,
                'missing_modules_count' => $missingCount,
                'calculated_at' => now(),
            ]
        );
    }

    /**
     * Count modules by status for a student in a semester
     *
     * @return array{validated: int, compensated: int, failed: int, total: int}
     */
    public function countModulesByStatus(int $studentId, int $semesterId): array
    {
        $config = GradeConfig::getConfig();
        $validationThreshold = $config->min_module_average ?? 10.00;

        $moduleGrades = ModuleGrade::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereNotNull('average')
            ->get();

        $validated = $moduleGrades->filter(fn ($mg) => $mg->average >= $validationThreshold)->count();
        $compensated = $moduleGrades->where('status', 'Compensated')->count();
        $failed = $moduleGrades->filter(
            fn ($mg) => $mg->average < $validationThreshold && $mg->status !== 'Compensated'
        )->count();

        return [
            'validated' => $validated,
            'compensated' => $compensated,
            'failed' => $failed,
            'total' => $moduleGrades->count(),
        ];
    }

    /**
     * Calculate acquired credits for a student in a semester
     * (validated + compensated modules)
     */
    public function calculateAcquiredCredits(int $studentId, int $semesterId): int
    {
        $config = GradeConfig::getConfig();
        $validationThreshold = $config->min_module_average ?? 10.00;

        return ModuleGrade::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereNotNull('average')
            ->where(function ($query) use ($validationThreshold) {
                $query->where('average', '>=', $validationThreshold)
                    ->orWhere('status', 'Compensated');
            })
            ->with('module')
            ->get()
            ->sum(fn ($mg) => $mg->module->credits_ects ?? 0);
    }

    /**
     * Determine global status based on module results
     *
     * @return string validated|partially_validated|to_retake|deferred
     */
    public function determineGlobalStatus(
        bool $isValidated,
        int $validatedCount,
        int $compensatedCount,
        int $failedCount,
        int $totalCount
    ): string {
        // If no modules at all
        if ($totalCount === 0) {
            return 'deferred';
        }

        // All modules validated (or compensated)
        $acquiredCount = $validatedCount + $compensatedCount;
        if ($acquiredCount === $totalCount && $isValidated) {
            return 'validated';
        }

        // Some modules failed
        if ($failedCount > 0) {
            // Check if semester average allows pass
            if ($isValidated && $acquiredCount >= ($totalCount - $failedCount)) {
                return 'partially_validated';
            }

            return 'to_retake';
        }

        // Partial validation (some compensated but average OK)
        if ($compensatedCount > 0 && $isValidated) {
            return 'partially_validated';
        }

        return 'deferred';
    }

    /**
     * Cache the result
     */
    protected function cacheResult(int $studentId, int $semesterId, ?float $average): void
    {
        $key = "semester_avg:{$studentId}:{$semesterId}";

        Cache::put($key, $average, now()->addHours(24));
    }

    /**
     * Get cached result or calculate
     */
    public function getCached(int $studentId, int $semesterId): ?float
    {
        $key = "semester_avg:{$studentId}:{$semesterId}";

        return Cache::remember(
            $key,
            now()->addHours(24),
            fn () => $this->calculate($studentId, $semesterId)
        );
    }

    /**
     * Invalidate cache
     */
    public function invalidateCache(int $studentId, int $semesterId): void
    {
        $key = "semester_avg:{$studentId}:{$semesterId}";
        Cache::forget($key);
    }

    /**
     * Recalculate averages for all students in a semester
     */
    public function recalculateForSemester(int $semesterId): void
    {
        $studentIds = StudentEnrollment::whereHas('semester', function ($q) use ($semesterId) {
            $q->where('id', $semesterId);
        })->pluck('student_id')->unique();

        // If >100 students, use async job
        if ($studentIds->count() > 100) {
            RecalculateSemesterAveragesJob::dispatch($semesterId, $studentIds->toArray());
        } else {
            foreach ($studentIds as $studentId) {
                $this->calculateWithEliminatories($studentId, $semesterId);
            }

            // Compute rankings after all averages calculated
            $this->computeRankings($semesterId);
        }
    }

    /**
     * Compute rankings for all students in a semester
     * Handles ex-aequo (same rank for same average)
     */
    public function computeRankings(int $semesterId): void
    {
        // Get all results with averages, ordered by average DESC
        $results = SemesterResult::where('semester_id', $semesterId)
            ->whereNotNull('average')
            ->orderBy('average', 'desc')
            ->get();

        $totalRanked = $results->count();

        if ($totalRanked === 0) {
            return;
        }

        $currentRank = 0;
        $previousAverage = null;
        $sameRankCount = 0;

        DB::beginTransaction();
        try {
            foreach ($results as $result) {
                if ($previousAverage !== $result->average) {
                    $currentRank += $sameRankCount + 1;
                    $sameRankCount = 0;
                } else {
                    // Same average as previous = same rank (ex-aequo)
                    $sameRankCount++;
                }

                $result->update([
                    'rank' => $currentRank,
                    'total_ranked' => $totalRanked,
                ]);

                $previousAverage = $result->average;
            }

            DB::commit();

            Log::info('Semester rankings computed', [
                'semester_id' => $semesterId,
                'total_ranked' => $totalRanked,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to compute semester rankings', [
                'semester_id' => $semesterId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get students by global status for a semester
     */
    public function getStudentsByStatus(int $semesterId, string $status): Collection
    {
        return SemesterResult::where('semester_id', $semesterId)
            ->where('global_status', $status)
            ->with(['student', 'semester'])
            ->orderBy('average', 'desc')
            ->get();
    }

    /**
     * Get progression status - can student progress to next year
     */
    public function updateProgressionStatus(int $semesterId): int
    {
        $config = GradeConfig::getConfig();
        $minCreditsForProgression = $config->min_credits_for_progression ?? 0;

        return SemesterResult::where('semester_id', $semesterId)
            ->where(function ($query) use ($minCreditsForProgression) {
                $query->where('acquired_credits', '>=', $minCreditsForProgression)
                    ->orWhereIn('global_status', ['validated', 'partially_validated']);
            })
            ->update(['can_progress_next_year' => true]);
    }

    /**
     * Publish semester results
     */
    public function publishResults(int $semesterId): int
    {
        $count = SemesterResult::where('semester_id', $semesterId)
            ->where('is_final', true)
            ->whereNull('published_at')
            ->update(['published_at' => now()]);

        // Invalidate cache
        Cache::flush();

        // Fire event for notifications
        event(new SemesterResultsPublished($semesterId));

        Log::info('Semester results published', [
            'semester_id' => $semesterId,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Get semester results for a student
     */
    public function getStudentSemesterResults(int $studentId): array
    {
        return SemesterResult::where('student_id', $studentId)
            ->with(['semester.academicYear'])
            ->orderBy('semester_id', 'desc')
            ->get()
            ->map(function ($result) {
                return [
                    'semester_id' => $result->semester_id,
                    'semester_name' => $result->semester->name,
                    'academic_year' => $result->semester->academicYear->name ?? null,
                    'average' => $result->average,
                    'status' => $result->status,
                    'is_final' => $result->is_final,
                    'is_validated' => $result->is_validated,
                    'is_published' => $result->is_published,
                    'total_credits' => $result->total_credits,
                    'acquired_credits' => $result->acquired_credits,
                    'success_rate' => $result->success_rate,
                    'blocking_reasons' => $result->blocking_reasons,
                ];
            })
            ->toArray();
    }

    /**
     * Get statistics for a semester
     */
    public function getSemesterStatistics(int $semesterId): array
    {
        $results = SemesterResult::where('semester_id', $semesterId)
            ->whereNotNull('average')
            ->get();

        if ($results->isEmpty()) {
            return [
                'count' => 0,
                'average' => null,
                'min' => null,
                'max' => null,
                'median' => null,
                'validated_count' => 0,
                'validation_rate' => 0,
                'blocked_by_eliminatory_count' => 0,
                'status_distribution' => [
                    'validated' => 0,
                    'partially_validated' => 0,
                    'to_retake' => 0,
                    'deferred' => 0,
                ],
                'credits_statistics' => [
                    'total_credits' => 0,
                    'average_acquired_credits' => 0,
                    'average_success_rate' => 0,
                ],
                'progression' => [
                    'can_progress_count' => 0,
                    'cannot_progress_count' => 0,
                ],
            ];
        }

        $averages = $results->pluck('average')->sort()->values();
        $validatedCount = $results->where('is_validated', true)->count();
        $blockedCount = $results->where('validation_blocked_by_eliminatory', true)->count();

        // Calculate median
        $count = $averages->count();
        $median = $count % 2 === 0
            ? ($averages[$count / 2 - 1] + $averages[$count / 2]) / 2
            : $averages[floor($count / 2)];

        // Status distribution
        $statusDistribution = [
            'validated' => $results->where('global_status', 'validated')->count(),
            'partially_validated' => $results->where('global_status', 'partially_validated')->count(),
            'to_retake' => $results->where('global_status', 'to_retake')->count(),
            'deferred' => $results->where('global_status', 'deferred')->count(),
        ];

        // Credits statistics
        $totalCredits = $results->sum('total_credits');
        $avgAcquired = $results->avg('acquired_credits');
        $avgSuccessRate = $results->avg('success_rate');

        // Progression statistics
        $canProgressCount = $results->where('can_progress_next_year', true)->count();

        return [
            'count' => $results->count(),
            'average' => round($averages->avg(), 2),
            'min' => round($averages->min(), 2),
            'max' => round($averages->max(), 2),
            'median' => round($median, 2),
            'validated_count' => $validatedCount,
            'validation_rate' => round(($validatedCount / $results->count()) * 100, 2),
            'blocked_by_eliminatory_count' => $blockedCount,
            'status_distribution' => $statusDistribution,
            'credits_statistics' => [
                'total_credits' => $totalCredits,
                'average_acquired_credits' => round($avgAcquired, 2),
                'average_success_rate' => round($avgSuccessRate, 2),
            ],
            'progression' => [
                'can_progress_count' => $canProgressCount,
                'cannot_progress_count' => $results->count() - $canProgressCount,
            ],
            'module_statistics' => [
                'average_validated_modules' => round($results->avg('validated_modules_count'), 2),
                'average_compensated_modules' => round($results->avg('compensated_modules_count'), 2),
                'average_failed_modules' => round($results->avg('failed_modules_count'), 2),
            ],
        ];
    }
}
