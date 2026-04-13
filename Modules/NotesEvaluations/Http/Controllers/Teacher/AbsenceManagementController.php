<?php

namespace Modules\NotesEvaluations\Http\Controllers\Teacher;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\GradeAbsence;
use Modules\NotesEvaluations\Entities\ReplacementEvaluation;
use Modules\NotesEvaluations\Http\Resources\GradeResource;
use Modules\NotesEvaluations\Services\AbsencePolicyService;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;

class AbsenceManagementController extends Controller
{
    public function __construct(
        private AbsencePolicyService $absenceService
    ) {}

    /**
     * List absences for an evaluation
     */
    public function index(ModuleEvaluationConfig $evaluation): JsonResponse
    {
        $absences = GradeAbsence::with(['grade.student', 'justification'])
            ->whereHas('grade', fn ($q) => $q->where('evaluation_id', $evaluation->id))
            ->get();

        return response()->json([
            'data' => $absences->map(fn ($absence) => [
                'id' => $absence->id,
                'student' => [
                    'id' => $absence->grade->student->id,
                    'firstname' => $absence->grade->student->firstname,
                    'lastname' => $absence->grade->student->lastname,
                    'matricule' => $absence->grade->student->matricule,
                ],
                'absence_type' => $absence->absence_type,
                'justification_deadline' => $absence->justification_deadline?->toIso8601String(),
                'has_justification' => $absence->justification !== null,
                'justification_status' => $absence->justification?->status,
                'created_at' => $absence->created_at->toIso8601String(),
            ]),
            'statistics' => $this->absenceService->getAbsenceStatistics($evaluation),
            'policy' => $this->absenceService->getPolicy($evaluation),
        ]);
    }

    /**
     * Mark students as absent
     */
    public function markAbsent(Request $request, ModuleEvaluationConfig $evaluation): JsonResponse
    {
        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|integer|exists:tenant.students,id',
            'absence_type' => 'sometimes|string|in:unjustified,medical,other',
        ]);

        $teacher = $request->user();

        $results = $this->absenceService->bulkMarkAbsent(
            $evaluation,
            $validated['student_ids'],
            $teacher,
            $validated['absence_type'] ?? 'unjustified'
        );

        return response()->json([
            'message' => "{$results['marked']} étudiant(s) marqué(s) absent(s).",
            'marked_count' => $results['marked'],
            'errors' => $results['errors'],
        ]);
    }

    /**
     * Get absence policy for evaluation's module
     */
    public function policy(ModuleEvaluationConfig $evaluation): JsonResponse
    {
        return response()->json([
            'data' => $this->absenceService->getPolicy($evaluation),
        ]);
    }

    /**
     * Get absence statistics for an evaluation
     */
    public function statistics(ModuleEvaluationConfig $evaluation): JsonResponse
    {
        return response()->json([
            'data' => $this->absenceService->getAbsenceStatistics($evaluation),
        ]);
    }

    /**
     * List replacement evaluations for an evaluation
     */
    public function replacements(ModuleEvaluationConfig $evaluation): JsonResponse
    {
        $replacements = ReplacementEvaluation::with(['student', 'grade'])
            ->where('original_evaluation_id', $evaluation->id)
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'data' => $replacements->map(fn ($r) => [
                'id' => $r->id,
                'student' => [
                    'id' => $r->student->id,
                    'firstname' => $r->student->firstname,
                    'lastname' => $r->student->lastname,
                    'matricule' => $r->student->matricule,
                ],
                'scheduled_at' => $r->scheduled_at->toIso8601String(),
                'location' => $r->location,
                'type' => $r->type,
                'status' => $r->status,
                'has_grade' => $r->hasGrade(),
                'convocation_sent_at' => $r->convocation_sent_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Schedule a replacement evaluation
     */
    public function scheduleReplacement(Request $request, ModuleEvaluationConfig $evaluation): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:tenant.students,id',
            'scheduled_at' => 'required|date|after:now',
            'location' => 'nullable|string|max:255',
            'type' => 'sometimes|string|in:same,alternative',
            'comment' => 'nullable|string|max:1000',
        ]);

        $student = \Modules\Enrollment\Entities\Student::on('tenant')
            ->findOrFail($validated['student_id']);

        $replacement = $this->absenceService->scheduleReplacementEvaluation(
            $evaluation,
            $student,
            new \DateTime($validated['scheduled_at']),
            $validated['location'] ?? null,
            $validated['type'] ?? 'same',
            $validated['comment'] ?? null
        );

        return response()->json([
            'message' => 'Évaluation de remplacement programmée avec succès.',
            'data' => [
                'id' => $replacement->id,
                'student_id' => $replacement->student_id,
                'scheduled_at' => $replacement->scheduled_at->toIso8601String(),
                'location' => $replacement->location,
                'type' => $replacement->type,
                'status' => $replacement->status,
            ],
        ], 201);
    }

    /**
     * Cancel a replacement evaluation
     */
    public function cancelReplacement(Request $request, ReplacementEvaluation $replacement): JsonResponse
    {
        if (! $replacement->isScheduled()) {
            return response()->json([
                'message' => 'Cette évaluation ne peut plus être annulée.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $replacement->cancel($validated['reason'] ?? null);

        return response()->json([
            'message' => 'Évaluation de remplacement annulée.',
        ]);
    }

    /**
     * Record grade for replacement evaluation
     */
    public function recordReplacementGrade(Request $request, ReplacementEvaluation $replacement): JsonResponse
    {
        if (! $replacement->isScheduled()) {
            return response()->json([
                'message' => 'Cette évaluation n\'est pas en attente de note.',
            ], 422);
        }

        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:20',
            'comment' => 'nullable|string|max:500',
        ]);

        $evaluation = $replacement->originalEvaluation;
        $teacher = $request->user();

        // Create or update grade
        $grade = \Modules\NotesEvaluations\Entities\Grade::updateOrCreate(
            [
                'student_id' => $replacement->student_id,
                'evaluation_id' => $evaluation->id,
            ],
            [
                'score' => $validated['score'],
                'is_absent' => false,
                'entered_by' => $teacher->id,
                'entered_at' => now(),
                'comment' => $validated['comment'] ?? null,
                'status' => 'Draft',
            ]
        );

        // Mark absence as resolved if exists
        if ($grade->absence) {
            $grade->absence->delete();
        }

        // Complete replacement
        $replacement->complete($grade);

        return response()->json([
            'message' => 'Note de remplacement enregistrée.',
            'data' => new GradeResource($grade),
        ]);
    }
}
