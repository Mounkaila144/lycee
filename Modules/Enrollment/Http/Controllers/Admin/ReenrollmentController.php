<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Reenrollment;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Requests\CreateReenrollmentRequest;
use Modules\Enrollment\Http\Requests\RejectReenrollmentRequest;
use Modules\Enrollment\Http\Resources\EligibilityCheckResource;
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
     * Créer une réinscription (admin)
     */
    public function store(CreateReenrollmentRequest $request): JsonResponse
    {
        $student = Student::on('tenant')->findOrFail($request->student_id);
        $campaign = ReenrollmentCampaign::on('tenant')->findOrFail($request->campaign_id);

        try {
            $reenrollment = $this->reenrollmentService->createReenrollment(
                $student,
                $campaign,
                $request->validated()
            );

            return response()->json([
                'message' => 'Réinscription créée avec succès.',
                'data' => new ReenrollmentResource($reenrollment->load(['student', 'campaign', 'targetProgram'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
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
     * Vérifier l'éligibilité d'un étudiant
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer'],
            'campaign_id' => ['required', 'integer'],
        ]);

        $student = Student::on('tenant')->findOrFail($request->student_id);
        $campaign = ReenrollmentCampaign::on('tenant')->findOrFail($request->campaign_id);

        $eligibility = $this->reenrollmentService->checkEligibility($student, $campaign);

        return response()->json([
            'data' => new EligibilityCheckResource($eligibility),
        ]);
    }

    /**
     * Valider une réinscription
     */
    public function validate(int $id): JsonResponse
    {
        $reenrollment = Reenrollment::on('tenant')->findOrFail($id);

        try {
            $reenrollment = $this->reenrollmentService->validateReenrollment(
                $reenrollment,
                auth()->user()
            );

            return response()->json([
                'message' => 'Réinscription validée avec succès.',
                'data' => new ReenrollmentResource($reenrollment->load(['student', 'campaign', 'newEnrollment'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
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
                auth()->user(),
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
     * Validation en masse
     */
    public function batchValidate(Request $request): JsonResponse
    {
        $request->validate([
            'reenrollment_ids' => ['required', 'array', 'min:1'],
            'reenrollment_ids.*' => ['integer'],
        ]);

        $validated = [];
        $errors = [];

        foreach ($request->reenrollment_ids as $id) {
            $reenrollment = Reenrollment::on('tenant')->find($id);

            if (! $reenrollment) {
                $errors[] = "Réinscription {$id} non trouvée";

                continue;
            }

            try {
                $this->reenrollmentService->validateReenrollment($reenrollment, auth()->user());
                $validated[] = $id;
            } catch (\Exception $e) {
                $errors[] = "Réinscription {$id}: {$e->getMessage()}";
            }
        }

        return response()->json([
            'message' => count($validated).' réinscription(s) validée(s)',
            'data' => [
                'validated' => $validated,
                'errors' => $errors,
            ],
        ]);
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
