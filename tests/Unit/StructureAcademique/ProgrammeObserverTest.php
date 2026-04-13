<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\ProgrammeHistory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ProgrammeObserverTest extends TestCase
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
    public function it_records_history_when_programme_is_created(): void
    {
        $programme = Programme::create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Brouillon',
        ]);

        $history = ProgrammeHistory::where('programme_id', $programme->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('created', $history->action);
        $this->assertNull($history->field_changed);
        $this->assertNotNull($history->new_value);
    }

    #[Test]
    public function it_records_history_for_each_field_when_programme_is_updated(): void
    {
        $programme = Programme::create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Brouillon',
        ]);

        // Clear creation history
        ProgrammeHistory::where('programme_id', $programme->id)->delete();

        // Update multiple fields
        $programme->update([
            'libelle' => 'Licence Informatique Modifiée',
            'duree_annees' => 4,
        ]);

        $histories = ProgrammeHistory::where('programme_id', $programme->id)
            ->where('action', 'updated')
            ->get();

        $this->assertCount(2, $histories);

        $libelleHistory = $histories->firstWhere('field_changed', 'libelle');
        $this->assertNotNull($libelleHistory);
        $this->assertEquals('Licence Informatique', $libelleHistory->old_value);
        $this->assertEquals('Licence Informatique Modifiée', $libelleHistory->new_value);

        $dureeHistory = $histories->firstWhere('field_changed', 'duree_annees');
        $this->assertNotNull($dureeHistory);
        $this->assertEquals(3, $dureeHistory->old_value);
        $this->assertEquals(4, $dureeHistory->new_value);
    }

    #[Test]
    public function it_ignores_timestamp_fields_in_history(): void
    {
        $programme = Programme::create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Brouillon',
        ]);

        ProgrammeHistory::where('programme_id', $programme->id)->delete();

        // Force update timestamps
        $programme->touch();

        $histories = ProgrammeHistory::where('programme_id', $programme->id)
            ->where('action', 'updated')
            ->get();

        // Should not record updated_at changes
        $this->assertCount(0, $histories);
    }

    #[Test]
    public function it_records_history_when_programme_is_deleted(): void
    {
        $programme = Programme::create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Brouillon',
        ]);

        $programmeId = $programme->id;
        $programme->delete();

        $history = ProgrammeHistory::where('programme_id', $programmeId)
            ->where('action', 'deleted')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('deleted', $history->action);
        $this->assertNotNull($history->old_value);
    }

    #[Test]
    public function it_records_ip_address_and_user_agent(): void
    {
        $user = \Modules\UsersGuard\Entities\User::factory()->create();
        $this->actingAs($user);

        $programme = Programme::create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Brouillon',
        ]);

        $history = ProgrammeHistory::where('programme_id', $programme->id)->first();

        $this->assertEquals($user->id, $history->user_id);
        $this->assertNotNull($history->ip_address);
    }

    #[Test]
    public function it_stores_json_values_correctly(): void
    {
        $programme = Programme::create([
            'code' => 'INFO-L',
            'libelle' => 'Licence Informatique',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Brouillon',
        ]);

        $history = ProgrammeHistory::where('programme_id', $programme->id)
            ->where('action', 'created')
            ->first();

        // new_value should be properly decoded as array
        $this->assertIsArray($history->new_value);
        $this->assertEquals('INFO-L', $history->new_value['code']);
    }
}
