<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ProgrammeTest extends TestCase
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
    public function it_can_create_a_programme(): void
    {
        $user = User::factory()->create();

        $programme = Programme::create([
            'code' => 'L-INFO',
            'libelle' => 'Licence en Informatique',
            'type' => 'Licence',
            'duree_annees' => 3,
            'description' => 'Programme de Licence en Informatique',
            'responsable_id' => $user->id,
            'statut' => 'Brouillon',
        ]);

        $this->assertDatabaseHas('programmes', [
            'code' => 'L-INFO',
            'libelle' => 'Licence en Informatique',
        ], 'tenant');
    }

    #[Test]
    public function it_can_check_if_programme_can_be_modified(): void
    {
        $user = User::factory()->create();

        $programmeBrouillon = Programme::factory()->create([
            'statut' => 'Brouillon',
            'responsable_id' => $user->id,
        ]);

        $programmeActif = Programme::factory()->create([
            'statut' => 'Actif',
            'responsable_id' => $user->id,
        ]);

        $this->assertTrue($programmeBrouillon->canBeModified());
        $this->assertFalse($programmeActif->canBeModified());
    }

    #[Test]
    public function it_can_check_if_programme_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $programmeBrouillon = Programme::factory()->create([
            'statut' => 'Brouillon',
            'responsable_id' => $user->id,
        ]);

        $programmeActif = Programme::factory()->create([
            'statut' => 'Actif',
            'responsable_id' => $user->id,
        ]);

        $this->assertTrue($programmeBrouillon->canBeDeleted());
        $this->assertFalse($programmeActif->canBeDeleted());
    }

    #[Test]
    public function it_can_check_if_programme_can_be_activated(): void
    {
        $user = User::factory()->create();

        $programmeComplet = Programme::factory()->create([
            'code' => 'M-IA',
            'libelle' => 'Master IA',
            'type' => 'Master',
            'duree_annees' => 2,
            'statut' => 'Brouillon',
            'responsable_id' => $user->id,
        ]);

        // Ajouter au moins 1 niveau au programme complet
        $programmeComplet->levels()->create([
            'level' => 'M1',
        ]);

        $programmeIncomplet = Programme::factory()->create([
            'code' => '',
            'libelle' => '',
            'statut' => 'Brouillon',
            'responsable_id' => $user->id,
        ]);

        $this->assertTrue($programmeComplet->canBeActivated());
        $this->assertFalse($programmeIncomplet->canBeActivated());
    }

    #[Test]
    public function it_validates_status_transitions(): void
    {
        $user = User::factory()->create();

        $programme = Programme::factory()->create([
            'statut' => 'Brouillon',
            'responsable_id' => $user->id,
        ]);

        // Ajouter un niveau pour pouvoir activer le programme
        $programme->levels()->create([
            'level' => 'L1',
        ]);

        // Transition valide : Brouillon → Actif
        $this->assertTrue($programme->transitionTo('Actif'));
        $this->assertEquals('Actif', $programme->statut);

        // Transition valide : Actif → Inactif
        $this->assertTrue($programme->transitionTo('Inactif'));
        $this->assertEquals('Inactif', $programme->statut);

        // Transition invalide : Inactif → Brouillon
        $this->assertFalse($programme->transitionTo('Brouillon'));
        $this->assertEquals('Inactif', $programme->statut);
    }

    #[Test]
    public function it_has_scope_for_actif_programmes(): void
    {
        $user = User::factory()->create();

        Programme::factory()->count(3)->create([
            'statut' => 'Actif',
            'responsable_id' => $user->id,
        ]);

        Programme::factory()->count(2)->create([
            'statut' => 'Brouillon',
            'responsable_id' => $user->id,
        ]);

        $actifProgrammes = Programme::actif()->get();

        $this->assertCount(3, $actifProgrammes);
    }

    #[Test]
    public function it_has_scope_for_filtering_by_type(): void
    {
        $user = User::factory()->create();

        Programme::factory()->count(2)->create([
            'type' => 'Licence',
            'responsable_id' => $user->id,
        ]);

        Programme::factory()->create([
            'type' => 'Master',
            'responsable_id' => $user->id,
        ]);

        $licences = Programme::byType('Licence')->get();

        $this->assertCount(2, $licences);
    }
}
