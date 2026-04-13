<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleSemesterAssignment;
use Modules\StructureAcademique\Entities\ProgramLevel;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Services\ProgrammeActivationValidator;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ProgrammeActivationValidatorTest extends TestCase
{
    use InteractsWithTenancy;

    private ProgrammeActivationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->validator = new ProgrammeActivationValidator;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_validates_complete_programme_successfully()
    {
        $responsable = User::factory()->create();
        $programme = Programme::factory()->create([
            'responsable_id' => $responsable->id,
            'statut' => 'Brouillon',
        ]);

        ProgramLevel::factory()->create([
            'program_id' => $programme->id,
            'level' => 'L3',
        ]);

        $semester = Semester::factory()->create();
        $module = Module::factory()->create(['credits_ects' => 6]);

        ModuleSemesterAssignment::create([
            'module_id' => $module->id,
            'programme_id' => $programme->id,
            'semester_id' => $semester->id,
            'is_active' => true,
        ]);

        $result = $this->validator->validate($programme->fresh());

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    #[Test]
    public function it_fails_validation_when_no_levels_associated()
    {
        $responsable = User::factory()->create();
        $programme = Programme::factory()->create([
            'responsable_id' => $responsable->id,
        ]);

        $result = $this->validator->validate($programme);

        $this->assertFalse($result->isValid);
        $this->assertContains('Le programme doit avoir au moins un niveau associé.', $result->errors);
    }

    #[Test]
    public function it_fails_validation_when_no_modules_assigned()
    {
        $responsable = User::factory()->create();
        $programme = Programme::factory()->create([
            'responsable_id' => $responsable->id,
        ]);

        ProgramLevel::factory()->create([
            'program_id' => $programme->id,
            'level' => 'L1',
        ]);

        $result = $this->validator->validate($programme->fresh());

        $this->assertFalse($result->isValid);
        $this->assertContains('Le programme doit avoir au moins un module associé.', $result->errors);
    }

    #[Test]
    public function it_fails_validation_when_modules_have_no_credits()
    {
        $responsable = User::factory()->create();
        $programme = Programme::factory()->create([
            'responsable_id' => $responsable->id,
        ]);

        ProgramLevel::factory()->create([
            'program_id' => $programme->id,
        ]);

        $semester = Semester::factory()->create();
        $module = Module::factory()->create(['credits_ects' => 0]);

        ModuleSemesterAssignment::create([
            'module_id' => $module->id,
            'programme_id' => $programme->id,
            'semester_id' => $semester->id,
            'is_active' => true,
        ]);

        $result = $this->validator->validate($programme->fresh());

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('n\'ont pas de crédits ECTS définis', $result->errors[0]);
    }

    #[Test]
    public function it_fails_validation_when_no_responsable_assigned()
    {
        $programme = Programme::factory()->create([
            'responsable_id' => null,
        ]);

        ProgramLevel::factory()->create([
            'program_id' => $programme->id,
        ]);

        $semester = Semester::factory()->create();
        $module = Module::factory()->create(['credits_ects' => 6]);

        ModuleSemesterAssignment::create([
            'module_id' => $module->id,
            'programme_id' => $programme->id,
            'semester_id' => $semester->id,
            'is_active' => true,
        ]);

        $result = $this->validator->validate($programme->fresh());

        $this->assertFalse($result->isValid);
        $this->assertContains('Un responsable de programme doit être assigné.', $result->errors);
    }

    #[Test]
    public function it_adds_warning_when_no_description()
    {
        $responsable = User::factory()->create();
        $programme = Programme::factory()->create([
            'responsable_id' => $responsable->id,
            'description' => null,
        ]);

        ProgramLevel::factory()->create([
            'program_id' => $programme->id,
        ]);

        $semester = Semester::factory()->create();
        $module = Module::factory()->create(['credits_ects' => 6]);

        ModuleSemesterAssignment::create([
            'module_id' => $module->id,
            'programme_id' => $programme->id,
            'semester_id' => $semester->id,
            'is_active' => true,
        ]);

        $result = $this->validator->validate($programme->fresh());

        $this->assertTrue($result->isValid);
        $this->assertContains('Aucune description n\'est définie pour ce programme.', $result->warnings);
    }
}
