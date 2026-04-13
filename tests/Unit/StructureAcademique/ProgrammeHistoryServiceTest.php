<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\ProgrammeHistory;
use Modules\StructureAcademique\Services\ProgrammeHistoryService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ProgrammeHistoryServiceTest extends TestCase
{
    use InteractsWithTenancy;

    protected ProgrammeHistoryService $service;

    protected Programme $programme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = new ProgrammeHistoryService;

        $this->programme = Programme::create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Brouillon',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_retrieves_history_with_pagination(): void
    {
        // Create some history entries (note: setUp already created 1 "created" entry via Observer)
        for ($i = 0; $i < 19; $i++) {
            ProgrammeHistory::record(
                $this->programme,
                'updated',
                'libelle',
                "Old Value $i",
                "New Value $i"
            );
        }

        // Total: 1 (created) + 19 (updated) = 20 entries
        $history = $this->service->getHistory($this->programme, perPage: 10);

        $this->assertCount(10, $history);
        $this->assertEquals(2, $history->lastPage());
    }

    #[Test]
    public function it_filters_history_by_action(): void
    {
        ProgrammeHistory::record($this->programme, 'updated', 'libelle', 'old', 'new');
        ProgrammeHistory::record($this->programme, 'updated', 'code', 'old', 'new');

        $history = $this->service->getHistory($this->programme, action: 'updated');

        // +1 for the created event from setUp
        $this->assertTrue($history->count() >= 2);
        $history->each(fn ($h) => $this->assertEquals('updated', $h->action));
    }

    #[Test]
    public function it_reconstructs_state_at_given_date(): void
    {
        // Initial state from creation
        $createdHistory = ProgrammeHistory::where('programme_id', $this->programme->id)
            ->where('action', 'created')
            ->first();

        // Make some changes
        sleep(1); // Ensure different timestamps
        $this->programme->update(['libelle' => 'Licence Info V2']);
        sleep(1);
        $this->programme->update(['duree_annees' => 4]);

        // Reconstruct state at creation time
        $stateAtCreation = $this->service->reconstructStateAt(
            $this->programme,
            $createdHistory->created_at
        );

        $this->assertEquals('Licence Informatique', $stateAtCreation['libelle']);
        $this->assertEquals(3, $stateAtCreation['duree_annees']);
    }

    #[Test]
    public function it_compares_two_versions(): void
    {
        $history1 = ProgrammeHistory::where('programme_id', $this->programme->id)->first();

        $this->programme->update(['libelle' => 'Licence Info Modifiée']);
        $history2 = ProgrammeHistory::where('programme_id', $this->programme->id)
            ->where('action', 'updated')
            ->first();

        $comparison = $this->service->compareVersions(
            $this->programme,
            $history1->id,
            $history2->id
        );

        $this->assertArrayHasKey('version1', $comparison);
        $this->assertArrayHasKey('version2', $comparison);
        $this->assertArrayHasKey('differences', $comparison);
    }

    #[Test]
    public function it_validates_restore_possibility(): void
    {
        $result = $this->service->canRestore($this->programme);
        $this->assertTrue($result->isValid);

        // Archived programmes cannot be restored
        $this->programme->statut = 'Archivé';
        $this->programme->saveQuietly();

        $result = $this->service->canRestore($this->programme);
        $this->assertFalse($result->isValid);
        $this->assertContains('Impossible de restaurer un programme archivé', $result->errors);
    }

    #[Test]
    public function it_restores_programme_to_previous_version(): void
    {
        $user = \Modules\UsersGuard\Entities\User::factory()->create();
        $this->actingAs($user);

        $originalLibelle = $this->programme->libelle;
        $historyBeforeChange = ProgrammeHistory::where('programme_id', $this->programme->id)->first();

        // Advance time to ensure timestamp difference
        $this->travel(1)->seconds();

        // Make changes
        $this->programme->update(['libelle' => 'Nouveau Libellé']);
        $this->programme->update(['duree_annees' => 5]);

        // Restore to original
        $result = $this->service->restoreToVersion(
            $this->programme,
            $historyBeforeChange->id,
            'Test de restauration'
        );

        $this->assertTrue($result->isValid);

        $this->programme->refresh();
        $this->assertEquals($originalLibelle, $this->programme->libelle);
        $this->assertEquals(3, $this->programme->duree_annees);

        // Check restoration history entry
        $restorationHistory = ProgrammeHistory::where('programme_id', $this->programme->id)
            ->where('action', 'restored')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($restorationHistory);
        $this->assertStringContainsString('restauration', strtolower($restorationHistory->reason));
    }
}
