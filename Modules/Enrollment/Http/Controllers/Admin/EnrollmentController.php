<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\Enrollment\Http\Requests\AddModulesRequest;
use Modules\Enrollment\Http\Requests\CreateEnrollmentRequest;
use Modules\Enrollment\Http\Requests\RemoveModulesRequest;
use Modules\Enrollment\Http\Requests\UpdateEnrollmentRequest;
use Modules\Enrollment\Http\Resources\AvailableModuleResource;
use Modules\Enrollment\Http\Resources\StudentEnrollmentResource;
use Modules\Enrollment\Http\Resources\StudentModuleEnrollmentResource;
use Modules\Enrollment\Services\EnrollmentService;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;

class EnrollmentController extends Controller
{
    public function __construct(
        private EnrollmentService $enrollmentService
    ) {}

    /**
     * List all enrollments with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = StudentEnrollment::on('tenant')
            ->with(['student', 'programme', 'semester', 'academicYear', 'moduleEnrollments.module']);

        // Filter by student
        if ($studentId = $request->input('student_id')) {
            $query->where('student_id', $studentId);
        }

        // Filter by programme
        if ($programmeId = $request->input('programme_id')) {
            $query->where('programme_id', $programmeId);
        }

        // Filter by semester
        if ($semesterId = $request->input('semester_id')) {
            $query->where('semester_id', $semesterId);
        }

        // Filter by academic year
        if ($academicYearId = $request->input('academic_year_id')) {
            $query->where('academic_year_id', $academicYearId);
        }

        // Filter by level
        if ($level = $request->input('level')) {
            $query->where('level', $level);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->input('per_page', 15);
        $enrollments = $query->paginate($perPage);

        return response()->json([
            'data' => StudentEnrollmentResource::collection($enrollments),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    /**
     * Create a new enrollment
     */
    public function store(CreateEnrollmentRequest $request): JsonResponse
    {
        $student = Student::on('tenant')->findOrFail($request->input('student_id'));
        $programme = Programme::on('tenant')->findOrFail($request->input('programme_id'));
        $semester = Semester::on('tenant')->findOrFail($request->input('semester_id'));

        try {
            $result = $this->enrollmentService->createEnrollment(
                $student,
                $programme,
                $semester,
                $request->input('level'),
                $request->input('module_ids', []),
                $request->input('group_id'),
                $request->getAutoEnrollObligatory()
            );

            $enrollment = $result['enrollment']->load(['student', 'programme', 'semester', 'academicYear', 'moduleEnrollments.module']);

            return response()->json([
                'message' => 'Inscription créée avec succès',
                'data' => [
                    'enrollment' => new StudentEnrollmentResource($enrollment),
                    'modules_enrolled_count' => $result['module_enrollments']->count(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Show a specific enrollment
     */
    public function show(int $id): JsonResponse
    {
        $enrollment = StudentEnrollment::on('tenant')
            ->with(['student', 'programme', 'semester', 'academicYear', 'moduleEnrollments.module', 'enrolledBy'])
            ->findOrFail($id);

        return response()->json([
            'data' => new StudentEnrollmentResource($enrollment),
        ]);
    }

    /**
     * Update an enrollment
     */
    public function update(UpdateEnrollmentRequest $request, int $id): JsonResponse
    {
        $enrollment = StudentEnrollment::on('tenant')->findOrFail($id);

        if (! $enrollment->canBeModified()) {
            return response()->json([
                'message' => 'Cette inscription ne peut pas être modifiée',
            ], 422);
        }

        $enrollment->update($request->validated());
        $enrollment->load(['student', 'programme', 'semester', 'academicYear', 'moduleEnrollments.module']);

        return response()->json([
            'message' => 'Inscription mise à jour avec succès',
            'data' => new StudentEnrollmentResource($enrollment),
        ]);
    }

    /**
     * Delete an enrollment
     */
    public function destroy(int $id): JsonResponse
    {
        $enrollment = StudentEnrollment::on('tenant')->findOrFail($id);

        if (! $enrollment->canBeCancelled()) {
            return response()->json([
                'message' => 'Cette inscription ne peut pas être supprimée car des modules ont été validés',
            ], 422);
        }

        $enrollment->delete();

        return response()->json([
            'message' => 'Inscription supprimée avec succès',
        ]);
    }

    /**
     * Get available modules for a programme/level/semester
     */
    public function availableModules(Request $request): JsonResponse
    {
        $request->validate([
            'programme_id' => 'required|integer|exists:tenant.programmes,id',
            'level' => 'required|string|in:L1,L2,L3,M1,M2',
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
            'student_id' => 'nullable|integer|exists:tenant.students,id',
        ]);

        $modules = $this->enrollmentService->getModulesForEnrollment(
            $request->input('programme_id'),
            $request->input('level'),
            $request->input('semester_id'),
            $request->input('student_id')
        );

        $obligatoryCredits = $modules->where('type', 'Obligatoire')->sum('credits_ects');
        $optionalCredits = $modules->where('type', 'Optionnel')->sum('credits_ects');

        return response()->json([
            'data' => AvailableModuleResource::collection($modules),
            'meta' => [
                'total_modules' => $modules->count(),
                'obligatory_modules' => $modules->where('type', 'Obligatoire')->count(),
                'optional_modules' => $modules->where('type', 'Optionnel')->count(),
                'obligatory_credits' => $obligatoryCredits,
                'optional_credits' => $optionalCredits,
                'total_credits' => $obligatoryCredits + $optionalCredits,
            ],
        ]);
    }

    /**
     * Add modules to an existing enrollment
     */
    public function addModules(AddModulesRequest $request, int $enrollmentId): JsonResponse
    {
        $enrollment = StudentEnrollment::on('tenant')->findOrFail($enrollmentId);

        if (! $enrollment->canBeModified()) {
            return response()->json([
                'message' => 'Cette inscription ne peut pas être modifiée',
            ], 422);
        }

        $addedEnrollments = $this->enrollmentService->addModulesToEnrollment(
            $enrollment,
            $request->input('module_ids')
        );

        $enrollment->load(['moduleEnrollments.module']);

        // Load the module relationship on each enrollment
        $addedEnrollments->each(fn ($enrollment) => $enrollment->load('module'));

        return response()->json([
            'message' => $addedEnrollments->count().' module(s) ajouté(s) avec succès',
            'data' => [
                'added_modules' => StudentModuleEnrollmentResource::collection($addedEnrollments),
                'total_credits' => $this->enrollmentService->calculateTotalCredits($enrollment),
            ],
        ]);
    }

    /**
     * Remove modules from an enrollment
     */
    public function removeModules(RemoveModulesRequest $request, int $enrollmentId): JsonResponse
    {
        $enrollment = StudentEnrollment::on('tenant')->findOrFail($enrollmentId);

        if (! $enrollment->canBeModified()) {
            return response()->json([
                'message' => 'Cette inscription ne peut pas être modifiée',
            ], 422);
        }

        $result = $this->enrollmentService->removeModulesFromEnrollment(
            $enrollment,
            $request->input('module_ids')
        );

        $enrollment->load(['moduleEnrollments.module']);

        return response()->json([
            'message' => count($result['removed']).' module(s) retiré(s)',
            'data' => [
                'removed_count' => count($result['removed']),
                'errors' => $result['errors'],
                'total_credits' => $this->enrollmentService->calculateTotalCredits($enrollment),
            ],
        ], ! empty($result['errors']) ? 207 : 200); // 207 Multi-Status if partial success
    }

    /**
     * Get module enrollments for a student/semester
     */
    public function moduleEnrollments(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|integer|exists:tenant.students,id',
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $moduleEnrollments = $this->enrollmentService->getStudentModuleEnrollments(
            $request->input('student_id'),
            $request->input('semester_id')
        );

        $totalCredits = $moduleEnrollments->sum(fn ($me) => $me->module->credits_ects ?? 0);

        return response()->json([
            'data' => StudentModuleEnrollmentResource::collection($moduleEnrollments),
            'meta' => [
                'total_modules' => $moduleEnrollments->count(),
                'total_credits' => $totalCredits,
                'by_status' => $moduleEnrollments->groupBy('status')->map->count(),
            ],
        ]);
    }

    /**
     * Update a module enrollment status
     */
    public function updateModuleEnrollment(Request $request, int $moduleEnrollmentId): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:'.implode(',', StudentModuleEnrollment::VALID_STATUSES),
            'notes' => 'nullable|string|max:1000',
        ]);

        $moduleEnrollment = StudentModuleEnrollment::on('tenant')->findOrFail($moduleEnrollmentId);

        $moduleEnrollment->update($request->only(['status', 'notes']));
        $moduleEnrollment->load('module');

        return response()->json([
            'message' => 'Inscription au module mise à jour',
            'data' => new StudentModuleEnrollmentResource($moduleEnrollment),
        ]);
    }

    /**
     * Get enrollment statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'programme_id' => 'required|integer|exists:tenant.programmes,id',
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $stats = $this->enrollmentService->getEnrollmentStatistics(
            $request->input('programme_id'),
            $request->input('semester_id')
        );

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get students enrolled in a module
     */
    public function studentsInModule(Request $request): JsonResponse
    {
        $request->validate([
            'module_id' => 'required|integer|exists:tenant.modules,id',
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $students = $this->enrollmentService->getStudentsInModule(
            $request->input('module_id'),
            $request->input('semester_id')
        );

        return response()->json([
            'data' => $students->map(fn ($student) => [
                'id' => $student->id,
                'matricule' => $student->matricule,
                'full_name' => $student->full_name,
                'email' => $student->email,
            ]),
            'meta' => [
                'total' => $students->count(),
            ],
        ]);
    }

    /**
     * Check if student can enroll to a module
     */
    public function checkModulePrerequisites(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|integer|exists:tenant.students,id',
            'module_id' => 'required|integer|exists:tenant.modules,id',
        ]);

        $student = Student::on('tenant')->findOrFail($request->input('student_id'));
        $module = \Modules\StructureAcademique\Entities\Module::on('tenant')->findOrFail($request->input('module_id'));

        $result = $this->enrollmentService->canEnrollToModule($student, $module);

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Download enrollment sheet PDF
     */
    public function downloadEnrollmentSheet(int $id): mixed
    {
        $enrollment = StudentEnrollment::on('tenant')
            ->with(['student', 'programme', 'semester', 'academicYear', 'moduleEnrollments.module', 'enrolledBy'])
            ->findOrFail($id);

        try {
            $data = [
                'enrollment' => $enrollment,
                'student' => $enrollment->student,
                'programme' => $enrollment->programme,
                'semester' => $enrollment->semester,
                'academicYear' => $enrollment->academicYear,
                'moduleEnrollments' => $enrollment->moduleEnrollments,
                'enrolledBy' => $enrollment->enrolledBy,
                'generatedAt' => now(),
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('enrollment::contracts.enrollment_sheet', $data);
            $pdf->setPaper('a4', 'portrait');

            $fileName = "fiche_inscription_{$enrollment->student->matricule}_{$enrollment->id}.pdf";

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la génération du PDF.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
