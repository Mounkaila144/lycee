<?php

namespace Tests\Unit\StructureAcademique;

use Illuminate\Support\Facades\Event;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleSemesterAssignment;
use Modules\StructureAcademique\Entities\ProgramLevel;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Events\ProgrammeActivated;
use Modules\StructureAcademique\Events\ProgrammeDeactivated;
use Modules\StructureAcademique\Services\ProgrammeActivationValidator;
use Modules\StructureAcademique\Services\ProgrammeLifecycleService;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ProgrammeLifecycleServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private ProgrammeLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $validator = new ProgrammeActivationValidator;
        $this->service = new ProgrammeLifecycleService($validator);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_can_activate_complete_programme()
    {
        Event::fake();

        $programme = $this->createCompleteProgramme();

        $result = $this->service->activate($programme);

        $this->assertTrue($result);
        $this->assertEquals('Actif', $programme->fresh()->statut);
        Event::assertDispatched(ProgrammeActivated::class);
    }

    #[Test]
    public function it_cannot_activate_incomplete_programme()
    {
        $programme = Programme::factory()->create([
            'statut' => 'Brouillon',
            'responsable_id' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->activate($programme);
    }

    #[Test]
    public function it_cannot_activate_already_active_programme()
    {
        $programme = $this->createCompleteProgramme();
        $programme->statut = 'Actif';
        $programme->save();

        $this->expectException(\RuntimeException::class);
        $this->service->activate($programme);
    }

    #[Test]
    public function it_can_deactivate_active_programme_without_enrollments()
    {
        Event::fake();

        $programme = $this->createCompleteProgramme();
        $programme->statut = 'Actif';
        $programme->save();

        $result = $this->service->deactivate($programme, 'Test reason');

        $this->assertTrue($result);
        $this->assertEquals('Inactif', $programme->fresh()->statut);
        Event::assertDispatched(ProgrammeDeactivated::class);
    }

    #[Test]
    public function it_cannot_deactivate_non_active_programme()
    {
        $programme = Programme::factory()->create([
            'statut' => 'Brouillon',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->deactivate($programme);
    }

    #[Test]
    public function can_activate_returns_validation_result()
    {
        $programme = $this->createCompleteProgramme();

        $result = $this->service->canActivate($programme);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    #[Test]
    public function can_deactivate_returns_validation_result()
    {
        $programme = $this->createCompleteProgramme();
        $programme->statut = 'Actif';
        $programme->save();

        $result = $this->service->canDeactivate($programme);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    private function createCompleteProgramme(): Programme
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

        return $programme->fresh();
    }
}
