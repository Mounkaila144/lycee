<?php

namespace Modules\NotesEvaluations\Http\Controllers\Lycee;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\NotesEvaluations\Http\Requests\Lycee\BulkTeacherAssignmentRequest;
use Modules\NotesEvaluations\Http\Requests\Lycee\StoreTeacherAssignmentRequest;
use Modules\NotesEvaluations\Http\Requests\Lycee\UpdateTeacherAssignmentRequest;
use Modules\NotesEvaluations\Http\Resources\Lycee\TeacherAssignmentResource;
use Modules\NotesEvaluations\Services\Lycee\LyceeGradeAccessService;
use Modules\NotesEvaluations\Services\Lycee\LyceeGradeCalculator;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Notes lycée — affectations professeur ↔ (classe, matière).
 */
class TeacherAssignmentController extends Controller
{
    public function __construct(
        private LyceeGradeCalculator $calculator,
        private LyceeGradeAccessService $access
    ) {}

    /**
     * GET /assignments (Admin|Manager).
     */
    public function index(Request $request): JsonResponse
    {
        $assignments = TeacherAssignment::query()
            ->with(['teacher:id,firstname,lastname', 'classe:id,name', 'subject:id,name,code'])
            ->when($request->filled('academic_year_id'), fn ($query) => $query->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('classe_id'), fn ($query) => $query->where('classe_id', $request->integer('classe_id')))
            ->when($request->filled('teacher_id'), fn ($query) => $query->where('teacher_id', $request->integer('teacher_id')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
            ->orderBy('classe_id')
            ->orderBy('subject_id')
            ->get();

        return response()->json([
            'data' => TeacherAssignmentResource::collection($assignments)->resolve(),
            'meta' => ['total' => $assignments->count()],
        ]);
    }

    /**
     * POST /assignments (Admin|Manager). L'année est celle de la classe.
     */
    public function store(StoreTeacherAssignmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $classe = Classe::query()->findOrFail($validated['classe_id']);
        $this->ensureTeacher((int) $validated['teacher_id']);

        $exists = TeacherAssignment::query()
            ->where('classe_id', $classe->id)
            ->where('subject_id', $validated['subject_id'])
            ->where('academic_year_id', $classe->academic_year_id)
            ->exists();

        if ($exists) {
            abort(422, 'Cette matière a déjà un professeur affecté dans cette classe.');
        }

        $assignment = TeacherAssignment::query()->create([
            'teacher_id' => $validated['teacher_id'],
            'classe_id' => $classe->id,
            'subject_id' => $validated['subject_id'],
            'academic_year_id' => $classe->academic_year_id,
        ]);

        return response()->json([
            'data' => $this->present($assignment),
            'message' => 'Affectation créée.',
        ], 201);
    }

    /**
     * PUT /assignments/{id} (Admin|Manager) : changement de professeur.
     */
    public function update(UpdateTeacherAssignmentRequest $request, int $id): JsonResponse
    {
        $assignment = TeacherAssignment::query()->findOrFail($id);
        $this->ensureTeacher((int) $request->validated('teacher_id'));

        $assignment->update(['teacher_id' => $request->validated('teacher_id')]);

        return response()->json([
            'data' => $this->present($assignment),
            'message' => 'Affectation mise à jour.',
        ]);
    }

    /**
     * DELETE /assignments/{id} (Admin|Manager).
     */
    public function destroy(int $id): JsonResponse
    {
        TeacherAssignment::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Affectation supprimée.']);
    }

    /**
     * POST /assignments/bulk (Admin|Manager) : grille d'une classe. teacher_id null = retrait.
     */
    public function bulk(BulkTeacherAssignmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $classe = Classe::query()->findOrFail($validated['classe_id']);

        $teacherIds = collect($validated['items'])->pluck('teacher_id')->filter()->unique();
        foreach ($teacherIds as $teacherId) {
            $this->ensureTeacher((int) $teacherId);
        }

        DB::connection('tenant')->transaction(function () use ($validated, $classe): void {
            foreach ($validated['items'] as $item) {
                $attributes = [
                    'classe_id' => $classe->id,
                    'subject_id' => $item['subject_id'],
                    'academic_year_id' => $classe->academic_year_id,
                ];

                if (empty($item['teacher_id'])) {
                    TeacherAssignment::query()->where($attributes)->delete();

                    continue;
                }

                TeacherAssignment::query()->updateOrCreate($attributes, ['teacher_id' => $item['teacher_id']]);
            }
        });

        $assignments = TeacherAssignment::query()
            ->with(['teacher:id,firstname,lastname', 'classe:id,name', 'subject:id,name,code'])
            ->where('classe_id', $classe->id)
            ->where('academic_year_id', $classe->academic_year_id)
            ->orderBy('subject_id')
            ->get();

        return response()->json([
            'data' => TeacherAssignmentResource::collection($assignments)->resolve(),
            'message' => 'Affectations de la classe enregistrées.',
        ]);
    }

    /**
     * GET /assignments/mine : affectations du user connecté pour l'année active.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeYearId = AcademicYear::query()->active()->value('id');

        $assignments = $this->access->assignmentsOf($user, $activeYearId !== null ? (int) $activeYearId : null)
            ->load([
                'teacher:id,firstname,lastname',
                'classe' => fn ($query) => $query->withCount(['activeEnrollments as students_count']),
                'subject:id,name,code',
            ])
            ->sortBy(fn (TeacherAssignment $assignment): string => ($assignment->classe?->name ?? '').'|'.($assignment->subject?->name ?? ''))
            ->values();

        $coefficientsByClasse = [];

        foreach ($assignments as $assignment) {
            $classe = $assignment->classe;

            if ($classe === null) {
                continue;
            }

            $coefficientsByClasse[$classe->id] ??= $this->calculator->coefficientsFor($classe);

            $assignment->setAttribute('students_count', (int) $classe->getAttribute('students_count'));
            $assignment->setAttribute('coefficient', $coefficientsByClasse[$classe->id][$assignment->subject_id]['coefficient'] ?? 1.0);
            $assignment->setAttribute('is_head_teacher', $this->access->isHeadTeacher($user, $classe));
        }

        return response()->json([
            'data' => TeacherAssignmentResource::collection($assignments)->resolve(),
            'meta' => ['academic_year_id' => $activeYearId !== null ? (int) $activeYearId : null],
        ]);
    }

    /**
     * GET /classes/{classe}/subjects : matières de la classe avec coefficient et professeur affecté.
     */
    public function classSubjects(Request $request, int $classe): JsonResponse
    {
        $classe = Classe::query()->findOrFail($classe);

        if (! $this->access->canViewClassResults($request->user(), $classe)) {
            abort(403, "Vous n'avez pas accès à cette classe.");
        }

        return response()->json([
            'data' => $this->calculator->classSubjects($classe)->all(),
            'meta' => ['classe' => ['id' => $classe->id, 'name' => $classe->name, 'academic_year_id' => $classe->academic_year_id]],
        ]);
    }

    /**
     * L'utilisateur doit avoir le rôle Professeur (422 sinon).
     */
    private function ensureTeacher(int $userId): void
    {
        $user = TenantUser::query()->find($userId);

        if ($user === null || ! $user->hasRole('Professeur')) {
            abort(422, "L'utilisateur sélectionné n'est pas un professeur.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(TeacherAssignment $assignment): array
    {
        return (new TeacherAssignmentResource(
            $assignment->fresh(['teacher:id,firstname,lastname', 'classe:id,name', 'subject:id,name,code'])
        ))->resolve();
    }
}
