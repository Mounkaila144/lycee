<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\CoreCurriculumModule;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Specialization;
use Modules\StructureAcademique\Entities\SpecializationModule;
use Modules\StructureAcademique\Entities\StudentModuleChoice;
use Modules\StructureAcademique\Services\CurriculumService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class CurriculumServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private CurriculumService $service;

    private Programme $programme;

    private Specialization $specialization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        $this->service = new CurriculumService;
        $this->programme = $this->createProgramme();
        $this->specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function createProgramme(array $attributes = []): Programme
    {
        return Programme::create(array_merge([
            'code' => 'TEST-'.uniqid(),
            'libelle' => 'Programme Test',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Actif',
        ], $attributes));
    }

    #[Test]
    public function it_can_add_module_to_core_curriculum(): void
    {
        $module = Module::factory()->create();

        $result = $this->service->addCoreCurriculumModule(
            $this->programme->id,
            'L1',
            $module->id
        );

        $this->assertTrue($result->success);
        $this->assertEquals('Module ajouté au tronc commun.', $result->message);

        $this->assertDatabaseHas('core_curriculum_modules', [
            'programme_id' => $this->programme->id,
            'level' => 'L1',
            'module_id' => $module->id,
        ], 'tenant');
    }

    #[Test]
    public function it_prevents_duplicate_core_curriculum_module(): void
    {
        $module = Module::factory()->create();

        CoreCurriculumModule::create([
            'programme_id' => $this->programme->id,
            'level' => 'L1',
            'module_id' => $module->id,
        ]);

        $result = $this->service->addCoreCurriculumModule(
            $this->programme->id,
            'L1',
            $module->id
        );

        $this->assertFalse($result->success);
        $this->assertEquals('Ce module est déjà dans le tronc commun pour ce niveau.', $result->message);
    }

    #[Test]
    public function it_can_remove_module_from_core_curriculum(): void
    {
        $module = Module::factory()->create();

        CoreCurriculumModule::create([
            'programme_id' => $this->programme->id,
            'level' => 'L1',
            'module_id' => $module->id,
        ]);

        $result = $this->service->removeCoreCurriculumModule(
            $this->programme->id,
            'L1',
            $module->id
        );

        $this->assertTrue($result->success);
        $this->assertEquals('Module retiré du tronc commun.', $result->message);

        $this->assertDatabaseMissing('core_curriculum_modules', [
            'programme_id' => $this->programme->id,
            'module_id' => $module->id,
        ], 'tenant');
    }

    #[Test]
    public function it_returns_failure_when_removing_nonexistent_core_module(): void
    {
        $result = $this->service->removeCoreCurriculumModule(
            $this->programme->id,
            'L1',
            999
        );

        $this->assertFalse($result->success);
        $this->assertEquals('Module non trouvé dans le tronc commun.', $result->message);
    }

    #[Test]
    public function it_can_add_mandatory_module_to_specialization(): void
    {
        $module = Module::factory()->create();

        $result = $this->service->addSpecializationModule(
            $this->specialization->id,
            $module->id,
            'Obligatoire'
        );

        $this->assertTrue($result->success);
        $this->assertEquals('Module ajouté à la spécialité.', $result->message);

        $this->assertDatabaseHas('specialization_modules', [
            'specialization_id' => $this->specialization->id,
            'module_id' => $module->id,
            'type' => 'Obligatoire',
            'capacity' => null,
        ], 'tenant');
    }

    #[Test]
    public function it_can_add_optional_module_with_capacity(): void
    {
        $module = Module::factory()->create();

        $result = $this->service->addSpecializationModule(
            $this->specialization->id,
            $module->id,
            'Optionnel',
            25
        );

        $this->assertTrue($result->success);

        $this->assertDatabaseHas('specialization_modules', [
            'specialization_id' => $this->specialization->id,
            'module_id' => $module->id,
            'type' => 'Optionnel',
            'capacity' => 25,
        ], 'tenant');
    }

    #[Test]
    public function it_prevents_duplicate_specialization_module(): void
    {
        $module = Module::factory()->create();

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $module->id,
            'type' => 'Obligatoire',
        ]);

        $result = $this->service->addSpecializationModule(
            $this->specialization->id,
            $module->id,
            'Optionnel'
        );

        $this->assertFalse($result->success);
        $this->assertEquals('Ce module est déjà associé à cette spécialité.', $result->message);
    }

    #[Test]
    public function it_can_remove_module_from_specialization(): void
    {
        $module = Module::factory()->create();

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $module->id,
            'type' => 'Obligatoire',
        ]);

        $result = $this->service->removeSpecializationModule(
            $this->specialization->id,
            $module->id
        );

        $this->assertTrue($result->success);
        $this->assertEquals('Module retiré de la spécialité.', $result->message);
    }

    #[Test]
    public function it_can_choose_valid_electives(): void
    {
        $module1 = Module::factory()->create();
        $module2 = Module::factory()->create();

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $module1->id,
            'type' => 'Optionnel',
        ]);

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $module2->id,
            'type' => 'Optionnel',
        ]);

        $result = $this->service->chooseElectives(
            studentId: 1,
            specializationId: $this->specialization->id,
            moduleIds: [$module1->id, $module2->id]
        );

        $this->assertTrue($result->success);
        $this->assertStringContainsString('2 module(s) optionnel(s) sélectionné(s)', $result->message);
        $this->assertCount(2, $result->data['choices']);
    }

    #[Test]
    public function it_rejects_invalid_elective_choices(): void
    {
        $mandatoryModule = Module::factory()->create();

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $mandatoryModule->id,
            'type' => 'Obligatoire',
        ]);

        $result = $this->service->chooseElectives(
            studentId: 1,
            specializationId: $this->specialization->id,
            moduleIds: [$mandatoryModule->id]
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('ne sont pas des options valides', $result->message);
    }

    #[Test]
    public function it_rejects_elective_when_capacity_full(): void
    {
        $module = Module::factory()->create();

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $module->id,
            'type' => 'Optionnel',
            'capacity' => 1,
        ]);

        // First student fills the capacity
        StudentModuleChoice::create([
            'student_id' => 1,
            'module_id' => $module->id,
            'specialization_id' => $this->specialization->id,
            'choice_date' => now(),
            'status' => 'Confirmé',
        ]);

        // Second student tries to choose
        $result = $this->service->chooseElectives(
            studentId: 2,
            specializationId: $this->specialization->id,
            moduleIds: [$module->id]
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('est complet', $result->message);
    }

    #[Test]
    public function it_can_confirm_elective_choices(): void
    {
        $module = Module::factory()->create();

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $module->id,
            'type' => 'Optionnel',
        ]);

        StudentModuleChoice::create([
            'student_id' => 1,
            'module_id' => $module->id,
            'specialization_id' => $this->specialization->id,
            'choice_date' => now(),
            'status' => 'En attente',
        ]);

        $result = $this->service->confirmElectiveChoices(1, $this->specialization->id);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('1 choix confirmé(s)', $result->message);

        $this->assertDatabaseHas('student_module_choices', [
            'student_id' => 1,
            'module_id' => $module->id,
            'status' => 'Confirmé',
        ], 'tenant');
    }

    #[Test]
    public function it_returns_failure_when_no_pending_choices(): void
    {
        $result = $this->service->confirmElectiveChoices(1, $this->specialization->id);

        $this->assertFalse($result->success);
        $this->assertEquals('Aucun choix en attente à confirmer.', $result->message);
    }

    #[Test]
    public function it_can_get_student_curriculum(): void
    {
        $coreModule = Module::factory()->create();
        $mandatoryModule = Module::factory()->create();
        $electiveModule = Module::factory()->create();

        CoreCurriculumModule::create([
            'programme_id' => $this->programme->id,
            'level' => 'L3',
            'module_id' => $coreModule->id,
        ]);

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $mandatoryModule->id,
            'type' => 'Obligatoire',
        ]);

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $electiveModule->id,
            'type' => 'Optionnel',
        ]);

        StudentModuleChoice::create([
            'student_id' => 1,
            'module_id' => $electiveModule->id,
            'specialization_id' => $this->specialization->id,
            'choice_date' => now(),
            'status' => 'Confirmé',
        ]);

        $curriculum = $this->service->getStudentCurriculum(
            studentId: 1,
            programmeId: $this->programme->id,
            level: 'L3',
            specializationId: $this->specialization->id
        );

        $this->assertCount(1, $curriculum['core']);
        $this->assertCount(1, $curriculum['mandatory']);
        $this->assertCount(1, $curriculum['electives']);
        $this->assertEquals(3, $curriculum['total_modules']);
    }

    #[Test]
    public function it_can_get_available_electives(): void
    {
        $optionalModule = Module::factory()->create();
        $mandatoryModule = Module::factory()->create();

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $optionalModule->id,
            'type' => 'Optionnel',
            'capacity' => 20,
        ]);

        SpecializationModule::create([
            'specialization_id' => $this->specialization->id,
            'module_id' => $mandatoryModule->id,
            'type' => 'Obligatoire',
        ]);

        $electives = $this->service->getAvailableElectives($this->specialization->id);

        $this->assertCount(1, $electives);
        $this->assertEquals($optionalModule->id, $electives->first()['module']->id);
        $this->assertEquals(20, $electives->first()['capacity']);
    }
}
