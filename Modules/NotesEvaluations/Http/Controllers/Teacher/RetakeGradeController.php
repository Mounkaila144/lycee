<?php

namespace Modules\NotesEvaluations\Http\Controllers\Teacher;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\NotesEvaluations\Entities\RetakeEnrollment;
use Modules\NotesEvaluations\Exports\RetakeGradeTemplateExport;
use Modules\NotesEvaluations\Http\Requests\StoreRetakeGradeRequest;
use Modules\NotesEvaluations\Http\Requests\StoreRetakeGradesBatchRequest;
use Modules\NotesEvaluations\Http\Resources\RetakeGradeResource;
use Modules\NotesEvaluations\Services\RetakeGradeService;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\TeacherModuleAssignment;

class RetakeGradeController extends Controller
{
    public function __construct(
        protected RetakeGradeService $retakeGradeService
    ) {}

    /**
     * Get modules with retake students for teacher
     * GET /api/frontend/teacher/retake-modules
     */
    public function myRetakeModules(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $semesterId = $request->query('semester_id');

        $assignments = TeacherModuleAssignment::with(['module', 'semester'])
            ->byTeacher($teacher->id)
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->active()
            ->get();

        $modules = $assignments->map(function ($assignment) {
            $retakeCount = RetakeEnrollment::where('module_id', $assignment->module_id)
                ->where('semester_id', $assignment->semester_id)
                ->active()
                ->count();

            $gradedCount = RetakeEnrollment::where('module_id', $assignment->module_id)
                ->where('semester_id', $assignment->semester_id)
                ->whereHas('retakeGrade')
                ->active()
                ->count();

            return [
                'module_id' => $assignment->module_id,
                'module_code' => $assignment->module->code,
                'module_name' => $assignment->module->name,
                'semester_id' => $assignment->semester_id,
                'semester_name' => $assignment->semester->name ?? null,
                'retake_students_count' => $retakeCount,
                'grades_entered_count' => $gradedCount,
                'completion_rate' => $retakeCount > 0 ? round(($gradedCount / $retakeCount) * 100, 1) : 0,
            ];
        })->filter(fn ($m) => $m['retake_students_count'] > 0)->values();

        return response()->json([
            'data' => $modules,
        ]);
    }

    /**
     * Get retake students list for a module
     * GET /api/frontend/teacher/modules/{module}/retake-students
     */
    public function retakeStudents(Request $request, int $module): JsonResponse
    {
        $module = Module::findOrFail($module);
        $teacher = $request->user();
        $semesterId = $request->query('semester_id');

        if (! $semesterId) {
            return response()->json([
                'message' => 'semester_id is required',
            ], 422);
        }

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

        $students = $this->retakeGradeService->getModuleRetakeStudents($module->id, $semesterId);

        return response()->json([
            'data' => $students,
            'module' => [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
            ],
            'meta' => [
                'total' => $students->count(),
                'graded' => $students->where('retake_grade_id', '!=', null)->count(),
            ],
        ]);
    }

    /**
     * Store a single retake grade
     * POST /api/frontend/teacher/retake-grades
     */
    public function store(StoreRetakeGradeRequest $request): JsonResponse
    {
        $teacher = $request->user();
        $data = $request->validated();

        $enrollment = RetakeEnrollment::with('module')->find($data['retake_enrollment_id']);

        if (! $enrollment) {
            return response()->json([
                'message' => 'Inscription rattrapage introuvable.',
            ], 404);
        }

        // Verify teacher is assigned to this module
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($enrollment->module_id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $grade = $this->retakeGradeService->storeGrade(
            $data['retake_enrollment_id'],
            $data['score'] ?? null,
            $data['is_absent'] ?? false,
            $data['comment'] ?? null
        );

        return response()->json([
            'message' => 'Note de rattrapage enregistrée.',
            'data' => new RetakeGradeResource($grade->load('retakeEnrollment.student')),
        ]);
    }

    /**
     * Store batch retake grades
     * POST /api/frontend/teacher/retake-grades/batch
     */
    public function storeBatch(StoreRetakeGradesBatchRequest $request): JsonResponse
    {
        $teacher = $request->user();
        $grades = $request->validated()['grades'];

        // Validate all enrollments belong to modules teacher is assigned to
        $enrollmentIds = collect($grades)->pluck('retake_enrollment_id');
        $enrollments = RetakeEnrollment::whereIn('id', $enrollmentIds)->get()->keyBy('id');

        foreach ($enrollments as $enrollment) {
            $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
                ->byModule($enrollment->module_id)
                ->active()
                ->exists();

            if (! $isAssigned) {
                return response()->json([
                    'message' => "Vous n'êtes pas affecté à tous les modules concernés.",
                ], 403);
            }
        }

        $results = $this->retakeGradeService->storeBatchGrades($grades);

        return response()->json([
            'message' => count($results).' notes de rattrapage enregistrées.',
            'data' => [
                'count' => count($results),
            ],
        ]);
    }

    /**
     * Submit retake grades for validation
     * POST /api/frontend/teacher/modules/{module}/submit-retake-grades
     */
    public function submit(Request $request, int $module): JsonResponse
    {
        $module = Module::findOrFail($module);
        $teacher = $request->user();
        $semesterId = $request->input('semester_id');

        if (! $semesterId) {
            return response()->json([
                'message' => 'semester_id is required',
            ], 422);
        }

        // Verify teacher is assigned
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($module->id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        // Check if grades can be submitted
        $canSubmit = $this->retakeGradeService->canSubmitGrades($module->id, $semesterId);

        if (! $canSubmit['can_submit']) {
            return response()->json([
                'message' => 'Aucune note à soumettre.',
                'data' => $canSubmit,
            ], 422);
        }

        $submitted = $this->retakeGradeService->submitGrades($module->id, $semesterId);

        return response()->json([
            'message' => "{$submitted} notes de rattrapage soumises pour validation.",
            'data' => [
                'submitted' => $submitted,
            ],
        ]);
    }

    /**
     * Get retake statistics for a module
     * GET /api/frontend/teacher/modules/{module}/retake-statistics
     */
    public function statistics(Request $request, int $module): JsonResponse
    {
        $module = Module::findOrFail($module);
        $teacher = $request->user();
        $semesterId = $request->query('semester_id');

        if (! $semesterId) {
            return response()->json([
                'message' => 'semester_id is required',
            ], 422);
        }

        // Verify teacher is assigned
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($module->id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $stats = $this->retakeGradeService->getStatistics($module->id, $semesterId);

        return response()->json([
            'data' => $stats,
            'module' => [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
            ],
        ]);
    }

    /**
     * Export retake grades template
     * GET /api/frontend/teacher/modules/{module}/retake-template
     */
    public function exportTemplate(Request $request, int $module)
    {
        $module = Module::findOrFail($module);
        $teacher = $request->user();
        $semesterId = $request->query('semester_id');

        if (! $semesterId) {
            return response()->json([
                'message' => 'semester_id is required',
            ], 422);
        }

        // Verify teacher is assigned
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($module->id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $filename = sprintf(
            'rattrapage_%s_%s.xlsx',
            $module->code ?? 'module',
            now()->format('Ymd')
        );

        return Excel::download(
            new RetakeGradeTemplateExport($module->id, $semesterId),
            $filename
        );
    }
}
