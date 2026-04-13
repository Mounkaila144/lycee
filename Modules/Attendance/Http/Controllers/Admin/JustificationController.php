<?php

namespace Modules\Attendance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Attendance\Entities\AbsenceJustification;
use Modules\Attendance\Services\JustificationService;

class JustificationController extends Controller
{
    public function __construct(
        private JustificationService $justificationService
    ) {}

    /**
     * Soumettre justificatif (Story 05)
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:tenant.users,id',
            'absence_date_from' => 'required|date',
            'absence_date_to' => 'required|date|after_or_equal:absence_date_from',
            'type' => ['required', 'string', Rule::in(['medical', 'family', 'administrative', 'other'])],
            'reason' => 'required|string|max:1000',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $justification = $this->justificationService->submitJustification(
            $validated['student_id'],
            Carbon::parse($validated['absence_date_from']),
            Carbon::parse($validated['absence_date_to']),
            $validated['type'],
            $validated['reason'],
            $request->file('document')
        );

        return response()->json($justification->load(['student', 'submitter']), 201);
    }

    /**
     * Valider justificatif (Story 06)
     */
    public function validate(Request $request, int $justificationId): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approved', 'rejected'])],
            'notes' => 'nullable|string|max:500',
        ]);

        $justification = $this->justificationService->validateJustification(
            $justificationId,
            $validated['decision'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Justificatif traité avec succès',
            'justification' => $justification->load(['student', 'validator']),
        ]);
    }

    /**
     * Liste justificatifs en attente (Story 06)
     */
    public function pending(): JsonResponse
    {
        $justifications = $this->justificationService->getPendingJustifications();

        return response()->json($justifications);
    }

    /**
     * Justificatifs d'un étudiant
     */
    public function getStudentJustifications(int $studentId): JsonResponse
    {
        $justifications = $this->justificationService->getStudentJustifications($studentId);

        return response()->json($justifications);
    }

    /**
     * Liste tous les justificatifs
     */
    public function index(Request $request): JsonResponse
    {
        $query = AbsenceJustification::with(['student', 'submitter', 'validator']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $justifications = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($justifications);
    }

    /**
     * Télécharger document
     */
    public function downloadDocument(int $justificationId)
    {
        $path = $this->justificationService->downloadDocument($justificationId);

        if (! $path) {
            return response()->json(['message' => 'Aucun document disponible'], 404);
        }

        return response()->download($path);
    }
}
