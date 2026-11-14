<?php

namespace Modules\NotesEvaluations\Services\Lycee;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ReportCardComment;
use Modules\Settings\Services\SchoolProfileService;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;

/**
 * Mise en forme des résultats lycée (API résultats, portails Élève/Parent, bulletins PDF).
 */
class LyceeReportService
{
    /**
     * Libellés des décisions de fin d'année (class_enrollments.final_decision).
     */
    private const COUNCIL_DECISION_LABELS = [
        'Passage' => 'Admis(e) en classe supérieure',
        'Redoublement' => 'Redouble la classe',
        'Exclusion' => 'Exclu(e)',
        'Diplôme' => 'Fin de cycle',
        'Abandon' => 'Abandon',
        'Transfert' => 'Transféré(e)',
    ];

    public function __construct(
        private LyceeGradeCalculator $calculator,
        private SchoolProfileService $schoolProfile
    ) {}

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

        return $this->studentReportFromResults(
            $student, $classe, $semester, $results, $annual,
            $this->commentsFor([$student->id], $semester)[$student->id] ?? [],
            $visibleSubjectIds,
            $this->absencesFor(collect([$student]), $semester)[$student->id] ?? null,
            $annual !== null ? ($this->councilDecisionsFor([$student->id], $classe)[$student->id] ?? null) : null,
        );
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
        $studentIds = $results['students']->pluck('id')->all();
        $comments = $this->commentsFor($studentIds, $semester);
        $absences = $this->absencesFor($results['students'], $semester);
        $decisions = $annual !== null ? $this->councilDecisionsFor($studentIds, $classe) : [];

        return $results['students']
            ->map(fn (Student $student): array => $this->studentReportFromResults(
                $student, $classe, $semester, $results, $annual, $comments[$student->id] ?? [], null,
                $absences[$student->id] ?? null, $decisions[$student->id] ?? null,
            ))
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
     * Informations d'en-tête des bulletins (profil de l'établissement, cf. Paramètres > Établissement).
     *
     * @return array<string, mixed>
     */
    public function establishment(): array
    {
        $school = $this->schoolProfile->forDocuments();

        return ['name' => $school['display_name']] + $school;
    }

    /**
     * @param  array<string, mixed>  $results  sortie de LyceeGradeCalculator::classSemesterResults()
     * @param  array{averages: array<int, float|null>, ranks: array<int, int|null>}|null  $annual
     * @param  array<int|string, string>  $comments  appréciations par subject_id ('general' = générale)
     * @param  list<int>|null  $visibleSubjectIds
     * @param  array{justified_minutes: int, unjustified_minutes: int, justified_count: int, unjustified_count: int, late_count: int}|null  $absences
     * @return array<string, mixed>
     */
    private function studentReportFromResults(
        Student $student,
        Classe $classe,
        Semester $semester,
        array $results,
        ?array $annual,
        array $comments,
        ?array $visibleSubjectIds = null,
        ?array $absences = null,
        ?string $councilDecision = null,
    ): array {
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
            'student' => $this->calculator->studentSummary($student) + [
                'birthdate' => $student->birthdate?->format('Y-m-d'),
                'birthplace' => $student->birthplace,
                'sex' => $student->sex,
            ],
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
            'absences' => $absences,
            'council_decision' => $councilDecision,
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
            'absences' => null,
            'council_decision' => null,
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
     * Absences du semestre par élève (module Présences, attendance_records.student_id = users.id de l'élève).
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array{justified_minutes: int, unjustified_minutes: int, justified_count: int, unjustified_count: int, late_count: int}> indexé par students.id
     */
    private function absencesFor(Collection $students, Semester $semester): array
    {
        $studentIdsByUser = $students
            ->filter(fn (Student $student): bool => $student->user_id !== null)
            ->mapWithKeys(fn (Student $student): array => [(int) $student->user_id => (int) $student->id])
            ->all();

        if ($studentIdsByUser === [] || $semester->start_date === null || $semester->end_date === null
            || ! Schema::connection('tenant')->hasTable('attendance_records')) {
            return [];
        }

        $summaries = array_fill_keys(array_values($studentIdsByUser), [
            'justified_minutes' => 0,
            'unjustified_minutes' => 0,
            'justified_count' => 0,
            'unjustified_count' => 0,
            'late_count' => 0,
        ]);

        AttendanceRecord::query()
            ->whereIn('student_id', array_keys($studentIdsByUser))
            ->whereIn('status', ['absent', 'excused', 'late'])
            ->whereHas('session', fn ($query) => $query->whereBetween('session_date', [
                $semester->start_date->toDateString(),
                $semester->end_date->toDateString(),
            ]))
            ->with('session:id,start_time,end_time')
            ->get(['id', 'attendance_session_id', 'student_id', 'status'])
            ->each(function (AttendanceRecord $record) use (&$summaries, $studentIdsByUser): void {
                $studentId = $studentIdsByUser[(int) $record->student_id];

                if ($record->status === 'late') {
                    $summaries[$studentId]['late_count']++;

                    return;
                }

                $minutes = $this->sessionMinutes($record->session?->start_time, $record->session?->end_time);
                $kind = $record->status === 'excused' ? 'justified' : 'unjustified';
                $summaries[$studentId][$kind.'_minutes'] += $minutes;
                $summaries[$studentId][$kind.'_count']++;
            });

        return $summaries;
    }

    private function sessionMinutes(?string $startTime, ?string $endTime): int
    {
        if ($startTime === null || $endTime === null) {
            return 60;
        }

        $minutes = (int) ((strtotime($endTime) - strtotime($startTime)) / 60);

        return $minutes > 0 ? $minutes : 60;
    }

    /**
     * Décisions de fin d'année (conseil de classe) par élève, si elles ont été enregistrées.
     *
     * @param  list<int>  $studentIds
     * @return array<int, string>
     */
    private function councilDecisionsFor(array $studentIds, Classe $classe): array
    {
        if ($studentIds === []) {
            return [];
        }

        return ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('academic_year_id', $classe->academic_year_id)
            ->whereNotNull('final_decision')
            ->pluck('final_decision', 'student_id')
            ->map(fn (string $decision): string => self::COUNCIL_DECISION_LABELS[$decision] ?? $decision)
            ->all();
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
