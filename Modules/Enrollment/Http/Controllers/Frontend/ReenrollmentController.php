<?php

namespace Modules\Enrollment\Http\Controllers\Frontend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Reenrollment;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Resources\EligibilityCheckResource;
use Modules\Enrollment\Http\Resources\ReenrollmentCampaignResource;
use Modules\Enrollment\Http\Resources\ReenrollmentResource;
use Modules\Enrollment\Services\ReenrollmentService;

class ReenrollmentController extends Controller
{
    public function __construct(
        private ReenrollmentService $reenrollmentService
    ) {}

    /**
     * Campagnes ouvertes pour réinscription
     */
    public function campaigns()
    {
        $campaigns = ReenrollmentCampaign::query()
            ->openForRegistration()
            ->with(['fromAcademicYear', 'toAcademicYear'])
            ->get();

        return ReenrollmentCampaignResource::collection($campaigns);
    }

    /**
     * Vérifier l'éligibilité de l'étudiant connecté
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id' => ['required', 'exists:reenrollment_campaigns,id'],
        ]);

        // Get current student (linked to authenticated user)
        $student = Student::where('email', auth()->user()->email)->firstOrFail();
        $campaign = ReenrollmentCampaign::findOrFail($request->campaign_id);

        $eligibility = $this->reenrollmentService->checkEligibility($student, $campaign);

        return response()->json([
            'data' => new EligibilityCheckResource($eligibility),
        ]);
    }

    /**
     * Créer une réinscription
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id' => ['required', 'exists:reenrollment_campaigns,id'],
            'target_program_id' => ['sometimes', 'exists:programmes,id'],
            'is_redoing' => ['boolean'],
            'personal_data_updates' => ['nullable', 'array'],
        ]);

        $student = Student::where('email', auth()->user()->email)->firstOrFail();
        $campaign = ReenrollmentCampaign::findOrFail($request->campaign_id);

        if (! $campaign->isOpen()) {
            return response()->json([
                'message' => 'La campagne de réinscription n\'est pas ouverte.',
            ], 422);
        }

        try {
            $reenrollment = $this->reenrollmentService->createReenrollment(
                $student,
                $campaign,
                $request->only(['target_program_id', 'is_redoing', 'personal_data_updates'])
            );

            return response()->json([
                'message' => 'Réinscription créée avec succès.',
                'data' => new ReenrollmentResource($reenrollment->load(['campaign', 'targetProgram'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mettre à jour ma réinscription
     */
    public function update(Request $request, int $reenrollment): JsonResponse
    {
        $reenrollment = Reenrollment::findOrFail($reenrollment);

        // Verify ownership
        $student = Student::where('email', auth()->user()->email)->firstOrFail();

        if ($reenrollment->student_id !== $student->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if (! $reenrollment->isDraft()) {
            return response()->json([
                'message' => 'Seule une réinscription en brouillon peut être modifiée.',
            ], 422);
        }

        try {
            $reenrollment = $this->reenrollmentService->updateReenrollment(
                $reenrollment,
                $request->only(['target_program_id', 'is_redoing', 'personal_data_updates', 'uploaded_documents', 'has_accepted_rules'])
            );

            return response()->json([
                'message' => 'Réinscription mise à jour.',
                'data' => new ReenrollmentResource($reenrollment),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Soumettre ma réinscription
     */
    public function submit(Request $request, int $reenrollment): JsonResponse
    {
        $reenrollment = Reenrollment::findOrFail($reenrollment);

        $request->validate([
            'has_accepted_rules' => ['required', 'accepted'],
        ]);

        // Verify ownership
        $student = Student::where('email', auth()->user()->email)->firstOrFail();

        if ($reenrollment->student_id !== $student->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // Update rules acceptance
        $reenrollment->update(['has_accepted_rules' => true]);

        try {
            $reenrollment = $this->reenrollmentService->submitReenrollment($reenrollment);

            return response()->json([
                'message' => 'Réinscription soumise avec succès.',
                'data' => new ReenrollmentResource($reenrollment),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mon statut de réinscription
     */
    public function myStatus(): JsonResponse
    {
        $student = Student::where('email', auth()->user()->email)->first();

        if (! $student) {
            return response()->json([
                'message' => 'Aucun dossier étudiant trouvé.',
            ], 404);
        }

        $reenrollments = Reenrollment::where('student_id', $student->id)
            ->with(['campaign', 'targetProgram'])
            ->latest()
            ->get();

        return response()->json([
            'data' => ReenrollmentResource::collection($reenrollments),
        ]);
    }

    /**
     * Détails de ma réinscription
     */
    public function show(int $reenrollment): JsonResponse
    {
        $reenrollment = Reenrollment::findOrFail($reenrollment);
        $student = Student::where('email', auth()->user()->email)->firstOrFail();

        if ($reenrollment->student_id !== $student->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        return response()->json([
            'data' => new ReenrollmentResource($reenrollment->load(['campaign', 'targetProgram', 'previousEnrollment'])),
        ]);
    }

    /**
     * Télécharger ma confirmation
     */
    public function downloadConfirmation(int $reenrollment)
    {
        $reenrollment = Reenrollment::findOrFail($reenrollment);
        $student = Student::where('email', auth()->user()->email)->firstOrFail();

        if ($reenrollment->student_id !== $student->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if (! $reenrollment->confirmation_pdf_path) {
            return response()->json([
                'message' => 'Aucun PDF de confirmation disponible.',
            ], 404);
        }

        return Storage::disk('tenant')->download(
            $reenrollment->confirmation_pdf_path,
            "confirmation_reinscription_{$student->matricule}.pdf"
        );
    }
}
