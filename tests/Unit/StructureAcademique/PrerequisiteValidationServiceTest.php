<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Services\PrerequisiteValidationService;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class PrerequisiteValidationServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private PrerequisiteValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        $this->service = new PrerequisiteValidationService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_detects_simple_cycle_a_to_b_to_a(): void
    {
        // Créer deux modules
        $moduleA = Module::factory()->create(['code' => 'A', 'level' => 'L1', 'semester' => 'S1']);
        $moduleB = Module::factory()->create(['code' => 'B', 'level' => 'L1', 'semester' => 'S2']);

        // A → B
        $moduleA->prerequisites()->attach($moduleB->id, ['type' => 'Strict']);

        // B → A (crée un cycle)
        $moduleB->prerequisites()->attach($moduleA->id, ['type' => 'Strict']);

        // Rafraîchir les relations
        $moduleA->load('prerequisites');
        $moduleB->load('prerequisites');

        // Détecter le cycle
        $hasCycle = $this->service->detectCycles($moduleA);

        $this->assertTrue($hasCycle, 'Un cycle A → B → A devrait être détecté');
    }

    public function test_detects_complex_cycle_a_to_b_to_c_to_a(): void
    {
        // Créer trois modules
        $moduleA = Module::factory()->create(['code' => 'A', 'level' => 'L1', 'semester' => 'S1']);
        $moduleB = Module::factory()->create(['code' => 'B', 'level' => 'L1', 'semester' => 'S2']);
        $moduleC = Module::factory()->create(['code' => 'C', 'level' => 'L2', 'semester' => 'S3']);

        // A → B → C → A
        $moduleA->prerequisites()->attach($moduleB->id, ['type' => 'Strict']);
        $moduleB->prerequisites()->attach($moduleC->id, ['type' => 'Strict']);
        $moduleC->prerequisites()->attach($moduleA->id, ['type' => 'Strict']);

        // Rafraîchir les relations
        $moduleA->load('prerequisites');

        // Détecter le cycle
        $hasCycle = $this->service->detectCycles($moduleA);

        $this->assertTrue($hasCycle, 'Un cycle A → B → C → A devrait être détecté');
    }

    public function test_no_cycle_in_linear_chain(): void
    {
        // Créer trois modules en chaîne linéaire
        $moduleA = Module::factory()->create(['code' => 'A', 'level' => 'L1', 'semester' => 'S1']);
        $moduleB = Module::factory()->create(['code' => 'B', 'level' => 'L1', 'semester' => 'S2']);
        $moduleC = Module::factory()->create(['code' => 'C', 'level' => 'L2', 'semester' => 'S3']);

        // C → B → A (pas de cycle)
        $moduleC->prerequisites()->attach($moduleB->id, ['type' => 'Strict']);
        $moduleB->prerequisites()->attach($moduleA->id, ['type' => 'Strict']);

        // Rafraîchir les relations
        $moduleC->load('prerequisites');

        // Détecter le cycle
        $hasCycle = $this->service->detectCycles($moduleC);

        $this->assertFalse($hasCycle, 'Aucun cycle ne devrait être détecté dans une chaîne linéaire');
    }

    public function test_validate_new_prerequisite_rejects_self_reference(): void
    {
        $module = Module::factory()->create(['code' => 'A']);

        $result = $this->service->validateNewPrerequisite($module, $module);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('propre prérequis', $result['message']);
    }

    public function test_validate_new_prerequisite_rejects_cycle_creation(): void
    {
        // Créer A → B
        $moduleA = Module::factory()->create(['code' => 'A', 'level' => 'L1', 'semester' => 'S1']);
        $moduleB = Module::factory()->create(['code' => 'B', 'level' => 'L1', 'semester' => 'S2']);

        $moduleA->prerequisites()->attach($moduleB->id, ['type' => 'Strict']);
        $moduleA->load('prerequisites');

        // Tenter d'ajouter B → A (créerait un cycle)
        $result = $this->service->validateNewPrerequisite($moduleB, $moduleA);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('cyclique', $result['message']);
    }

    public function test_validate_new_prerequisite_accepts_valid_addition(): void
    {
        $moduleA = Module::factory()->create(['code' => 'A', 'level' => 'L1', 'semester' => 'S1']);
        $moduleB = Module::factory()->create(['code' => 'B', 'level' => 'L1', 'semester' => 'S2']);

        // B peut avoir A comme prérequis (pas de cycle)
        $result = $this->service->validateNewPrerequisite($moduleB, $moduleA);

        $this->assertTrue($result['valid']);
        $this->assertStringContainsString('valide', $result['message']);
    }

    public function test_get_critical_path_returns_ordered_prerequisites(): void
    {
        // Créer une chaîne: D → C → B → A
        $moduleA = Module::factory()->create(['code' => 'A', 'level' => 'L1', 'semester' => 'S1']);
        $moduleB = Module::factory()->create(['code' => 'B', 'level' => 'L1', 'semester' => 'S2']);
        $moduleC = Module::factory()->create(['code' => 'C', 'level' => 'L2', 'semester' => 'S3']);
        $moduleD = Module::factory()->create(['code' => 'D', 'level' => 'L2', 'semester' => 'S4']);

        $moduleD->prerequisites()->attach($moduleC->id, ['type' => 'Strict']);
        $moduleC->prerequisites()->attach($moduleB->id, ['type' => 'Strict']);
        $moduleB->prerequisites()->attach($moduleA->id, ['type' => 'Strict']);

        // Rafraîchir les relations
        $moduleD->load('prerequisites');

        // Obtenir le chemin critique
        $path = $this->service->getCriticalPath($moduleD);

        // Le chemin devrait être: A, B, C, D
        $this->assertCount(4, $path);
        $this->assertEquals('A', $path[0]['code']);
        $this->assertEquals('B', $path[1]['code']);
        $this->assertEquals('C', $path[2]['code']);
        $this->assertEquals('D', $path[3]['code']);
    }

    public function test_get_critical_path_handles_multiple_prerequisites(): void
    {
        // Créer: D → (B et C), B → A, C → A
        $moduleA = Module::factory()->create(['code' => 'A', 'level' => 'L1', 'semester' => 'S1']);
        $moduleB = Module::factory()->create(['code' => 'B', 'level' => 'L1', 'semester' => 'S2']);
        $moduleC = Module::factory()->create(['code' => 'C', 'level' => 'L1', 'semester' => 'S2']);
        $moduleD = Module::factory()->create(['code' => 'D', 'level' => 'L2', 'semester' => 'S3']);

        $moduleD->prerequisites()->attach([$moduleB->id, $moduleC->id], ['type' => 'Strict']);
        $moduleB->prerequisites()->attach($moduleA->id, ['type' => 'Strict']);
        $moduleC->prerequisites()->attach($moduleA->id, ['type' => 'Strict']);

        // Rafraîchir les relations
        $moduleD->load('prerequisites');

        // Obtenir le chemin critique
        $path = $this->service->getCriticalPath($moduleD);

        // Le chemin devrait contenir A (une fois), B, C, D
        $codes = array_column($path, 'code');

        $this->assertContains('A', $codes);
        $this->assertContains('B', $codes);
        $this->assertContains('C', $codes);
        $this->assertContains('D', $codes);

        // A ne devrait apparaître qu'une fois (pas de duplication)
        $countA = count(array_filter($codes, fn ($code) => $code === 'A'));
        $this->assertEquals(1, $countA);
    }

    public function test_can_enroll_blocks_when_strict_prerequisite_missing(): void
    {
        $module = Module::factory()->create(['code' => 'B']);
        $prerequisite = Module::factory()->create(['code' => 'A']);

        // B nécessite A (strict)
        $module->prerequisites()->attach($prerequisite->id, ['type' => 'Strict']);
        $module->load('prerequisites');

        // Vérifier pour un étudiant (ID fictif)
        $eligibility = $this->service->canEnroll(1, $module);

        $this->assertFalse($eligibility->allowed);
        $this->assertNotEmpty($eligibility->missingPrerequisites);
        $this->assertStringContainsString('A', $eligibility->message);
    }

    public function test_get_recommended_warnings_returns_warnings_for_unvalidated_modules(): void
    {
        $module = Module::factory()->create(['code' => 'B']);
        $prerequisite = Module::factory()->create(['code' => 'A']);

        // B a A comme prérequis recommandé
        $module->prerequisites()->attach($prerequisite->id, ['type' => 'Recommandé']);
        $module->load('prerequisites');

        // Obtenir les avertissements
        $warnings = $this->service->getRecommendedWarnings(1, $module);

        $this->assertNotEmpty($warnings);
        $this->assertEquals('A', $warnings[0]['code']);
        $this->assertStringContainsString('recommandé', $warnings[0]['message']);
    }
}
