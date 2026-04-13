<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\Option;
use Modules\Enrollment\Entities\OptionAssignment;
use Modules\Enrollment\Entities\OptionChoice;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Requests\AssignOptionsRequest;
use Modules\Enrollment\Http\Requests\StoreOptionChoiceRequest;
use Modules\Enrollment\Http\Requests\StoreOptionRequest;
use Modules\Enrollment\Http\Requests\UpdateOptionRequest;
use Modules\Enrollment\Http\Resources\OptionAssignmentResource;
use Modules\Enrollment\Http\Resources\OptionChoiceResource;
use Modules\Enrollment\Http\Resources\OptionResource;
use Modules\Enrollment\Services\OptionAssignmentService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;

class OptionController extends Controller
{
    public function __construct(
        private OptionAssignmentService $assignmentService
    ) {}

    /**
     * List all options with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Option::on('tenant')
            ->with(['programme'])
            ->withCount(['choices', 'assignments']);

        // Filter by programme
        if ($programmeId = $request->input('programme_id')) {
            $query->where('programme_id', $programmeId);
        }

        // Filter by level
        if ($level = $request->input('level')) {
            $query->where('level', $level);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by mandatory
        if ($request->has('is_mandatory')) {
            $query->where('is_mandatory', filter_var($request->input('is_mandatory'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by choice period active
        if ($request->boolean('choice_period_active')) {
            $query->choicePeriodActive();
        }

        // Search by name or code
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->input('per_page', 15);
        $options = $query->paginate($perPage);

        return response()->json([
            'data' => OptionResource::collection($options),
            'meta' => [
                'current_page' => $options->currentPage(),
                'last_page' => $options->lastPage(),
                'per_page' => $options->perPage(),
                'total' => $options->total(),
            ],
        ]);
    }

    /**
     * Create a new option
     */
    public function store(StoreOptionRequest $request): JsonResponse
    {
        $option = Option::create($request->validated());

        return response()->json([
            'message' => 'Option créée avec succès.',
            'data' => new OptionResource($option->load('programme')),
        ], 201);
    }

    /**
     * Show option details
     */
    public function show(int $option, Request $request): JsonResponse
    {
        $optionModel = Option::on('tenant')->findOrFail($option);
        $optionModel->load('programme');
        $optionModel->loadCount(['choices', 'assignments']);

        // Include statistics if requested
        $data = new OptionResource($optionModel);

        if ($request->boolean('with_statistics') && $request->has('academic_year_id')) {
            $academicYear = AcademicYear::on('tenant')->findOrFail($request->input('academic_year_id'));
            $statistics = $this->assignmentService->getOptionStatistics($optionModel, $academicYear);

            return response()->json([
                'data' => $data,
                'statistics' => $statistics,
            ]);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Update an option
     */
    public function update(UpdateOptionRequest $request, int $option): JsonResponse
    {
        $optionModel = Option::on('tenant')->findOrFail($option);
        $optionModel->update($request->validated());

        return response()->json([
            'message' => 'Option modifiée avec succès.',
            'data' => new OptionResource($optionModel->fresh()->load('programme')),
        ]);
    }

    /**
     * Delete an option
     */
    public function destroy(int $option): JsonResponse
    {
        $optionModel = Option::on('tenant')->findOrFail($option);

        // Check if option has assignments
        if ($optionModel->assignments()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une option avec des affectations.',
            ], 422);
        }

        $optionModel->delete();

        return response()->json([
            'message' => 'Option supprimée avec succès.',
        ]);
    }

    /**
     * Run automatic option assignment
     */
    public function assign(AssignOptionsRequest $request): JsonResponse
    {
        $academicYear = AcademicYear::on('tenant')->findOrFail($request->input('academic_year_id'));
        $programme = Programme::on('tenant')->findOrFail($request->input('programme_id'));
        $level = $request->input('level');

        $result = $this->assignmentService->assignOptionsAutomatically(
            $academicYear,
            $programme,
            $level
        );

        if (! empty($result['errors'])) {
            return response()->json([
                'message' => 'Erreurs lors de l\'affectation.',
                'errors' => $result['errors'],
                'result' => $result,
            ], 422);
        }

        return response()->json([
            'message' => "Affectation terminée: {$result['assigned']} étudiants affectés.",
            'data' => [
                'assigned' => $result['assigned'],
                'waitlist' => $result['waitlist'],
                'unassigned' => $result['unassigned'],
                'assignments' => OptionAssignmentResource::collection($result['assignments']),
                'waitlist_students' => $result['waitlist_students'],
                'unassigned_students' => $result['unassigned_students'],
            ],
        ]);
    }

    /**
     * Manual assignment of a student to an option
     */
    public function assignManual(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer', 'exists:tenant.students,id'],
            'option_id' => ['required', 'integer', 'exists:tenant.options,id'],
            'academic_year_id' => ['required', 'integer', 'exists:tenant.academic_years,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'choice_rank_obtained' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $student = Student::on('tenant')->findOrFail($request->input('student_id'));
        $option = Option::on('tenant')->findOrFail($request->input('option_id'));
        $academicYear = AcademicYear::on('tenant')->findOrFail($request->input('academic_year_id'));

        $assignment = $this->assignmentService->assignManually(
            $student,
            $option,
            $academicYear,
            $request->user(),
            $request->input('notes'),
            $request->input('choice_rank_obtained')
        );

        return response()->json([
            'message' => 'Étudiant affecté manuellement avec succès.',
            'data' => new OptionAssignmentResource($assignment->load(['student', 'option', 'academicYear'])),
        ], 201);
    }

    /**
     * Remove an assignment
     */
    public function removeAssignment(OptionAssignment $assignment): JsonResponse
    {
        $this->assignmentService->removeAssignment($assignment);

        return response()->json([
            'message' => 'Affectation supprimée avec succès.',
        ]);
    }

    /**
     * List choices for an option
     */
    public function choices(int $option, Request $request): JsonResponse
    {
        $optionModel = Option::on('tenant')->findOrFail($option);

        $query = OptionChoice::on('tenant')
            ->where('option_id', $optionModel->id)
            ->with(['student', 'academicYear']);

        // Filter by academic year
        if ($academicYearId = $request->input('academic_year_id')) {
            $query->where('academic_year_id', $academicYearId);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sort by choice rank
        $query->orderBy('choice_rank');

        $perPage = $request->input('per_page', 15);
        $choices = $query->paginate($perPage);

        return response()->json([
            'data' => OptionChoiceResource::collection($choices),
            'meta' => [
                'current_page' => $choices->currentPage(),
                'last_page' => $choices->lastPage(),
                'per_page' => $choices->perPage(),
                'total' => $choices->total(),
            ],
        ]);
    }

    /**
     * List assignments for an option
     */
    public function assignments(int $option, Request $request): JsonResponse
    {
        $optionModel = Option::on('tenant')->findOrFail($option);

        $query = OptionAssignment::on('tenant')
            ->where('option_id', $optionModel->id)
            ->with(['student', 'academicYear', 'assignedByUser']);

        // Filter by academic year
        if ($academicYearId = $request->input('academic_year_id')) {
            $query->where('academic_year_id', $academicYearId);
        }

        // Filter by method
        if ($method = $request->input('assignment_method')) {
            $query->where('assignment_method', $method);
        }

        // Sort
        $query->orderBy('assigned_at', 'desc');

        $perPage = $request->input('per_page', 15);
        $assignments = $query->paginate($perPage);

        return response()->json([
            'data' => OptionAssignmentResource::collection($assignments),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
        ]);
    }

    /**
     * Get statistics for an option
     */
    public function statistics(int $option, Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:tenant.academic_years,id'],
        ]);

        $optionModel = Option::on('tenant')->findOrFail($option);
        $academicYear = AcademicYear::on('tenant')->findOrFail($request->input('academic_year_id'));
        $statistics = $this->assignmentService->getOptionStatistics($optionModel, $academicYear);

        return response()->json(['data' => $statistics]);
    }

    /**
     * Get global statistics for a programme/level
     */
    public function globalStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:tenant.academic_years,id'],
            'programme_id' => ['required', 'integer', 'exists:tenant.programmes,id'],
            'level' => ['required', 'string', 'in:L1,L2,L3,M1,M2'],
        ]);

        $academicYear = AcademicYear::on('tenant')->findOrFail($request->input('academic_year_id'));
        $programme = Programme::on('tenant')->findOrFail($request->input('programme_id'));
        $level = $request->input('level');

        $statistics = $this->assignmentService->getGlobalStatistics($academicYear, $programme, $level);

        return response()->json(['data' => $statistics]);
    }

    /**
     * Store a student's option choice
     */
    public function storeChoice(StoreOptionChoiceRequest $request): JsonResponse
    {
        $choice = OptionChoice::create($request->validated());

        return response()->json([
            'message' => 'Vœu enregistré avec succès.',
            'data' => new OptionChoiceResource($choice->load(['student', 'option', 'academicYear'])),
        ], 201);
    }

    /**
     * Get student's choices for an academic year
     */
    public function studentChoices(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer', 'exists:tenant.students,id'],
            'academic_year_id' => ['required', 'integer', 'exists:tenant.academic_years,id'],
        ]);

        $choices = OptionChoice::on('tenant')
            ->where('student_id', $request->input('student_id'))
            ->where('academic_year_id', $request->input('academic_year_id'))
            ->with(['option.programme', 'academicYear'])
            ->orderBy('choice_rank')
            ->get();

        $assignment = OptionAssignment::on('tenant')
            ->where('student_id', $request->input('student_id'))
            ->where('academic_year_id', $request->input('academic_year_id'))
            ->with(['option.programme', 'academicYear'])
            ->first();

        return response()->json([
            'data' => [
                'choices' => OptionChoiceResource::collection($choices),
                'assignment' => $assignment ? new OptionAssignmentResource($assignment) : null,
            ],
        ]);
    }

    /**
     * Check prerequisites for a student and option
     */
    public function checkPrerequisites(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer', 'exists:tenant.students,id'],
            'option_id' => ['required', 'integer', 'exists:tenant.options,id'],
        ]);

        $student = Student::on('tenant')->findOrFail($request->input('student_id'));
        $option = Option::on('tenant')->findOrFail($request->input('option_id'));

        $result = $this->assignmentService->checkPrerequisites($student, $option);

        return response()->json(['data' => $result]);
    }
}
