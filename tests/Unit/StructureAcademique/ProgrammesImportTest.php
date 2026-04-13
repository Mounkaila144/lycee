<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Imports\ProgrammesImport;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ProgrammesImportTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $import = new ProgrammesImport(previewMode: true);

        // Create a fake Excel with missing required fields
        $rows = collect([
            ['code' => '', 'libelle' => 'Test', 'type' => 'Licence', 'duree_annees' => 3, 'responsable_email' => '', 'description' => ''],
        ]);

        $this->simulateImport($import, $rows);
        $preview = $import->getPreviewData();

        $this->assertFalse($preview[0]['valid']);
        $this->assertContains('Le code est obligatoire', $preview[0]['errors']);
    }

    #[Test]
    public function it_detects_duplicate_codes(): void
    {
        // Create existing programme
        Programme::create([
            'code' => 'INFO-L',
            'libelle' => 'Existing Programme',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Brouillon',
        ]);

        $import = new ProgrammesImport(previewMode: true);

        $rows = collect([
            ['code' => 'INFO-L', 'libelle' => 'New Programme', 'type' => 'Licence', 'duree_annees' => 3, 'responsable_email' => '', 'description' => ''],
        ]);

        $this->simulateImport($import, $rows);
        $preview = $import->getPreviewData();

        $this->assertFalse($preview[0]['valid']);
        $this->assertTrue(
            collect($preview[0]['errors'])->contains(fn ($e) => str_contains($e, 'existe déjà'))
        );
    }

    #[Test]
    public function it_validates_type_values(): void
    {
        $import = new ProgrammesImport(previewMode: true);

        $rows = collect([
            ['code' => 'TEST-1', 'libelle' => 'Test', 'type' => 'InvalidType', 'duree_annees' => 3, 'responsable_email' => '', 'description' => ''],
        ]);

        $this->simulateImport($import, $rows);
        $preview = $import->getPreviewData();

        $this->assertFalse($preview[0]['valid']);
        $this->assertTrue(
            collect($preview[0]['errors'])->contains(fn ($e) => str_contains($e, 'Type invalide'))
        );
    }

    #[Test]
    public function it_validates_duree_range(): void
    {
        $import = new ProgrammesImport(previewMode: true);

        $rows = collect([
            ['code' => 'TEST-1', 'libelle' => 'Test', 'type' => 'Licence', 'duree_annees' => 10, 'responsable_email' => '', 'description' => ''],
        ]);

        $this->simulateImport($import, $rows);
        $preview = $import->getPreviewData();

        $this->assertFalse($preview[0]['valid']);
        $this->assertTrue(
            collect($preview[0]['errors'])->contains(fn ($e) => str_contains($e, 'entre 1 et 8'))
        );
    }

    #[Test]
    public function it_validates_responsable_email(): void
    {
        $import = new ProgrammesImport(previewMode: true);

        $rows = collect([
            ['code' => 'TEST-1', 'libelle' => 'Test', 'type' => 'Licence', 'duree_annees' => 3, 'responsable_email' => 'nonexistent@example.com', 'description' => ''],
        ]);

        $this->simulateImport($import, $rows);
        $preview = $import->getPreviewData();

        $this->assertFalse($preview[0]['valid']);
        $this->assertTrue(
            collect($preview[0]['errors'])->contains(fn ($e) => str_contains($e, 'Responsable non trouvé'))
        );
    }

    #[Test]
    public function it_creates_programmes_with_valid_data(): void
    {
        $user = User::factory()->create(['email' => 'responsable@test.com']);

        $import = new ProgrammesImport(previewMode: false);

        $rows = collect([
            ['code' => 'INFO-L', 'libelle' => 'Licence Informatique', 'type' => 'Licence', 'duree_annees' => 3, 'responsable_email' => 'responsable@test.com', 'description' => 'Description test'],
            ['code' => 'MATH-L', 'libelle' => 'Licence Mathématiques', 'type' => 'Licence', 'duree_annees' => 3, 'responsable_email' => '', 'description' => ''],
        ]);

        $this->simulateImport($import, $rows);
        $results = $import->getResults();

        $this->assertEquals(2, $results['created']);
        $this->assertEmpty($results['errors']);

        $infoL = Programme::where('code', 'INFO-L')->first();
        $this->assertNotNull($infoL);
        $this->assertEquals('Licence Informatique', $infoL->libelle);
        $this->assertEquals($user->id, $infoL->responsable_id);
        $this->assertEquals('Brouillon', $infoL->statut);

        $mathL = Programme::where('code', 'MATH-L')->first();
        $this->assertNotNull($mathL);
        $this->assertNull($mathL->responsable_id);
    }

    #[Test]
    public function it_skips_empty_rows(): void
    {
        $import = new ProgrammesImport(previewMode: true);

        $rows = collect([
            ['code' => '', 'libelle' => '', 'type' => '', 'duree_annees' => '', 'responsable_email' => '', 'description' => ''],
            ['code' => 'INFO-L', 'libelle' => 'Test', 'type' => 'Licence', 'duree_annees' => 3, 'responsable_email' => '', 'description' => ''],
        ]);

        $this->simulateImport($import, $rows);
        $preview = $import->getPreviewData();

        // Only 1 row should be in preview (empty row skipped)
        $this->assertCount(1, $preview);
    }

    /**
     * Helper to simulate import without actual file
     */
    protected function simulateImport(ProgrammesImport $import, $rows): void
    {
        $import->collection($rows);
    }
}
