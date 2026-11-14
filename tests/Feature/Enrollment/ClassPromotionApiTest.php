<?php

namespace Tests\Feature\Enrollment;

use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Entities\ReenrollmentIntention;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\NotesEvaluations\Lycee\InteractsWithLyceeGrades;
use Tests\TestCase;

/**
 * Passages de classe (fin d'année) : préparation à partir de la moyenne annuelle,
 * validation en masse (passage, redoublement, départ), contrôles et droits.
 *
 * Jeu de notes de référence (S1 publié) : A ≈ 12,48 → passage ; B ≈ 9,71 → redoublement ;
 * C ≈ 17,6 ; D = 12 ; E (ajouté) sans note → décision vide.
 */
class ClassPromotionApiTest extends TestCase
{
    use InteractsWithLyceeGrades {
        setUp as setUpLyceeClass;
    }

    private const PREPARE_URI = '/api/admin/enrollment/class-promotions/classes/%d/prepare';

    private const VALIDATE_URI = '/api/admin/enrollment/class-promotions/validate';

    private Level $nextLevel;

    private AcademicYear $targetYear;

    private Classe $promotionClasse;

    private Classe $repeatClasse;

    private Student $studentWithoutGrades;

    protected function setUp(): void
    {
        $this->setUpLyceeClass();

        $this->classe->level->update(['order_index' => 100]);
        $this->nextLevel = Level::factory()->create(['order_index' => 101, 'name' => 'Première']);
        Level::factory()->create(['order_index' => 102, 'name' => 'Terminale']);

        $this->targetYear = AcademicYear::factory()->create([
            'name' => 'Année cible',
            'start_date' => $this->year->start_date->copy()->addYear()->toDateString(),
            'end_date' => $this->year->end_date->copy()->addYear()->toDateString(),
        ]);

        $this->promotionClasse = Classe::factory()->create([
            'academic_year_id' => $this->targetYear->id,
            'level_id' => $this->nextLevel->id,
            'name' => '1ère A',
            'max_capacity' => 40,
        ]);
        $this->repeatClasse = Classe::factory()->create([
            'academic_year_id' => $this->targetYear->id,
            'level_id' => $this->classe->level_id,
            'name' => '2nde A (année cible)',
            'max_capacity' => 40,
        ]);

        $this->studentWithoutGrades = Student::factory()->create(['firstname' => 'Élève', 'lastname' => 'Echo']);
        $this->enroll($this->studentWithoutGrades, $this->classe);

        $this->seedReferenceGrades();
    }

    #[Test]
    public function it_prepares_promotions_with_default_decisions_from_annual_average(): void
    {
        $response = $this->getAs($this->admin, sprintf(self::PREPARE_URI, $this->classe->id));

        $response->assertOk()
            ->assertJsonPath('data.classe.id', $this->classe->id)
            ->assertJsonPath('data.target_academic_year.id', $this->targetYear->id)
            ->assertJsonPath('data.next_level.id', $this->nextLevel->id)
            ->assertJsonPath('data.grades_available', true)
            ->assertJsonPath('data.target_classes.passage.0.id', $this->promotionClasse->id)
            ->assertJsonPath('data.target_classes.redoublement.0.id', $this->repeatClasse->id)
            ->assertJsonCount(5, 'data.students');

        $rows = collect($response->json('data.students'))->keyBy('student.id');

        $rowA = $rows[$this->students['A']->id];
        $this->assertEqualsWithDelta(12.48, $rowA['annual_average'], 0.01);
        $this->assertSame('passage', $rowA['proposed_decision']);
        $this->assertSame($this->promotionClasse->id, $rowA['proposed_target_classe_id']);

        $rowB = $rows[$this->students['B']->id];
        $this->assertLessThan(10, $rowB['annual_average']);
        $this->assertSame('redoublement', $rowB['proposed_decision']);
        $this->assertSame($this->repeatClasse->id, $rowB['proposed_target_classe_id']);

        $rowE = $rows[$this->studentWithoutGrades->id];
        $this->assertNull($rowE['annual_average']);
        $this->assertNull($rowE['proposed_decision']);
        $this->assertNull($rowE['target_enrollment']);
    }

    #[Test]
    public function it_promotes_a_student_to_the_next_level(): void
    {
        $response = $this->validateDecisions([
            ['student_id' => $this->students['A']->id, 'decision' => 'passage', 'target_classe_id' => $this->promotionClasse->id],
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.processed')
            ->assertJsonCount(0, 'data.errors');

        $source = $this->sourceEnrollment($this->students['A']);
        $this->assertSame('Terminé', $source->status);
        $this->assertSame('Passage', $source->final_decision);

        $this->assertDatabaseHas('class_enrollments', [
            'student_id' => $this->students['A']->id,
            'classe_id' => $this->promotionClasse->id,
            'academic_year_id' => $this->targetYear->id,
            'assignment_type' => 'Passage',
            'status' => 'Actif',
            'previous_class_enrollment_id' => $source->id,
            'assigned_by' => $this->admin->id,
        ], 'tenant');
    }

    #[Test]
    public function it_registers_a_repeat_in_a_class_of_the_same_level(): void
    {
        $this->validateDecisions([
            ['student_id' => $this->students['B']->id, 'decision' => 'redoublement', 'target_classe_id' => $this->repeatClasse->id],
        ])->assertOk()->assertJsonCount(1, 'data.processed');

        $this->assertSame('Redoublement', $this->sourceEnrollment($this->students['B'])->final_decision);
        $this->assertDatabaseHas('class_enrollments', [
            'student_id' => $this->students['B']->id,
            'classe_id' => $this->repeatClasse->id,
            'academic_year_id' => $this->targetYear->id,
            'assignment_type' => 'Redoublement',
        ], 'tenant');
    }

    #[Test]
    public function it_records_a_departure_without_creating_an_enrollment(): void
    {
        $this->validateDecisions([
            ['student_id' => $this->students['C']->id, 'decision' => 'depart', 'depart_reason' => 'Transfert'],
            ['student_id' => $this->students['D']->id, 'decision' => 'depart'],
        ])->assertOk()->assertJsonCount(2, 'data.processed');

        $this->assertSame('Transfert', $this->sourceEnrollment($this->students['C'])->final_decision);
        $this->assertSame('Abandon', $this->sourceEnrollment($this->students['D'])->final_decision);
        $this->assertSame('Terminé', $this->sourceEnrollment($this->students['D'])->status);
        $this->assertSame(0, ClassEnrollment::query()->where('academic_year_id', $this->targetYear->id)->count());
    }

    #[Test]
    public function it_refuses_a_promotion_into_a_class_of_the_wrong_level(): void
    {
        $response = $this->validateDecisions([
            ['student_id' => $this->students['A']->id, 'decision' => 'passage', 'target_classe_id' => $this->repeatClasse->id],
            ['student_id' => $this->students['B']->id, 'decision' => 'redoublement', 'target_classe_id' => $this->promotionClasse->id],
        ]);

        $response->assertStatus(422)->assertJsonCount(2, 'data.errors');
        $this->assertStringContainsString('Première', $response->json('data.errors.0.message'));
        $this->assertSame('Actif', $this->sourceEnrollment($this->students['A'])->status);
    }

    #[Test]
    public function it_refuses_a_target_class_that_is_not_in_the_target_year(): void
    {
        $sameYearNextLevel = Classe::factory()->create([
            'academic_year_id' => $this->year->id,
            'level_id' => $this->nextLevel->id,
            'name' => '1ère B',
        ]);

        $response = $this->validateDecisions([
            ['student_id' => $this->students['A']->id, 'decision' => 'passage', 'target_classe_id' => $sameYearNextLevel->id],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString("n'appartient pas à l'année cible", $response->json('data.errors.0.message'));
    }

    #[Test]
    public function it_refuses_a_target_year_equal_to_the_source_year(): void
    {
        $this->postAs($this->admin, self::VALIDATE_URI, [
            'source_classe_id' => $this->classe->id,
            'target_academic_year_id' => $this->year->id,
            'items' => [['student_id' => $this->students['C']->id, 'decision' => 'depart']],
        ])->assertStatus(422)->assertJsonPath('message', "L'année cible doit être différente de l'année de la classe source.");
    }

    #[Test]
    public function it_refuses_a_promotion_when_the_target_class_is_full(): void
    {
        $this->promotionClasse->update(['max_capacity' => 1]);

        $response = $this->validateDecisions([
            ['student_id' => $this->students['A']->id, 'decision' => 'passage', 'target_classe_id' => $this->promotionClasse->id],
            ['student_id' => $this->students['C']->id, 'decision' => 'passage', 'target_classe_id' => $this->promotionClasse->id],
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.processed')
            ->assertJsonPath('data.errors.0.student_id', $this->students['C']->id);
        $this->assertStringContainsString('complète', $response->json('data.errors.0.message'));
        $this->assertSame('Actif', $this->sourceEnrollment($this->students['C'])->status);
    }

    #[Test]
    public function it_refuses_a_duplicate_enrollment_in_the_target_year(): void
    {
        $this->validateDecisions([
            ['student_id' => $this->students['A']->id, 'decision' => 'passage', 'target_classe_id' => $this->promotionClasse->id],
        ])->assertOk();

        $response = $this->validateDecisions([
            ['student_id' => $this->students['A']->id, 'decision' => 'redoublement', 'target_classe_id' => $this->repeatClasse->id],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('déjà inscrit en 1ère A', $response->json('data.errors.0.message'));
        $this->assertSame(1, ClassEnrollment::query()
            ->where('student_id', $this->students['A']->id)
            ->where('academic_year_id', $this->targetYear->id)
            ->count());

        $prepared = collect($this->getAs($this->admin, sprintf(self::PREPARE_URI, $this->classe->id))->json('data.students'))
            ->firstWhere('student.id', $this->students['A']->id);
        $this->assertSame($this->promotionClasse->id, $prepared['target_enrollment']['classe_id']);
    }

    #[Test]
    public function it_refuses_a_student_listed_twice_or_not_enrolled_in_the_source_class(): void
    {
        $this->validateDecisions([
            ['student_id' => $this->students['A']->id, 'decision' => 'depart'],
            ['student_id' => $this->students['A']->id, 'decision' => 'depart'],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.1.student_id']);

        $outsider = Student::factory()->create();

        $this->validateDecisions([
            ['student_id' => $outsider->id, 'decision' => 'depart'],
        ])->assertStatus(422)->assertJsonPath('data.errors.0.student_id', $outsider->id);
    }

    #[Test]
    public function it_requires_a_target_class_for_a_promotion_or_a_repeat(): void
    {
        $this->validateDecisions([
            ['student_id' => $this->students['A']->id, 'decision' => 'passage'],
            ['student_id' => $this->students['B']->id, 'decision' => 'inconnue'],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0.target_classe_id', 'items.1.decision']);
    }

    #[Test]
    public function the_last_level_cannot_be_promoted_and_good_students_are_proposed_to_leave(): void
    {
        $this->classe->level->update(['order_index' => 120]);

        $row = collect($this->getAs($this->admin, sprintf(self::PREPARE_URI, $this->classe->id))
            ->assertOk()
            ->assertJsonPath('data.next_level', null)
            ->json('data.students'))
            ->firstWhere('student.id', $this->students['A']->id);
        $this->assertSame('depart', $row['proposed_decision']);

        $response = $this->validateDecisions([
            ['student_id' => $this->students['A']->id, 'decision' => 'passage', 'target_classe_id' => $this->repeatClasse->id],
        ]);
        $response->assertStatus(422);
        $this->assertStringContainsString('Aucun niveau supérieur', $response->json('data.errors.0.message'));
    }

    #[Test]
    public function manager_can_validate_promotions(): void
    {
        $manager = $this->userWithRole('Manager');

        $this->getAs($manager, sprintf(self::PREPARE_URI, $this->classe->id))->assertOk();
        $this->postAs($manager, self::VALIDATE_URI, [
            'source_classe_id' => $this->classe->id,
            'target_academic_year_id' => $this->targetYear->id,
            'items' => [['student_id' => $this->students['D']->id, 'decision' => 'passage', 'target_classe_id' => $this->promotionClasse->id]],
        ])->assertOk();
    }

    #[Test]
    public function professeur_and_eleve_cannot_access_promotions(): void
    {
        foreach ([$this->headTeacher, $this->studentUser('A')] as $user) {
            $this->getAs($user, sprintf(self::PREPARE_URI, $this->classe->id))->assertForbidden();
            $this->postAs($user, self::VALIDATE_URI, [
                'source_classe_id' => $this->classe->id,
                'target_academic_year_id' => $this->targetYear->id,
                'items' => [['student_id' => $this->students['A']->id, 'decision' => 'depart']],
            ])->assertForbidden();
        }

        $this->assertSame('Actif', $this->sourceEnrollment($this->students['A'])->status);
    }

    #[Test]
    public function student_can_express_an_intention_for_an_open_campaign(): void
    {
        $campaign = $this->openCampaign();
        $studentUser = $this->studentUser('B');

        $this->postAs($studentUser, '/api/frontend/student/reenrollment/intention', [
            'campaign_id' => $campaign->id,
            'wants_to_reenroll' => false,
            'comment' => 'Départ à l\'étranger',
        ])->assertOk()->assertJsonPath('data.wants_to_reenroll', false);

        $this->postAs($studentUser, '/api/frontend/student/reenrollment/intention', [
            'campaign_id' => $campaign->id,
            'wants_to_reenroll' => true,
        ])->assertOk();

        $this->assertSame(1, ReenrollmentIntention::query()->where('student_id', $this->students['B']->id)->count());

        $this->getAs($studentUser, '/api/frontend/student/reenrollment')
            ->assertOk()
            ->assertJsonPath('data.intentions_available', true)
            ->assertJsonPath('data.open_campaigns.0.can_respond', true)
            ->assertJsonPath('data.open_campaigns.0.my_intention.wants_to_reenroll', true);
    }

    #[Test]
    public function prepare_proposes_a_departure_when_the_student_does_not_want_to_reenroll(): void
    {
        $campaign = $this->openCampaign();

        $this->postAs($this->studentUser('A'), '/api/frontend/student/reenrollment/intention', [
            'campaign_id' => $campaign->id,
            'wants_to_reenroll' => false,
        ])->assertOk();

        $row = collect($this->getAs($this->admin, sprintf(self::PREPARE_URI, $this->classe->id))->json('data.students'))
            ->firstWhere('student.id', $this->students['A']->id);

        $this->assertFalse($row['intention']);
        $this->assertSame('depart', $row['proposed_decision']);
    }

    #[Test]
    public function student_cannot_answer_a_closed_campaign_or_one_he_is_not_concerned_by(): void
    {
        $closed = $this->openCampaign(['status' => ReenrollmentCampaign::STATUS_CLOSED]);

        $this->postAs($this->studentUser('A'), '/api/frontend/student/reenrollment/intention', [
            'campaign_id' => $closed->id,
            'wants_to_reenroll' => true,
        ])->assertStatus(422);

        $otherYearCampaign = $this->openCampaign(['from_academic_year_id' => $this->targetYear->id, 'to_academic_year_id' => $this->year->id]);

        $this->postAs($this->studentUser('A'), '/api/frontend/student/reenrollment/intention', [
            'campaign_id' => $otherYearCampaign->id,
            'wants_to_reenroll' => true,
        ])->assertStatus(422);

        $this->postAs($this->studentUser('A'), '/api/frontend/student/reenrollment/intention', [
            'campaign_id' => $otherYearCampaign->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['wants_to_reenroll']);

        $this->assertSame(0, ReenrollmentIntention::query()->count());
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function validateDecisions(array $items): \Illuminate\Testing\TestResponse
    {
        return $this->postAs($this->admin, self::VALIDATE_URI, [
            'source_classe_id' => $this->classe->id,
            'target_academic_year_id' => $this->targetYear->id,
            'items' => $items,
        ]);
    }

    private function sourceEnrollment(Student $student): ClassEnrollment
    {
        return ClassEnrollment::query()
            ->where('student_id', $student->id)
            ->where('classe_id', $this->classe->id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function openCampaign(array $attributes = []): ReenrollmentCampaign
    {
        return ReenrollmentCampaign::query()->create(array_merge([
            'from_academic_year_id' => $this->year->id,
            'to_academic_year_id' => $this->targetYear->id,
            'name' => 'Réinscriptions',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => ReenrollmentCampaign::STATUS_ACTIVE,
        ], $attributes));
    }
}
