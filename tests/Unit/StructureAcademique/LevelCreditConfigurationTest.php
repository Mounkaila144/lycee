<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\LevelCreditConfiguration;
use Modules\StructureAcademique\Entities\Programme;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class LevelCreditConfigurationTest extends TestCase
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

    public function test_level_credit_config_can_be_created(): void
    {
        $config = LevelCreditConfiguration::create([
            'level' => 'L1',
            'semester_1_credits' => 30,
            'semester_2_credits' => 30,
        ]);

        $this->assertDatabaseHas('level_credit_configurations', [
            'level' => 'L1',
            'semester_1_credits' => 30,
            'semester_2_credits' => 30,
            'program_id' => null,
        ], 'tenant');

        $this->assertEquals(60, $config->total_credits);
    }

    public function test_total_credits_attribute_works(): void
    {
        $config = new LevelCreditConfiguration([
            'semester_1_credits' => 32,
            'semester_2_credits' => 28,
        ]);

        $this->assertEquals(60, $config->total_credits);
    }

    public function test_imbalance_check_works(): void
    {
        $balanced = new LevelCreditConfiguration([
            'semester_1_credits' => 30,
            'semester_2_credits' => 30,
        ]);
        $this->assertFalse($balanced->hasImbalancedDistribution());

        $slightlyImbalanced = new LevelCreditConfiguration([
            'semester_1_credits' => 35,
            'semester_2_credits' => 25,
        ]);
        $this->assertFalse($slightlyImbalanced->hasImbalancedDistribution()); // diff = 10 (not > 10)

        $imbalanced = new LevelCreditConfiguration([
            'semester_1_credits' => 36,
            'semester_2_credits' => 24,
        ]);
        $this->assertTrue($imbalanced->hasImbalancedDistribution()); // diff = 12 (> 10)
    }

    public function test_get_for_program_level_priority(): void
    {
        // Créer un programme sans responsable (évite la création d'un User)
        $programme = Programme::create([
            'code' => 'TEST-001',
            'libelle' => 'Programme Test',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Actif',
        ]);

        // Global config
        LevelCreditConfiguration::create([
            'level' => 'L1',
            'semester_1_credits' => 30,
            'semester_2_credits' => 30,
        ]);

        // Specific config
        LevelCreditConfiguration::create([
            'program_id' => $programme->id,
            'level' => 'L1',
            'semester_1_credits' => 35,
            'semester_2_credits' => 25,
        ]);

        $effective = LevelCreditConfiguration::getForProgramLevel($programme->id, 'L1');

        $this->assertEquals($programme->id, $effective->program_id);
        $this->assertEquals(35, $effective->semester_1_credits);
    }

    public function test_get_for_program_level_falls_back_to_global(): void
    {
        // Créer un programme sans responsable
        $programme = Programme::create([
            'code' => 'TEST-002',
            'libelle' => 'Programme Test 2',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Actif',
        ]);

        // Global config
        LevelCreditConfiguration::create([
            'level' => 'L1',
            'semester_1_credits' => 30,
            'semester_2_credits' => 30,
        ]);

        $effective = LevelCreditConfiguration::getForProgramLevel($programme->id, 'L1');

        $this->assertNull($effective->program_id);
        $this->assertEquals(30, $effective->semester_1_credits);
    }

    public function test_program_has_credit_configurations_relationship(): void
    {
        // Créer un programme sans responsable
        $programme = Programme::create([
            'code' => 'TEST-003',
            'libelle' => 'Programme Test 3',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Actif',
        ]);

        $programme->creditConfigurations()->create([
            'level' => 'L1',
            'semester_1_credits' => 30,
            'semester_2_credits' => 30,
        ]);

        $this->assertCount(1, $programme->creditConfigurations);
        $this->assertEquals('L1', $programme->creditConfigurations->first()->level);
    }
}
