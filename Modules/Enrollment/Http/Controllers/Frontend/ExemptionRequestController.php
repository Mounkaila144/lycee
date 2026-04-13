<?php

namespace Modules\Enrollment\Http\Controllers\Frontend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\ModuleExemption;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Resources\ModuleExemptionResource;
use Modules\Enrollment\Services\ExemptionService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;

class ExemptionRequestController extends Controller
{
    public function __construct(
        private ExemptionService $exemptionService
    ) {}

    /**
     * Modules disponibles pour demande de dispense
     */
    public function availableModules(Request $request)
    {
        $student = Student::where('email', auth()->user()->email)->firstOrFail();

        // Get current enrollment to know program and level
        $currentEnrollment = $student->enrollments()
            ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))
            ->first();

        if (! $currentEnrollment) {
            return response()->json([
                'message' => 'Aucune inscription active trouvée.',
            ], 404);
        }

        // Get modules for student's current program/level
        $modules = Module::query()
            ->whereHas('programmes', fn ($q) => $q->where('programmes.id', $currentEnrollment->program_id))
            ->where('level', $currentEnrollment->level)
            ->select(['id', 'code', 'name', 'credits_ects', 'type'])
            ->orderBy('name')
            ->get();

        // Filter out modules already exempted
        $exemptedModuleIds = ModuleExemption::where('student_id', $student->id)
            ->whereIn('status', ['Pending', 'Under_Review', 'Approved', 'Partially_Approved'])
            ->pluck('module_id');

        $availableModules = $modules->whereNotIn('id', $exemptedModuleIds)->values();

        return response()->json([
            'data' => $availableModules,
        ]);
    }

    /**
     * Soumettre une demande de dispense
     */
    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'module_id' => ['required', 'exists:modules,id'],
            'exemption_type' => ['required', 'in:Full,Partial,Exemption'],
            'reason_category' => ['required', 'in:VAE,Prior_Training,Professional_Certification,Special_Situation,Double_Degree,Other'],
            'reason_details' => ['required', 'string', 'min:100', 'max:2000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf', 'max:5120'],
        ]);

        $student = Student::where('email', auth()->user()->email)->firstOrFail();
        $module = Module::findOrFail($request->module_id);
        $academicYear = AcademicYear::active()->firstOrFail();

        try {
            // Handle document uploads
            $uploadedDocs = [];
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store("exemptions/{$academicYear->id}", 'tenant');
                    $uploadedDocs[] = [
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ];
                }
            }

            $data = $request->only(['exemption_type', 'reason_category', 'reason_details']);
            $data['uploaded_documents'] = $uploadedDocs;

            $exemption = $this->exemptionService->createExemptionRequest(
                $student,
                $module,
                $academicYear,
                $data
            );

            return response()->json([
                'message' => 'Demande de dispense soumise avec succès. Numéro: '.$exemption->exemption_number,
                'data' => new ModuleExemptionResource($exemption->load(['module', 'academicYear'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mes demandes de dispense
     */
    public function myRequests(Request $request): JsonResponse
    {
        $student = Student::where('email', auth()->user()->email)->first();

        if (! $student) {
            return response()->json([
                'message' => 'Aucun dossier étudiant trouvé.',
            ], 404);
        }

        $exemptions = $this->exemptionService->getStudentExemptions(
            $student->id,
            $request->academic_year_id
        );

        return response()->json([
            'data' => ModuleExemptionResource::collection(collect($exemptions)),
        ]);
    }

    /**
     * Détails d'une de mes demandes
     */
    public function show(int $exemption): JsonResponse
    {
        $exemption = ModuleExemption::findOrFail($exemption);
        $student = Student::where('email', auth()->user()->email)->firstOrFail();

        if ($exemption->student_id !== $student->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        return response()->json([
            'data' => new ModuleExemptionResource($exemption->load(['module', 'academicYear', 'teacherReviewer', 'validator'])),
        ]);
    }

    /**
     * Télécharger mon attestation
     */
    public function downloadCertificate(int $exemption)
    {
        $exemption = ModuleExemption::findOrFail($exemption);
        $student = Student::where('email', auth()->user()->email)->firstOrFail();

        if ($exemption->student_id !== $student->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if (! $exemption->certificate_path) {
            return response()->json([
                'message' => 'Aucune attestation disponible.',
            ], 404);
        }

        return Storage::disk('tenant')->download(
            $exemption->certificate_path,
            "attestation_dispense_{$exemption->exemption_number}.pdf"
        );
    }
}
