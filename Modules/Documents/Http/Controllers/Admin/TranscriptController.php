<?php

namespace Modules\Documents\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Documents\Services\TranscriptService;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Semester;

/**
 * Controller for Epic 1: Génération Relevés (Stories 01-05)
 */
class TranscriptController extends Controller
{
    public function __construct(
        private TranscriptService $transcriptService
    ) {}

    /**
     * Story 01 & 02: Generate semester transcript
     */
    public function generateSemesterTranscript(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'semester_id' => 'required|exists:tenant.semesters,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'is_provisional' => 'boolean',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $semester = Semester::findOrFail($validated['semester_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);

        $document = $this->transcriptService->generateSemesterTranscript(
            $student,
            $semester,
            $academicYear,
            $validated['is_provisional'] ?? false
        );

        return response()->json([
            'message' => 'Transcript generated successfully',
            'document' => $document->load(['student', 'academicYear', 'semester']),
        ], 201);
    }

    /**
     * Story 03: Generate global transcript
     */
    public function generateGlobalTranscript(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'nullable|exists:tenant.academic_years,id',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $academicYear = isset($validated['academic_year_id'])
            ? AcademicYear::findOrFail($validated['academic_year_id'])
            : null;

        $document = $this->transcriptService->generateGlobalTranscript($student, $academicYear);

        return response()->json([
            'message' => 'Global transcript generated successfully',
            'document' => $document->load(['student', 'academicYear']),
        ], 201);
    }

    /**
     * Story 05: Batch generate transcripts
     */
    public function batchGenerateTranscripts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_ids' => 'required|array|min:1|max:500',
            'student_ids.*' => 'exists:tenant.students,id',
            'semester_id' => 'required|exists:tenant.semesters,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'is_provisional' => 'boolean',
        ]);

        $semester = Semester::findOrFail($validated['semester_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);

        $results = $this->transcriptService->generateBatchTranscripts(
            $validated['student_ids'],
            $semester,
            $academicYear,
            $validated['is_provisional'] ?? false
        );

        $successCount = collect($results)->where('status', 'success')->count();
        $failedCount = collect($results)->where('status', 'failed')->count();

        return response()->json([
            'message' => "Batch generation completed: {$successCount} succeeded, {$failedCount} failed",
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failedCount,
            ],
        ]);
    }
}
