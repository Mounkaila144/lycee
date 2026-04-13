<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Http\Requests\StoreSubjectRequest;
use Modules\StructureAcademique\Http\Requests\UpdateSubjectRequest;
use Modules\StructureAcademique\Http\Resources\SubjectResource;

class SubjectController extends Controller
{
    /**
     * Liste des matières avec recherche et filtres
     */
    public function index(Request $request)
    {
        $subjects = Subject::on('tenant')
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->when($request->category, fn ($q, $category) => $q->where('category', $category))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('code')
            ->paginate($request->per_page ?? 15);

        return SubjectResource::collection($subjects);
    }

    /**
     * Créer une matière
     */
    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = Subject::on('tenant')->create($request->validated());

        return response()->json([
            'message' => 'Matière créée avec succès.',
            'data' => new SubjectResource($subject),
        ], 201);
    }

    /**
     * Détails d'une matière
     */
    public function show(int $subject): SubjectResource
    {
        $subject = Subject::on('tenant')->findOrFail($subject);

        return new SubjectResource($subject);
    }

    /**
     * Modifier une matière
     */
    public function update(UpdateSubjectRequest $request, int $subject): JsonResponse
    {
        $subject = Subject::on('tenant')->findOrFail($subject);
        $subject->update($request->validated());

        return response()->json([
            'message' => 'Matière modifiée avec succès.',
            'data' => new SubjectResource($subject),
        ]);
    }

    /**
     * Supprimer une matière (bloqué si dépendances)
     */
    public function destroy(int $subject): JsonResponse
    {
        $subject = Subject::on('tenant')->findOrFail($subject);

        if ($this->hasDependencies($subject)) {
            return response()->json([
                'message' => 'Impossible de supprimer cette matière : des coefficients, notes ou affectations enseignants existent.',
            ], 422);
        }

        $subject->delete();

        return response()->json([
            'message' => 'Matière supprimée avec succès.',
        ]);
    }

    /**
     * Vérifie si la matière a des dépendances
     */
    private function hasDependencies(Subject $subject): bool
    {
        if (\Schema::connection('tenant')->hasTable('subject_class_coefficients')) {
            $hasCoefficients = \DB::connection('tenant')
                ->table('subject_class_coefficients')
                ->where('subject_id', $subject->id)
                ->exists();

            if ($hasCoefficients) {
                return true;
            }
        }

        if (\Schema::connection('tenant')->hasTable('grades')) {
            $hasGrades = \DB::connection('tenant')
                ->table('grades')
                ->where('subject_id', $subject->id)
                ->exists();

            if ($hasGrades) {
                return true;
            }
        }

        if (\Schema::connection('tenant')->hasTable('teacher_subject_assignments')) {
            $hasAssignments = \DB::connection('tenant')
                ->table('teacher_subject_assignments')
                ->where('subject_id', $subject->id)
                ->exists();

            if ($hasAssignments) {
                return true;
            }
        }

        return false;
    }
}
