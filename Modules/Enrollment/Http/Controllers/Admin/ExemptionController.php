<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\ModuleExemption;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Requests\RevokeExemptionRequest;
use Modules\Enrollment\Http\Requests\StoreExemptionRequest;
use Modules\Enrollment\Http\Requests\TeacherReviewExemptionRequest;
use Modules\Enrollment\Http\Requests\ValidateExemptionRequest;
use Modules\Enrollment\Http\Resources\ModuleExemptionResource;
use Modules\Enrollment\Services\ExemptionService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;

class ExemptionController extends Controller
{
    public function __construct(
        private ExemptionService $exemptionService
    ) {}

    /**
     * Liste des demandes de dispense
     */
    public function index(Request $request)
    {
        $exemptions = ModuleExemption::query()
            ->with(['student', 'module', 'academicYear'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->reason_category, fn ($q, $cat) => $q->where('reason_category', $cat))
            ->when($request->student_id, fn ($q, $id) => $q->where('student_id', $id))
            ->when($request->module_id, fn ($q, $id) => $q->where('module_id', $id))
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('exemption_number', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($ssq) use ($search) {
                        $ssq->where('matricule', 'like', "%{$search}%")
                            ->orWhere('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%");
                    });
            }))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return ModuleExemptionResource::collection($exemptions);
    }

    /**
     * Créer une demande de dispense (admin)
     */
    public function store(StoreExemptionRequest $request): JsonResponse
    {
        $student = Student::findOrFail($request->student_id);
        $module = Module::findOrFail($request->module_id);
        $academicYear = AcademicYear::findOrFail($request->academic_year_id);

        try {
            // Handle document uploads
            $uploadedDocs = [];
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $index => $file) {
                    $path = $file->store("exemptions/{$academicYear->id}", 'tenant');
                    $uploadedDocs[] = [
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ];
                }
            }

            $data = $request->validated();
            $data['uploaded_documents'] = $uploadedDocs;

            $exemption = $this->exemptionService->createExemptionRequest(
                $student,
                $module,
                $academicYear,
                $data
            );

            return response()->json([
                'message' => 'Demande de dispense créée avec succès.',
                'data' => new ModuleExemptionResource($exemption->load(['student', 'module', 'academicYear'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Détails d'une dispense
     */
    public function show(int $exemption)
    {
        $exemption = ModuleExemption::findOrFail($exemption);

        return new ModuleExemptionResource(
            $exemption->load(['student', 'module', 'academicYear', 'teacherReviewer', 'validator'])
        );
    }

    /**
     * Avis de l'enseignant
     */
    public function teacherReview(TeacherReviewExemptionRequest $request, int $exemption): JsonResponse
    {
        $exemption = ModuleExemption::findOrFail($exemption);

        try {
            $exemption = $this->exemptionService->teacherReview(
                $exemption,
                $request->user(),
                $request->opinion
            );

            return response()->json([
                'message' => 'Avis enregistré avec succès.',
                'data' => new ModuleExemptionResource($exemption),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Valider/Rejeter une dispense
     */
    public function validate(ValidateExemptionRequest $request, int $exemption): JsonResponse
    {
        $exemption = ModuleExemption::findOrFail($exemption);

        try {
            $exemption = $this->exemptionService->validateExemption(
                $exemption,
                $request->user(),
                $request->decision,
                $request->only(['notes', 'grade', 'rejection_reason'])
            );

            return response()->json([
                'message' => $request->decision === 'Rejected'
                    ? 'Dispense rejetée.'
                    : 'Dispense validée avec succès.',
                'data' => new ModuleExemptionResource($exemption),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Révoquer une dispense
     */
    public function revoke(RevokeExemptionRequest $request, int $exemption): JsonResponse
    {
        $exemption = ModuleExemption::findOrFail($exemption);

        try {
            $exemption = $this->exemptionService->revokeExemption(
                $exemption,
                $request->user(),
                $request->reason
            );

            return response()->json([
                'message' => 'Dispense révoquée.',
                'data' => new ModuleExemptionResource($exemption),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Liste des dispenses en attente
     */
    public function pending(Request $request): JsonResponse
    {
        $pending = $this->exemptionService->getPendingExemptions($request->academic_year_id);

        return response()->json([
            'data' => ModuleExemptionResource::collection(collect($pending)),
        ]);
    }

    /**
     * Télécharger l'attestation
     */
    public function downloadCertificate(int $exemption)
    {
        $exemption = ModuleExemption::findOrFail($exemption);

        if (! $exemption->certificate_path) {
            return response()->json([
                'message' => 'Aucune attestation disponible.',
            ], 404);
        }

        if (! Storage::disk('tenant')->exists($exemption->certificate_path)) {
            return response()->json([
                'message' => 'Fichier non trouvé.',
            ], 404);
        }

        return Storage::disk('tenant')->download(
            $exemption->certificate_path,
            "attestation_dispense_{$exemption->exemption_number}.pdf"
        );
    }

    /**
     * Statistiques
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->exemptionService->getStatistics($request->academic_year_id);

        return response()->json([
            'data' => $stats,
        ]);
    }
}
