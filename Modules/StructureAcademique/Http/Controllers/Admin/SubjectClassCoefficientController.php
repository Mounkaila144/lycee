<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Series;
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;
use Modules\StructureAcademique\Http\Requests\DuplicateCoefficientsRequest;
use Modules\StructureAcademique\Http\Requests\StoreSubjectCoefficientRequest;
use Modules\StructureAcademique\Http\Requests\UpdateSubjectCoefficientRequest;
use Modules\StructureAcademique\Http\Resources\SubjectClassCoefficientResource;

class SubjectClassCoefficientController extends Controller
{
    /**
     * Liste des coefficients par level_id et series_id, avec totaux
     */
    public function index(Request $request)
    {
        $request->validate([
            'level_id' => ['required', 'integer'],
        ]);

        $coefficients = SubjectClassCoefficient::on('tenant')
            ->where('level_id', $request->level_id)
            ->when($request->series_id, fn ($q, $seriesId) => $q->where('series_id', $seriesId), fn ($q) => $q->whereNull('series_id'))
            ->with(['subject', 'level', 'series'])
            ->get();

        $totalCoefficient = $coefficients->sum('coefficient');
        $totalHours = $coefficients->sum('hours_per_week');

        return response()->json([
            'data' => SubjectClassCoefficientResource::collection($coefficients),
            'totals' => [
                'total_coefficient' => (float) $totalCoefficient,
                'total_hours' => (int) $totalHours,
            ],
        ]);
    }

    /**
     * Créer un coefficient
     */
    public function store(StoreSubjectCoefficientRequest $request): JsonResponse
    {
        $coefficient = SubjectClassCoefficient::on('tenant')->create($request->validated());
        $coefficient->load(['subject', 'level', 'series']);

        return response()->json([
            'message' => 'Coefficient créé avec succès.',
            'data' => new SubjectClassCoefficientResource($coefficient),
        ], 201);
    }

    /**
     * Détails d'un coefficient
     */
    public function show(int $coefficient): SubjectClassCoefficientResource
    {
        $coefficient = SubjectClassCoefficient::on('tenant')
            ->with(['subject', 'level', 'series'])
            ->findOrFail($coefficient);

        return new SubjectClassCoefficientResource($coefficient);
    }

    /**
     * Modifier un coefficient
     */
    public function update(UpdateSubjectCoefficientRequest $request, int $coefficient): JsonResponse
    {
        $coefficient = SubjectClassCoefficient::on('tenant')->findOrFail($coefficient);
        $coefficient->update($request->validated());
        $coefficient->load(['subject', 'level', 'series']);

        return response()->json([
            'message' => 'Coefficient modifié avec succès.',
            'data' => new SubjectClassCoefficientResource($coefficient),
        ]);
    }

    /**
     * Supprimer un coefficient (bloqué si notes existent)
     */
    public function destroy(int $coefficient): JsonResponse
    {
        $coefficient = SubjectClassCoefficient::on('tenant')->findOrFail($coefficient);

        if ($this->hasGrades($coefficient)) {
            return response()->json([
                'message' => 'Impossible de supprimer ce coefficient : des notes existent pour cette matière/niveau/série.',
            ], 422);
        }

        $coefficient->delete();

        return response()->json([
            'message' => 'Coefficient supprimé avec succès.',
        ]);
    }

    /**
     * Tableau comparatif des coefficients entre séries pour un niveau
     */
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'level_id' => ['required', 'integer'],
        ]);

        $level = Level::on('tenant')->findOrFail($request->level_id);

        if (! in_array($level->code, ['1ERE', 'TLE'])) {
            return response()->json([
                'message' => 'La comparaison n\'est disponible que pour les niveaux 1ère et Terminale.',
            ], 422);
        }

        return response()->json($this->buildComparisonData($level));
    }

    /**
     * Export PDF du tableau comparatif
     */
    public function compareExport(Request $request)
    {
        $request->validate([
            'level_id' => ['required', 'integer'],
        ]);

        $level = Level::on('tenant')->findOrFail($request->level_id);

        if (! in_array($level->code, ['1ERE', 'TLE'])) {
            return response()->json([
                'message' => 'La comparaison n\'est disponible que pour les niveaux 1ère et Terminale.',
            ], 422);
        }

        $data = $this->buildComparisonData($level);
        $data['level'] = $level;

        $pdf = Pdf::loadView('documents.coefficients-comparison', $data);

        return $pdf->download("comparaison-coefficients-{$level->code}.pdf");
    }

    /**
     * Dupliquer les coefficients d'une source vers une cible
     */
    public function duplicate(DuplicateCoefficientsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $sourceCoefficients = SubjectClassCoefficient::on('tenant')
            ->where('level_id', $validated['source_level_id'])
            ->where('series_id', $validated['source_series_id'] ?? null)
            ->get();

        if ($sourceCoefficients->isEmpty()) {
            return response()->json([
                'message' => 'Aucun coefficient trouvé pour la source.',
                'report' => ['created_count' => 0, 'skipped_count' => 0, 'replaced_count' => 0],
            ], 422);
        }

        $strategy = $validated['strategy'];
        $targetLevelId = $validated['target_level_id'];
        $targetSeriesId = $validated['target_series_id'] ?? null;

        $createdCount = 0;
        $skippedCount = 0;
        $replacedCount = 0;

        if ($strategy === 'replace') {
            $replacedCount = SubjectClassCoefficient::on('tenant')
                ->where('level_id', $targetLevelId)
                ->where('series_id', $targetSeriesId)
                ->delete();

            foreach ($sourceCoefficients as $coeff) {
                SubjectClassCoefficient::on('tenant')->create([
                    'subject_id' => $coeff->subject_id,
                    'level_id' => $targetLevelId,
                    'series_id' => $targetSeriesId,
                    'coefficient' => $coeff->coefficient,
                    'hours_per_week' => $coeff->hours_per_week,
                ]);
                $createdCount++;
            }
        } else {
            foreach ($sourceCoefficients as $coeff) {
                $exists = SubjectClassCoefficient::on('tenant')
                    ->where('subject_id', $coeff->subject_id)
                    ->where('level_id', $targetLevelId)
                    ->where('series_id', $targetSeriesId)
                    ->exists();

                if ($exists) {
                    $skippedCount++;
                } else {
                    SubjectClassCoefficient::on('tenant')->create([
                        'subject_id' => $coeff->subject_id,
                        'level_id' => $targetLevelId,
                        'series_id' => $targetSeriesId,
                        'coefficient' => $coeff->coefficient,
                        'hours_per_week' => $coeff->hours_per_week,
                    ]);
                    $createdCount++;
                }
            }
        }

        return response()->json([
            'message' => 'Duplication effectuée avec succès.',
            'report' => [
                'created_count' => $createdCount,
                'skipped_count' => $skippedCount,
                'replaced_count' => $replacedCount,
            ],
        ]);
    }

    /**
     * Build comparison data structure for a level
     *
     * @return array{subjects: array, totals: array, series: array}
     */
    private function buildComparisonData(Level $level): array
    {
        $activeSeries = Series::on('tenant')->where('is_active', true)->orderBy('code')->get();

        $coefficients = SubjectClassCoefficient::on('tenant')
            ->where('level_id', $level->id)
            ->with(['subject', 'series'])
            ->get()
            ->groupBy('subject_id');

        $subjects = [];
        $totals = [];

        foreach ($activeSeries as $s) {
            $totals[$s->code] = 0;
        }

        foreach ($coefficients as $subjectId => $items) {
            $subject = $items->first()->subject;
            $row = [
                'code' => $subject->code,
                'name' => $subject->name,
                'coefficients' => [],
            ];

            foreach ($activeSeries as $s) {
                $coeff = $items->firstWhere('series_id', $s->id);
                $value = $coeff ? (float) $coeff->coefficient : null;
                $row['coefficients'][$s->code] = $value;

                if ($value !== null) {
                    $totals[$s->code] += $value;
                }
            }

            $subjects[] = $row;
        }

        usort($subjects, fn ($a, $b) => strcmp($a['code'], $b['code']));

        return [
            'subjects' => $subjects,
            'totals' => $totals,
            'series' => $activeSeries->pluck('code')->all(),
        ];
    }

    /**
     * Vérifie si des notes existent pour ce coefficient
     */
    private function hasGrades(SubjectClassCoefficient $coefficient): bool
    {
        if (! \Schema::connection('tenant')->hasTable('grades')) {
            return false;
        }

        return \DB::connection('tenant')
            ->table('grades')
            ->where('subject_id', $coefficient->subject_id)
            ->exists();
    }
}
