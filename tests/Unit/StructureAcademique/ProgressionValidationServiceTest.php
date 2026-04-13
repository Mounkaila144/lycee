<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\EliminatoryModule;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\ProgressionRule;
use Modules\StructureAcademique\Services\ProgressionValidationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ProgressionValidationServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private ProgressionValidationService $service;

    private Programme $programme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        $this->service = new ProgressionValidationService;
        $this->programme = $this->createProgramme();
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

    #[Test]
    public function it_allows_automatic_pass_with_sufficient_credits(): void
    {
        ProgressionRule::factory()
            ->forProgramme($this->programme)
            ->transition('L1', 'L2')
            ->create([
                'min_credits_required' => 45,
            ]);

        $result = $this->service->canProgress(
            $this->programme->id,
            'L1',
            'L2',
            50, // 50 credits acquired
            []
        );

        $this->assertTrue($result->allowed);
        $this->assertEquals('automatic_pass', $result->status);
        $this->assertEquals(50, $result->credits);
    }

    #[Test]
    public function it_allows_conditional_pass_with_acceptable_debt(): void
    {
        ProgressionRule::factory()
            ->forProgramme($this->programme)
            ->transition('L1', 'L2')
            ->create([
                'min_credits_required' => 45,
                'max_debt_allowed' => 15,
                'allow_conditional_pass' => true,
            ]);

        $result = $this->service->canProgress(
            $this->programme->id,
            'L1',
            'L2',
            40, // 40 credits = 5 credits debt
            []
        );

        $this->assertTrue($result->allowed);
        $this->assertEquals('conditional_pass', $result->status);
        $this->assertEquals(40, $result->credits);
        $this->assertEquals(5, $result->debt);
    }

    #[Test]
    public function it_requires_repeat_when_debt_exceeds_maximum(): void
    {
        ProgressionRule::factory()
            ->forProgramme($this->programme)
            ->transition('L1', 'L2')
            ->create([
                'min_credits_required' => 45,
                'max_debt_allowed' => 10,
            ]);

        $result = $this->service->canProgress(
            $this->programme->id,
            'L1',
            'L2',
            30, // 30 credits = 15 credits debt (exceeds max 10)
            []
        );

        $this->assertFalse($result->allowed);
        $this->assertEquals('must_repeat', $result->status);
        $this->assertEquals(30, $result->credits);
        $this->assertEquals(15, $result->debt);
    }

    #[Test]
    public function it_blocks_progression_when_eliminatory_module_not_validated(): void
    {
        ProgressionRule::factory()
            ->forProgramme($this->programme)
            ->transition('L1', 'L2')
            ->create([
                'min_credits_required' => 45,
            ]);

        $eliminatoryModule = Module::factory()->create();

        EliminatoryModule::create([
            'programme_id' => $this->programme->id,
            'module_id' => $eliminatoryModule->id,
            'level' => 'L1',
        ]);

        $result = $this->service->canProgress(
            $this->programme->id,
            'L1',
            'L2',
            50, // Sufficient credits
            [] // But eliminatory module not validated
        );

        $this->assertFalse($result->allowed);
        $this->assertEquals('blocked_eliminatory', $result->status);
        $this->assertNotEmpty($result->missingEliminatoryModules);
    }

    #[Test]
    public function it_allows_progression_when_all_eliminatory_modules_validated(): void
    {
        ProgressionRule::factory()
            ->forProgramme($this->programme)
            ->transition('L1', 'L2')
            ->create([
                'min_credits_required' => 45,
            ]);

        $eliminatoryModule = Module::factory()->create();

        EliminatoryModule::create([
            'programme_id' => $this->programme->id,
            'module_id' => $eliminatoryModule->id,
            'level' => 'L1',
        ]);

        $result = $this->service->canProgress(
            $this->programme->id,
            'L1',
            'L2',
            50,
            [$eliminatoryModule->id] // Eliminatory module validated
        );

        $this->assertTrue($result->allowed);
        $this->assertEquals('automatic_pass', $result->status);
    }

    #[Test]
    public function it_uses_global_rule_when_no_programme_specific_rule_exists(): void
    {
        // Create global rule
        ProgressionRule::factory()
            ->global()
            ->transition('L1', 'L2')
            ->create([
                'min_credits_required' => 45,
            ]);

        $result = $this->service->canProgress(
            $this->programme->id,
            'L1',
            'L2',
            50,
            []
        );

        $this->assertTrue($result->allowed);
        $this->assertEquals('automatic_pass', $result->status);
    }

    #[Test]
    public function it_prioritizes_programme_specific_rule_over_global(): void
    {
        // Global rule: 45 credits
        ProgressionRule::factory()
            ->global()
            ->transition('L1', 'L2')
            ->create([
                'min_credits_required' => 45,
                'max_debt_allowed' => 15,
            ]);

        // Programme-specific rule: 50 credits, no debt allowed
        ProgressionRule::factory()
            ->forProgramme($this->programme)
            ->transition('L1', 'L2')
            ->create([
                'min_credits_required' => 50,
                'max_debt_allowed' => 0,
                'allow_conditional_pass' => false,
            ]);

        $result = $this->service->canProgress(
            $this->programme->id,
            'L1',
            'L2',
            48, // Between 45 and 50
            []
        );

        // Should use programme-specific rule (50 credits required, no debt allowed)
        $this->assertFalse($result->allowed);
        $this->assertEquals('must_repeat', $result->status);
    }

    #[Test]
    public function it_simulates_progression_for_cohort(): void
    {
        ProgressionRule::factory()
            ->forProgramme($this->programme)
            ->transition('L1', 'L2')
            ->create([
                'min_credits_required' => 45,
                'max_debt_allowed' => 10,
            ]);

        $students = [
            ['id' => 1, 'acquired_credits' => 50, 'validated_modules' => []],
            ['id' => 2, 'acquired_credits' => 40, 'validated_modules' => []],
            ['id' => 3, 'acquired_credits' => 30, 'validated_modules' => []],
        ];

        $results = $this->service->simulateProgression(
            $this->programme->id,
            'L1',
            'L2',
            $students
        );

        $this->assertEquals(3, $results['total']);
        $this->assertEquals(1, $results['automatic_pass']);
        $this->assertEquals(1, $results['conditional_pass']);
        $this->assertEquals(1, $results['must_repeat']);
        $this->assertArrayHasKey('pass_rate', $results);
    }

    #[Test]
    public function it_checks_eliminatory_modules_correctly(): void
    {
        $module1 = Module::factory()->create();
        $module2 = Module::factory()->create();

        EliminatoryModule::create([
            'programme_id' => $this->programme->id,
            'module_id' => $module1->id,
            'level' => 'L1',
        ]);

        EliminatoryModule::create([
            'programme_id' => $this->programme->id,
            'module_id' => $module2->id,
            'level' => 'L1',
        ]);

        // Only module1 validated
        $result = $this->service->checkEliminatoryModules(
            $this->programme->id,
            'L1',
            [$module1->id]
        );

        $this->assertFalse($result['all_validated']);
        $this->assertCount(1, $result['missing']);
        $this->assertEquals(2, $result['total']);
    }

    #[Test]
    public function it_returns_no_rule_status_when_rule_not_found(): void
    {
        $result = $this->service->canProgress(
            $this->programme->id,
            'L1',
            'L2',
            50,
            []
        );

        $this->assertFalse($result->allowed);
        $this->assertEquals('no_rule', $result->status);
    }
}
