<?php

namespace Tests\Feature\NotesEvaluations\Lycee;

use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ClassEvaluationGrade;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Semester;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Notes lycée — évaluations, saisie des notes, cycle draft → submitted → published et droits.
 */
class LyceeEvaluationsApiTest extends TestCase
{
    use InteractsWithLyceeGrades;

    private const BASE = '/api/admin/lycee-grades/evaluations';

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'classe_id' => $this->classe->id,
            'subject_id' => $this->maths->id,
            'semester_id' => $this->s1->id,
            'title' => 'Devoir n°1',
            'type' => 'devoir',
            'evaluation_date' => '2025-11-12',
        ], $overrides);
    }

    private function draft(array $attributes = []): ClassEvaluation
    {
        return $this->evaluation($this->maths, [], array_merge([
            'status' => ClassEvaluation::STATUS_DRAFT, 'published_at' => null,
        ], $attributes));
    }

    #[Test]
    public function assigned_teacher_creates_an_evaluation_with_defaults(): void
    {
        $this->postAs($this->mathTeacher, self::BASE, $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Devoir n°1')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.max_score', 20)
            ->assertJsonPath('data.weight', 1)
            ->assertJsonPath('data.classe.id', $this->classe->id)
            ->assertJsonPath('data.subject.code', 'MATH')
            ->assertJsonPath('data.semester.name', 'S1')
            ->assertJsonPath('data.teacher.id', $this->mathTeacher->id)
            ->assertJsonPath('data.students_count', 4)
            ->assertJsonPath('data.grades_count', 0)
            ->assertJsonPath('data.average', null);

        $this->postAs($this->mathTeacher, self::BASE, $this->payload(['type' => 'composition', 'title' => 'Compo', 'max_score' => 40]))
            ->assertCreated()
            ->assertJsonPath('data.weight', 2)
            ->assertJsonPath('data.max_score', 40);
    }

    #[Test]
    public function admin_creating_an_evaluation_assigns_it_to_the_subject_teacher(): void
    {
        $this->postAs($this->admin, self::BASE, $this->payload(['subject_id' => $this->french->id]))
            ->assertCreated()
            ->assertJsonPath('data.teacher.id', $this->frenchTeacher->id);
    }

    #[Test]
    public function teacher_cannot_create_an_evaluation_outside_his_assignments(): void
    {
        $this->postAs($this->mathTeacher, self::BASE, $this->payload(['subject_id' => $this->french->id]))->assertForbidden();
        $this->postAs($this->headTeacher, self::BASE, $this->payload())->assertForbidden();
    }

    #[Test]
    public function student_parent_and_cashier_cannot_use_evaluation_endpoints(): void
    {
        $evaluation = $this->draft();

        foreach ([$this->studentUser('A'), $this->parentOf($this->students['A']), $this->userWithRole('Caissier')] as $user) {
            $this->getAs($user, self::BASE)->assertForbidden();
            $this->getAs($user, self::BASE."/{$evaluation->id}")->assertForbidden();
            $this->postAs($user, self::BASE, $this->payload())->assertForbidden();
        }
    }

    #[Test]
    public function semester_must_belong_to_the_class_year_and_be_open(): void
    {
        $foreign = Semester::factory()->create(['academic_year_id' => AcademicYear::factory()->create()->id]);
        $this->postAs($this->mathTeacher, self::BASE, $this->payload(['semester_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonPath('message', "Le semestre sélectionné n'appartient pas à l'année scolaire de la classe.");

        $this->s1->update(['closed_at' => now()]);
        $this->postAs($this->mathTeacher, self::BASE, $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Le semestre est clôturé : impossible de créer une évaluation.');
    }

    #[Test]
    public function store_validates_payload_with_french_messages(): void
    {
        $this->postAs($this->mathTeacher, self::BASE, ['type' => 'examen'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['classe_id', 'subject_id', 'semester_id', 'title', 'type', 'evaluation_date'])
            ->assertJsonPath('errors.title.0', "L'intitulé de l'évaluation est obligatoire.");
    }

    #[Test]
    public function index_is_filtered_on_teacher_assignments(): void
    {
        $maths = $this->draft(['title' => 'Maths D1']);
        $french = $this->evaluation($this->french, [], ['status' => 'draft', 'published_at' => null, 'title' => 'Français D1']);

        $this->getAs($this->mathTeacher, self::BASE)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $maths->id)
            ->assertJsonPath('meta.total', 1);

        $this->getAs($this->admin, self::BASE.'?subject_id='.$this->french->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $french->id);

        $this->getAs($this->admin, self::BASE.'?status=draft&semester_id='.$this->s1->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getAs($this->headTeacher, self::BASE)->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function show_lists_every_active_student_with_empty_rows(): void
    {
        $evaluation = $this->evaluation($this->maths, ['A' => 14], ['status' => 'draft', 'published_at' => null]);

        $response = $this->getAs($this->mathTeacher, self::BASE."/{$evaluation->id}")
            ->assertOk()
            ->assertJsonCount(4, 'data.grades')
            ->assertJsonPath('data.grades_count', 1)
            ->assertJsonPath('data.average', 14);

        $grades = collect($response->json('data.grades'))->keyBy('student.matricule');
        $this->assertEquals(14, $grades['MAT-A']['score']);
        $this->assertNull($grades['MAT-B']['score']);
        $this->assertFalse($grades['MAT-B']['is_absent']);
        $this->assertSame('Élève Alpha', $grades['MAT-A']['student']['full_name']);

        $this->getAs($this->frenchTeacher, self::BASE."/{$evaluation->id}")->assertForbidden();
        $this->getAs($this->admin, self::BASE.'/999999')->assertNotFound();
    }

    #[Test]
    public function teacher_saves_grades_with_absences_and_comments(): void
    {
        $evaluation = $this->draft(['max_score' => 10]);

        $this->putAs($this->mathTeacher, self::BASE."/{$evaluation->id}/grades", [
            'grades' => [
                ['student_id' => $this->students['A']->id, 'score' => 7.5, 'is_absent' => false, 'comment' => 'Bien'],
                ['student_id' => $this->students['B']->id, 'score' => 4, 'is_absent' => true],
                ['student_id' => $this->students['C']->id, 'score' => null, 'is_absent' => false],
            ],
        ])->assertOk()->assertJsonPath('data.grades_count', 2);

        $this->assertDatabaseHas('class_evaluation_grades', [
            'class_evaluation_id' => $evaluation->id, 'student_id' => $this->students['A']->id, 'score' => 7.5, 'comment' => 'Bien',
        ], 'tenant');
        $this->assertDatabaseHas('class_evaluation_grades', [
            'class_evaluation_id' => $evaluation->id, 'student_id' => $this->students['B']->id, 'score' => null, 'is_absent' => true,
        ], 'tenant');

        // Upsert : la note de A est remplacée
        $this->putAs($this->mathTeacher, self::BASE."/{$evaluation->id}/grades", [
            'grades' => [['student_id' => $this->students['A']->id, 'score' => 9]],
        ])->assertOk();

        $this->assertSame(1, ClassEvaluationGrade::query()->where('class_evaluation_id', $evaluation->id)->where('student_id', $this->students['A']->id)->count());
        $this->assertEquals(9, ClassEvaluationGrade::query()->where('class_evaluation_id', $evaluation->id)->where('student_id', $this->students['A']->id)->value('score'));
    }

    #[Test]
    public function grades_above_max_score_or_for_foreign_students_are_rejected(): void
    {
        $evaluation = $this->draft(['max_score' => 10]);
        $stranger = Student::factory()->create();

        $this->putAs($this->mathTeacher, self::BASE."/{$evaluation->id}/grades", [
            'grades' => [
                ['student_id' => $this->students['A']->id, 'score' => 12],
                ['student_id' => $stranger->id, 'score' => 5],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'grades.0.score' => 'La note doit être comprise entre 0 et 10.',
                'grades.1.student_id' => "L'élève n'est pas inscrit dans cette classe.",
            ]);

        $this->putAs($this->mathTeacher, self::BASE."/{$evaluation->id}/grades", [
            'grades' => [['student_id' => $this->students['A']->id, 'score' => -1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['grades.0.score']);

        $this->assertSame(0, ClassEvaluationGrade::query()->where('class_evaluation_id', $evaluation->id)->count());
    }

    #[Test]
    public function teacher_submits_and_reopens_but_cannot_publish(): void
    {
        $evaluation = $this->draft();

        $this->postAs($this->mathTeacher, self::BASE."/{$evaluation->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->postAs($this->mathTeacher, self::BASE."/{$evaluation->id}/submit")->assertUnprocessable();
        $this->postAs($this->mathTeacher, self::BASE."/{$evaluation->id}/publish")->assertForbidden();
        $this->postAs($this->mathTeacher, self::BASE.'/publish-bulk', ['semester_id' => $this->s1->id])->assertForbidden();
        $this->postAs($this->mathTeacher, self::BASE."/{$evaluation->id}/reopen")->assertOk()->assertJsonPath('data.status', 'draft');
        $this->postAs($this->mathTeacher, self::BASE."/{$evaluation->id}/reopen")->assertUnprocessable();
        $this->postAs($this->frenchTeacher, self::BASE."/{$evaluation->id}/submit")->assertForbidden();
    }

    #[Test]
    public function admin_and_manager_publish_and_unpublish(): void
    {
        $evaluation = $this->draft();
        $manager = $this->userWithRole('Manager');

        $this->postAs($manager, self::BASE."/{$evaluation->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
        $this->assertSame($manager->id, (int) $evaluation->fresh()->published_by);
        $this->postAs($this->admin, self::BASE."/{$evaluation->id}/publish")->assertUnprocessable();

        $this->postAs($this->admin, self::BASE."/{$evaluation->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
        $this->assertNull($evaluation->fresh()->published_at);
        $this->postAs($this->admin, self::BASE."/{$evaluation->id}/unpublish")->assertUnprocessable();
    }

    #[Test]
    public function published_evaluation_is_locked_for_the_teacher_but_not_for_admin(): void
    {
        $evaluation = $this->evaluation($this->maths, ['A' => 12]);

        $this->putAs($this->mathTeacher, self::BASE."/{$evaluation->id}", ['title' => 'Modifié'])->assertForbidden();
        $this->putAs($this->mathTeacher, self::BASE."/{$evaluation->id}/grades", [
            'grades' => [['student_id' => $this->students['A']->id, 'score' => 20]],
        ])->assertForbidden();
        $this->deleteAs($this->mathTeacher, self::BASE."/{$evaluation->id}")->assertForbidden();

        $this->putAs($this->admin, self::BASE."/{$evaluation->id}/grades", [
            'grades' => [['student_id' => $this->students['A']->id, 'score' => 15]],
        ])->assertOk();
        $this->putAs($this->admin, self::BASE."/{$evaluation->id}", ['title' => 'Corrigé'])->assertOk()->assertJsonPath('data.title', 'Corrigé');
        $this->deleteAs($this->admin, self::BASE."/{$evaluation->id}")->assertOk();
        $this->assertSoftDeleted('class_evaluations', ['id' => $evaluation->id], 'tenant');
    }

    #[Test]
    public function teacher_updates_and_deletes_his_draft(): void
    {
        $evaluation = $this->evaluation($this->maths, ['A' => 18], ['status' => 'draft', 'published_at' => null]);

        $this->putAs($this->mathTeacher, self::BASE."/{$evaluation->id}", ['title' => 'Interro', 'type' => 'interrogation', 'weight' => 0.5])
            ->assertOk()
            ->assertJsonPath('data.title', 'Interro')
            ->assertJsonPath('data.type', 'interrogation')
            ->assertJsonPath('data.weight', 0.5);

        $this->putAs($this->mathTeacher, self::BASE."/{$evaluation->id}", ['max_score' => 10])
            ->assertUnprocessable();

        $this->deleteAs($this->mathTeacher, self::BASE."/{$evaluation->id}")->assertOk();
        $this->assertSoftDeleted('class_evaluations', ['id' => $evaluation->id], 'tenant');
    }

    #[Test]
    public function teacher_cannot_enter_grades_once_the_semester_is_closed(): void
    {
        $evaluation = $this->draft();
        $this->s1->update(['closed_at' => now()]);

        $this->putAs($this->mathTeacher, self::BASE."/{$evaluation->id}/grades", [
            'grades' => [['student_id' => $this->students['A']->id, 'score' => 10]],
        ])->assertUnprocessable();
    }

    #[Test]
    public function publish_bulk_publishes_only_submitted_evaluations_of_the_scope(): void
    {
        $submitted = $this->draft(['status' => 'submitted', 'submitted_at' => now()]);
        $otherSubmitted = $this->evaluation($this->french, [], ['status' => 'submitted', 'published_at' => null]);
        $draft = $this->draft();
        $s2Submitted = $this->draft(['status' => 'submitted', 'semester_id' => $this->s2->id]);

        $this->postAs($this->admin, self::BASE.'/publish-bulk', [
            'semester_id' => $this->s1->id,
            'classe_id' => $this->classe->id,
            'evaluation_ids' => [$submitted->id, $draft->id, $s2Submitted->id],
        ])->assertOk()
            ->assertJsonPath('data.published_count', 1)
            ->assertJsonPath('data.evaluation_ids', [$submitted->id]);

        $this->postAs($this->admin, self::BASE.'/publish-bulk', ['semester_id' => $this->s1->id])
            ->assertOk()
            ->assertJsonPath('data.published_count', 1);

        $this->assertSame('published', $otherSubmitted->fresh()->status);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame('submitted', $s2Submitted->fresh()->status);

        $this->postAs($this->admin, self::BASE.'/publish-bulk', [])->assertUnprocessable()->assertJsonValidationErrors(['semester_id']);
    }
}
