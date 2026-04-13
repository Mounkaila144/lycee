<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Http\Requests\StoreReenrollmentCampaignRequest;
use Modules\Enrollment\Http\Requests\UpdateReenrollmentCampaignRequest;
use Modules\Enrollment\Http\Resources\ReenrollmentCampaignResource;
use Modules\Enrollment\Services\ReenrollmentService;

class ReenrollmentCampaignController extends Controller
{
    public function __construct(
        private ReenrollmentService $reenrollmentService
    ) {}

    /**
     * Liste des campagnes de réinscription
     */
    public function index(Request $request)
    {
        $campaigns = ReenrollmentCampaign::on('tenant')
            ->with(['fromAcademicYear', 'toAcademicYear'])
            ->withCount('reenrollments')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('to_academic_year_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return ReenrollmentCampaignResource::collection($campaigns);
    }

    /**
     * Créer une campagne
     */
    public function store(StoreReenrollmentCampaignRequest $request): JsonResponse
    {
        $campaign = ReenrollmentCampaign::on('tenant')->create($request->validated());

        return response()->json([
            'message' => 'Campagne de réinscription créée avec succès.',
            'data' => new ReenrollmentCampaignResource($campaign->load(['fromAcademicYear', 'toAcademicYear'])),
        ], 201);
    }

    /**
     * Détails d'une campagne
     */
    public function show(int $id): JsonResponse
    {
        $campaign = ReenrollmentCampaign::on('tenant')
            ->with(['fromAcademicYear', 'toAcademicYear'])
            ->findOrFail($id);

        return response()->json([
            'data' => new ReenrollmentCampaignResource($campaign),
        ]);
    }

    /**
     * Mettre à jour une campagne
     */
    public function update(UpdateReenrollmentCampaignRequest $request, int $id): JsonResponse
    {
        $campaign = ReenrollmentCampaign::on('tenant')->findOrFail($id);
        $campaign->update($request->validated());

        return response()->json([
            'message' => 'Campagne mise à jour avec succès.',
            'data' => new ReenrollmentCampaignResource($campaign->fresh(['fromAcademicYear', 'toAcademicYear'])),
        ]);
    }

    /**
     * Supprimer une campagne
     */
    public function destroy(int $id): JsonResponse
    {
        $campaign = ReenrollmentCampaign::on('tenant')->findOrFail($id);

        if ($campaign->reenrollments()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une campagne avec des réinscriptions.',
            ], 422);
        }

        $campaign->delete();

        return response()->json([
            'message' => 'Campagne supprimée avec succès.',
        ]);
    }

    /**
     * Activer une campagne
     */
    public function activate(int $id): JsonResponse
    {
        $campaign = ReenrollmentCampaign::on('tenant')->findOrFail($id);

        if ($campaign->status !== ReenrollmentCampaign::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Seule une campagne en brouillon peut être activée.',
            ], 422);
        }

        $campaign->update(['status' => ReenrollmentCampaign::STATUS_ACTIVE]);

        return response()->json([
            'message' => 'Campagne activée avec succès.',
            'data' => new ReenrollmentCampaignResource($campaign->fresh()),
        ]);
    }

    /**
     * Clôturer une campagne
     */
    public function close(int $id): JsonResponse
    {
        $campaign = ReenrollmentCampaign::on('tenant')->findOrFail($id);

        if ($campaign->status !== ReenrollmentCampaign::STATUS_ACTIVE) {
            return response()->json([
                'message' => 'Seule une campagne active peut être clôturée.',
            ], 422);
        }

        $campaign->update(['status' => ReenrollmentCampaign::STATUS_CLOSED]);

        return response()->json([
            'message' => 'Campagne clôturée avec succès.',
            'data' => new ReenrollmentCampaignResource($campaign->fresh()),
        ]);
    }

    /**
     * Statistiques d'une campagne
     */
    public function statistics(int $id): JsonResponse
    {
        $campaign = ReenrollmentCampaign::on('tenant')->findOrFail($id);
        $stats = $this->reenrollmentService->getCampaignStatistics($campaign);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Liste des étudiants éligibles
     */
    public function eligibleStudents(int $id): JsonResponse
    {
        $campaign = ReenrollmentCampaign::on('tenant')->findOrFail($id);
        $result = $this->reenrollmentService->getEligibleStudents($campaign);

        return response()->json([
            'data' => $result,
        ]);
    }
}
