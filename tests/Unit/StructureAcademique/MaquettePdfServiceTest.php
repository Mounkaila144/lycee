<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Services\MaquettePdfService;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class MaquettePdfServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private MaquettePdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = new MaquettePdfService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_generates_pdf_content_for_programme(): void
    {
        $programme = Programme::factory()->create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Actif',
            'responsable_id' => null,
        ]);

        $programme->programLevels()->create(['level' => 'L1']);

        $module = Module::factory()->create([
            'code' => 'INF101',
            'name' => 'Algorithmique',
            'level' => 'L1',
            'semester' => 'S1',
            'credits_ects' => 6,
            'coefficient' => 2.0,
            'type' => 'Obligatoire',
            'hours_cm' => 20,
            'hours_td' => 20,
            'hours_tp' => 10,
        ]);

        $module->programmes()->attach($programme->id);

        $pdfContent = $this->service->generatePdfContent($programme);

        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    public function test_generates_pdf_with_level_filter(): void
    {
        $programme = Programme::factory()->create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'type' => 'Licence',
            'duree_annees' => 3,
            'responsable_id' => null,
        ]);

        $programme->programLevels()->create(['level' => 'L1']);
        $programme->programLevels()->create(['level' => 'L2']);

        $moduleL1 = Module::factory()->create([
            'code' => 'INF101',
            'name' => 'Algo L1',
            'level' => 'L1',
            'semester' => 'S1',
        ]);

        $moduleL2 = Module::factory()->create([
            'code' => 'INF201',
            'name' => 'Algo L2',
            'level' => 'L2',
            'semester' => 'S3',
        ]);

        $moduleL1->programmes()->attach($programme->id);
        $moduleL2->programmes()->attach($programme->id);

        $pdfContent = $this->service->generatePdfContent($programme, ['level' => 'L1']);

        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    public function test_generates_pdf_without_optional_modules(): void
    {
        $programme = Programme::factory()->create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'responsable_id' => null,
        ]);

        $programme->programLevels()->create(['level' => 'L1']);

        $obligatoire = Module::factory()->create([
            'code' => 'INF101',
            'name' => 'Module Obligatoire',
            'level' => 'L1',
            'semester' => 'S1',
            'type' => 'Obligatoire',
        ]);

        $optionnel = Module::factory()->create([
            'code' => 'INF102',
            'name' => 'Module Optionnel',
            'level' => 'L1',
            'semester' => 'S1',
            'type' => 'Optionnel',
        ]);

        $obligatoire->programmes()->attach($programme->id);
        $optionnel->programmes()->attach($programme->id);

        $pdfContent = $this->service->generatePdfContent($programme, ['show_optional' => false]);

        $this->assertNotEmpty($pdfContent);
    }

    public function test_generates_pdf_without_teachers(): void
    {
        $programme = Programme::factory()->create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'responsable_id' => null,
        ]);

        $programme->programLevels()->create(['level' => 'L1']);

        $module = Module::factory()->create([
            'code' => 'INF101',
            'name' => 'Algorithmique',
            'level' => 'L1',
            'semester' => 'S1',
        ]);

        $module->programmes()->attach($programme->id);

        $pdfContent = $this->service->generatePdfContent($programme, ['show_teachers' => false]);

        $this->assertNotEmpty($pdfContent);
    }

    public function test_generates_pdf_without_hours(): void
    {
        $programme = Programme::factory()->create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'responsable_id' => null,
        ]);

        $programme->programLevels()->create(['level' => 'L1']);

        $module = Module::factory()->create([
            'code' => 'INF101',
            'name' => 'Algorithmique',
            'level' => 'L1',
            'semester' => 'S1',
            'hours_cm' => 20,
            'hours_td' => 20,
            'hours_tp' => 10,
        ]);

        $module->programmes()->attach($programme->id);

        $pdfContent = $this->service->generatePdfContent($programme, ['show_hours' => false]);

        $this->assertNotEmpty($pdfContent);
    }

    public function test_saves_pdf_to_storage(): void
    {
        $programme = Programme::factory()->create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'responsable_id' => null,
        ]);

        $programme->programLevels()->create(['level' => 'L1']);

        $module = Module::factory()->create([
            'code' => 'INF101',
            'name' => 'Algorithmique',
            'level' => 'L1',
            'semester' => 'S1',
        ]);

        $module->programmes()->attach($programme->id);

        $path = $this->service->generateProgramMaquette($programme);

        $this->assertNotEmpty($path);
        $this->assertStringContainsString('documents/maquettes/', $path);
        $this->assertStringContainsString('maquette_INFO-L', $path);
        $this->assertStringEndsWith('.pdf', $path);
    }

    public function test_generates_pdf_for_empty_programme(): void
    {
        $programme = Programme::factory()->create([
            'code' => 'EMPTY',
            'libelle' => 'Programme Vide',
            'responsable_id' => null,
        ]);

        $pdfContent = $this->service->generatePdfContent($programme);

        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    public function test_get_semester_label_returns_correct_labels(): void
    {
        $this->assertEquals('Semestre 1', $this->service->getSemesterLabel('S1'));
        $this->assertEquals('Semestre 5', $this->service->getSemesterLabel('S5'));
        $this->assertEquals('Semestre 10', $this->service->getSemesterLabel('S10'));
        $this->assertEquals('Unknown', $this->service->getSemesterLabel('Unknown'));
    }
}
