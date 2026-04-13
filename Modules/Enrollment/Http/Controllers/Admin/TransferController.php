<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Transfer;
use Modules\Enrollment\Http\Requests\AnalyzeEquivalencesRequest;
use Modules\Enrollment\Http\Requests\RejectTransferRequest;
use Modules\Enrollment\Http\Requests\StoreTransferRequest;
use Modules\Enrollment\Http\Resources\TransferResource;
use Modules\Enrollment\Services\TransferService;

class TransferController extends Controller
{
    public function __construct(
        private TransferService $transferService
    ) {}

    /**
     * Liste des demandes de transfert
     */
    public function index(Request $request)
    {
        $transfers = Transfer::query()
            ->with(['targetProgram', 'academicYear'])
            ->withCount(['equivalences', 'documents'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('transfer_number', 'like', "%{$search}%")
                    ->orWhere('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return TransferResource::collection($transfers);
    }

    /**
     * Créer une demande de transfert (admin)
     */
    public function store(StoreTransferRequest $request): JsonResponse
    {
        try {
            $documents = [];
            if ($request->hasFile('documents')) {
                $documents = $request->file('documents');
            }

            $transfer = $this->transferService->createTransferRequest(
                $request->validated(),
                $documents
            );

            return response()->json([
                'message' => 'Demande de transfert créée avec succès.',
                'data' => new TransferResource($transfer->load(['targetProgram', 'academicYear', 'documents'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Détails d'un transfert
     */
    public function show(int $transfer)
    {
        $transfer = Transfer::with(['targetProgram', 'academicYear', 'equivalences.targetModule', 'documents', 'reviewer', 'student'])
            ->findOrFail($transfer);

        return new TransferResource($transfer);
    }

    /**
     * Démarrer la révision
     */
    public function startReview(Request $request, int $transfer): JsonResponse
    {
        $transfer = Transfer::findOrFail($transfer);

        try {
            $transfer = $this->transferService->startReview($transfer, $request->user());

            return response()->json([
                'message' => 'Révision démarrée.',
                'data' => new TransferResource($transfer),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Analyser et suggérer les équivalences
     */
    public function analyzeEquivalences(AnalyzeEquivalencesRequest $request, int $transfer): JsonResponse
    {
        $transfer = Transfer::findOrFail($transfer);

        try {
            $result = $this->transferService->analyzeEquivalences(
                $transfer,
                $request->origin_modules
            );

            return response()->json([
                'message' => 'Équivalences analysées avec succès.',
                'data' => [
                    'transfer' => new TransferResource($result['transfer']->load(['equivalences.targetModule'])),
                    'suggestions_count' => count($result['suggestions']),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Valider le transfert
     */
    public function validate(Request $request, int $transfer): JsonResponse
    {
        $transfer = Transfer::findOrFail($transfer);

        try {
            $transfer = $this->transferService->validateTransfer($transfer, $request->user());

            return response()->json([
                'message' => 'Transfert validé avec succès.',
                'data' => new TransferResource($transfer),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Intégrer l'étudiant
     */
    public function integrate(Request $request, int $transfer): JsonResponse
    {
        $transfer = Transfer::findOrFail($transfer);

        try {
            $student = $this->transferService->integrateStudent($transfer, $request->user());

            return response()->json([
                'message' => 'Étudiant intégré avec succès.',
                'data' => [
                    'transfer' => new TransferResource($transfer->fresh(['student', 'equivalences'])),
                    'student_matricule' => $student->matricule,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Rejeter le transfert
     */
    public function reject(RejectTransferRequest $request, int $transfer): JsonResponse
    {
        $transfer = Transfer::findOrFail($transfer);

        try {
            $transfer = $this->transferService->rejectTransfer(
                $transfer,
                $request->user(),
                $request->reason
            );

            return response()->json([
                'message' => 'Transfert rejeté.',
                'data' => new TransferResource($transfer),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Télécharger l'attestation d'équivalences
     */
    public function downloadCertificate(int $transfer)
    {
        $transfer = Transfer::findOrFail($transfer);

        if (! $transfer->equivalence_certificate_path) {
            return response()->json([
                'message' => 'Aucune attestation disponible.',
            ], 404);
        }

        if (! Storage::disk('tenant')->exists($transfer->equivalence_certificate_path)) {
            return response()->json([
                'message' => 'Fichier non trouvé.',
            ], 404);
        }

        return Storage::disk('tenant')->download(
            $transfer->equivalence_certificate_path,
            "attestation_equivalences_{$transfer->transfer_number}.pdf"
        );
    }

    /**
     * Statistiques
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->transferService->getStatistics($request->academic_year_id);

        return response()->json([
            'data' => $stats,
        ]);
    }
}
