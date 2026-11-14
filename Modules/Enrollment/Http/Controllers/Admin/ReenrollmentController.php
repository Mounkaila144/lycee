<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Reenrollment;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Http\Requests\RejectReenrollmentRequest;
use Modules\Enrollment\Http\Resources\ReenrollmentResource;
use Modules\Enrollment\Services\ReenrollmentService;

class ReenrollmentController extends Controller
{
    public function __construct(
        private ReenrollmentService $reenrollmentService
    ) {}

    /**
     * Liste des réinscriptions
     */
    public function index(Request $request)
    {
        $reenrollments = Reenrollment::on('tenant')
            ->with(['student', 'campaign', 'targetProgram'])
            ->when($request->campaign_id, fn ($q, $id) => $q->where('campaign_id', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->eligibility_status, fn ($q, $status) => $q->where('eligibility_status', $status))
            ->when($request->is_redoing, fn ($q) => $q->where('is_redoing', true))
            ->when($request->is_reorientation, fn ($q) => $q->where('is_reorientation', true))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', function ($sq) use ($search) {
                $sq->where('matricule', 'like', "%{$search}%")
                    ->orWhere('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return ReenrollmentResource::collection($reenrollments);
    }

    /**
     * Détails d'une réinscription
     */
    public function show(int $id): JsonResponse
    {
        $reenrollment = Reenrollment::on('tenant')
            ->with(['student', 'campaign', 'targetProgram', 'previousEnrollment', 'validator'])
            ->findOrFail($id);

        return response()->json([
            'data' => new ReenrollmentResource($reenrollment),
        ]);
    }

    /**
     * Rejeter une réinscription
     */
    public function reject(RejectReenrollmentRequest $request, int $id): JsonResponse
    {
        $reenrollment = Reenrollment::on('tenant')->findOrFail($id);

        try {
            $reenrollment = $this->reenrollmentService->rejectReenrollment(
                $reenrollment,
                $request->user(),
                $request->reason
            );

            return response()->json([
                'message' => 'Réinscription rejetée.',
                'data' => new ReenrollmentResource($reenrollment),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Télécharger la confirmation PDF
     */
    public function downloadConfirmation(int $id)
    {
        $reenrollment = Reenrollment::on('tenant')
            ->with('student')
            ->findOrFail($id);

        if (! $reenrollment->confirmation_pdf_path) {
            return response()->json([
                'message' => 'Aucun PDF de confirmation disponible.',
            ], 404);
        }

        if (! Storage::disk('tenant')->exists($reenrollment->confirmation_pdf_path)) {
            return response()->json([
                'message' => 'Fichier PDF non trouvé.',
            ], 404);
        }

        return Storage::disk('tenant')->download(
            $reenrollment->confirmation_pdf_path,
            "confirmation_reinscription_{$reenrollment->student->matricule}.pdf"
        );
    }

    /**
     * Statistiques globales
     */
    public function statistics(Request $request): JsonResponse
    {
        if ($request->campaign_id) {
            $campaign = ReenrollmentCampaign::on('tenant')->findOrFail($request->campaign_id);
            $stats = $this->reenrollmentService->getCampaignStatistics($campaign);
        } else {
            $stats = [
                'total' => Reenrollment::on('tenant')->count(),
                'by_status' => [
                    'draft' => Reenrollment::on('tenant')->draft()->count(),
                    'submitted' => Reenrollment::on('tenant')->submitted()->count(),
                    'validated' => Reenrollment::on('tenant')->validated()->count(),
                    'rejected' => Reenrollment::on('tenant')->rejected()->count(),
                ],
            ];
        }

        return response()->json([
            'data' => $stats,
        ]);
    }
}
