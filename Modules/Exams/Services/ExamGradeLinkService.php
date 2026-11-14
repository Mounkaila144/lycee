<?php

namespace Modules\Exams\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Exams\Entities\ExamSession;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ClassEvaluationGrade;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;

/**
 * Lien Examens → Notes lycée : à partir d'une session terminée, crée une évaluation
 * de type « composition » (brouillon) par classe concernée, dans le nouveau modèle de notes.
 * Les élèves notés absents sur la feuille d'émargement sont pré-saisis « absent ».
 */
class ExamGradeLinkService
{
    public function __construct(private ExamPlanningService $planningService) {}

    /**
     * Les tables du modèle de notes lycée existent-elles sur le tenant courant ?
     */
    public function isAvailable(): bool
    {
        $schema = Schema::connection('tenant');

        return ExamSession::hasLyceeColumns()
            && $schema->hasTable('class_evaluations')
            && $schema->hasTable('class_evaluation_grades');
    }

    /**
     * @param  array{semester_id?: int|null, max_score?: float|int|null, weight?: float|int|null, title?: string|null}  $options
     * @return Collection<int, ClassEvaluation>
     */
    public function createCompositions(ExamSession $session, Authenticatable $user, array $options = []): Collection
    {
        if (! $this->isAvailable()) {
            throw ValidationException::withMessages([
                'session' => 'Le module de notes lycée n\'est pas encore installé sur cet établissement (migrations à exécuter).',
            ]);
        }

        if ($session->status !== 'completed') {
            throw ValidationException::withMessages([
                'session' => 'Seule une session terminée peut alimenter les notes.',
            ]);
        }

        if (! empty($session->class_evaluation_ids)) {
            throw ValidationException::withMessages([
                'session' => 'Les évaluations de cette session ont déjà été créées.',
            ]);
        }

        if (! $session->subject_id) {
            throw ValidationException::withMessages([
                'session' => 'La session n\'est rattachée à aucune matière.',
            ]);
        }

        $classeIds = $session->targetClasseIds();

        if (empty($classeIds)) {
            throw ValidationException::withMessages([
                'session' => 'La session n\'est rattachée à aucune classe.',
            ]);
        }

        $semester = $this->resolveSemester($session, $options['semester_id'] ?? null);

        $absentStudentIds = $session->attendanceSheets()
            ->where('status', 'absent')
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return DB::connection('tenant')->transaction(function () use ($session, $user, $options, $classeIds, $semester, $absentStudentIds): Collection {
            $evaluations = collect();

            foreach (Classe::query()->whereIn('id', $classeIds)->get() as $classe) {
                $evaluation = ClassEvaluation::query()->create([
                    'classe_id' => $classe->id,
                    'subject_id' => $session->subject_id,
                    'semester_id' => $semester->id,
                    'teacher_id' => $this->teacherFor($classe, (int) $session->subject_id) ?? $user->getAuthIdentifier(),
                    'title' => mb_substr($options['title'] ?? $session->title, 0, 150),
                    'type' => 'composition',
                    'evaluation_date' => $session->exam_date->toDateString(),
                    'max_score' => $options['max_score'] ?? 20,
                    'weight' => $options['weight'] ?? 2,
                    'status' => ClassEvaluation::STATUS_DRAFT,
                    'created_by' => $user->getAuthIdentifier(),
                ]);

                $this->prefillAbsences($evaluation, $classe, $absentStudentIds, $user);

                $evaluations->push($evaluation);
            }

            $session->update(['class_evaluation_ids' => $evaluations->pluck('id')->all()]);

            return $evaluations;
        });
    }

    /**
     * Semestre explicite, sinon celui de la session, sinon celui contenant la date d'épreuve.
     * Le semestre doit appartenir à l'année de la session et ne pas être clôturé.
     */
    private function resolveSemester(ExamSession $session, ?int $semesterId): Semester
    {
        $semester = null;

        if ($semesterId) {
            $semester = Semester::query()->find($semesterId);
        } elseif ($session->semester_id) {
            $semester = Semester::query()->find($session->semester_id);
        } else {
            $semester = $this->planningService->findSemesterForDate((int) $session->academic_year_id, $session->exam_date->toDateString());
        }

        if (! $semester || (int) $semester->academic_year_id !== (int) $session->academic_year_id) {
            throw ValidationException::withMessages([
                'semester_id' => 'Aucun semestre de l\'année scolaire de la session ne correspond : précisez le semestre.',
            ]);
        }

        if (Schema::connection('tenant')->hasColumn('semesters', 'closed_at') && $semester->closed_at !== null) {
            throw ValidationException::withMessages([
                'semester_id' => 'Le semestre est clôturé.',
            ]);
        }

        return $semester;
    }

    private function teacherFor(Classe $classe, int $subjectId): ?int
    {
        if (! Schema::connection('tenant')->hasTable('teacher_assignments')) {
            return null;
        }

        $teacherId = TeacherAssignment::query()
            ->where('classe_id', $classe->id)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $classe->academic_year_id)
            ->value('teacher_id');

        return $teacherId ? (int) $teacherId : null;
    }

    /**
     * @param  array<int, int>  $absentStudentIds
     */
    private function prefillAbsences(ClassEvaluation $evaluation, Classe $classe, array $absentStudentIds, Authenticatable $user): void
    {
        if (empty($absentStudentIds)) {
            return;
        }

        $classStudentIds = ClassEnrollment::query()
            ->where('classe_id', $classe->id)
            ->where('status', 'Actif')
            ->whereIn('student_id', $absentStudentIds)
            ->pluck('student_id');

        foreach ($classStudentIds as $studentId) {
            ClassEvaluationGrade::query()->create([
                'class_evaluation_id' => $evaluation->id,
                'student_id' => $studentId,
                'score' => null,
                'is_absent' => true,
                'entered_by' => $user->getAuthIdentifier(),
            ]);
        }
    }
}
