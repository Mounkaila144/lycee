<?php

namespace Modules\Enrollment\Http\Controllers\Frontend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\Transfer;
use Modules\Enrollment\Http\Requests\StoreTransferRequest;
use Modules\Enrollment\Http\Resources\TransferResource;
use Modules\Enrollment\Services\TransferService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;

class TransferRequestController extends Controller
{
    public function __construct(
        private TransferService $transferService
    ) {}

    /**
     * Programmes disponibles pour transfert
     */
    public function availablePrograms()
    {
        $programs = Programme::query()
            ->where('status', 'Active')
            ->select(['id', 'code', 'name', 'level', 'department_id'])
            ->with('department:id,name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $programs,
        ]);
    }

    /**
     * Année académique active
     */
    public function activeAcademicYear()
    {
        $year = AcademicYear::active()->first();

        return response()->json([
            'data' => $year,
        ]);
    }

    /**
     * Soumettre une demande de transfert
     */
    public function request(StoreTransferRequest $request): JsonResponse
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
                'message' => 'Demande de transfert soumise avec succès. Numéro: '.$transfer->transfer_number,
                'data' => new TransferResource($transfer),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Vérifier le statut de ma demande
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $request->validate([
            'transfer_number' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $transfer = Transfer::where('transfer_number', $request->transfer_number)
            ->where('email', $request->email)
            ->first();

        if (! $transfer) {
            return response()->json([
                'message' => 'Demande non trouvée. Vérifiez le numéro et l\'email.',
            ], 404);
        }

        return response()->json([
            'data' => new TransferResource($transfer->load(['targetProgram', 'academicYear'])),
        ]);
    }

    /**
     * Mes demandes de transfert (si authentifié)
     */
    public function myRequests(): JsonResponse
    {
        $email = auth()->user()->email;

        $transfers = Transfer::where('email', $email)
            ->with(['targetProgram', 'academicYear'])
            ->latest()
            ->get();

        return response()->json([
            'data' => TransferResource::collection($transfers),
        ]);
    }
}
