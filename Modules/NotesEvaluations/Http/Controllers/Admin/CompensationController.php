<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\CompensationLog;
use Modules\NotesEvaluations\Entities\GradeConfig;
use Modules\NotesEvaluations\Http\Requests\UpdateCompensationRulesRequest;
use Modules\NotesEvaluations\Services\CompensationRulesService;
use Modules\StructureAcademique\Entities\Semester;

class CompensationController extends Controller
{
    public function __construct(
        private CompensationRulesService $compensationService
    ) {}

    /**
     * Get current compensation rules configuration
     */
    public function getRules(): JsonResponse
    {
        $config = GradeConfig::getConfig();

        return response()->json([
            'data' => [
                'compensation_enabled' => $config->compensation_enabled,
                'min_semester_average' => $config->min_semester_average,
                'min_compensable_grade' => $config->min_compensable_grade,
                'max_compensated_modules' => $config->max_compensated_modules,
                'allow_professional_module_compensation' => $config->allow_professional_module_compensation,
                'min_module_average' => $config->min_module_average,
            ],
        ]);
    }

    /**
     * Update compensation rules configuration
     */
    public function updateRules(UpdateCompensationRulesRequest $request): JsonResponse
    {
        $config = GradeConfig::getConfig();

        $config->update($request->validated());

        return response()->json([
            'message' => 'Règles de compensation mises à jour.',
            'data' => [
                'compensation_enabled' => $config->compensation_enabled,
                'min_semester_average' => $config->min_semester_average,
                'min_compensable_grade' => $config->min_compensable_grade,
                'max_compensated_modules' => $config->max_compensated_modules,
                'allow_professional_module_compensation' => $config->allow_professional_module_compensation,
            ],
        ]);
    }

    /**
     * Simulate compensation impact for a semester
     */
    public function simulate(int $semester): JsonResponse
    {
        $semester = Semester::findOrFail($semester);
        $result = $this->compensationService->simulateCompensation($semester->id);

        return response()->json([
            'data' => [
                'semester_id' => $semester->id,
                'semester_name' => $semester->name,
                'simulation' => [
                    'students_impacted' => $result['students_impacted'],
                    'modules_compensated' => $result['modules_compensated'],
                    'credits_allocated' => $result['credits_allocated'],
                ],
                'details' => $result['details'] ?? [],
            ],
        ]);
    }

    /**
     * Apply compensation rules to all eligible students in a semester
     */
    public function apply(int $semester): JsonResponse
    {
        $semester = Semester::findOrFail($semester);
        $config = GradeConfig::getConfig();

        if (! $config->compensation_enabled) {
            return response()->json([
                'message' => 'Compensation désactivée pour ce tenant.',
                'data' => ['compensated_count' => 0],
            ], 400);
        }

        // Get all students with results in this semester
        $studentIds = \Modules\NotesEvaluations\Entities\SemesterResult::where('semester_id', $semester->id)
            ->pluck('student_id')
            ->unique();

        $totalCompensated = 0;
        $studentsImpacted = 0;
        $errors = [];

        foreach ($studentIds as $studentId) {
            try {
                $result = $this->compensationService->applyCompensation($studentId, $semester->id);

                if ($result['compensated']->isNotEmpty()) {
                    $studentsImpacted++;
                    $totalCompensated += $result['compensated']->count();
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'student_id' => $studentId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Compensation appliquée.',
            'data' => [
                'students_impacted' => $studentsImpacted,
                'modules_compensated' => $totalCompensated,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * Apply compensation for a specific student in a semester
     */
    public function applyForStudent(int $student, int $semester): JsonResponse
    {
        $student = Student::findOrFail($student);
        $semester = Semester::findOrFail($semester);

        $result = $this->compensationService->applyCompensation($student->id, $semester->id);

        if ($result['reason']) {
            return response()->json([
                'message' => $result['reason'],
                'data' => [
                    'compensated' => [],
                    'skipped' => [],
                ],
            ], 400);
        }

        return response()->json([
            'message' => 'Compensation appliquée pour l\'étudiant.',
            'data' => [
                'compensated' => $result['compensated']->map(fn ($mg) => [
                    'module_id' => $mg->module_id,
                    'module_code' => $mg->module->code ?? 'N/A',
                    'average' => $mg->average,
                ]),
                'skipped' => $result['skipped']->map(fn ($mg) => [
                    'module_id' => $mg->module_id,
                    'module_code' => $mg->module->code ?? 'N/A',
                    'average' => $mg->average,
                    'reason' => 'Limite max atteinte',
                ]),
            ],
        ]);
    }

    /**
     * Check if a module can be compensated for a student
     */
    public function canCompensate(int $student, int $moduleId, int $semester): JsonResponse
    {
        $student = Student::findOrFail($student);
        $semester = Semester::findOrFail($semester);

        $result = $this->compensationService->canBeCompensated($student->id, $moduleId, $semester->id);

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Get compensable modules for a student
     */
    public function getCompensableModules(int $student, int $semester): JsonResponse
    {
        $student = Student::findOrFail($student);
        $semester = Semester::findOrFail($semester);

        $modules = $this->compensationService->getCompensableModules($student->id, $semester->id);

        return response()->json([
            'data' => $modules->map(fn ($mg) => [
                'module_grade_id' => $mg->id,
                'module_id' => $mg->module_id,
                'module_code' => $mg->module->code ?? 'N/A',
                'module_name' => $mg->module->name ?? 'N/A',
                'average' => $mg->average,
                'credits_ects' => $mg->module->credits_ects ?? 0,
            ]),
        ]);
    }

    /**
     * Get compensation history for a student
     */
    public function getStudentHistory(int $student, Request $request): JsonResponse
    {
        $student = Student::findOrFail($student);
        $semesterId = $request->query('semester_id');

        $history = $this->compensationService->getStudentCompensationHistory(
            $student->id,
            $semesterId ? (int) $semesterId : null
        );

        return response()->json([
            'data' => $history->map(fn ($log) => [
                'id' => $log->id,
                'module' => [
                    'id' => $log->module_id,
                    'code' => $log->module->code ?? 'N/A',
                    'name' => $log->module->name ?? 'N/A',
                ],
                'semester' => [
                    'id' => $log->semester_id,
                    'name' => $log->semester->name ?? 'N/A',
                ],
                'module_average' => $log->module_average,
                'semester_average' => $log->semester_average,
                'compensation_reason' => $log->compensation_reason,
                'applied_at' => $log->applied_at?->toIso8601String(),
                'applied_by' => $log->appliedByUser ? [
                    'id' => $log->appliedByUser->id,
                    'name' => $log->appliedByUser->name,
                ] : null,
            ]),
        ]);
    }

    /**
     * Revoke compensation for a module (admin only)
     */
    public function revoke(int $student, int $moduleId, int $semester): JsonResponse
    {
        $student = Student::findOrFail($student);
        $semester = Semester::findOrFail($semester);

        $revoked = $this->compensationService->revokeCompensation($student->id, $moduleId, $semester->id);

        if (! $revoked) {
            return response()->json([
                'message' => 'Module non compensé ou déjà révoqué.',
            ], 404);
        }

        return response()->json([
            'message' => 'Compensation révoquée avec succès.',
        ]);
    }

    /**
     * Get compensation statistics for a semester
     */
    public function getSemesterStatistics(int $semester): JsonResponse
    {
        $semester = Semester::findOrFail($semester);
        $logs = CompensationLog::where('semester_id', $semester->id)->get();

        $uniqueStudents = $logs->pluck('student_id')->unique()->count();
        $totalModules = $logs->count();

        return response()->json([
            'data' => [
                'semester_id' => $semester->id,
                'semester_name' => $semester->name,
                'students_with_compensation' => $uniqueStudents,
                'total_modules_compensated' => $totalModules,
                'recent_compensations' => $logs->sortByDesc('applied_at')
                    ->take(10)
                    ->map(fn ($log) => [
                        'student_id' => $log->student_id,
                        'module_code' => $log->module->code ?? 'N/A',
                        'module_average' => $log->module_average,
                        'applied_at' => $log->applied_at?->toIso8601String(),
                    ])
                    ->values(),
            ],
        ]);
    }
}
