<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Exceptions\ClassPromotionException;
use Modules\Enrollment\Http\Requests\PrepareClassPromotionsRequest;
use Modules\Enrollment\Http\Requests\ValidateClassPromotionsRequest;
use Modules\Enrollment\Services\ClassPromotionService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;

/**
 * Passages de classe de fin d'année (Admin | Manager).
 */
class ClassPromotionController extends Controller
{
    public function __construct(private ClassPromotionService $promotionService) {}

    /**
     * Prépare les passages d'une classe : décision proposée par élève à partir de la moyenne annuelle.
     */
    public function prepare(PrepareClassPromotionsRequest $request, int $classeId): JsonResponse
    {
        $classe = Classe::query()->findOrFail($classeId);
        $targetYear = $request->filled('target_academic_year_id')
            ? AcademicYear::query()->findOrFail($request->integer('target_academic_year_id'))
            : null;

        return response()->json([
            'data' => $this->promotionService->prepare($classe, $targetYear),
        ]);
    }

    /**
     * Valide en masse les décisions (passage, redoublement, départ) des élèves d'une classe.
     */
    public function validateBatch(ValidateClassPromotionsRequest $request): JsonResponse
    {
        $source = Classe::query()->findOrFail($request->integer('source_classe_id'));
        $targetYear = AcademicYear::query()->findOrFail($request->integer('target_academic_year_id'));

        try {
            $result = $this->promotionService->validateBatch($source, $targetYear, $request->validated('items'), $request->user());
        } catch (ClassPromotionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $processedCount = count($result['processed']);
        $errorCount = count($result['errors']);

        return response()->json([
            'message' => $errorCount === 0
                ? "{$processedCount} décision(s) validée(s)."
                : "{$processedCount} décision(s) validée(s), {$errorCount} en erreur.",
            'data' => $result,
        ], $processedCount === 0 && $errorCount > 0 ? 422 : 200);
    }
}
