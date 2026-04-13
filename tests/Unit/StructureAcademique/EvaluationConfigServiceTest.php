<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\EvaluationTemplate;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Services\EvaluationConfigService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class EvaluationConfigServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private EvaluationConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        $this->service = new EvaluationConfigService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_can_apply_template_to_module(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        $template = EvaluationTemplate::create([
            'name' => 'Standard',
            'description' => 'Test template',
            'is_active' => true,
            'config_json' => [
                'evaluations' => [
                    [
                        'name' => 'CC1',
                        'type' => 'CC',
                        'coefficient' => 40,
                        'max_score' => 20,
                    ],
                    [
                        'name' => 'Examen',
                        'type' => 'Examen',
                        'coefficient' => 60,
                        'max_score' => 20,
                    ],
                ],
            ],
        ]);

        $this->service->applyTemplate($module, $semester, $template);

        $configs = ModuleEvaluationConfig::forModuleAndSemester($module->id, $semester->id)->get();

        $this->assertCount(2, $configs);
        $this->assertEquals('CC1', $configs[0]->name);
        $this->assertEquals('Examen', $configs[1]->name);
    }

    #[Test]
    public function it_validates_configuration_with_correct_total_coefficient(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC',
            'type' => 'CC',
            'coefficient' => 40,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'Examen',
            'type' => 'Examen',
            'coefficient' => 60,
            'max_score' => 20,
            'order' => 2,
            'status' => 'Draft',
        ]);

        $result = $this->service->validateConfiguration($module, $semester);

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->warnings);
        $this->assertEmpty($result->errors);
    }

    #[Test]
    public function it_warns_when_total_coefficient_is_not_100(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC',
            'type' => 'CC',
            'coefficient' => 30,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'Examen',
            'type' => 'Examen',
            'coefficient' => 60,
            'max_score' => 20,
            'order' => 2,
            'status' => 'Draft',
        ]);

        $result = $this->service->validateConfiguration($module, $semester);

        $this->assertTrue($result->valid);
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('90%', $result->warnings[0]);
    }

    #[Test]
    public function it_errors_when_eliminatory_module_has_no_exam(): void
    {
        $module = Module::factory()->create(['is_eliminatory' => true]);
        $semester = Semester::factory()->create();

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC',
            'type' => 'CC',
            'coefficient' => 100,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        $result = $this->service->validateConfiguration($module, $semester);

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('Examen', $result->errors[0]);
    }

    #[Test]
    public function it_errors_when_multiple_rattrapage_evaluations_exist(): void
    {
        $module = Module::factory()->create(['is_eliminatory' => false]);
        $semester = Semester::factory()->create();

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'Rattrapage 1',
            'type' => 'Rattrapage',
            'coefficient' => 50,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'Rattrapage 2',
            'type' => 'Rattrapage',
            'coefficient' => 50,
            'max_score' => 20,
            'order' => 2,
            'status' => 'Draft',
        ]);

        $result = $this->service->validateConfiguration($module, $semester);

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('Rattrapage', $result->errors[0]);
    }

    #[Test]
    public function it_can_publish_valid_configuration(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC',
            'type' => 'CC',
            'coefficient' => 40,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'Examen',
            'type' => 'Examen',
            'coefficient' => 60,
            'max_score' => 20,
            'order' => 2,
            'status' => 'Draft',
        ]);

        $result = $this->service->publishConfiguration($module, $semester);

        $this->assertTrue($result);

        $configs = ModuleEvaluationConfig::forModuleAndSemester($module->id, $semester->id)->get();
        $this->assertTrue($configs->every(fn ($c) => $c->status === 'Published'));
    }

    #[Test]
    public function it_cannot_publish_invalid_configuration(): void
    {
        $this->expectException(\RuntimeException::class);

        $module = Module::factory()->create(['is_eliminatory' => true]);
        $semester = Semester::factory()->create();

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC',
            'type' => 'CC',
            'coefficient' => 100,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        $this->service->publishConfiguration($module, $semester);
    }

    #[Test]
    public function it_can_calculate_weighted_average(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        $config1 = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC',
            'type' => 'CC',
            'coefficient' => 40,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Published',
        ]);

        $config2 = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'Examen',
            'type' => 'Examen',
            'coefficient' => 60,
            'max_score' => 20,
            'order' => 2,
            'status' => 'Published',
        ]);

        $grades = [
            $config1->id => 15,
            $config2->id => 12,
        ];

        $average = $this->service->calculateWeightedAverage($grades, $module, $semester);

        // (15 * 40 + 12 * 60) / 100 = (600 + 720) / 100 = 13.20
        $this->assertEquals(13.20, $average);
    }

    #[Test]
    public function it_returns_null_for_eliminatory_failure(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        $config1 = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC',
            'type' => 'CC',
            'coefficient' => 40,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Published',
        ]);

        $config2 = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'Examen',
            'type' => 'Examen',
            'coefficient' => 60,
            'max_score' => 20,
            'is_eliminatory' => true,
            'elimination_threshold' => 8,
            'order' => 2,
            'status' => 'Published',
        ]);

        $grades = [
            $config1->id => 15,
            $config2->id => 7, // Below threshold
        ];

        $average = $this->service->calculateWeightedAverage($grades, $module, $semester);

        $this->assertNull($average);
    }
}
