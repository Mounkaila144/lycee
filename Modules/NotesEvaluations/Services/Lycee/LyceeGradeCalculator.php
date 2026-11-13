<?php

namespace Modules\NotesEvaluations\Services\Lycee;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ClassEvaluationGrade;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;

/**
 * Calculs des notes lycée : moyennes pondérées, coefficients, rangs (ex-aequo), statistiques,
 * mentions/distinctions et moyenne annuelle.
 *
 * Règles :
 *  - note ramenée sur 20 = score / max_score × 20 ; absent ou score NULL → exclu ;
 *  - moyenne matière = Σ(note/20 × poids) / Σ(poids), arrondie à 2 décimales ;
 *  - moyenne générale = Σ(moyenne matière × coef) / Σ(coef) sur les matières ayant une moyenne
 *    (moyennes matière arrondies, coef par défaut 1) ;
 *  - moyenne annuelle = moyenne simple des moyennes générales disponibles des semestres de l'année ;
 *  - rang : classement décroissant, ex-aequo = même rang (1, 2, 2, 4).
 */
class LyceeGradeCalculator
{
    public const PASS_MARK = 10.0;

    private ?bool $tablesAvailable = null;

    /**
     * Les tables lycée existent-elles sur le tenant courant (migrations jouées) ?
     */
    public function isAvailable(): bool
    {
        return $this->tablesAvailable ??= Schema::connection('tenant')->hasTable('class_evaluations')
            && Schema::connection('tenant')->hasTable('class_evaluation_grades')
            && Schema::connection('tenant')->hasTable('teacher_assignments')
            && Schema::connection('tenant')->hasTable('report_card_comments');
    }

    /**
     * Note ramenée sur 20 (null si absent, non notée ou barème invalide).
     */
    public function normalizeScore(?float $score, float $maxScore, bool $isAbsent = false): ?float
    {
        if ($isAbsent || $score === null || $maxScore <= 0) {
            return null;
        }

        return $score / $maxScore * 20;
    }

    /**
     * Moyenne pondérée d'une matière.
     *
     * @param  iterable<array{score: float|null, max_score: float, weight: float, is_absent?: bool}>  $marks
     */
    public function subjectAverage(iterable $marks): ?float
    {
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($marks as $mark) {
            $normalized = $this->normalizeScore($mark['score'], (float) $mark['max_score'], (bool) ($mark['is_absent'] ?? false));
            $weight = (float) $mark['weight'];

            if ($normalized === null || $weight <= 0) {
                continue;
            }

            $weightedSum += $normalized * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : null;
    }

    /**
     * Moyenne générale pondérée par les coefficients.
     *
     * @param  iterable<array{average: float|null, coefficient: float}>  $subjectAverages
     */
    public function generalAverage(iterable $subjectAverages): ?float
    {
        $weightedSum = 0.0;
        $totalCoefficient = 0.0;

        foreach ($subjectAverages as $item) {
            $coefficient = (float) $item['coefficient'];

            if ($item['average'] === null || $coefficient <= 0) {
                continue;
            }

            $weightedSum += (float) $item['average'] * $coefficient;
            $totalCoefficient += $coefficient;
        }

        return $totalCoefficient > 0 ? round($weightedSum / $totalCoefficient, 2) : null;
    }

    /**
     * Moyenne annuelle : moyenne simple des moyennes générales semestrielles disponibles.
     *
     * @param  iterable<float|null>  $semesterAverages
     */
    public function annualAverage(iterable $semesterAverages): ?float
    {
        $values = collect($semesterAverages)->filter(fn (?float $value): bool => $value !== null);

        return $values->isEmpty() ? null : round($values->avg(), 2);
    }

    /**
     * Rangs décroissants avec ex-aequo (1, 2, 2, 4). Les valeurs null n'ont pas de rang.
     *
     * @param  array<int|string, float|null>  $values
     * @return array<int|string, int|null>
     */
    public function rank(array $values): array
    {
        $ranked = array_filter($values, fn (?float $value): bool => $value !== null);
        arsort($ranked);

        $ranks = array_fill_keys(array_keys($values), null);
        $position = 0;
        $previousValue = null;
        $previousRank = 0;

        foreach ($ranked as $key => $value) {
            $position++;
            $rounded = round((float) $value, 2);

            if ($previousValue === null || $rounded !== $previousValue) {
                $previousRank = $position;
                $previousValue = $rounded;
            }

            $ranks[$key] = $previousRank;
        }

        return $ranks;
    }

    /**
     * Statistiques d'une série de moyennes (les null sont ignorées).
     *
     * @param  iterable<float|null>  $averages
     * @return array{class_average: float|null, highest: float|null, lowest: float|null, passed_count: int, graded_count: int, pass_rate: float|null}
     */
    public function stats(iterable $averages): array
    {
        $values = collect($averages)->filter(fn (?float $value): bool => $value !== null)->values();
        $passed = $values->filter(fn (float $value): bool => $value >= self::PASS_MARK)->count();

        return [
            'class_average' => $values->isEmpty() ? null : round($values->avg(), 2),
            'highest' => $values->isEmpty() ? null : round($values->max(), 2),
            'lowest' => $values->isEmpty() ? null : round($values->min(), 2),
            'passed_count' => $passed,
            'graded_count' => $values->count(),
            'pass_rate' => $values->isEmpty() ? null : round($passed / $values->count() * 100, 2),
        ];
    }

    public function mention(?float $average): ?string
    {
        return match (true) {
            $average === null => null,
            $average >= 16 => 'Très bien',
            $average >= 14 => 'Bien',
            $average >= 12 => 'Assez bien',
            $average >= 10 => 'Passable',
            default => 'Insuffisant',
        };
    }

    public function distinction(?float $average): ?string
    {
        return match (true) {
            $average === null => null,
            $average >= 16 => 'Félicitations',
            $average >= 14 => "Tableau d'honneur + Encouragements",
            $average >= 12 => "Tableau d'honneur",
            $average < 8 => 'Avertissement',
            default => null,
        };
    }

    /**
     * Matières d'une classe : celles du niveau/série (subject_class_coefficients),
     * complétées par les matières affectées ou évaluées. Coefficient par défaut 1.
     *
     * @param  list<int>  $extraSubjectIds
     * @return Collection<int, array{id: int, name: string, code: string|null, short_name: string|null, coefficient: float, hours_per_week: int|null, teacher: array{id: int, full_name: string}|null, assignment_id: int|null}>
     */
    public function classSubjects(Classe $classe, array $extraSubjectIds = []): Collection
    {
        $coefficients = $this->coefficientsFor($classe);

        $assignments = TeacherAssignment::query()
            ->where('classe_id', $classe->id)
            ->where('academic_year_id', $classe->academic_year_id)
            ->with('teacher:id,firstname,lastname')
            ->get()
            ->keyBy('subject_id');

        $subjectIds = collect(array_keys($coefficients))
            ->merge($assignments->keys())
            ->merge($extraSubjectIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        return Subject::query()
            ->withTrashed()
            ->whereIn('id', $subjectIds)
            ->orderBy('name')
            ->get()
            ->map(function (Subject $subject) use ($coefficients, $assignments): array {
                $assignment = $assignments->get($subject->id);

                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'short_name' => $subject->short_name,
                    'coefficient' => $coefficients[$subject->id]['coefficient'] ?? 1.0,
                    'hours_per_week' => $coefficients[$subject->id]['hours_per_week'] ?? null,
                    'teacher' => $assignment?->teacher ? $this->teacherSummary($assignment->teacher) : null,
                    'assignment_id' => $assignment?->id,
                ];
            })
            ->values();
    }

    /**
     * Coefficient d'une matière pour une classe (ligne niveau+série, sinon niveau seul, sinon 1).
     */
    public function coefficientFor(Classe $classe, int $subjectId): float
    {
        return $this->coefficientsFor($classe)[$subjectId]['coefficient'] ?? 1.0;
    }

    /**
     * @return array<int, array{coefficient: float, hours_per_week: int|null}>
     */
    public function coefficientsFor(Classe $classe): array
    {
        $rows = SubjectClassCoefficient::query()
            ->where('level_id', $classe->level_id)
            ->where(function ($query) use ($classe) {
                $query->whereNull('series_id');

                if ($classe->series_id) {
                    $query->orWhere('series_id', $classe->series_id);
                }
            })
            ->get();

        $coefficients = [];

        foreach ($rows->sortBy(fn (SubjectClassCoefficient $row): int => $row->series_id ? 1 : 0) as $row) {
            $coefficients[(int) $row->subject_id] = [
                'coefficient' => (float) $row->coefficient,
                'hours_per_week' => $row->hours_per_week,
            ];
        }

        return $coefficients;
    }

    /**
     * Élèves actifs de la classe (année de la classe), triés par nom.
     *
     * @param  list<int>  $includeStudentIds  élèves à inclure même si leur inscription n'est plus active
     * @return Collection<int, Student>
     */
    public function classStudents(Classe $classe, array $includeStudentIds = []): Collection
    {
        $enrolledIds = ClassEnrollment::query()
            ->where('classe_id', $classe->id)
            ->where('academic_year_id', $classe->academic_year_id)
            ->where('status', 'Actif')
            ->pluck('student_id')
            ->merge($includeStudentIds)
            ->unique()
            ->values();

        return Student::query()
            ->whereIn('id', $enrolledIds)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();
    }

    /**
     * Résultats complets d'une classe sur un semestre.
     *
     * @param  list<int>  $includeStudentIds
     * @return array{
     *     subjects: Collection<int, array<string, mixed>>,
     *     students: Collection<int, Student>,
     *     evaluations: Collection<int, ClassEvaluation>,
     *     subject_averages: array<int, array<int, float|null>>,
     *     subject_ranks: array<int, array<int, int|null>>,
     *     general_averages: array<int, float|null>,
     *     ranks: array<int, int|null>,
     *     stats: array<string, mixed>,
     *     subject_stats: array<int, array<string, mixed>>
     * }
     */
    public function classSemesterResults(Classe $classe, Semester $semester, bool $publishedOnly, array $includeStudentIds = []): array
    {
        $students = $this->classStudents($classe, $includeStudentIds);

        $evaluations = $this->isAvailable()
            ? ClassEvaluation::query()
                ->where('classe_id', $classe->id)
                ->where('semester_id', $semester->id)
                ->when($publishedOnly, fn ($query) => $query->published())
                ->with('grades')
                ->orderBy('evaluation_date')
                ->orderBy('id')
                ->get()
            : collect();

        $subjects = $this->isAvailable()
            ? $this->classSubjects($classe, $evaluations->pluck('subject_id')->map(fn ($id): int => (int) $id)->all())
            : collect();

        $evaluationsBySubject = $evaluations->groupBy('subject_id');
        $gradesByEvaluation = $evaluations->mapWithKeys(fn (ClassEvaluation $evaluation): array => [
            $evaluation->id => $evaluation->grades->keyBy('student_id'),
        ]);
        $subjectAverages = [];
        $generalAverages = [];

        foreach ($students as $student) {
            $subjectAverages[$student->id] = [];

            foreach ($subjects as $subject) {
                $marks = $evaluationsBySubject->get($subject['id'], collect())
                    ->map(function (ClassEvaluation $evaluation) use ($student, $gradesByEvaluation): ?array {
                        /** @var ClassEvaluationGrade|null $grade */
                        $grade = $gradesByEvaluation->get($evaluation->id)?->get($student->id);

                        return $grade === null ? null : [
                            'score' => $grade->score !== null ? (float) $grade->score : null,
                            'max_score' => (float) $evaluation->max_score,
                            'weight' => (float) $evaluation->weight,
                            'is_absent' => (bool) $grade->is_absent,
                        ];
                    })
                    ->filter();

                $subjectAverages[$student->id][$subject['id']] = $this->subjectAverage($marks);
            }

            $generalAverages[$student->id] = $this->generalAverage(
                $subjects->map(fn (array $subject): array => [
                    'average' => $subjectAverages[$student->id][$subject['id']],
                    'coefficient' => $subject['coefficient'],
                ])
            );
        }

        $subjectRanks = [];
        $subjectStats = [];

        foreach ($subjects as $subject) {
            $column = [];

            foreach ($students as $student) {
                $column[$student->id] = $subjectAverages[$student->id][$subject['id']];
            }

            $subjectRanks[$subject['id']] = $this->rank($column);
            $subjectStats[$subject['id']] = $this->stats($column);
        }

        return [
            'subjects' => $subjects,
            'students' => $students,
            'evaluations' => $evaluations,
            'subject_averages' => $subjectAverages,
            'subject_ranks' => $subjectRanks,
            'general_averages' => $generalAverages,
            'ranks' => $this->rank($generalAverages),
            'stats' => $this->stats($generalAverages),
            'subject_stats' => $subjectStats,
        ];
    }

    /**
     * Moyennes et rangs annuels d'une classe (tous les semestres de l'année de la classe).
     *
     * @param  list<int>  $includeStudentIds
     * @return array{averages: array<int, float|null>, ranks: array<int, int|null>}
     */
    public function classAnnualResults(Classe $classe, bool $publishedOnly, array $includeStudentIds = []): array
    {
        $semesterAverages = [];

        foreach ($this->yearSemesters($classe->academic_year_id) as $semester) {
            $results = $this->classSemesterResults($classe, $semester, $publishedOnly, $includeStudentIds);

            foreach ($results['general_averages'] as $studentId => $average) {
                $semesterAverages[$studentId][] = $average;
            }
        }

        $annual = [];

        foreach ($semesterAverages as $studentId => $averages) {
            $annual[$studentId] = $this->annualAverage($averages);
        }

        return [
            'averages' => $annual,
            'ranks' => $this->rank($annual),
        ];
    }

    /**
     * Semestres d'une année scolaire, dans l'ordre chronologique.
     *
     * @return Collection<int, Semester>
     */
    public function yearSemesters(?int $academicYearId): Collection
    {
        if ($academicYearId === null) {
            return collect();
        }

        return Semester::query()
            ->where('academic_year_id', $academicYearId)
            ->orderBy('start_date')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Le semestre est-il le dernier de son année (bulletin annuel) ?
     */
    public function isLastSemesterOfYear(Semester $semester): bool
    {
        $semesters = $this->yearSemesters($semester->academic_year_id);

        return $semesters->count() > 1 && (int) $semesters->last()->id === (int) $semester->id;
    }

    /**
     * @return array{id: int, full_name: string}
     */
    public function teacherSummary(object $teacher): array
    {
        return [
            'id' => (int) $teacher->id,
            'full_name' => trim(($teacher->firstname ?? '').' '.($teacher->lastname ?? '')) ?: (string) ($teacher->username ?? ''),
        ];
    }

    /**
     * @return array{id: int, matricule: string|null, full_name: string}
     */
    public function studentSummary(Student $student): array
    {
        return [
            'id' => $student->id,
            'matricule' => $student->matricule,
            'full_name' => $student->full_name,
        ];
    }
}
