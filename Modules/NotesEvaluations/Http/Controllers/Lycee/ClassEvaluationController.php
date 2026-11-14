<?php

namespace Modules\NotesEvaluations\Http\Controllers\Lycee;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ClassEvaluationGrade;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\NotesEvaluations\Http\Requests\Lycee\ListClassEvaluationsRequest;
use Modules\NotesEvaluations\Http\Requests\Lycee\PublishBulkEvaluationsRequest;
use Modules\NotesEvaluations\Http\Requests\Lycee\SaveClassEvaluationGradesRequest;
use Modules\NotesEvaluations\Http\Requests\Lycee\StoreClassEvaluationRequest;
use Modules\NotesEvaluations\Http\Requests\Lycee\UpdateClassEvaluationRequest;
use Modules\NotesEvaluations\Http\Resources\Lycee\ClassEvaluationResource;
use Modules\NotesEvaluations\Services\Lycee\LyceeGradeAccessService;
use Modules\NotesEvaluations\Services\Lycee\LyceeGradeCalculator;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;

/**
 * Notes lycée — évaluations de classe, saisie des notes et cycle de publication
 * (draft → submitted → published).
 */
class ClassEvaluationController extends Controller
{
    public function __construct(
        private LyceeGradeCalculator $calculator,
        private LyceeGradeAccessService $access
    ) {}

    /**
     * GET /evaluations — le professeur ne voit que ses affectations.
     */
    public function index(ListClassEvaluationsRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $query = $this->withListData(ClassEvaluation::query())
            ->when(! empty($filters['classe_id']), fn ($query) => $query->where('classe_id', $filters['classe_id']))
            ->when(! empty($filters['subject_id']), fn ($query) => $query->where('subject_id', $filters['subject_id']))
            ->when(! empty($filters['semester_id']), fn ($query) => $query->where('semester_id', $filters['semester_id']))
            ->when(! empty($filters['teacher_id']), fn ($query) => $query->where('teacher_id', $filters['teacher_id']))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(! empty($filters['academic_year_id']), fn ($query) => $query->whereHas('classe', fn ($classeQuery) => $classeQuery->where('academic_year_id', $filters['academic_year_id'])));

        if (! $this->access->isSupervisor($user)) {
            $this->restrictToAssignments($query, $user);
        }

        $evaluations = $query
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 50));

        return response()->json([
            'data' => ClassEvaluationResource::collection($evaluations->getCollection())->resolve(),
            'meta' => [
                'current_page' => $evaluations->currentPage(),
                'last_page' => $evaluations->lastPage(),
                'per_page' => $evaluations->perPage(),
                'total' => $evaluations->total(),
            ],
        ]);
    }

    /**
     * POST /evaluations.
     */
    public function store(StoreClassEvaluationRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $classe = Classe::query()->findOrFail($validated['classe_id']);
        $semester = Semester::query()->findOrFail($validated['semester_id']);
        $subjectId = (int) $validated['subject_id'];

        if (! $this->access->canTeach($user, $classe, $subjectId)) {
            abort(403, "Vous n'êtes pas affecté à cette matière dans cette classe.");
        }

        $this->ensureSemesterOpenForClasse($semester, $classe);

        $assignedTeacherId = TeacherAssignment::query()
            ->where('classe_id', $classe->id)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $classe->academic_year_id)
            ->value('teacher_id');

        $evaluation = ClassEvaluation::query()->create([
            'classe_id' => $classe->id,
            'subject_id' => $subjectId,
            'semester_id' => $semester->id,
            'teacher_id' => $assignedTeacherId ?? $user->getAuthIdentifier(),
            'title' => $validated['title'],
            'type' => $validated['type'],
            'evaluation_date' => $validated['evaluation_date'],
            'max_score' => $validated['max_score'] ?? 20,
            'weight' => $validated['weight'] ?? ($validated['type'] === 'composition' ? 2 : 1),
            'status' => ClassEvaluation::STATUS_DRAFT,
            'created_by' => $user->getAuthIdentifier(),
        ]);

        return response()->json([
            'data' => $this->presentEvaluation($evaluation),
            'message' => 'Évaluation créée.',
        ], 201);
    }

    /**
     * GET /evaluations/{id} — évaluation + notes de tous les élèves actifs de la classe.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $evaluation = $this->findManageable($request->user(), $id);

        return response()->json(['data' => $this->presentWithGrades($evaluation)]);
    }

    /**
     * PUT /evaluations/{id}.
     */
    public function update(UpdateClassEvaluationRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $evaluation = $this->findManageable($user, $id);
        $this->ensureEditable($user, $evaluation);

        $validated = $request->validated();

        if (isset($validated['max_score'])) {
            $highestScore = $evaluation->grades()->max('score');

            if ($highestScore !== null && (float) $highestScore > (float) $validated['max_score']) {
                abort(422, "Le barème ne peut pas être inférieur à une note déjà saisie ({$highestScore}).");
            }
        }

        $evaluation->update($validated);

        return response()->json([
            'data' => $this->presentEvaluation($evaluation),
            'message' => 'Évaluation mise à jour.',
        ]);
    }

    /**
     * DELETE /evaluations/{id} — interdit au professeur une fois publiée.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $evaluation = $this->findManageable($user, $id);
        $this->ensureEditable($user, $evaluation);

        if ($evaluation->isPublished()) {
            abort(422, 'Évaluation publiée : dépubliez-la avant de la supprimer (les familles voient ses notes).');
        }

        $evaluation->delete();

        return response()->json(['message' => 'Évaluation supprimée.']);
    }

    /**
     * PUT /evaluations/{id}/grades — upsert des notes.
     */
    public function saveGrades(SaveClassEvaluationGradesRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $evaluation = $this->findManageable($user, $id);
        $this->ensureEditable($user, $evaluation);

        if (! $this->access->isSupervisor($user) && $evaluation->semester?->closed_at !== null) {
            abort(422, 'Le semestre est clôturé : la saisie des notes est fermée.');
        }

        $items = $request->validated('grades');
        $enrolledIds = $this->calculator->classStudents($evaluation->classe)->pluck('id')->map(fn ($studentId): int => (int) $studentId)->all();
        $maxScore = (float) $evaluation->max_score;
        $errors = [];

        foreach ($items as $index => $item) {
            if (! in_array((int) $item['student_id'], $enrolledIds, true)) {
                $errors["grades.{$index}.student_id"] = "L'élève n'est pas inscrit dans cette classe.";
            }

            if (empty($item['is_absent']) && isset($item['score']) && (float) $item['score'] > $maxScore) {
                $errors["grades.{$index}.score"] = 'La note doit être comprise entre 0 et '.rtrim(rtrim(number_format($maxScore, 2, '.', ''), '0'), '.').'.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::connection('tenant')->transaction(function () use ($items, $evaluation, $user): void {
            foreach ($items as $item) {
                $isAbsent = (bool) ($item['is_absent'] ?? false);

                ClassEvaluationGrade::query()->updateOrCreate(
                    ['class_evaluation_id' => $evaluation->id, 'student_id' => $item['student_id']],
                    [
                        'score' => $isAbsent ? null : ($item['score'] ?? null),
                        'is_absent' => $isAbsent,
                        'comment' => $item['comment'] ?? null,
                        'entered_by' => $user->getAuthIdentifier(),
                    ]
                );
            }
        });

        return response()->json([
            'data' => $this->presentWithGrades($evaluation->refresh()),
            'message' => 'Notes enregistrées.',
        ]);
    }

    /**
     * POST /evaluations/{id}/submit — draft → submitted.
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $evaluation = $this->findManageable($request->user(), $id);

        if ($evaluation->status !== ClassEvaluation::STATUS_DRAFT) {
            abort(422, 'Seule une évaluation en brouillon peut être soumise.');
        }

        $evaluation->update(['status' => ClassEvaluation::STATUS_SUBMITTED, 'submitted_at' => now()]);

        return response()->json(['data' => $this->presentEvaluation($evaluation), 'message' => 'Évaluation soumise.']);
    }

    /**
     * POST /evaluations/{id}/reopen — submitted → draft.
     */
    public function reopen(Request $request, int $id): JsonResponse
    {
        $evaluation = $this->findManageable($request->user(), $id);

        if ($evaluation->status !== ClassEvaluation::STATUS_SUBMITTED) {
            abort(422, 'Seule une évaluation soumise (non publiée) peut être rouverte.');
        }

        $evaluation->update(['status' => ClassEvaluation::STATUS_DRAFT, 'submitted_at' => null]);

        return response()->json(['data' => $this->presentEvaluation($evaluation), 'message' => 'Évaluation rouverte.']);
    }

    /**
     * POST /evaluations/{id}/publish (Administrator) — draft|submitted → published.
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $evaluation = ClassEvaluation::query()->findOrFail($id);

        if ($evaluation->isPublished()) {
            abort(422, 'Cette évaluation est déjà publiée.');
        }

        if (! $evaluation->grades()->where(fn (Builder $scored) => $this->scopeEnteredGrades($scored))->exists()) {
            abort(422, 'Impossible de publier cette évaluation : aucune note saisie.');
        }

        $evaluation->update([
            'status' => ClassEvaluation::STATUS_PUBLISHED,
            'submitted_at' => $evaluation->submitted_at ?? now(),
            'published_at' => now(),
            'published_by' => $request->user()->getAuthIdentifier(),
        ]);

        return response()->json(['data' => $this->presentEvaluation($evaluation), 'message' => 'Évaluation publiée.']);
    }

    /**
     * POST /evaluations/{id}/unpublish (Administrator) — published → submitted.
     */
    public function unpublish(int $id): JsonResponse
    {
        $evaluation = ClassEvaluation::query()->findOrFail($id);

        if (! $evaluation->isPublished()) {
            abort(422, "Cette évaluation n'est pas publiée.");
        }

        $evaluation->update([
            'status' => ClassEvaluation::STATUS_SUBMITTED,
            'published_at' => null,
            'published_by' => null,
        ]);

        return response()->json(['data' => $this->presentEvaluation($evaluation), 'message' => 'Évaluation dépubliée.']);
    }

    /**
     * POST /evaluations/publish-bulk (Administrator) — publie toutes les évaluations soumises du périmètre.
     */
    public function publishBulk(PublishBulkEvaluationsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $evaluations = ClassEvaluation::query()
            ->where('semester_id', $validated['semester_id'])
            ->where('status', ClassEvaluation::STATUS_SUBMITTED)
            ->when(! empty($validated['classe_id']), fn ($query) => $query->where('classe_id', $validated['classe_id']))
            ->when(! empty($validated['evaluation_ids']), fn ($query) => $query->whereIn('id', $validated['evaluation_ids']))
            ->withCount(['grades as grades_count' => fn ($gradeQuery) => $gradeQuery->where(fn (Builder $scored) => $this->scopeEnteredGrades($scored))])
            ->get();

        $skipped = $evaluations->filter(fn (ClassEvaluation $evaluation): bool => (int) $evaluation->grades_count === 0);
        $evaluations = $evaluations->reject(fn (ClassEvaluation $evaluation): bool => (int) $evaluation->grades_count === 0)->values();

        $userId = $request->user()->getAuthIdentifier();

        DB::connection('tenant')->transaction(function () use ($evaluations, $userId): void {
            foreach ($evaluations as $evaluation) {
                $evaluation->update([
                    'status' => ClassEvaluation::STATUS_PUBLISHED,
                    'published_at' => now(),
                    'published_by' => $userId,
                ]);
            }
        });

        return response()->json([
            'data' => [
                'published_count' => $evaluations->count(),
                'evaluation_ids' => $evaluations->pluck('id')->values()->all(),
                'skipped_count' => $skipped->count(),
                'skipped' => $skipped->map(fn (ClassEvaluation $evaluation): array => [
                    'id' => $evaluation->id,
                    'title' => $evaluation->title,
                    'reason' => 'Aucune note saisie',
                ])->values()->all(),
            ],
            'message' => "{$evaluations->count()} évaluation(s) publiée(s).".($skipped->isNotEmpty() ? " {$skipped->count()} ignorée(s) : aucune note saisie." : ''),
        ]);
    }

    /**
     * Évaluation accessible (prof : sur ses affectations) — 404 si inexistante, 403 sinon.
     */
    private function findManageable(Authenticatable $user, int $id): ClassEvaluation
    {
        $evaluation = ClassEvaluation::query()->with(['classe', 'semester'])->findOrFail($id);

        if (! $this->access->canManageEvaluation($user, $evaluation)) {
            abort(403, "Vous n'êtes pas affecté à cette matière dans cette classe.");
        }

        return $evaluation;
    }

    /**
     * Une évaluation publiée n'est modifiable que par l'Administrator.
     */
    private function ensureEditable(Authenticatable $user, ClassEvaluation $evaluation): void
    {
        if ($evaluation->isPublished() && ! $this->access->canPublish($user)) {
            abort(403, "Évaluation publiée : seul l'administrateur peut la modifier.");
        }
    }

    private function ensureSemesterOpenForClasse(Semester $semester, Classe $classe): void
    {
        if ((int) $semester->academic_year_id !== (int) $classe->academic_year_id) {
            abort(422, "Le semestre sélectionné n'appartient pas à l'année scolaire de la classe.");
        }

        if ($semester->closed_at !== null) {
            abort(422, 'Le semestre est clôturé : impossible de créer une évaluation.');
        }
    }

    /**
     * Restreint la requête aux (classe, matière, année de la classe) affectées à l'enseignant.
     */
    private function restrictToAssignments(Builder $query, Authenticatable $user): void
    {
        $pairs = $this->access->assignmentsOf($user)
            ->map(fn (TeacherAssignment $assignment): array => [
                'classe_id' => (int) $assignment->classe_id,
                'subject_id' => (int) $assignment->subject_id,
                'academic_year_id' => (int) $assignment->academic_year_id,
            ]);

        $classeYears = Classe::query()->whereIn('id', $pairs->pluck('classe_id')->unique())->pluck('academic_year_id', 'id');
        $pairs = $pairs->filter(fn (array $pair): bool => (int) ($classeYears[$pair['classe_id']] ?? 0) === $pair['academic_year_id']);

        if ($pairs->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $scope) use ($pairs): void {
            foreach ($pairs as $pair) {
                $scope->orWhere(fn (Builder $pairQuery) => $pairQuery
                    ->where('classe_id', $pair['classe_id'])
                    ->where('subject_id', $pair['subject_id']));
            }
        });
    }

    private function withListData(Builder $query): Builder
    {
        return $query
            ->with([
                'classe' => fn ($classeQuery) => $classeQuery->withCount(['activeEnrollments as students_count']),
                'subject',
                'semester',
                'teacher:id,firstname,lastname',
            ])
            ->withCount(['grades as grades_count' => fn ($gradeQuery) => $gradeQuery->where(fn (Builder $scored) => $this->scopeEnteredGrades($scored))])
            ->withAvg(['grades as average' => fn ($gradeQuery) => $gradeQuery->where('is_absent', false)], 'score');
    }

    /**
     * Note réellement saisie : un score renseigné ou une absence déclarée.
     */
    private function scopeEnteredGrades(Builder $query): Builder
    {
        return $query->whereNotNull('score')->orWhere('is_absent', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentEvaluation(ClassEvaluation $evaluation): array
    {
        $fresh = $this->withListData(ClassEvaluation::query())->findOrFail($evaluation->id);

        return (new ClassEvaluationResource($fresh))->resolve();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWithGrades(ClassEvaluation $evaluation): array
    {
        $grades = $evaluation->grades()->get()->keyBy('student_id');
        $students = $this->calculator->classStudents($evaluation->classe, $grades->keys()->map(fn ($studentId): int => (int) $studentId)->all());

        return $this->presentEvaluation($evaluation) + [
            'grades' => $students->map(function (Student $student) use ($grades): array {
                /** @var ClassEvaluationGrade|null $grade */
                $grade = $grades->get($student->id);

                return [
                    'student' => $this->calculator->studentSummary($student),
                    'score' => $grade?->score !== null ? (float) $grade->score : null,
                    'is_absent' => (bool) ($grade?->is_absent ?? false),
                    'comment' => $grade?->comment,
                ];
            })->values()->all(),
        ];
    }
}
