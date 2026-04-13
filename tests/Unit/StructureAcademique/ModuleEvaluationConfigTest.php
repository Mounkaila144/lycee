<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\StructureAcademique\Entities\Semester;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ModuleEvaluationConfigTest extends TestCase
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

    #[Test]
    public function it_can_create_an_evaluation_config(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        $config = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC1',
            'type' => 'CC',
            'coefficient' => 20,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        $this->assertNotNull($config->id);
        $this->assertEquals('CC1', $config->name);
        $this->assertEquals('CC', $config->type);
        $this->assertEquals(20, $config->coefficient);
    }

    #[Test]
    public function it_belongs_to_a_module(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        $config = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'Examen',
            'type' => 'Examen',
            'coefficient' => 60,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        $this->assertInstanceOf(Module::class, $config->module);
        $this->assertEquals($module->id, $config->module->id);
    }

    #[Test]
    public function it_belongs_to_a_semester(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        $config = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'TP',
            'type' => 'TP',
            'coefficient' => 50,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        $this->assertInstanceOf(Semester::class, $config->semester);
        $this->assertEquals($semester->id, $config->semester->id);
    }

    #[Test]
    public function it_can_filter_by_module_and_semester(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();
        $otherSemester = Semester::factory()->create();

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC1',
            'type' => 'CC',
            'coefficient' => 20,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $otherSemester->id,
            'name' => 'CC2',
            'type' => 'CC',
            'coefficient' => 20,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        $configs = ModuleEvaluationConfig::forModuleAndSemester($module->id, $semester->id)->get();

        $this->assertCount(1, $configs);
        $this->assertEquals('CC1', $configs->first()->name);
    }

    #[Test]
    public function it_can_filter_by_status(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC1',
            'type' => 'CC',
            'coefficient' => 20,
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
            'status' => 'Published',
        ]);

        $published = ModuleEvaluationConfig::published()->get();
        $this->assertCount(1, $published);
        $this->assertEquals('Examen', $published->first()->name);
    }

    #[Test]
    public function it_can_check_if_can_be_modified(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        $draft = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC1',
            'type' => 'CC',
            'coefficient' => 20,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        $published = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'Examen',
            'type' => 'Examen',
            'coefficient' => 60,
            'max_score' => 20,
            'order' => 2,
            'status' => 'Published',
        ]);

        $this->assertTrue($draft->canBeModified());
        $this->assertFalse($published->canBeModified());
    }

    #[Test]
    public function it_can_be_published(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        $config = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC1',
            'type' => 'CC',
            'coefficient' => 20,
            'max_score' => 20,
            'order' => 1,
            'status' => 'Draft',
        ]);

        $config->publish();

        $this->assertEquals('Published', $config->fresh()->status);
    }

    #[Test]
    public function it_casts_coefficient_and_scores_to_decimal(): void
    {
        $module = Module::factory()->create();
        $semester = Semester::factory()->create();

        $config = ModuleEvaluationConfig::create([
            'module_id' => $module->id,
            'semester_id' => $semester->id,
            'name' => 'CC1',
            'type' => 'CC',
            'coefficient' => '20.50',
            'max_score' => '20.00',
            'elimination_threshold' => '8.00',
            'order' => 1,
            'status' => 'Draft',
        ]);

        $this->assertIsString($config->coefficient);
        $this->assertIsString($config->max_score);
        $this->assertEquals('20.50', $config->coefficient);
        $this->assertEquals('20.00', $config->max_score);
    }
}
