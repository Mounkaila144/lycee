<?php

namespace Tests\Feature\NotesEvaluations\Lycee;

use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Subject;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Notes lycée — affectations professeur ↔ (classe, matière).
 */
class LyceeAssignmentsApiTest extends TestCase
{
    use InteractsWithLyceeGrades;

    private const BASE = '/api/admin/lycee-grades/assignments';

    #[Test]
    public function admin_lists_assignments_with_filters(): void
    {
        $this->getAs($this->admin, self::BASE.'?classe_id='.$this->classe->id)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'teacher' => ['id', 'full_name'], 'classe' => ['id', 'name'], 'subject' => ['id', 'name', 'code'], 'academic_year_id']]]);

        $this->getAs($this->admin, self::BASE.'?teacher_id='.$this->frenchTeacher->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject.code', 'FR')
            ->assertJsonPath('data.0.teacher.full_name', 'Ibrahim Lettres')
            ->assertJsonPath('data.0.academic_year_id', $this->year->id);
    }

    #[Test]
    public function admin_creates_updates_and_deletes_an_assignment(): void
    {
        $physics = Subject::factory()->create(['code' => 'PC']);

        $id = $this->postAs($this->admin, self::BASE, [
            'teacher_id' => $this->headTeacher->id,
            'classe_id' => $this->classe->id,
            'subject_id' => $physics->id,
        ])->assertCreated()
            ->assertJsonPath('data.subject.code', 'PC')
            ->assertJsonPath('data.academic_year_id', $this->classe->academic_year_id)
            ->json('data.id');

        $this->putAs($this->admin, self::BASE."/{$id}", ['teacher_id' => $this->mathTeacher->id])
            ->assertOk()
            ->assertJsonPath('data.teacher.id', $this->mathTeacher->id);

        $this->deleteAs($this->admin, self::BASE."/{$id}")->assertOk();
        $this->assertDatabaseMissing('teacher_assignments', ['id' => $id], 'tenant');
    }

    #[Test]
    public function duplicate_assignment_and_non_teacher_are_rejected(): void
    {
        $this->postAs($this->admin, self::BASE, [
            'teacher_id' => $this->headTeacher->id,
            'classe_id' => $this->classe->id,
            'subject_id' => $this->maths->id,
        ])->assertUnprocessable()->assertJsonPath('message', 'Cette matière a déjà un professeur affecté dans cette classe.');

        $cashier = $this->userWithRole('Caissier');
        $this->postAs($this->admin, self::BASE, [
            'teacher_id' => $cashier->id,
            'classe_id' => $this->classe->id,
            'subject_id' => Subject::factory()->create()->id,
        ])->assertUnprocessable()->assertJsonPath('message', "L'utilisateur sélectionné n'est pas un professeur.");

        $this->putAs($this->admin, self::BASE.'/'.TeacherAssignment::query()->value('id'), ['teacher_id' => $cashier->id])
            ->assertUnprocessable();

        $this->postAs($this->admin, self::BASE, [])->assertUnprocessable()
            ->assertJsonValidationErrors(['teacher_id' => 'Le professeur est obligatoire.', 'classe_id', 'subject_id']);
    }

    #[Test]
    public function bulk_replaces_and_removes_class_assignments(): void
    {
        $physics = Subject::factory()->create(['code' => 'PC']);

        $this->postAs($this->admin, self::BASE.'/bulk', [
            'classe_id' => $this->classe->id,
            'items' => [
                ['subject_id' => $this->maths->id, 'teacher_id' => $this->headTeacher->id],
                ['subject_id' => $this->svt->id, 'teacher_id' => null],
                ['subject_id' => $physics->id, 'teacher_id' => $this->mathTeacher->id],
            ],
        ])->assertOk()->assertJsonCount(3, 'data');

        $byCode = TeacherAssignment::query()->with('subject')->where('classe_id', $this->classe->id)->get()->keyBy('subject.code');
        $this->assertSame($this->headTeacher->id, (int) $byCode['MATH']->teacher_id);
        $this->assertSame($this->mathTeacher->id, (int) $byCode['PC']->teacher_id);
        $this->assertSame($this->frenchTeacher->id, (int) $byCode['FR']->teacher_id);
        $this->assertArrayNotHasKey('SVT', $byCode->all());

        $this->postAs($this->admin, self::BASE.'/bulk', [
            'classe_id' => $this->classe->id,
            'items' => [['subject_id' => $this->maths->id, 'teacher_id' => $this->userWithRole('Parent')->id]],
        ])->assertUnprocessable();
    }

    #[Test]
    public function teacher_cannot_manage_assignments_but_gets_his_own(): void
    {
        $this->getAs($this->mathTeacher, self::BASE)->assertForbidden();
        $this->postAs($this->mathTeacher, self::BASE, [])->assertForbidden();
        $this->postAs($this->mathTeacher, self::BASE.'/bulk', [])->assertForbidden();
        $this->deleteAs($this->mathTeacher, self::BASE.'/'.TeacherAssignment::query()->value('id'))->assertForbidden();

        $response = $this->getAs($this->mathTeacher, self::BASE.'/mine')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.academic_year_id', $this->year->id);

        $rows = collect($response->json('data'))->keyBy('subject.code');
        $this->assertSame(4, $rows['MATH']['students_count']);
        $this->assertEquals(4, $rows['MATH']['coefficient']);
        $this->assertEquals(1, $rows['SVT']['coefficient']);
        $this->assertFalse($rows['MATH']['is_head_teacher']);
    }

    #[Test]
    public function deleted_students_are_not_counted_in_class_size(): void
    {
        $this->getAs($this->mathTeacher, self::BASE.'/mine')->assertOk();

        reset($this->students)->delete();

        $rows = collect($this->getAs($this->mathTeacher, self::BASE.'/mine')->assertOk()->json('data'))->keyBy('subject.code');

        $this->assertSame(3, $rows['MATH']['students_count']);
    }

    #[Test]
    public function mine_ignores_assignments_of_inactive_years(): void
    {
        $oldYear = AcademicYear::factory()->create();
        $oldClasse = Classe::factory()->create(['academic_year_id' => $oldYear->id]);
        $this->assign($this->frenchTeacher, $this->french, $oldClasse);

        $this->getAs($this->frenchTeacher, self::BASE.'/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.classe.id', $this->classe->id);
    }

    #[Test]
    public function students_and_parents_cannot_read_assignments(): void
    {
        foreach ([$this->studentUser('A'), $this->parentOf($this->students['A'])] as $user) {
            $this->getAs($user, self::BASE)->assertForbidden();
            $this->getAs($user, self::BASE.'/mine')->assertForbidden();
            $this->getAs($user, "/api/admin/lycee-grades/classes/{$this->classe->id}/subjects")->assertForbidden();
        }
    }

    #[Test]
    public function class_subjects_returns_coefficients_and_teachers(): void
    {
        $response = $this->getAs($this->admin, "/api/admin/lycee-grades/classes/{$this->classe->id}/subjects")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $subjects = collect($response->json('data'))->keyBy('code');
        $this->assertEquals(4, $subjects['MATH']['coefficient']);
        $this->assertSame($this->mathTeacher->id, $subjects['MATH']['teacher']['id']);
        $this->assertNotNull($subjects['MATH']['assignment_id']);

        $this->getAs($this->headTeacher, "/api/admin/lycee-grades/classes/{$this->classe->id}/subjects")->assertOk();
        $this->getAs($this->userWithRole('Professeur'), "/api/admin/lycee-grades/classes/{$this->classe->id}/subjects")->assertForbidden();
    }
}
