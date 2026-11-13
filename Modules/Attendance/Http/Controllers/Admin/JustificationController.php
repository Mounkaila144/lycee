<?php

namespace Modules\Attendance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Modules\Attendance\Entities\AbsenceJustification;
use Modules\Attendance\Http\Requests\SubmitJustificationRequest;
use Modules\Attendance\Http\Requests\ValidateJustificationRequest;
use Modules\Attendance\Services\AttendanceAccessService;
use Modules\Attendance\Services\JustificationService;
use Modules\UsersGuard\Entities\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JustificationController extends Controller
{
    public function __construct(
        private JustificationService $justificationService,
        private AttendanceAccessService $accessService
    ) {}

    /**
     * Soumettre justificatif côté administration (Story 05) — `student_id` = users.id de l'élève.
     */
    public function submit(SubmitJustificationRequest $request): JsonResponse
    {
        $this->ensureJustificationsTable();

        $validated = $request->validated();

        if (empty($validated['student_id']) || ! User::query()->whereKey($validated['student_id'])->exists()) {
            return response()->json([
                'message' => 'L\'élève sélectionné est introuvable.',
                'errors' => ['student_id' => ['L\'élève sélectionné est introuvable.']],
            ], 422);
        }

        if (! $this->accessService->canViewStudent($request->user(), (int) $validated['student_id'])) {
            abort(403, 'Vous ne pouvez pas soumettre de justificatif pour cet élève.');
        }

        $justification = $this->justificationService->submitJustification(
            (int) $validated['student_id'],
            Carbon::parse($validated['absence_date_from']),
            Carbon::parse($validated['absence_date_to']),
            $validated['type'],
            $validated['reason'],
            $request->file('document'),
            (int) $request->user()->getAuthIdentifier()
        );

        return response()->json($justification->load(['student', 'submitter']), 201);
    }

    /**
     * Valider / refuser un justificatif (Story 06) — Administrator / Manager uniquement.
     */
    public function validate(ValidateJustificationRequest $request, int $justificationId): JsonResponse
    {
        if (! $this->accessService->isSupervisor($request->user())) {
            abort(403, 'Seule l\'administration peut valider les justificatifs.');
        }

        $this->ensureJustificationsTable();

        $justification = AbsenceJustification::query()->findOrFail($justificationId);

        if ($justification->status !== 'pending') {
            return response()->json([
                'message' => 'Ce justificatif a déjà été traité.',
            ], 422);
        }

        $validated = $request->validated();

        $justification = $this->justificationService->validateJustification(
            $justification,
            $validated['decision'],
            $validated['notes'] ?? null,
            (int) $request->user()->getAuthIdentifier()
        );

        return response()->json([
            'message' => 'Justificatif traité avec succès',
            'justification' => $justification->load(['student', 'validator']),
        ]);
    }

    /**
     * Liste justificatifs en attente (Story 06)
     */
    public function pending(Request $request): JsonResponse
    {
        if (! $this->accessService->justificationsTableExists()) {
            return response()->json([]);
        }

        $justifications = $this->justificationService->getPendingJustifications(
            $this->accessService->visibleStudentUserIds($request->user())
        );

        return response()->json($justifications);
    }

    /**
     * Justificatifs d'un élève (users.id)
     */
    public function getStudentJustifications(Request $request, int $studentId): JsonResponse
    {
        if (! $this->accessService->canViewStudent($request->user(), $studentId)) {
            abort(403, 'Vous ne pouvez pas consulter les justificatifs de cet élève.');
        }

        if (! $this->accessService->justificationsTableExists()) {
            return response()->json([]);
        }

        return response()->json($this->justificationService->getStudentJustifications($studentId));
    }

    /**
     * Liste tous les justificatifs
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->accessService->justificationsTableExists()) {
            return response()->json(new LengthAwarePaginator([], 0, 15));
        }

        $query = AbsenceJustification::with(['student', 'submitter', 'validator']);

        $visibleStudentIds = $this->accessService->visibleStudentUserIds($request->user());
        if ($visibleStudentIds !== null) {
            $query->whereIn('student_id', $visibleStudentIds);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $justifications = $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 15));

        return response()->json($justifications);
    }

    /**
     * Télécharger document
     */
    public function downloadDocument(Request $request, int $justificationId): JsonResponse|BinaryFileResponse
    {
        $this->ensureJustificationsTable();

        $justification = AbsenceJustification::query()->findOrFail($justificationId);

        if (! $this->accessService->canViewJustification($request->user(), $justification)) {
            abort(403, 'Vous ne pouvez pas consulter ce justificatif.');
        }

        $path = $this->justificationService->downloadDocument($justification);

        if (! $path || ! is_file($path)) {
            return response()->json(['message' => 'Aucun document disponible'], 404);
        }

        return response()->download($path);
    }

    private function ensureJustificationsTable(): void
    {
        if (! $this->accessService->justificationsTableExists()) {
            abort(503, 'Justificatifs indisponibles : migration en attente. Réessayez plus tard.');
        }
    }
}
