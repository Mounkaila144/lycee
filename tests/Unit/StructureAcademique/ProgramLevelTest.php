<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\ProgramLevel;
use Modules\StructureAcademique\Entities\Programme;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ProgramLevelTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper pour créer un programme sans User (évite les erreurs de migration)
     */
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

    public function test_program_level_can_be_created(): void
    {
        $programme = $this->createProgramme(['type' => 'Licence']);

        $level = ProgramLevel::create([
            'program_id' => $programme->id,
            'level' => 'L1',
        ]);

        $this->assertDatabaseHas('program_levels', [
            'program_id' => $programme->id,
            'level' => 'L1',
        ], 'tenant');

        $this->assertEquals('L1', $level->level);
        $this->assertEquals($programme->id, $level->program_id);
    }

    public function test_program_level_belongs_to_program(): void
    {
        $programme = $this->createProgramme(['type' => 'Licence']);
        $level = ProgramLevel::create([
            'program_id' => $programme->id,
            'level' => 'L1',
        ]);

        $this->assertInstanceOf(Programme::class, $level->program);
        $this->assertEquals($programme->id, $level->program->id);
    }

    public function test_validate_level_for_program_type_returns_true_for_valid_licence(): void
    {
        $programme = $this->createProgramme(['type' => 'Licence']);
        $level = ProgramLevel::create([
            'program_id' => $programme->id,
            'level' => 'L1',
        ]);

        $this->assertTrue($level->validateLevelForProgramType());
    }

    public function test_validate_level_for_program_type_returns_false_for_invalid_licence(): void
    {
        $programme = $this->createProgramme(['type' => 'Licence']);
        $level = ProgramLevel::create([
            'program_id' => $programme->id,
            'level' => 'M1',
        ]);

        $this->assertFalse($level->validateLevelForProgramType());
    }

    public function test_validate_level_for_program_type_returns_true_for_valid_master(): void
    {
        $programme = $this->createProgramme(['type' => 'Master', 'duree_annees' => 2]);
        $level = ProgramLevel::create([
            'program_id' => $programme->id,
            'level' => 'M1',
        ]);

        $this->assertTrue($level->validateLevelForProgramType());
    }

    public function test_validate_level_for_program_type_returns_false_for_invalid_master(): void
    {
        $programme = $this->createProgramme(['type' => 'Master', 'duree_annees' => 2]);
        $level = ProgramLevel::create([
            'program_id' => $programme->id,
            'level' => 'L1',
        ]);

        $this->assertFalse($level->validateLevelForProgramType());
    }

    public function test_scope_by_level_filters_correctly(): void
    {
        $programme = $this->createProgramme(['type' => 'Licence']);
        ProgramLevel::create(['program_id' => $programme->id, 'level' => 'L1']);
        ProgramLevel::create(['program_id' => $programme->id, 'level' => 'L2']);

        $levels = ProgramLevel::byLevel('L1')->get();

        $this->assertCount(1, $levels);
        $this->assertEquals('L1', $levels->first()->level);
    }

    public function test_scope_by_program_filters_correctly(): void
    {
        $programme1 = $this->createProgramme(['type' => 'Licence']);
        $programme2 = $this->createProgramme(['type' => 'Master', 'duree_annees' => 2]);

        ProgramLevel::create(['program_id' => $programme1->id, 'level' => 'L1']);
        ProgramLevel::create(['program_id' => $programme2->id, 'level' => 'M1']);

        $levels = ProgramLevel::byProgram($programme1->id)->get();

        $this->assertCount(1, $levels);
        $this->assertEquals($programme1->id, $levels->first()->program_id);
    }

    public function test_program_has_levels_relationship(): void
    {
        $programme = $this->createProgramme(['type' => 'Licence']);
        $programme->levels()->create(['level' => 'L1']);
        $programme->levels()->create(['level' => 'L2']);

        $this->assertCount(2, $programme->levels);
        $this->assertTrue($programme->hasLevel('L1'));
        $this->assertTrue($programme->hasLevel('L2'));
        $this->assertFalse($programme->hasLevel('L3'));
    }

    public function test_program_can_be_activated_with_levels(): void
    {
        $programme = $this->createProgramme([
            'type' => 'Licence',
            'statut' => 'Brouillon',
            'code' => 'TEST-ACTIV',
            'libelle' => 'Test Programme',
            'duree_annees' => 3,
        ]);

        // Sans niveaux, ne peut pas être activé
        $this->assertFalse($programme->canBeActivated());

        // Avec niveaux, peut être activé
        $programme->levels()->create(['level' => 'L1']);
        $programme->refresh();

        $this->assertTrue($programme->canBeActivated());
    }
}
