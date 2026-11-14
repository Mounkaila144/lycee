<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Entities\FeeType;
use Modules\Finance\Http\Requests\BulkBillingRequest;
use Modules\Finance\Services\BulkBillingService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;

/**
 * Facturation en masse (Administrator / Comptable) : cibles, aperçu, génération.
 */
class BulkBillingController extends Controller
{
    public function __construct(
        private BulkBillingService $bulkBillingService
    ) {}

    /**
     * Niveaux et classes de l'année avec leur effectif inscrit actif.
     */
    public function targets(Request $request): JsonResponse
    {
        $academicYear = $request->filled('academic_year_id')
            ? AcademicYear::on('tenant')->findOrFail($request->integer('academic_year_id'))
            : AcademicYear::on('tenant')->where('is_active', true)->first();

        if (! $academicYear) {
            return response()->json(['message' => 'Aucune année scolaire active.'], 422);
        }

        return response()->json([
            'data' => array_merge(
                ['academic_year' => ['id' => $academicYear->id, 'name' => $academicYear->name]],
                $this->bulkBillingService->targets($academicYear)
            ),
        ]);
    }

    /**
     * Aperçu (dry-run) : aucune facture n'est créée.
     */
    public function preview(BulkBillingRequest $request): JsonResponse
    {
        [$academicYear, $feeTypes] = $this->resolveContext($request);

        return response()->json([
            'data' => $this->bulkBillingService->preview($academicYear, $this->target($request), $feeTypes),
        ]);
    }

    /**
     * Génération effective des factures (doublons ignorés).
     */
    public function generate(BulkBillingRequest $request): JsonResponse
    {
        [$academicYear, $feeTypes] = $this->resolveContext($request);

        $result = $this->bulkBillingService->generate(
            $academicYear,
            $this->target($request),
            $feeTypes,
            [
                'invoice_date' => $request->input('invoice_date') ?: now()->toDateString(),
                'due_date' => $request->input('due_date'),
                'notes' => $request->input('notes'),
            ]
        );

        return response()->json([
            'message' => "{$result['summary']['invoices_count']} facture(s) générée(s)",
            'data' => $result,
        ], 201);
    }

    /**
     * @return array{0: AcademicYear, 1: Collection<int, FeeType>}
     */
    private function resolveContext(BulkBillingRequest $request): array
    {
        $academicYear = AcademicYear::on('tenant')->findOrFail($request->integer('academic_year_id'));

        $feeTypes = FeeType::on('tenant')
            ->whereIn('id', $request->input('fee_type_ids', []))
            ->orderBy('name')
            ->get();

        $otherYearFeeTypes = $feeTypes->reject(fn (FeeType $feeType): bool => $feeType->appliesToAcademicYear($academicYear->id));

        if ($otherYearFeeTypes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fee_type_ids' => 'Type(s) de frais rattaché(s) à une autre année : '.$otherYearFeeTypes->pluck('name')->implode(', ').'.',
            ]);
        }

        if ($request->input('scope') === 'classe') {
            $classeYearId = Classe::on('tenant')
                ->whereKey($request->integer('classe_id'))
                ->value('academic_year_id');

            if ((int) $classeYearId !== (int) $academicYear->id) {
                throw ValidationException::withMessages([
                    'classe_id' => 'Cette classe n\'appartient pas à l\'année scolaire choisie.',
                ]);
            }
        }

        return [$academicYear, $feeTypes];
    }

    /**
     * @return array{scope: string, classe_id: int|null, level_id: int|null}
     */
    private function target(BulkBillingRequest $request): array
    {
        return [
            'scope' => (string) $request->input('scope'),
            'classe_id' => $request->filled('classe_id') ? $request->integer('classe_id') : null,
            'level_id' => $request->filled('level_id') ? $request->integer('level_id') : null,
        ];
    }
}
