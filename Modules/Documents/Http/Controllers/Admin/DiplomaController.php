<?php

namespace Modules\Documents\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Documents\Entities\DiplomaRegister;
use Modules\Documents\Services\DiplomaService;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;

/**
 * Controller for Epic 2: Diplômes (Stories 06-10)
 */
class DiplomaController extends Controller
{
    public function __construct(
        private DiplomaService $diplomaService
    ) {}

    /**
     * Story 06 & 08: Generate diploma with honors
     */
    public function generateDiploma(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'programme_id' => 'required|exists:tenant.programmes,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'graduation_date' => 'required|date',
            'final_gpa' => 'required|numeric|min:0|max:20',
            'specialization' => 'nullable|string',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $programme = Programme::findOrFail($validated['programme_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);

        $diploma = $this->diplomaService->generateDiploma(
            $student,
            $programme,
            $academicYear,
            new \DateTime($validated['graduation_date']),
            $validated['final_gpa'],
            $validated['specialization'] ?? null
        );

        return response()->json([
            'message' => 'Diploma generated successfully',
            'diploma' => $diploma->load(['student', 'programme', 'academicYear', 'document']),
        ], 201);
    }

    /**
     * Story 07: Get diploma register
     */
    public function index(Request $request): JsonResponse
    {
        $query = DiplomaRegister::with(['student', 'programme', 'academicYear'])
            ->orderBy('issue_date', 'desc');

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('programme_id')) {
            $query->where('programme_id', $request->programme_id);
        }

        if ($request->has('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        if ($request->has('is_duplicate')) {
            $query->where('is_duplicate', $request->boolean('is_duplicate'));
        }

        if ($request->has('delivered')) {
            if ($request->boolean('delivered')) {
                $query->whereNotNull('delivered_at');
            } else {
                $query->whereNull('delivered_at');
            }
        }

        $diplomas = $query->paginate($request->per_page ?? 50);

        return response()->json($diplomas);
    }

    /**
     * Story 09: Generate duplicate diploma
     */
    public function generateDuplicate(Request $request, int $diplomaId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $originalDiploma = DiplomaRegister::findOrFail($diplomaId);

        $duplicate = $this->diplomaService->generateDuplicate(
            $originalDiploma,
            $validated['reason']
        );

        return response()->json([
            'message' => 'Diploma duplicate generated successfully',
            'duplicate' => $duplicate->load(['student', 'programme', 'academicYear', 'originalDiploma', 'document']),
        ], 201);
    }

    /**
     * Story 10: Generate diploma supplement
     */
    public function generateSupplement(int $diplomaId): JsonResponse
    {
        $diploma = DiplomaRegister::findOrFail($diplomaId);

        if ($diploma->supplement_generated) {
            return response()->json([
                'message' => 'Supplement already generated for this diploma',
                'supplement_document' => $diploma->supplementDocument,
            ], 400);
        }

        $supplement = $this->diplomaService->generateSupplement($diploma);

        return response()->json([
            'message' => 'Diploma supplement generated successfully',
            'supplement' => $supplement->load(['student', 'academicYear', 'programme']),
        ], 201);
    }

    /**
     * Mark diploma as delivered
     */
    public function markAsDelivered(Request $request, int $diplomaId): JsonResponse
    {
        $validated = $request->validate([
            'recipient_name' => 'required|string',
            'recipient_id_type' => 'required|string|in:cni,passport,other',
            'recipient_id_number' => 'required|string',
            'delivery_notes' => 'nullable|string',
        ]);

        $diploma = DiplomaRegister::findOrFail($diplomaId);

        $diploma->markAsDelivered(
            auth()->id(),
            $validated['recipient_name'],
            $validated['recipient_id_type'],
            $validated['recipient_id_number'],
            $validated['delivery_notes'] ?? null
        );

        return response()->json([
            'message' => 'Diploma marked as delivered',
            'diploma' => $diploma->fresh(),
        ]);
    }
}
