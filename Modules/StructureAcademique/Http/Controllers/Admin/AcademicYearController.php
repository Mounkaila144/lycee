<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Http\Requests\StoreAcademicYearRequest;
use Modules\StructureAcademique\Http\Requests\UpdateAcademicYearRequest;
use Modules\StructureAcademique\Http\Resources\AcademicYearResource;

class AcademicYearController extends Controller
{
    /**
     * Liste des années scolaires
     */
    public function index(Request $request)
    {
        $query = AcademicYear::on('tenant')
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->with(['semesters']);

        // Tri
        $sortField = $request->input('sort', 'start_date');
        $sortDirection = $request->input('direction', 'desc');
        $allowedSorts = ['name', 'start_date', 'is_active'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $academicYears = $query->paginate($request->per_page ?? 15);

        return AcademicYearResource::collection($academicYears);
    }

    /**
     * Créer une année scolaire (avec auto-création de S1 et S2)
     */
    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $academicYear = AcademicYear::on('tenant')->create($request->safe()->only([
                'name', 'start_date', 'end_date',
            ]));

            $this->createDefaultSemesters($academicYear, $request->validated('semester1_end_date'));

            DB::connection('tenant')->commit();

            return response()->json([
                'message' => 'Année scolaire créée avec succès avec 2 semestres.',
                'data' => new AcademicYearResource($academicYear->load('semesters')),
            ], 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Détails d'une année scolaire
     */
    public function show(int $academicYear)
    {
        $academicYear = AcademicYear::on('tenant')
            ->with(['semesters'])
            ->findOrFail($academicYear);

        return new AcademicYearResource($academicYear);
    }

    /**
     * Modifier une année scolaire
     */
    public function update(UpdateAcademicYearRequest $request, int $academicYear): JsonResponse
    {
        $academicYear = AcademicYear::on('tenant')->findOrFail($academicYear);
        $academicYear->update($request->validated());

        return response()->json([
            'message' => 'Année scolaire modifiée avec succès.',
            'data' => new AcademicYearResource($academicYear->load('semesters')),
        ]);
    }

    /**
     * Supprimer une année scolaire (uniquement si aucune dépendance)
     */
    public function destroy(int $academicYear): JsonResponse
    {
        $academicYear = AcademicYear::on('tenant')->findOrFail($academicYear);

        if ($academicYear->is_active) {
            return response()->json([
                'message' => 'Impossible de supprimer l\'année scolaire active.',
            ], 422);
        }

        $academicYear->delete();

        return response()->json([
            'message' => 'Année scolaire supprimée avec succès.',
        ]);
    }

    /**
     * Activer une année scolaire (désactive les autres)
     */
    public function activate(int $academicYear): JsonResponse
    {
        $academicYear = AcademicYear::on('tenant')->findOrFail($academicYear);

        DB::connection('tenant')->transaction(function () use ($academicYear) {
            AcademicYear::on('tenant')
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $academicYear->update(['is_active' => true]);
        });

        return response()->json([
            'message' => "Année scolaire {$academicYear->name} activée avec succès.",
            'data' => new AcademicYearResource($academicYear->load('semesters')),
        ]);
    }

    /**
     * Obtenir l'année scolaire active
     */
    public function active(): JsonResponse
    {
        $academicYear = AcademicYear::on('tenant')
            ->active()
            ->with(['semesters'])
            ->first();

        if (! $academicYear) {
            return response()->json([
                'message' => 'Aucune année scolaire active.',
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => new AcademicYearResource($academicYear),
        ]);
    }

    /**
     * Créer les semestres par défaut (S1 et S2)
     */
    private function createDefaultSemesters(AcademicYear $academicYear, ?string $semester1EndDate = null): void
    {
        $startDate = $academicYear->start_date;
        $endDate = $academicYear->end_date;

        $s1EndDate = $semester1EndDate
            ? \Carbon\Carbon::parse($semester1EndDate)
            : $startDate->copy()->addMonths(5);

        Semester::on('tenant')->create([
            'academic_year_id' => $academicYear->id,
            'name' => 'S1',
            'start_date' => $startDate,
            'end_date' => $s1EndDate,
        ]);

        Semester::on('tenant')->create([
            'academic_year_id' => $academicYear->id,
            'name' => 'S2',
            'start_date' => $s1EndDate->copy()->addDay(),
            'end_date' => $endDate,
        ]);
    }
}
