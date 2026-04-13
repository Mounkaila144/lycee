<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\GradeConfig;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\RetakeEnrollment;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\NotesEvaluations\Notifications\RetakeModulesNotification;
use Modules\StructureAcademique\Entities\Module;

class RetakeIdentificationService
{
    /**
     * Identify all retakes for a semester
     */
    public function identify(int $semesterId, bool $sendNotifications = true): array
    {
        $config = GradeConfig::getConfig();
        $threshold = $config->min_module_average ?? 10.00;

        // Get students with global_status 'to_retake' or failed modules
        $semesterResults = SemesterResult::where('semester_id', $semesterId)
            ->where('is_final', true)
            ->whereNotNull('published_at')
            ->where(function ($q) {
                $q->where('global_status', 'to_retake')
                    ->orWhere('failed_modules_count', '>', 0);
            })
            ->get();

        $studentIds = $semesterResults->pluck('student_id')->unique();

        $totalRetakes = 0;
        $studentsImpacted = 0;
        $modulesAffected = collect();

        DB::beginTransaction();
        try {
            foreach ($studentIds as $studentId) {
                $retakeModules = $this->identifyForStudent($studentId, $semesterId, $threshold, $config);

                if ($retakeModules->isNotEmpty()) {
                    $studentsImpacted++;
                    $totalRetakes += $retakeModules->count();
                    $modulesAffected = $modulesAffected->merge($retakeModules->pluck('module_id'));

                    // Send notification to student
                    if ($sendNotifications) {
                        $student = Student::find($studentId);
                        if ($student) {
                            $student->notify(new RetakeModulesNotification($retakeModules));
                        }
                    }
                }
            }

            DB::commit();

            // Invalidate statistics cache
            $this->invalidateStatisticsCache($semesterId);

            return [
                'students_impacted' => $studentsImpacted,
                'total_retakes' => $totalRetakes,
                'unique_modules_affected' => $modulesAffected->unique()->count(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Identify retake modules for a specific student
     */
    public function identifyForStudent(int $studentId, int $semesterId, ?float $threshold = null, ?GradeConfig $config = null): Collection
    {
        $config = $config ?? GradeConfig::getConfig();
        $threshold = $threshold ?? ($config->min_module_average ?? 10.00);

        // Get all module grades for student in semester that are not validated
        $moduleGrades = ModuleGrade::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('is_final', true)
            ->where(function ($q) use ($threshold) {
                $q->where('average', '<', $threshold)
                    ->orWhereNull('average');
            })
            ->whereNot('status', 'Compensated')
            ->with(['module'])
            ->get();

        $retakeEnrollments = collect();

        foreach ($moduleGrades as $moduleGrade) {
            // Check if module can be compensated
            if ($this->isModuleCompensated($studentId, $moduleGrade->module_id, $semesterId)) {
                continue;
            }

            // Check if eliminatory module
            $module = $moduleGrade->module;
            $isEliminatory = $module && $module->is_eliminatory;

            // Eliminatory modules must always be retaken if failed
            // Non-eliminatory modules can be compensated
            if (! $isEliminatory && $this->canBeCompensatedBySemesterAverage($studentId, $semesterId, $config)) {
                continue;
            }

            // Create retake enrollment
            $retakeEnrollment = RetakeEnrollment::firstOrCreate(
                [
                    'student_id' => $studentId,
                    'module_id' => $moduleGrade->module_id,
                    'semester_id' => $semesterId,
                ],
                [
                    'original_average' => $moduleGrade->average,
                    'status' => 'pending',
                    'identified_at' => now(),
                ]
            );

            $retakeEnrollments->push($retakeEnrollment);
        }

        return $retakeEnrollments;
    }

    /**
     * Check if a module is already compensated
     */
    protected function isModuleCompensated(int $studentId, int $moduleId, int $semesterId): bool
    {
        $moduleGrade = ModuleGrade::where([
            'student_id' => $studentId,
            'module_id' => $moduleId,
            'semester_id' => $semesterId,
        ])->first();

        return $moduleGrade && $moduleGrade->status === 'Compensated';
    }

    /**
     * Check if student's semester average allows compensation
     */
    protected function canBeCompensatedBySemesterAverage(int $studentId, int $semesterId, GradeConfig $config): bool
    {
        if (! $config->compensation_enabled) {
            return false;
        }

        $semesterResult = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->first();

        if (! $semesterResult) {
            return false;
        }

        // If semester is validated, modules can be compensated
        return $semesterResult->is_validated && $semesterResult->average >= ($config->min_semester_average ?? 10.00);
    }

    /**
     * Get statistics for a semester's retakes
     */
    public function getStatistics(int $semesterId): array
    {
        $cacheKey = "retake_stats:{$semesterId}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($semesterId) {
            $retakes = RetakeEnrollment::where('semester_id', $semesterId)
                ->active()
                ->with(['student', 'module'])
                ->get();

            $studentsWithRetakes = $retakes->pluck('student_id')->unique();

            // Distribution: number of modules per student
            $distribution = $studentsWithRetakes->mapWithKeys(function ($studentId) use ($retakes) {
                return [$studentId => $retakes->where('student_id', $studentId)->count()];
            })->groupBy(function ($count) {
                if ($count === 1) {
                    return '1_module';
                }
                if ($count === 2) {
                    return '2_modules';
                }

                return '3_plus_modules';
            })->map->count();

            // Most failed modules (sorted by student count)
            $moduleStats = $retakes->groupBy('module_id')
                ->map(function ($group) {
                    $module = $group->first()->module;

                    return [
                        'module_id' => $group->first()->module_id,
                        'module_code' => $module?->code,
                        'module_name' => $module?->name,
                        'student_count' => $group->count(),
                        'average_original_grade' => round($group->whereNotNull('original_average')->avg('original_average'), 2),
                    ];
                })
                ->sortByDesc('student_count')
                ->values();

            $totalStudents = SemesterResult::where('semester_id', $semesterId)
                ->where('is_final', true)
                ->count();

            return [
                'total_students' => $totalStudents,
                'students_with_retakes' => $studentsWithRetakes->count(),
                'retake_rate' => $totalStudents > 0 ? round(($studentsWithRetakes->count() / $totalStudents) * 100, 2) : 0,
                'total_retakes' => $retakes->count(),
                'distribution' => [
                    '1_module' => $distribution->get('1_module', 0),
                    '2_modules' => $distribution->get('2_modules', 0),
                    '3_plus_modules' => $distribution->get('3_plus_modules', 0),
                ],
                'most_failed_modules' => $moduleStats->take(10)->toArray(),
            ];
        });
    }

    /**
     * Get students with retakes for a specific module
     */
    public function getStudentsByModule(int $moduleId, int $semesterId): Collection
    {
        return RetakeEnrollment::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->active()
            ->with(['student', 'module'])
            ->orderBy('original_average')
            ->get();
    }

    /**
     * Get retake modules for a specific student
     */
    public function getModulesByStudent(int $studentId, ?int $semesterId = null): Collection
    {
        $query = RetakeEnrollment::where('student_id', $studentId)
            ->active()
            ->with(['module', 'semester']);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Get all students with retakes in a semester
     */
    public function getStudentsWithRetakes(int $semesterId, ?string $status = null): Collection
    {
        $query = RetakeEnrollment::where('semester_id', $semesterId)
            ->with(['student', 'module']);

        if ($status) {
            $query->where('status', $status);
        } else {
            $query->active();
        }

        // Group by student
        return $query->get()
            ->groupBy('student_id')
            ->map(function ($retakes) {
                $student = $retakes->first()->student;

                return [
                    'student_id' => $student->id,
                    'matricule' => $student->matricule,
                    'firstname' => $student->firstname,
                    'lastname' => $student->lastname,
                    'full_name' => $student->firstname.' '.$student->lastname,
                    'retakes_count' => $retakes->count(),
                    'modules' => $retakes->map(function ($retake) {
                        return [
                            'retake_id' => $retake->id,
                            'module_id' => $retake->module_id,
                            'module_code' => $retake->module?->code,
                            'module_name' => $retake->module?->name,
                            'original_average' => $retake->original_average,
                            'status' => $retake->status,
                            'status_label' => $retake->status_label,
                        ];
                    })->values()->toArray(),
                ];
            })
            ->sortByDesc('retakes_count')
            ->values();
    }

    /**
     * Get modules with retakes in a semester (summary)
     */
    public function getModulesWithRetakes(int $semesterId): Collection
    {
        return RetakeEnrollment::where('semester_id', $semesterId)
            ->active()
            ->with(['module.teacher'])
            ->select('module_id', DB::raw('COUNT(*) as student_count'))
            ->groupBy('module_id')
            ->orderByDesc('student_count')
            ->get()
            ->map(function ($item) {
                $module = Module::find($item->module_id);

                return [
                    'module_id' => $item->module_id,
                    'module_code' => $module?->code,
                    'module_name' => $module?->name,
                    'credits_ects' => $module?->credits_ects,
                    'is_eliminatory' => $module?->is_eliminatory ?? false,
                    'teacher_name' => $module?->teacher?->full_name,
                    'student_count' => $item->student_count,
                ];
            });
    }

    /**
     * Invalidate statistics cache
     */
    public function invalidateStatisticsCache(int $semesterId): void
    {
        Cache::forget("retake_stats:{$semesterId}");
    }
}
