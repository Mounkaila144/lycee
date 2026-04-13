<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\Equivalence;
use Modules\Enrollment\Entities\Transfer;
use Modules\Enrollment\Http\Requests\BatchValidateEquivalencesRequest;
use Modules\Enrollment\Http\Requests\ValidateEquivalenceRequest;
use Modules\Enrollment\Http\Resources\EquivalenceResource;
use Modules\Enrollment\Services\EquivalenceMatchingService;

class EquivalenceController extends Controller
{
    public function __construct(
        private EquivalenceMatchingService $equivalenceService
    ) {}

    /**
     * Liste des équivalences d'un transfert
     */
    public function index(Request $request, int $transfer)
    {
        $transfer = Transfer::findOrFail($transfer);

        $equivalences = Equivalence::where('transfer_id', $transfer->id)
            ->with('targetModule')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->type, fn ($q, $type) => $q->where('equivalence_type', $type))
            ->orderBy('origin_module_name')
            ->get();

        return EquivalenceResource::collection($equivalences);
    }

    /**
     * Détails d'une équivalence
     */
    public function show(int $equivalence)
    {
        $equivalence = Equivalence::findOrFail($equivalence);

        return new EquivalenceResource(
            $equivalence->load(['transfer', 'targetModule'])
        );
    }

    /**
     * Créer une équivalence manuelle
     */
    public function store(Request $request, int $transfer): JsonResponse
    {
        $transfer = Transfer::findOrFail($transfer);

        $request->validate([
            'origin_module_code' => ['nullable', 'string', 'max:50'],
            'origin_module_name' => ['required', 'string', 'max:255'],
            'origin_ects' => ['nullable', 'integer', 'min:0'],
            'origin_hours' => ['nullable', 'integer', 'min:0'],
            'origin_grade' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'target_module_id' => ['nullable', 'exists:modules,id'],
            'equivalence_type' => ['required', 'in:Full,Partial,None,Exemption'],
            'equivalence_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'granted_ects' => ['nullable', 'integer', 'min:0'],
            'granted_grade' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $equivalence = $this->equivalenceService->createManualEquivalence(
                $transfer,
                $request->all()
            );

            return response()->json([
                'message' => 'Équivalence créée avec succès.',
                'data' => new EquivalenceResource($equivalence->load('targetModule')),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mettre à jour une équivalence
     */
    public function update(ValidateEquivalenceRequest $request, int $equivalence): JsonResponse
    {
        $equivalence = Equivalence::findOrFail($equivalence);

        try {
            $equivalence = $this->equivalenceService->updateEquivalence(
                $equivalence,
                $request->validated()
            );

            return response()->json([
                'message' => 'Équivalence mise à jour.',
                'data' => new EquivalenceResource($equivalence->load('targetModule')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Valider une équivalence
     */
    public function validate(int $equivalence): JsonResponse
    {
        $equivalence = Equivalence::findOrFail($equivalence);

        try {
            $equivalence = $this->equivalenceService->validateEquivalence($equivalence);

            return response()->json([
                'message' => 'Équivalence validée.',
                'data' => new EquivalenceResource($equivalence),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Rejeter une équivalence
     */
    public function reject(Request $request, int $equivalence): JsonResponse
    {
        $equivalence = Equivalence::findOrFail($equivalence);

        try {
            $equivalence = $this->equivalenceService->rejectEquivalence(
                $equivalence,
                $request->notes
            );

            return response()->json([
                'message' => 'Équivalence rejetée.',
                'data' => new EquivalenceResource($equivalence),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Validation en masse
     */
    public function batchValidate(BatchValidateEquivalencesRequest $request, int $transfer): JsonResponse
    {
        $transfer = Transfer::findOrFail($transfer);

        $result = $this->equivalenceService->batchValidateEquivalences(
            $transfer,
            $request->equivalence_ids
        );

        return response()->json([
            'message' => count($result['validated']).' équivalence(s) validée(s)',
            'data' => [
                'validated_count' => count($result['validated']),
                'errors' => $result['errors'],
            ],
        ]);
    }

    /**
     * Supprimer une équivalence
     */
    public function destroy(int $equivalence): JsonResponse
    {
        $equivalence = Equivalence::findOrFail($equivalence);

        if ($equivalence->isValidated()) {
            return response()->json([
                'message' => 'Impossible de supprimer une équivalence validée.',
            ], 422);
        }

        $equivalence->delete();

        return response()->json([
            'message' => 'Équivalence supprimée.',
        ]);
    }
}
