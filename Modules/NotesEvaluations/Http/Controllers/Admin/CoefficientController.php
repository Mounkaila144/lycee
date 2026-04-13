<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Exceptions\CoefficientLockedException;
use Modules\NotesEvaluations\Http\Requests\StoreCoefficientTemplateRequest;
use Modules\NotesEvaluations\Http\Requests\UpdateCoefficientRequest;
use Modules\NotesEvaluations\Http\Requests\UpdateCreditsRequest;
use Modules\NotesEvaluations\Http\Resources\CoefficientTemplateResource;
use Modules\NotesEvaluations\Services\CoefficientManagementService;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;

class CoefficientController extends Controller
{
    public function __construct(
        protected CoefficientManagementService $coefficientService
    ) {}

    /**
     * Get coefficients for a module
     */
    public function index(Request $request, int $moduleId): JsonResponse
    {
        $semesterId = $request->input('semester_id');

        if (! $semesterId) {
            return response()->json([
                'message' => 'semester_id is required',
            ], 422);
        }

        $evaluations = ModuleEvaluationConfig::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->orderBy('order')
            ->get();

        $totalCoefficient = $this->coefficientService->getTotalCoefficients($moduleId, $semesterId);

        return response()->json([
            'data' => $evaluations->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'type' => $e->type,
                'coefficient' => $e->coefficient,
                'max_score' => $e->max_score,
                'order' => $e->order,
                'status' => $e->status,
            ]),
            'total_coefficient' => $totalCoefficient,
        ]);
    }

    /**
     * Update evaluation coefficient
     */
    public function updateCoefficient(UpdateCoefficientRequest $request, int $evaluationId): JsonResponse
    {
        $evaluation = ModuleEvaluationConfig::findOrFail($evaluationId);

        try {
            $this->coefficientService->updateCoefficient(
                $evaluation,
                $request->coefficient,
                $request->reason
            );

            return response()->json([
                'message' => 'Coefficient mis à jour avec succès.',
                'data' => [
                    'id' => $evaluation->id,
                    'coefficient' => $evaluation->fresh()->coefficient,
                ],
            ]);
        } catch (CoefficientLockedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Update module credits ECTS
     */
    public function updateCredits(UpdateCreditsRequest $request, int $moduleId): JsonResponse
    {
        $module = Module::findOrFail($moduleId);

        $this->coefficientService->updateCredits(
            $module,
            $request->credits_ects,
            $request->reason
        );

        return response()->json([
            'message' => 'Crédits ECTS mis à jour avec succès.',
            'data' => [
                'id' => $module->id,
                'credits_ects' => $module->fresh()->credits_ects,
            ],
        ]);
    }

    /**
     * Simulate coefficient change impact
     */
    public function simulateImpact(Request $request, int $evaluationId): JsonResponse
    {
        $request->validate([
            'new_coefficient' => 'required|numeric|min:0.25|max:10',
        ]);

        $evaluation = ModuleEvaluationConfig::findOrFail($evaluationId);

        $impacts = $this->coefficientService->simulateImpact(
            $evaluation,
            $request->new_coefficient
        );

        return response()->json([
            'data' => $impacts,
        ]);
    }

    /**
     * Get coefficient history
     */
    public function coefficientHistory(int $evaluationId): JsonResponse
    {
        $history = $this->coefficientService->getCoefficientHistory($evaluationId);

        return response()->json([
            'data' => $history,
        ]);
    }

    /**
     * Get credits history
     */
    public function creditsHistory(int $moduleId): JsonResponse
    {
        $history = $this->coefficientService->getCreditsHistory($moduleId);

        return response()->json([
            'data' => $history,
        ]);
    }

    /**
     * Get coefficient templates
     */
    public function templates(): JsonResponse
    {
        $templates = $this->coefficientService->getTemplates();

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Create custom template
     */
    public function storeTemplate(StoreCoefficientTemplateRequest $request): JsonResponse
    {
        $template = $this->coefficientService->createTemplate(
            $request->name,
            $request->description,
            $request->evaluations
        );

        return response()->json([
            'message' => 'Template créé avec succès.',
            'data' => new CoefficientTemplateResource($template),
        ], 201);
    }

    /**
     * Apply template to a module
     */
    public function applyTemplate(Request $request, int $moduleId): JsonResponse
    {
        $request->validate([
            'template_id' => 'required|exists:coefficient_templates,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $module = Module::findOrFail($moduleId);

        try {
            $this->coefficientService->applyTemplate(
                $module,
                $request->template_id,
                $request->semester_id
            );

            return response()->json([
                'message' => 'Template appliqué avec succès.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
