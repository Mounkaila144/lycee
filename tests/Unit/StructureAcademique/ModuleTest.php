<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Module;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ModuleTest extends TestCase
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

    public function test_it_can_create_a_module(): void
    {
        $module = Module::create([
            'code' => 'INF101',
            'name' => 'Introduction à l\'Informatique',
            'credits_ects' => 4,
            'coefficient' => 2.0,
            'type' => 'Obligatoire',
            'semester' => 'S1',
            'level' => 'L1',
            'description' => 'Cours d\'introduction',
            'hours_cm' => 20,
            'hours_td' => 10,
            'hours_tp' => 5,
            'is_eliminatory' => false,
        ]);

        $this->assertDatabaseHas('modules', [
            'code' => 'INF101',
            'name' => 'Introduction à l\'Informatique',
        ], 'tenant');
    }

    public function test_total_hours_attribute_is_calculated_correctly(): void
    {
        $module = Module::factory()->create([
            'hours_cm' => 20,
            'hours_td' => 15,
            'hours_tp' => 10,
        ]);

        $this->assertEquals(45, $module->total_hours);
    }

    public function test_total_hours_handles_null_values(): void
    {
        $module = Module::factory()->create([
            'hours_cm' => 20,
            'hours_td' => null,
            'hours_tp' => 10,
        ]);

        $this->assertEquals(30, $module->total_hours);
    }

    public function test_semester_level_consistency_is_validated(): void
    {
        // Cohérent: L1 avec S1
        $moduleValid = Module::factory()->create([
            'level' => 'L1',
            'semester' => 'S1',
        ]);
        $this->assertTrue($moduleValid->isSemesterLevelConsistent());

        // Incohérent: L1 avec S3
        $moduleInvalid = Module::factory()->create([
            'level' => 'L1',
            'semester' => 'S3',
        ]);
        $this->assertFalse($moduleInvalid->isSemesterLevelConsistent());
    }

    public function test_minimum_hours_validation(): void
    {
        $moduleSufficient = Module::factory()->create([
            'hours_cm' => 10,
            'hours_td' => 5,
            'hours_tp' => 5,
        ]);
        $this->assertTrue($moduleSufficient->hasMinimumHours());

        $moduleInsufficient = Module::factory()->create([
            'hours_cm' => 5,
            'hours_td' => 5,
            'hours_tp' => 0,
        ]);
        $this->assertFalse($moduleInsufficient->hasMinimumHours());
    }

    public function test_it_has_scope_for_level(): void
    {
        Module::factory()->count(3)->forLevel('L1')->create();
        Module::factory()->count(2)->forLevel('L2')->create();

        $l1Modules = Module::byLevel('L1')->get();

        $this->assertCount(3, $l1Modules);
    }

    public function test_it_has_scope_for_semester(): void
    {
        Module::factory()->create(['level' => 'L1', 'semester' => 'S1']);
        Module::factory()->create(['level' => 'L1', 'semester' => 'S1']);
        Module::factory()->create(['level' => 'L1', 'semester' => 'S2']);

        $s1Modules = Module::bySemester('S1')->get();

        $this->assertCount(2, $s1Modules);
    }

    public function test_it_has_scope_for_obligatoire_modules(): void
    {
        Module::factory()->count(3)->obligatoire()->create();
        Module::factory()->count(2)->optionnel()->create();

        $obligatoireModules = Module::obligatoire()->get();

        $this->assertCount(3, $obligatoireModules);
    }

    public function test_it_has_scope_for_eliminatory_modules(): void
    {
        Module::factory()->count(2)->eliminatory()->create();
        Module::factory()->count(3)->create(['is_eliminatory' => false]);

        $eliminatoryModules = Module::eliminatory()->get();

        $this->assertCount(2, $eliminatoryModules);
    }
}
