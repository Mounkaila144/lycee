<?php

namespace Tests\Feature\NotesEvaluations\Lycee;

use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Notes lycée branchées sur les portails : Élève (mes notes, dashboard), Parent (notes d'un enfant),
 * Professeur (mes classes) et module Présences (classes du professeur via les affectations).
 */
class LyceePortalsTest extends TestCase
{
    use InteractsWithLyceeGrades;

    private function grantParentGradesPermission(): void
    {
        foreach (['view children', 'view children grades'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'tenant']);
        }
        Role::query()->where('name', 'Parent')->where('guard_name', 'tenant')->first()
            ?->givePermissionTo(['view children', 'view children grades']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    #[Test]
    public function student_my_grades_returns_the_lycee_report_of_the_active_semester(): void
    {
        $this->seedReferenceGrades();

        $response = $this->getAs($this->studentUser('A'), '/api/frontend/student/my-grades?student_id='.$this->students['B']->id)
            ->assertOk()
            ->assertJsonPath('data.student.id', $this->students['A']->id)
            ->assertJsonPath('data.semester.id', $this->s1->id)
            ->assertJsonPath('data.general_average', 12.47)
            ->assertJsonPath('data.rank', 2)
            ->assertJsonPath('data.class_size', 4)
            ->assertJsonPath('data.mention', 'Assez bien')
            ->assertJsonPath('meta.student_id', $this->students['A']->id)
            ->assertJsonPath('meta.semester_id', $this->s1->id)
            ->assertJsonCount(2, 'meta.semesters')
            ->assertJsonPath('meta.semesters.0.name', 'S1')
            ->assertJsonPath('meta.semesters.0.is_active', true);

        $subjects = collect($response->json('data.subjects'))->keyBy('subject.code');
        $this->assertEquals(14.33, $subjects['MATH']['average']);
        $this->assertSame([], $subjects['SVT']['evaluations']);
    }

    #[Test]
    public function student_my_grades_accepts_another_semester(): void
    {
        $this->seedReferenceGrades();
        $this->evaluation($this->maths, ['A' => 15.51, 'B' => 10.01, 'C' => 14, 'D' => 12], [], $this->s2);

        $this->getAs($this->studentUser('A'), '/api/frontend/student/my-grades?semester_id='.$this->s2->id)
            ->assertOk()
            ->assertJsonPath('data.semester.name', 'S2')
            ->assertJsonPath('data.general_average', 15.51)
            ->assertJsonPath('data.annual.average', 13.99);
    }

    #[Test]
    public function student_dashboard_average_is_the_published_general_average(): void
    {
        $this->seedReferenceGrades();

        $this->getAs($this->studentUser('A'), '/api/frontend/student/dashboard')
            ->assertOk()
            ->assertJsonPath('data.moyenne_actuelle', 12.47)
            ->assertJsonPath('data.average', 12.47);
    }

    #[Test]
    public function parent_child_grades_use_the_lycee_report_shape(): void
    {
        $this->grantParentGradesPermission();
        $this->seedReferenceGrades();
        $parent = $this->parentOf($this->students['C']);

        $this->getAs($parent, "/api/admin/parent/children/{$this->students['C']->id}/grades?semester_id={$this->s1->id}")
            ->assertOk()
            ->assertJsonPath('data.student.id', $this->students['C']->id)
            ->assertJsonPath('data.general_average', 17.62)
            ->assertJsonPath('data.rank', 1)
            ->assertJsonPath('data.distinction', 'Félicitations')
            ->assertJsonPath('meta.student_id', $this->students['C']->id)
            ->assertJsonCount(2, 'meta.semesters');

        $this->getAs($parent, "/api/admin/parent/children/{$this->students['A']->id}/grades")->assertForbidden();
    }

    #[Test]
    public function teacher_my_classes_include_assigned_classes_with_subjects(): void
    {
        $response = $this->getAs($this->mathTeacher, '/api/frontend/teacher/my-classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->classe->id)
            ->assertJsonPath('data.0.is_head_teacher', false)
            ->assertJsonPath('data.0.students_count', 4);

        $this->assertEqualsCanonicalizing(['MATH', 'SVT'], array_column($response->json('data.0.subjects'), 'code'));

        $this->getAs($this->headTeacher, '/api/frontend/teacher/my-classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_head_teacher', true)
            ->assertJsonPath('data.0.subjects', []);

        $this->getAs($this->userWithRole('Professeur'), '/api/frontend/teacher/my-classes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function assigned_teacher_can_list_students_of_his_class(): void
    {
        $this->getAs($this->frenchTeacher, "/api/frontend/teacher/my-classes/{$this->classe->id}/students")
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->getAs($this->userWithRole('Professeur'), "/api/frontend/teacher/my-classes/{$this->classe->id}/students")
            ->assertForbidden();
    }

    #[Test]
    public function attendance_classes_include_classes_from_teacher_assignments(): void
    {
        $this->getAs($this->frenchTeacher, '/api/admin/attendance/classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->classe->id);

        $this->getAs($this->userWithRole('Professeur'), '/api/admin/attendance/classes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
