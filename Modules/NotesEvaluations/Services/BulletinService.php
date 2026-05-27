<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\Evaluation;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;

/**
 * Computes secondary-school report cards (bulletins trimestriels) for a
 * class within a school period: per-subject averages (weighted by evaluation
 * coefficient), an overall average (weighted by subject coefficient), and the
 * rank of each student within the class.
 */
class BulletinService
{
    /**
     * Build the full bulletin payload for a class + semester.
     *
     * @return array{class: Classe, semester_id: int|null, subjects: array<int, array{subject_id:int, name:string, code:?string, coefficient:float}>, students: array<int, array<string, mixed>>, stats: array<string, mixed>}
     */
    public function buildForClass(int $classId, ?int $semesterId): array
    {
        $class = Classe::on('tenant')->with(['level', 'series', 'academicYear'])->findOrFail($classId);

        $students = Student::on('tenant')
            ->where('class_id', $classId)
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['id', 'matricule', 'firstname', 'lastname']);

        $evaluations = Evaluation::on('tenant')
            ->with('subject:id,name,code')
            ->where('class_id', $classId)
            ->when($semesterId !== null, fn ($q) => $q->where('semester_id', $semesterId))
            ->get();

        $evaluationsBySubject = $evaluations->groupBy('subject_id');

        $coefficients = $this->subjectCoefficients($class, $evaluationsBySubject->keys()->all());

        $grades = Grade::on('tenant')
            ->whereIn('evaluation_id', $evaluations->pluck('id'))
            ->get()
            ->groupBy('evaluation_id');

        $subjects = $evaluationsBySubject->map(function (Collection $evals, $subjectId) use ($coefficients) {
            $subject = $evals->first()->subject;

            return [
                'subject_id' => (int) $subjectId,
                'name' => $subject?->name ?? 'Matière',
                'code' => $subject?->code,
                'coefficient' => (float) ($coefficients[$subjectId] ?? 1.0),
            ];
        })->values();

        $studentRows = $students->map(function (Student $student) use ($evaluationsBySubject, $grades, $coefficients) {
            return $this->computeStudent($student, $evaluationsBySubject, $grades, $coefficients);
        });

        $studentRows = $this->assignRanks($studentRows);

        $generalAverages = $studentRows->pluck('general_average')->filter(fn ($v) => $v !== null);

        return [
            'class' => $class,
            'semester_id' => $semesterId,
            'subjects' => $subjects->all(),
            'students' => $studentRows->values()->all(),
            'stats' => [
                'students' => $studentRows->count(),
                'class_average' => $generalAverages->count() ? round($generalAverages->avg(), 2) : null,
                'highest' => $generalAverages->count() ? round($generalAverages->max(), 2) : null,
                'lowest' => $generalAverages->count() ? round($generalAverages->min(), 2) : null,
                'pass_count' => $generalAverages->filter(fn ($v) => $v >= 10)->count(),
            ],
        ];
    }

    /**
     * Per-subject coefficient for a class, resolved from subject_class_coefficients.
     * Matches the class level and (exact series, falling back to null series).
     *
     * @param  array<int, int>  $subjectIds
     * @return array<int, float> keyed by subject_id
     */
    private function subjectCoefficients(Classe $class, array $subjectIds): array
    {
        if (empty($subjectIds)) {
            return [];
        }

        $rows = SubjectClassCoefficient::on('tenant')
            ->where('level_id', $class->level_id)
            ->whereIn('subject_id', $subjectIds)
            ->where(function ($q) use ($class) {
                $q->whereNull('series_id');
                if ($class->series_id) {
                    $q->orWhere('series_id', $class->series_id);
                }
            })
            ->get();

        $coefficients = [];
        foreach ($rows as $row) {
            $existing = $coefficients[$row->subject_id] ?? null;
            // Prefer the exact series match over the generic (null series) one.
            if ($existing === null || ($row->series_id !== null && $class->series_id)) {
                $coefficients[$row->subject_id] = (float) $row->coefficient;
            }
        }

        return $coefficients;
    }

    /**
     * Compute a single student's subject averages and overall average.
     *
     * @param  Collection<int, Collection<int, Evaluation>>  $evaluationsBySubject
     * @param  Collection<int, Collection<int, Grade>>  $gradesByEvaluation
     * @param  array<int, float>  $coefficients
     * @return array<string, mixed>
     */
    private function computeStudent(Student $student, Collection $evaluationsBySubject, Collection $gradesByEvaluation, array $coefficients): array
    {
        $subjectResults = [];
        $weightedSum = 0.0;
        $coefficientSum = 0.0;

        foreach ($evaluationsBySubject as $subjectId => $evaluations) {
            $scoreSum = 0.0;
            $evalCoefSum = 0.0;

            foreach ($evaluations as $evaluation) {
                $grade = $gradesByEvaluation->get($evaluation->id)?->firstWhere('student_id', $student->id);
                if (! $grade || $grade->is_absent || $grade->score === null) {
                    continue;
                }

                $maxScore = (float) ($evaluation->max_score ?: 20);
                $normalized = $maxScore > 0 ? ((float) $grade->score / $maxScore) * 20 : 0.0;
                $evalCoef = (float) ($evaluation->coefficient ?: 1);

                $scoreSum += $normalized * $evalCoef;
                $evalCoefSum += $evalCoef;
            }

            $average = $evalCoefSum > 0 ? round($scoreSum / $evalCoefSum, 2) : null;
            $subjectCoef = (float) ($coefficients[$subjectId] ?? 1.0);

            $subjectResults[] = [
                'subject_id' => (int) $subjectId,
                'average' => $average,
                'coefficient' => $subjectCoef,
                'weighted' => $average !== null ? round($average * $subjectCoef, 2) : null,
            ];

            if ($average !== null) {
                $weightedSum += $average * $subjectCoef;
                $coefficientSum += $subjectCoef;
            }
        }

        $generalAverage = $coefficientSum > 0 ? round($weightedSum / $coefficientSum, 2) : null;

        return [
            'student_id' => $student->id,
            'matricule' => $student->matricule,
            'firstname' => $student->firstname,
            'lastname' => $student->lastname,
            'subjects' => $subjectResults,
            'general_average' => $generalAverage,
            'rank' => null,
        ];
    }

    /**
     * Assign competition ranks (ties share a rank) by descending general average.
     *
     * @param  Collection<int, array<string, mixed>>  $students
     * @return Collection<int, array<string, mixed>>
     */
    private function assignRanks(Collection $students): Collection
    {
        $ranked = $students->filter(fn ($s) => $s['general_average'] !== null)
            ->sortByDesc('general_average')
            ->values();

        $positions = [];
        $previousAverage = null;
        $rank = 0;
        foreach ($ranked as $index => $student) {
            if ($previousAverage === null || $student['general_average'] < $previousAverage) {
                $rank = $index + 1;
                $previousAverage = $student['general_average'];
            }
            $positions[$student['student_id']] = $rank;
        }

        return $students->map(function ($student) use ($positions) {
            $student['rank'] = $positions[$student['student_id']] ?? null;

            return $student;
        });
    }
}
