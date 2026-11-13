<?php

namespace Modules\NotesEvaluations\Services\Lycee;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ReportCardComment;
use Modules\Settings\Entities\TenantSetting;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;

/**
 * Mise en forme des résultats lycée (API résultats, portails Élève/Parent, bulletins PDF).
 */
class LyceeReportService
{
    /**
     * Clés de paramètres possibles pour le nom de l'établissement (en-tête des bulletins).
     */
    private const ESTABLISHMENT_NAME_KEYS = ['school_name', 'establishment_name', 'etablissement_nom', 'institution_name', 'app_name'];

    public function __construct(private LyceeGradeCalculator $calculator) {}

    public function calculator(): LyceeGradeCalculator
    {
        return $this->calculator;
    }

    /**
     * Semestre demandé (doit appartenir à l'année de la classe) ou semestre par défaut :
     * semestre actif, sinon semestre en cours, sinon dernier semestre commencé, sinon le premier.
     */
    public function resolveSemester(?Classe $classe, ?int $semesterId): ?Semester
    {
        if ($semesterId !== null) {
            $semester = Semester::query()->findOrFail($semesterId);

            if ($classe !== null && (int) $semester->academic_year_id !== (int) $classe->academic_year_id) {
                abort(422, "Le semestre sélectionné n'appartient pas à l'année scolaire de la classe.");
            }

            return $semester;
        }

        if ($classe === null) {
            return null;
        }

        $semesters = $this->calculator->yearSemesters($classe->academic_year_id);
        $today = now()->toDateString();

        return $semesters->firstWhere('is_active', true)
            ?? $semesters->first(fn (Semester $semester): bool => $semester->start_date?->toDateString() <= $today
                && $semester->end_date?->toDateString() >= $today)
            ?? $semesters->filter(fn (Semester $semester): bool => $semester->start_date?->toDateString() <= $today)->last()
            ?? $semesters->first();
    }

    /**
     * Classe de l'élève : celle de l'année du semestre demandé, sinon l'inscription active courante.
     */
    public function studentClasse(Student $student, ?int $semesterId): ?Classe
    {
        $enrollmentQuery = ClassEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', 'Annulé')
            ->orderByRaw("CASE WHEN status = 'Actif' THEN 0 ELSE 1 END")
            ->orderByDesc('id');

        if ($semesterId !== null) {
            $semester = Semester::query()->findOrFail($semesterId);
            $enrollment = $enrollmentQuery->where('academic_year_id', $semester->academic_year_id)->first();

            return $enrollment?->classe;
        }

        return $student->currentClassEnrollment()->first()?->classe
            ?? $enrollmentQuery->first()?->classe;
    }

    /**
     * @return list<array{id: int, name: string, is_active: bool, closed_at: string|null}>
     */
    public function semestersFor(?Classe $classe): array
    {
        return $this->calculator->yearSemesters($classe?->academic_year_id)
            ->map(fn (Semester $semester): array => $this->semesterSummary($semester))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, is_active: bool, closed_at: string|null}
     */
    public function semesterSummary(Semester $semester): array
    {
        return [
            'id' => $semester->id,
            'name' => $semester->name,
            'is_active' => (bool) $semester->is_active,
            'closed_at' => $semester->closed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, academic_year_id: int|null, head_teacher: array{id: int, full_name: string}|null}
     */
    public function classeSummary(Classe $classe): array
    {
        return [
            'id' => $classe->id,
            'name' => $classe->name,
            'academic_year_id' => $classe->academic_year_id,
            'head_teacher' => $classe->headTeacher ? $this->calculator->teacherSummary($classe->headTeacher) : null,
        ];
    }

    /**
     * Résultats d'une classe (GET /results/classes/{classe}).
     *
     * @param  list<int>|null  $visibleSubjectIds  null = toutes les matières
     * @return array<string, mixed>
     */
    public function classResults(Classe $classe, Semester $semester, bool $publishedOnly, ?array $visibleSubjectIds = null): array
    {
        $results = $this->calculator->classSemesterResults($classe, $semester, $publishedOnly);
        $subjects = $this->filterSubjects($results['subjects'], $visibleSubjectIds);

        return [
            'classe' => $this->classeSummary($classe),
            'semester' => $this->semesterSummary($semester),
            'subjects' => $subjects->map(fn (array $subject): array => [
                'id' => $subject['id'],
                'name' => $subject['name'],
                'code' => $subject['code'],
                'coefficient' => $subject['coefficient'],
                'teacher' => $subject['teacher'],
            ])->values()->all(),
            'students' => $results['students']->map(fn (Student $student): array => [
                'student' => $this->calculator->studentSummary($student),
                'subjects' => (object) $subjects->mapWithKeys(fn (array $subject): array => [
                    $subject['id'] => [
                        'average' => $results['subject_averages'][$student->id][$subject['id']] ?? null,
                        'rank' => $results['subject_ranks'][$subject['id']][$student->id] ?? null,
                    ],
                ])->all(),
                'general_average' => $results['general_averages'][$student->id] ?? null,
                'rank' => $results['ranks'][$student->id] ?? null,
                'mention' => $this->calculator->mention($results['general_averages'][$student->id] ?? null),
                'distinction' => $this->calculator->distinction($results['general_averages'][$student->id] ?? null),
            ])->values()->all(),
            'stats' => $results['stats'] + [
                'class_size' => $results['students']->count(),
                'by_subject' => (object) $subjects->mapWithKeys(fn (array $subject): array => [
                    $subject['id'] => $results['subject_stats'][$subject['id']],
                ])->all(),
            ],
        ];
    }

    /**
     * Relevé de notes d'un élève (GET /results/students/{student}, portails Élève/Parent).
     *
     * @param  list<int>|null  $visibleSubjectIds
     * @return array<string, mixed>
     */
    public function studentReport(Student $student, ?Classe $classe, ?Semester $semester, bool $publishedOnly, ?array $visibleSubjectIds = null): array
    {
        if ($classe === null || $semester === null || ! $this->calculator->isAvailable()) {
            return $this->emptyStudentReport($student, $classe, $semester);
        }

        $results = $this->calculator->classSemesterResults($classe, $semester, $publishedOnly, [$student->id]);
        $annual = $this->calculator->isLastSemesterOfYear($semester)
            ? $this->calculator->classAnnualResults($classe, $publishedOnly, [$student->id])
            : null;

        return $this->studentReportFromResults($student, $classe, $semester, $results, $annual, $this->commentsFor([$student->id], $semester)[$student->id] ?? [], $visibleSubjectIds);
    }

    /**
     * Données de bulletin de tous les élèves d'une classe (PDF classe).
     *
     * @return list<array<string, mixed>>
     */
    public function classReports(Classe $classe, Semester $semester, bool $publishedOnly): array
    {
        $results = $this->calculator->classSemesterResults($classe, $semester, $publishedOnly);
        $annual = $this->calculator->isLastSemesterOfYear($semester)
            ? $this->calculator->classAnnualResults($classe, $publishedOnly)
            : null;
        $comments = $this->commentsFor($results['students']->pluck('id')->all(), $semester);

        return $results['students']
            ->map(fn (Student $student): array => $this->studentReportFromResults($student, $classe, $semester, $results, $annual, $comments[$student->id] ?? []))
            ->values()
            ->all();
    }

    /**
     * Au moins une évaluation publiée pour la classe sur le semestre ?
     */
    public function hasPublishedEvaluations(?Classe $classe, ?Semester $semester): bool
    {
        if ($classe === null || $semester === null || ! $this->calculator->isAvailable()) {
            return false;
        }

        return ClassEvaluation::query()
            ->where('classe_id', $classe->id)
            ->where('semester_id', $semester->id)
            ->published()
            ->exists();
    }

    /**
     * Moyenne générale publiée de l'élève sur le semestre par défaut (dashboard Élève).
     */
    public function currentGeneralAverage(Student $student): ?float
    {
        if (! $this->calculator->isAvailable()) {
            return null;
        }

        $classe = $this->studentClasse($student, null);
        $semester = $this->resolveSemester($classe, null);

        if ($classe === null || $semester === null) {
            return null;
        }

        $results = $this->calculator->classSemesterResults($classe, $semester, true, [$student->id]);

        return $results['general_averages'][$student->id] ?? null;
    }

    /**
     * Informations d'en-tête des bulletins.
     *
     * @return array{name: string}
     */
    public function establishment(): array
    {
        $name = null;

        if (Schema::connection('tenant')->hasTable('tenant_settings')) {
            $name = TenantSetting::query()
                ->whereIn('key', self::ESTABLISHMENT_NAME_KEYS)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->orderByRaw("FIELD(`key`, '".implode("','", self::ESTABLISHMENT_NAME_KEYS)."')")
                ->value('value');
        }

        $tenant = function_exists('tenant') ? tenant() : null;

        return [
            'name' => (string) ($name ?? $tenant?->getAttribute('name') ?? config('app.name')),
        ];
    }

    /**
     * @param  array<string, mixed>  $results  sortie de LyceeGradeCalculator::classSemesterResults()
     * @param  array{averages: array<int, float|null>, ranks: array<int, int|null>}|null  $annual
     * @param  array<int|string, string>  $comments  appréciations par subject_id ('general' = générale)
     * @param  list<int>|null  $visibleSubjectIds
     * @return array<string, mixed>
     */
    private function studentReportFromResults(Student $student, Classe $classe, Semester $semester, array $results, ?array $annual, array $comments, ?array $visibleSubjectIds = null): array
    {
        $evaluationsBySubject = $results['evaluations']->groupBy('subject_id');
        $subjects = $this->filterSubjects($results['subjects'], $visibleSubjectIds);

        $subjectRows = $subjects->map(function (array $subject) use ($student, $results, $evaluationsBySubject, $comments): array {
            $average = $results['subject_averages'][$student->id][$subject['id']] ?? null;

            return [
                'subject' => [
                    'id' => $subject['id'],
                    'name' => $subject['name'],
                    'code' => $subject['code'],
                ],
                'coefficient' => $subject['coefficient'],
                'teacher' => $subject['teacher'],
                'evaluations' => $evaluationsBySubject->get($subject['id'], collect())
                    ->map(function (ClassEvaluation $evaluation) use ($student): array {
                        $grade = $evaluation->grades->firstWhere('student_id', $student->id);

                        return [
                            'id' => $evaluation->id,
                            'title' => $evaluation->title,
                            'type' => $evaluation->type,
                            'date' => $evaluation->evaluation_date?->format('Y-m-d'),
                            'score' => $grade?->score !== null ? (float) $grade->score : null,
                            'max_score' => (float) $evaluation->max_score,
                            'is_absent' => (bool) ($grade?->is_absent ?? false),
                            'weight' => (float) $evaluation->weight,
                            'status' => $evaluation->status,
                            'comment' => $grade?->comment,
                        ];
                    })->values()->all(),
                'average' => $average,
                'weighted_average' => $average !== null ? round($average * $subject['coefficient'], 2) : null,
                'class_average' => $results['subject_stats'][$subject['id']]['class_average'] ?? null,
                'highest' => $results['subject_stats'][$subject['id']]['highest'] ?? null,
                'lowest' => $results['subject_stats'][$subject['id']]['lowest'] ?? null,
                'rank' => $results['subject_ranks'][$subject['id']][$student->id] ?? null,
                'appreciation' => $comments[$subject['id']] ?? null,
            ];
        })->values();

        $graded = $subjectRows->filter(fn (array $row): bool => $row['average'] !== null);
        $generalAverage = $results['general_averages'][$student->id] ?? null;

        return [
            'student' => $this->calculator->studentSummary($student),
            'classe' => $this->classeSummary($classe),
            'semester' => $this->semesterSummary($semester),
            'subjects' => $subjectRows->all(),
            'totals' => [
                'coefficients' => round((float) $graded->sum('coefficient'), 2),
                'weighted_points' => round((float) $graded->sum('weighted_average'), 2),
            ],
            'general_average' => $generalAverage,
            'rank' => $results['ranks'][$student->id] ?? null,
            'class_size' => $results['students']->count(),
            'class_stats' => [
                'class_average' => $results['stats']['class_average'],
                'highest' => $results['stats']['highest'],
                'lowest' => $results['stats']['lowest'],
            ],
            'mention' => $this->calculator->mention($generalAverage),
            'distinction' => $this->calculator->distinction($generalAverage),
            'general_appreciation' => $comments['general'] ?? null,
            'annual' => $annual === null ? null : [
                'average' => $annual['averages'][$student->id] ?? null,
                'rank' => $annual['ranks'][$student->id] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStudentReport(Student $student, ?Classe $classe, ?Semester $semester): array
    {
        return [
            'student' => $this->calculator->studentSummary($student),
            'classe' => $classe ? $this->classeSummary($classe) : null,
            'semester' => $semester ? $this->semesterSummary($semester) : null,
            'subjects' => [],
            'totals' => ['coefficients' => 0.0, 'weighted_points' => 0.0],
            'general_average' => null,
            'rank' => null,
            'class_size' => 0,
            'class_stats' => ['class_average' => null, 'highest' => null, 'lowest' => null],
            'mention' => null,
            'distinction' => null,
            'general_appreciation' => null,
            'annual' => null,
        ];
    }

    /**
     * Appréciations par élève : [student_id => [subject_id|'general' => comment]].
     *
     * @param  list<int>  $studentIds
     * @return array<int, array<int|string, string>>
     */
    private function commentsFor(array $studentIds, Semester $semester): array
    {
        $comments = [];

        ReportCardComment::query()
            ->whereIn('student_id', $studentIds)
            ->where('semester_id', $semester->id)
            ->get()
            ->each(function (ReportCardComment $comment) use (&$comments): void {
                $comments[$comment->student_id][$comment->subject_id ?? 'general'] = $comment->comment;
            });

        return $comments;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $subjects
     * @param  list<int>|null  $visibleSubjectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function filterSubjects(Collection $subjects, ?array $visibleSubjectIds): Collection
    {
        if ($visibleSubjectIds === null) {
            return $subjects;
        }

        return $subjects->filter(fn (array $subject): bool => in_array((int) $subject['id'], $visibleSubjectIds, true))->values();
    }
}
