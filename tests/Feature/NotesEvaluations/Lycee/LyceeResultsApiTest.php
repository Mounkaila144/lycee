<?php

namespace Tests\Feature\NotesEvaluations\Lycee;

use Modules\NotesEvaluations\Entities\ReportCardComment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Semester;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Notes lycée — résultats de classe et relevé élève : chiffres exacts, rangs ex-aequo,
 * statistiques, mentions, moyenne annuelle et droits d'accès.
 *
 * Moyennes attendues (S1, évaluations publiées) :
 *   Maths (coef 4) : A 14.33, B 8.00, C 17.33, D —
 *   Français (coef 3) : A 10, B 12, C 18, D 12 (B et D ex-aequo 2e)
 *   Générale : A 12.47 (2e), B 9.71 (4e), C 17.62 (1er), D 12.00 (3e)
 */
class LyceeResultsApiTest extends TestCase
{
    use InteractsWithLyceeGrades;

    private function classResultsUri(array $query = []): string
    {
        return "/api/admin/lycee-grades/results/classes/{$this->classe->id}?".http_build_query(['semester_id' => $this->s1->id] + $query);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rowsByMatricule(array $students): array
    {
        return collect($students)->keyBy('student.matricule')->all();
    }

    #[Test]
    public function admin_gets_exact_class_results_with_ranks_stats_and_mentions(): void
    {
        $this->seedReferenceGrades();

        $response = $this->getAs($this->admin, $this->classResultsUri())->assertOk();

        $response->assertJsonPath('data.classe.id', $this->classe->id)
            ->assertJsonPath('data.semester.id', $this->s1->id)
            ->assertJsonPath('data.semester.name', 'S1')
            ->assertJsonCount(3, 'data.subjects')
            ->assertJsonCount(4, 'data.students')
            ->assertJsonPath('meta.published_only', true);

        $subjects = collect($response->json('data.subjects'))->keyBy('code');
        $this->assertEquals(4, $subjects['MATH']['coefficient']);
        $this->assertEquals(3, $subjects['FR']['coefficient']);
        $this->assertEquals(1, $subjects['SVT']['coefficient']);
        $this->assertSame($this->mathTeacher->id, $subjects['MATH']['teacher']['id']);
        $this->assertSame('Awa Maths', $subjects['MATH']['teacher']['full_name']);

        $rows = $this->rowsByMatricule($response->json('data.students'));
        $maths = (string) $this->maths->id;
        $french = (string) $this->french->id;
        $svt = (string) $this->svt->id;

        $this->assertEquals(14.33, $rows['MAT-A']['subjects'][$maths]['average']);
        $this->assertEquals(8, $rows['MAT-B']['subjects'][$maths]['average']);
        $this->assertEquals(17.33, $rows['MAT-C']['subjects'][$maths]['average']);
        $this->assertNull($rows['MAT-D']['subjects'][$maths]['average']);
        $this->assertNull($rows['MAT-D']['subjects'][$maths]['rank']);
        $this->assertSame(1, $rows['MAT-C']['subjects'][$maths]['rank']);
        $this->assertSame(3, $rows['MAT-B']['subjects'][$maths]['rank']);

        // Français : B et D ex-aequo
        $this->assertSame(1, $rows['MAT-C']['subjects'][$french]['rank']);
        $this->assertSame(2, $rows['MAT-B']['subjects'][$french]['rank']);
        $this->assertSame(2, $rows['MAT-D']['subjects'][$french]['rank']);
        $this->assertSame(4, $rows['MAT-A']['subjects'][$french]['rank']);

        // SVT en brouillon : exclue des résultats publiés
        $this->assertNull($rows['MAT-A']['subjects'][$svt]['average']);

        $this->assertEquals(12.47, $rows['MAT-A']['general_average']);
        $this->assertEquals(9.71, $rows['MAT-B']['general_average']);
        $this->assertEquals(17.62, $rows['MAT-C']['general_average']);
        $this->assertEquals(12, $rows['MAT-D']['general_average']);

        $this->assertSame(2, $rows['MAT-A']['rank']);
        $this->assertSame(4, $rows['MAT-B']['rank']);
        $this->assertSame(1, $rows['MAT-C']['rank']);
        $this->assertSame(3, $rows['MAT-D']['rank']);

        $this->assertSame('Très bien', $rows['MAT-C']['mention']);
        $this->assertSame('Félicitations', $rows['MAT-C']['distinction']);
        $this->assertSame('Assez bien', $rows['MAT-A']['mention']);
        $this->assertSame("Tableau d'honneur", $rows['MAT-A']['distinction']);
        $this->assertSame('Insuffisant', $rows['MAT-B']['mention']);
        $this->assertNull($rows['MAT-B']['distinction']);

        $response->assertJsonPath('data.stats.class_average', 12.95)
            ->assertJsonPath('data.stats.highest', 17.62)
            ->assertJsonPath('data.stats.lowest', 9.71)
            ->assertJsonPath('data.stats.passed_count', 3)
            ->assertJsonPath('data.stats.pass_rate', 75)
            ->assertJsonPath('data.stats.class_size', 4)
            ->assertJsonPath("data.stats.by_subject.{$maths}.class_average", 13.22)
            ->assertJsonPath("data.stats.by_subject.{$maths}.highest", 17.33)
            ->assertJsonPath("data.stats.by_subject.{$maths}.lowest", 8);
    }

    #[Test]
    public function include_unpublished_adds_draft_evaluations_for_staff(): void
    {
        $this->seedReferenceGrades();

        $rows = $this->rowsByMatricule(
            $this->getAs($this->admin, $this->classResultsUri(['include_unpublished' => 1]))
                ->assertOk()
                ->assertJsonPath('meta.published_only', false)
                ->json('data.students')
        );

        // (14.33 × 4 + 10 × 3 + 12.5 × 1) / 8 = 12.4775
        $this->assertEquals(12.5, $rows['MAT-A']['subjects'][(string) $this->svt->id]['average']);
        $this->assertEquals(12.48, $rows['MAT-A']['general_average']);
    }

    #[Test]
    public function coefficient_of_the_series_overrides_the_level_coefficient(): void
    {
        $series = \Modules\StructureAcademique\Entities\Series::factory()->create();
        $this->classe->update(['series_id' => $series->id]);
        \Modules\StructureAcademique\Entities\SubjectClassCoefficient::factory()->create([
            'subject_id' => $this->maths->id, 'level_id' => $this->classe->level_id, 'series_id' => $series->id, 'coefficient' => 5,
        ]);

        $subjects = collect($this->getAs($this->admin, $this->classResultsUri())->assertOk()->json('data.subjects'))->keyBy('code');

        $this->assertEquals(5, $subjects['MATH']['coefficient']);
        $this->assertEquals(3, $subjects['FR']['coefficient']);
    }

    #[Test]
    public function default_semester_is_the_active_one_and_foreign_semester_is_rejected(): void
    {
        $this->getAs($this->admin, "/api/admin/lycee-grades/results/classes/{$this->classe->id}")
            ->assertOk()
            ->assertJsonPath('data.semester.id', $this->s1->id)
            ->assertJsonCount(2, 'meta.semesters');

        $otherYear = AcademicYear::factory()->create();
        $foreign = Semester::factory()->create(['academic_year_id' => $otherYear->id, 'name' => 'S1']);

        $this->getAs($this->admin, "/api/admin/lycee-grades/results/classes/{$this->classe->id}?semester_id={$foreign->id}")
            ->assertUnprocessable();
    }

    #[Test]
    public function assigned_teacher_sees_only_his_subjects_and_head_teacher_sees_all(): void
    {
        $this->seedReferenceGrades();

        $this->getAs($this->frenchTeacher, $this->classResultsUri())
            ->assertOk()
            ->assertJsonCount(1, 'data.subjects')
            ->assertJsonPath('data.subjects.0.id', $this->french->id);

        $this->getAs($this->headTeacher, $this->classResultsUri())
            ->assertOk()
            ->assertJsonCount(3, 'data.subjects');
    }

    #[Test]
    public function unassigned_teacher_student_and_parent_cannot_read_class_results(): void
    {
        $outsider = $this->userWithRole('Professeur');

        $this->getAs($outsider, $this->classResultsUri())->assertForbidden();
        $this->getAs($this->studentUser('A'), $this->classResultsUri())->assertForbidden();
        $this->getAs($this->parentOf($this->students['A']), $this->classResultsUri())->assertForbidden();
        $this->flushHeaders()->getJson($this->classResultsUri())->assertUnauthorized();
    }

    #[Test]
    public function student_report_contains_evaluations_averages_rank_and_appreciations(): void
    {
        $this->seedReferenceGrades();
        ReportCardComment::query()->create([
            'student_id' => $this->students['A']->id, 'semester_id' => $this->s1->id, 'subject_id' => $this->maths->id,
            'comment' => 'Bon travail', 'author_id' => $this->mathTeacher->id,
        ]);
        ReportCardComment::query()->create([
            'student_id' => $this->students['A']->id, 'semester_id' => $this->s1->id, 'subject_id' => null,
            'comment' => 'Trimestre satisfaisant', 'author_id' => $this->headTeacher->id,
        ]);

        $response = $this->getAs($this->studentUser('A'), "/api/admin/lycee-grades/results/students/{$this->students['A']->id}?semester_id={$this->s1->id}")
            ->assertOk()
            ->assertJsonPath('data.student.id', $this->students['A']->id)
            ->assertJsonPath('data.student.matricule', 'MAT-A')
            ->assertJsonPath('data.classe.id', $this->classe->id)
            ->assertJsonPath('data.semester.name', 'S1')
            ->assertJsonPath('data.general_average', 12.47)
            ->assertJsonPath('data.rank', 2)
            ->assertJsonPath('data.class_size', 4)
            ->assertJsonPath('data.mention', 'Assez bien')
            ->assertJsonPath('data.distinction', "Tableau d'honneur")
            ->assertJsonPath('data.general_appreciation', 'Trimestre satisfaisant')
            ->assertJsonPath('data.annual', null)
            ->assertJsonPath('meta.published_only', true);

        $subjects = collect($response->json('data.subjects'))->keyBy('subject.code');
        $this->assertEquals(14.33, $subjects['MATH']['average']);
        $this->assertEquals(4, $subjects['MATH']['coefficient']);
        $this->assertEquals(13.22, $subjects['MATH']['class_average']);
        $this->assertSame(2, $subjects['MATH']['rank']);
        $this->assertSame('Bon travail', $subjects['MATH']['appreciation']);
        $this->assertCount(2, $subjects['MATH']['evaluations']);
        $this->assertSame('Composition', $subjects['MATH']['evaluations'][1]['title']);
        $this->assertEquals(30, $subjects['MATH']['evaluations'][1]['score']);
        $this->assertEquals(40, $subjects['MATH']['evaluations'][1]['max_score']);
        $this->assertEquals(2, $subjects['MATH']['evaluations'][1]['weight']);
        $this->assertSame('2025-11-10', $subjects['MATH']['evaluations'][1]['date']);
        $this->assertFalse($subjects['MATH']['evaluations'][1]['is_absent']);
        // Brouillon SVT invisible pour l'élève
        $this->assertSame([], $subjects['SVT']['evaluations']);
        $this->assertNull($subjects['SVT']['average']);
    }

    #[Test]
    public function student_cannot_force_unpublished_grades(): void
    {
        $this->seedReferenceGrades();

        $this->getAs($this->studentUser('A'), "/api/admin/lycee-grades/results/students/{$this->students['A']->id}?include_unpublished=1")
            ->assertOk()
            ->assertJsonPath('meta.published_only', true)
            ->assertJsonPath('data.general_average', 12.47);
    }

    #[Test]
    public function annual_average_and_rank_are_returned_on_the_last_semester(): void
    {
        $this->seedReferenceGrades();
        $this->evaluation($this->maths, ['A' => 15.51, 'B' => 10.01, 'C' => 14, 'D' => 12], [], $this->s2);

        $response = $this->getAs($this->admin, "/api/admin/lycee-grades/results/students/{$this->students['A']->id}?semester_id={$this->s2->id}")
            ->assertOk()
            ->assertJsonPath('data.semester.name', 'S2')
            ->assertJsonPath('data.general_average', 15.51)
            ->assertJsonPath('data.annual.average', 13.99)
            ->assertJsonPath('data.annual.rank', 2);

        $this->assertNotNull($response->json('data.annual'));

        $this->getAs($this->admin, "/api/admin/lycee-grades/results/students/{$this->students['B']->id}?semester_id={$this->s2->id}")
            ->assertJsonPath('data.annual.average', 9.86)
            ->assertJsonPath('data.annual.rank', 4);
        $this->getAs($this->admin, "/api/admin/lycee-grades/results/students/{$this->students['C']->id}?semester_id={$this->s2->id}")
            ->assertJsonPath('data.annual.average', 15.81)
            ->assertJsonPath('data.annual.rank', 1);
    }

    #[Test]
    public function student_and_parent_are_isolated_from_other_students(): void
    {
        $uriB = "/api/admin/lycee-grades/results/students/{$this->students['B']->id}";

        $this->getAs($this->studentUser('A'), $uriB)->assertForbidden();
        $this->getAs($this->parentOf($this->students['A']), $uriB)->assertForbidden();
        $this->getAs($this->parentOf($this->students['B']), $uriB)->assertOk()->assertJsonPath('data.student.id', $this->students['B']->id);
    }

    #[Test]
    public function teacher_access_to_student_report_follows_class_assignments(): void
    {
        $uri = "/api/admin/lycee-grades/results/students/{$this->students['A']->id}";

        $this->getAs($this->frenchTeacher, $uri)->assertOk()->assertJsonCount(1, 'data.subjects');
        $this->getAs($this->headTeacher, $uri)->assertOk()->assertJsonCount(3, 'data.subjects');
        $this->getAs($this->userWithRole('Professeur'), $uri)->assertForbidden();
        $this->getAs($this->userWithRole('Caissier'), $uri)->assertForbidden();
    }

    #[Test]
    public function student_without_class_gets_an_empty_report(): void
    {
        $student = \Modules\Enrollment\Entities\Student::factory()->create();

        $this->getAs($this->admin, "/api/admin/lycee-grades/results/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.classe', null)
            ->assertJsonPath('data.subjects', [])
            ->assertJsonPath('data.general_average', null);
    }

    #[Test]
    public function inactive_students_are_excluded_from_class_ranking(): void
    {
        $this->seedReferenceGrades();
        \Modules\Enrollment\Entities\ClassEnrollment::query()
            ->where('student_id', $this->students['C']->id)
            ->update(['status' => 'Transféré']);

        $rows = $this->rowsByMatricule($this->getAs($this->admin, $this->classResultsUri())->assertOk()->json('data.students'));

        $this->assertArrayNotHasKey('MAT-C', $rows);
        $this->assertSame(1, $rows['MAT-A']['rank']);
    }
}
