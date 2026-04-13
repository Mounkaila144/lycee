<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Modules\NotesEvaluations\Entities\GradeConfig;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleSemesterAssignment;

class EliminatoryRulesService
{
    /**
     * Check eliminatory status for a module
     */
    public function checkEliminatoryStatus(int $studentId, int $moduleId, int $semesterId): string
    {
        $module = Module::findOrFail($moduleId);

        if (! $module->is_eliminatory) {
            return 'not_eliminatory';
        }

        $moduleGrade = ModuleGrade::where([
            'student_id' => $studentId,
            'module_id' => $moduleId,
            'semester_id' => $semesterId,
        ])->first();

        if (! $moduleGrade || $moduleGrade->average === null) {
            return 'absent'; // Must retake
        }

        $threshold = $this->getEliminatoryThreshold($module);

        if ($moduleGrade->average >= $threshold) {
            return 'validated';
        }

        // Check if compensation is allowed
        $config = GradeConfig::getConfig();

        if ($config->allow_eliminatory_compensation) {
            $semesterResult = SemesterResult::where([
                'student_id' => $studentId,
                'semester_id' => $semesterId,
            ])->first();

            if ($semesterResult && $semesterResult->average >= $config->min_semester_average) {
                return 'compensated'; // Exceptional case
            }
        }

        return 'to_retake'; // Must retake
    }

    /**
     * Get the eliminatory threshold for a module
     */
    public function getEliminatoryThreshold(Module $module): float
    {
        // If specific threshold defined, use it
        if ($module->eliminatory_threshold !== null) {
            return (float) $module->eliminatory_threshold;
        }

        // Otherwise use global tenant threshold
        $config = GradeConfig::getConfig();

        return (float) $config->eliminatory_threshold;
    }

    /**
     * Get failed eliminatory modules for a student in a semester
     */
    public function getFailedEliminatoryModules(int $studentId, int $semesterId): Collection
    {
        $moduleIds = ModuleSemesterAssignment::where('semester_id', $semesterId)
            ->where('is_active', true)
            ->pluck('module_id')
            ->unique();

        $modules = Module::whereIn('id', $moduleIds)
            ->where('is_eliminatory', true)
            ->get();

        $failed = collect();

        foreach ($modules as $module) {
            $status = $this->checkEliminatoryStatus($studentId, $module->id, $semesterId);

            if (in_array($status, ['to_retake', 'absent'])) {
                $moduleGrade = ModuleGrade::where([
                    'student_id' => $studentId,
                    'module_id' => $module->id,
                    'semester_id' => $semesterId,
                ])->first();

                $failed->push([
                    'module' => $module,
                    'module_id' => $module->id,
                    'module_name' => $module->name,
                    'module_code' => $module->code,
                    'average' => $moduleGrade?->average,
                    'threshold' => $this->getEliminatoryThreshold($module),
                    'status' => $status,
                ]);
            }
        }

        return $failed;
    }

    /**
     * Check if a semester can be validated considering eliminatory rules
     */
    public function canValidateSemester(int $studentId, int $semesterId): array
    {
        $semesterResult = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->first();

        $failedEliminatoryModules = $this->getFailedEliminatoryModules($studentId, $semesterId);

        $config = GradeConfig::getConfig();

        $canValidate = true;
        $reasons = [];

        // Check semester average
        if (! $semesterResult || $semesterResult->average === null) {
            $canValidate = false;
            $reasons[] = 'Moyenne semestre non calculée';
        } elseif ($semesterResult->average < $config->min_semester_average) {
            $canValidate = false;
            $reasons[] = sprintf(
                'Moyenne semestre insuffisante: %.2f/20 (seuil: %.2f)',
                $semesterResult->average,
                $config->min_semester_average
            );
        }

        // Check eliminatory modules
        if ($failedEliminatoryModules->isNotEmpty()) {
            $canValidate = false;

            foreach ($failedEliminatoryModules as $failed) {
                $reasons[] = sprintf(
                    "Module éliminatoire '%s': %.2f/20 (seuil: %.2f)",
                    $failed['module_name'],
                    $failed['average'] ?? 0,
                    $failed['threshold']
                );
            }
        }

        return [
            'can_validate' => $canValidate,
            'reasons' => $reasons,
            'failed_eliminatory_count' => $failedEliminatoryModules->count(),
            'failed_eliminatory_modules' => $failedEliminatoryModules->toArray(),
        ];
    }

    /**
     * Get all eliminatory modules for a semester
     */
    public function getEliminatoryModules(int $semesterId): Collection
    {
        $moduleIds = ModuleSemesterAssignment::where('semester_id', $semesterId)
            ->where('is_active', true)
            ->pluck('module_id')
            ->unique();

        return Module::whereIn('id', $moduleIds)
            ->where('is_eliminatory', true)
            ->get()
            ->map(function ($module) {
                return [
                    'id' => $module->id,
                    'code' => $module->code,
                    'name' => $module->name,
                    'credits_ects' => $module->credits_ects,
                    'threshold' => $this->getEliminatoryThreshold($module),
                ];
            });
    }

    /**
     * Get students blocked by eliminatory modules
     */
    public function getStudentsBlockedByEliminatory(int $semesterId): Collection
    {
        return SemesterResult::where('semester_id', $semesterId)
            ->where('validation_blocked_by_eliminatory', true)
            ->with(['student', 'semester'])
            ->get()
            ->map(function ($result) use ($semesterId) {
                $failedModules = $this->getFailedEliminatoryModules($result->student_id, $semesterId);

                return [
                    'student_id' => $result->student_id,
                    'student_name' => $result->student->full_name ?? $result->student->firstname.' '.$result->student->lastname,
                    'student_matricule' => $result->student->matricule,
                    'semester_average' => $result->average,
                    'failed_modules' => $failedModules->toArray(),
                    'failed_count' => $failedModules->count(),
                ];
            });
    }

    /**
     * Get eliminatory statistics for a semester
     */
    public function getEliminatoryStatistics(int $semesterId): array
    {
        $eliminatoryModules = $this->getEliminatoryModules($semesterId);
        $blockedStudents = $this->getStudentsBlockedByEliminatory($semesterId);

        $totalStudents = SemesterResult::where('semester_id', $semesterId)->count();

        return [
            'eliminatory_modules_count' => $eliminatoryModules->count(),
            'eliminatory_modules' => $eliminatoryModules->toArray(),
            'blocked_students_count' => $blockedStudents->count(),
            'total_students' => $totalStudents,
            'blocked_percentage' => $totalStudents > 0
                ? round(($blockedStudents->count() / $totalStudents) * 100, 2)
                : 0,
        ];
    }
}
