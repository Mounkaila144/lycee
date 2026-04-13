<?php

namespace Modules\NotesEvaluations\Http\Controllers\Teacher;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Exports\GradeTemplateExport;
use Modules\NotesEvaluations\Http\Requests\AutoSaveGradeRequest;
use Modules\NotesEvaluations\Http\Requests\StoreGradesRequest;
use Modules\NotesEvaluations\Http\Resources\GradeResource;
use Modules\NotesEvaluations\Services\GradeStatisticsService;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\StructureAcademique\Entities\TeacherModuleAssignment;

class GradeEntryController extends Controller
{
    public function __construct(
        private GradeStatisticsService $statisticsService
    ) {}

    /**
     * Get teacher's assigned modules for the current semester
     */
    public function myModules(Request $request): JsonResponse
    {
        $teacher = $request->user();

        $assignments = TeacherModuleAssignment::with(['module', 'programme', 'semester'])
            ->byTeacher($teacher->id)
            ->active()
            ->get();

        $modules = $assignments->map(function ($assignment) {
            $evaluationCount = ModuleEvaluationConfig::where('module_id', $assignment->module_id)
                ->where('semester_id', $assignment->semester_id)
                ->count();

            $gradesEntered = Grade::whereHas('evaluation', function ($q) use ($assignment) {
                $q->where('module_id', $assignment->module_id)
                    ->where('semester_id', $assignment->semester_id);
            })->count();

            return [
                'id' => $assignment->module_id,
                'code' => $assignment->module->code,
                'name' => $assignment->module->name,
                'credits' => $assignment->module->credits_ects,
                'programme' => [
                    'id' => $assignment->programme_id,
                    'name' => $assignment->programme->name,
                ],
                'semester' => [
                    'id' => $assignment->semester_id,
                    'name' => $assignment->semester->name,
                ],
                'type' => $assignment->type,
                'hours_allocated' => $assignment->hours_allocated,
                'evaluation_count' => $evaluationCount,
                'grades_entered' => $gradesEntered,
            ];
        });

        return response()->json([
            'data' => $modules,
        ]);
    }

    /**
     * Get evaluations for a specific module
     */
    public function moduleEvaluations(Request $request, int $module): JsonResponse
    {
        $module = Module::findOrFail($module);
        $teacher = $request->user();

        // Verify teacher is assigned to this module
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($module->id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $semesterId = $request->query('semester_id');

        $evaluations = ModuleEvaluationConfig::where('module_id', $module->id)
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->orderBy('order')
            ->get()
            ->map(function ($evaluation) {
                $totalStudents = StudentModuleEnrollment::forModule($evaluation->module_id)
                    ->when($evaluation->semester_id, fn ($q) => $q->bySemester($evaluation->semester_id))
                    ->inscrit()
                    ->count();

                $gradesEntered = Grade::where('evaluation_id', $evaluation->id)->count();
                $gradesPublished = Grade::where('evaluation_id', $evaluation->id)
                    ->where('status', 'Published')
                    ->count();

                return [
                    'id' => $evaluation->id,
                    'name' => $evaluation->name,
                    'type' => $evaluation->type,
                    'coefficient' => $evaluation->coefficient,
                    'max_score' => $evaluation->max_score,
                    'planned_date' => $evaluation->planned_date?->toDateString(),
                    'is_eliminatory' => $evaluation->is_eliminatory,
                    'elimination_threshold' => $evaluation->elimination_threshold,
                    'status' => $evaluation->status,
                    'total_students' => $totalStudents,
                    'grades_entered' => $gradesEntered,
                    'grades_published' => $gradesPublished,
                    'completion_rate' => $totalStudents > 0 ? round(($gradesEntered / $totalStudents) * 100, 1) : 0,
                ];
            });

        return response()->json([
            'data' => $evaluations,
            'module' => [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
            ],
        ]);
    }

    /**
     * Get students list for grade entry
     */
    public function students(Request $request, int $evaluation): JsonResponse
    {
        $teacher = $request->user();

        // Load evaluation with tenant connection
        $evaluation = ModuleEvaluationConfig::on('tenant')->findOrFail($evaluation);

        // Verify teacher is assigned to this module
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($evaluation->module_id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $search = $request->query('search');
        $sortBy = $request->query('sort_by', 'lastname');
        $sortDir = $request->query('sort_dir', 'asc');

        // Get students enrolled in this module (deduplicate by student_id)
        $enrollments = StudentModuleEnrollment::with('student')
            ->forModule($evaluation->module_id)
            ->when($evaluation->semester_id, fn ($q) => $q->bySemester($evaluation->semester_id))
            ->inscrit()
            ->get()
            ->unique('student_id');

        $studentIds = $enrollments->pluck('student_id');

        // Get existing grades
        $existingGrades = Grade::where('evaluation_id', $evaluation->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        // Build response
        $students = $enrollments->map(function ($enrollment) use ($existingGrades) {
            $grade = $existingGrades->get($enrollment->student_id);

            return [
                'student_id' => $enrollment->student_id,
                'matricule' => $enrollment->student->matricule,
                'firstname' => $enrollment->student->firstname,
                'lastname' => $enrollment->student->lastname,
                'full_name' => $enrollment->student->full_name,
                'grade' => $grade ? [
                    'id' => $grade->id,
                    'score' => $grade->score,
                    'is_absent' => $grade->is_absent,
                    'comment' => $grade->comment,
                    'status' => $grade->status,
                    'entered_at' => $grade->entered_at?->toIso8601String(),
                ] : null,
                'has_grade' => $grade !== null,
            ];
        });

        // Apply search
        if ($search) {
            $search = strtolower($search);
            $students = $students->filter(function ($student) use ($search) {
                return str_contains(strtolower($student['matricule']), $search)
                    || str_contains(strtolower($student['firstname']), $search)
                    || str_contains(strtolower($student['lastname']), $search);
            });
        }

        // Apply sorting
        $students = $students->sortBy($sortBy, SORT_REGULAR, $sortDir === 'desc')->values();

        return response()->json([
            'data' => $students,
            'evaluation' => [
                'id' => $evaluation->id,
                'name' => $evaluation->name,
                'type' => $evaluation->type,
                'max_score' => $evaluation->max_score,
            ],
            'meta' => [
                'total' => $students->count(),
                'with_grades' => $students->where('has_grade', true)->count(),
                'without_grades' => $students->where('has_grade', false)->count(),
            ],
        ]);
    }

    /**
     * Store batch grades
     */
    public function storeBatch(StoreGradesRequest $request): JsonResponse
    {
        $teacher = $request->user();
        $grades = $request->validated()['grades'];
        $results = [
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        foreach ($grades as $gradeData) {
            try {
                // Verify teacher is assigned to this evaluation's module
                $evaluation = ModuleEvaluationConfig::find($gradeData['evaluation_id']);
                if (! $evaluation) {
                    $results['errors'][] = "Évaluation {$gradeData['evaluation_id']} introuvable.";

                    continue;
                }

                $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
                    ->byModule($evaluation->module_id)
                    ->active()
                    ->exists();

                if (! $isAssigned) {
                    $results['errors'][] = "Non autorisé pour l'évaluation {$evaluation->name}.";

                    continue;
                }

                // Check if grade exists
                $grade = Grade::where('student_id', $gradeData['student_id'])
                    ->where('evaluation_id', $gradeData['evaluation_id'])
                    ->first();

                $isAbsent = $gradeData['is_absent'] ?? false;
                $score = $isAbsent ? null : ($gradeData['score'] ?? null);

                if ($grade) {
                    // Check if grade can be modified
                    if ($grade->isPublished()) {
                        $results['errors'][] = "Note publiée pour étudiant {$gradeData['student_id']} - modification nécessite demande de correction.";

                        continue;
                    }

                    $grade->update([
                        'score' => $score,
                        'is_absent' => $isAbsent,
                        'comment' => $gradeData['comment'] ?? $grade->comment,
                        'entered_by' => $teacher->id,
                        'entered_at' => now(),
                    ]);
                    $results['updated']++;
                } else {
                    Grade::create([
                        'student_id' => $gradeData['student_id'],
                        'evaluation_id' => $gradeData['evaluation_id'],
                        'score' => $score,
                        'is_absent' => $isAbsent,
                        'comment' => $gradeData['comment'] ?? null,
                        'entered_by' => $teacher->id,
                        'entered_at' => now(),
                        'status' => 'Draft',
                    ]);
                    $results['created']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Erreur pour étudiant {$gradeData['student_id']}: {$e->getMessage()}";
            }
        }

        return response()->json([
            'message' => 'Notes enregistrées avec succès.',
            'data' => $results,
        ], $results['errors'] ? 207 : 200);
    }

    /**
     * Auto-save a single grade
     */
    public function autoSave(AutoSaveGradeRequest $request): JsonResponse
    {
        $teacher = $request->user();
        $data = $request->validated();

        $evaluation = ModuleEvaluationConfig::find($data['evaluation_id']);

        // Verify teacher is assigned
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($evaluation->module_id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $isAbsent = $data['is_absent'] ?? false;
        $score = $isAbsent ? null : ($data['score'] ?? null);

        $grade = Grade::updateOrCreate(
            [
                'student_id' => $data['student_id'],
                'evaluation_id' => $data['evaluation_id'],
            ],
            [
                'score' => $score,
                'is_absent' => $isAbsent,
                'comment' => $data['comment'] ?? null,
                'entered_by' => $teacher->id,
                'entered_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Note sauvegardée.',
            'data' => new GradeResource($grade),
        ]);
    }

    /**
     * Get statistics for an evaluation
     */
    public function statistics(Request $request, int $evaluation): JsonResponse
    {
        $teacher = $request->user();

        // Load evaluation with tenant connection
        $evaluation = ModuleEvaluationConfig::on('tenant')->findOrFail($evaluation);

        // Verify teacher is assigned
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($evaluation->module_id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $stats = $this->statisticsService->calculateStats($evaluation);
        $anomalies = $this->statisticsService->detectAnomalies($stats);

        return response()->json([
            'data' => array_merge($stats, [
                'evaluation_id' => $evaluation->id,
                'evaluation_name' => $evaluation->name,
                'anomalies' => $anomalies,
            ]),
        ]);
    }

    /**
     * Export students list as Excel template
     */
    public function export(Request $request, int $evaluation)
    {
        $teacher = $request->user();

        // Load evaluation with tenant connection
        $evaluation = ModuleEvaluationConfig::on('tenant')->findOrFail($evaluation);

        // Verify teacher is assigned
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($evaluation->module_id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $filename = sprintf(
            'notes_%s_%s_%s.xlsx',
            $evaluation->module->code ?? 'module',
            $evaluation->name,
            now()->format('Ymd')
        );

        return Excel::download(
            new GradeTemplateExport($evaluation),
            $filename
        );
    }

    /**
     * Check if all grades have been entered for an evaluation
     */
    public function checkCompleteness(Request $request, int $evaluation): JsonResponse
    {
        $teacher = $request->user();

        // Load evaluation with tenant connection
        $evaluation = ModuleEvaluationConfig::on('tenant')->findOrFail($evaluation);

        // Verify teacher is assigned
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($evaluation->module_id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        // Get total students enrolled
        $totalStudents = StudentModuleEnrollment::forModule($evaluation->module_id)
            ->when($evaluation->semester_id, fn ($q) => $q->bySemester($evaluation->semester_id))
            ->inscrit()
            ->distinct('student_id')
            ->count('student_id');

        // Get students with grades
        $studentsWithGrades = Grade::where('evaluation_id', $evaluation->id)
            ->distinct('student_id')
            ->count('student_id');

        $isComplete = $totalStudents > 0 && $studentsWithGrades >= $totalStudents;
        $completionRate = $totalStudents > 0 ? round(($studentsWithGrades / $totalStudents) * 100, 1) : 0;

        return response()->json([
            'data' => [
                'is_complete' => $isComplete,
                'total_students' => $totalStudents,
                'students_with_grades' => $studentsWithGrades,
                'students_without_grades' => $totalStudents - $studentsWithGrades,
                'completion_rate' => $completionRate,
                'can_submit' => $isComplete,
            ],
        ]);
    }

    /**
     * Publish grades for an evaluation
     */
    public function publish(Request $request, int $evaluation): JsonResponse
    {
        $teacher = $request->user();

        // Load evaluation with tenant connection
        $evaluation = ModuleEvaluationConfig::on('tenant')->findOrFail($evaluation);

        // Verify teacher is assigned
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($evaluation->module_id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        // Check if all grades are entered
        $totalStudents = StudentModuleEnrollment::forModule($evaluation->module_id)
            ->when($evaluation->semester_id, fn ($q) => $q->bySemester($evaluation->semester_id))
            ->inscrit()
            ->distinct('student_id')
            ->count('student_id');

        $studentsWithGrades = Grade::where('evaluation_id', $evaluation->id)
            ->distinct('student_id')
            ->count('student_id');

        if ($studentsWithGrades < $totalStudents) {
            return response()->json([
                'message' => 'Toutes les notes doivent être saisies avant publication.',
                'data' => [
                    'total_students' => $totalStudents,
                    'students_with_grades' => $studentsWithGrades,
                    'missing' => $totalStudents - $studentsWithGrades,
                ],
            ], 422);
        }

        // Publish all grades
        $updated = Grade::where('evaluation_id', $evaluation->id)
            ->where('status', '!=', 'Published')
            ->update([
                'status' => 'Published',
                'published_at' => now(),
                'is_visible_to_students' => true,
            ]);

        return response()->json([
            'message' => 'Notes publiées avec succès.',
            'data' => [
                'grades_published' => $updated,
                'total_grades' => $studentsWithGrades,
            ],
        ]);
    }
}
