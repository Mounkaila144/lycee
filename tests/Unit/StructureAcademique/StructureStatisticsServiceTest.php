<?php

namespace Tests\Unit\StructureAcademique;

use Illuminate\Support\Facades\Cache;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\TeacherModuleAssignment;
use Modules\StructureAcademique\Services\StructureStatisticsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StructureStatisticsServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private StructureStatisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = app(StructureStatisticsService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_calculates_program_stats_correctly(): void
    {
        // Arrange
        Programme::factory()->count(5)->create(['statut' => 'Actif']);
        Programme::factory()->count(2)->create(['statut' => 'Brouillon']);
        Programme::factory()->count(1)->create(['statut' => 'Inactif']);

        // Act
        $stats = $this->service->getProgramStats();

        // Assert
        $this->assertEquals(8, $stats['total']);
        $this->assertEquals(5, $stats['active']);
        $this->assertEquals(2, $stats['draft']);
        $this->assertEquals(1, $stats['inactive']);
    }

    #[Test]
    public function it_calculates_module_stats_correctly(): void
    {
        // Arrange - forcer is_eliminatory => false pour contrôler le compte
        Module::factory()->count(3)->create(['level' => 'L1', 'type' => 'Obligatoire', 'credits_ects' => 5, 'is_eliminatory' => false]);
        Module::factory()->count(2)->create(['level' => 'L2', 'type' => 'Optionnel', 'credits_ects' => 3, 'is_eliminatory' => false]);
        Module::factory()->create(['level' => 'L1', 'is_eliminatory' => true, 'credits_ects' => 6]);

        // Act
        $stats = $this->service->getModuleStats();

        // Assert
        $this->assertEquals(6, $stats['total']);
        $this->assertEquals(4, $stats['by_level']['L1']);
        $this->assertEquals(2, $stats['by_level']['L2']);
        $this->assertEquals(1, $stats['eliminatory_count']);
        $this->assertEquals(27, $stats['total_credits']); // (3*5) + (2*3) + 6
        $this->assertEquals(4.5, $stats['avg_credits']); // 27/6
    }

    #[Test]
    public function it_calculates_teacher_stats_correctly(): void
    {
        // Arrange - créer toutes les dépendances explicitement
        $teacher1 = \Modules\UsersGuard\Entities\User::factory()->create();
        $teacher2 = \Modules\UsersGuard\Entities\User::factory()->create();

        $programme = Programme::factory()->create();
        $semester = \Modules\StructureAcademique\Entities\Semester::factory()->create();

        $module1 = Module::factory()->create();
        $module2 = Module::factory()->create();
        $module3 = Module::factory()->create();

        // 2 enseignants différents - fournir toutes les clés étrangères
        TeacherModuleAssignment::factory()->create([
            'teacher_id' => $teacher1->id,
            'module_id' => $module1->id,
            'programme_id' => $programme->id,
            'semester_id' => $semester->id,
            'hours_allocated' => 40,
        ]);
        TeacherModuleAssignment::factory()->create([
            'teacher_id' => $teacher1->id,
            'module_id' => $module2->id,
            'programme_id' => $programme->id,
            'semester_id' => $semester->id,
            'hours_allocated' => 30,
        ]);
        TeacherModuleAssignment::factory()->create([
            'teacher_id' => $teacher2->id,
            'module_id' => $module3->id,
            'programme_id' => $programme->id,
            'semester_id' => $semester->id,
            'hours_allocated' => 50,
        ]);

        // Act
        $stats = $this->service->getTeacherStats();

        // Assert
        $this->assertEquals(2, $stats['total_assigned']);
        $this->assertEquals(60.0, $stats['average_workload']); // ((40+30) + 50) / 2
        $this->assertEquals(3, $stats['total_assignments']);
    }

    #[Test]
    public function it_calculates_coverage_rate_correctly(): void
    {
        // Arrange
        $modules = Module::factory()->count(10)->create();

        // Affecter des enseignants à 8 modules
        foreach ($modules->take(8) as $module) {
            TeacherModuleAssignment::factory()->create([
                'module_id' => $module->id,
            ]);
        }

        // Act
        $coverageRate = $this->service->getCoverageRate();

        // Assert
        $this->assertEquals(80.0, $coverageRate);
    }

    #[Test]
    public function it_returns_zero_coverage_rate_when_no_modules(): void
    {
        // Arrange - Aucun module

        // Act
        $coverageRate = $this->service->getCoverageRate();

        // Assert
        $this->assertEquals(0, $coverageRate);
    }

    #[Test]
    public function it_calculates_volume_distribution_correctly(): void
    {
        // Arrange
        Module::factory()->create([
            'hours_cm' => 20,
            'hours_td' => 30,
            'hours_tp' => 10,
        ]);
        Module::factory()->create([
            'hours_cm' => 10,
            'hours_td' => 20,
            'hours_tp' => 5,
        ]);

        // Act
        $distribution = $this->service->getVolumeDistribution();

        // Assert
        $this->assertEquals(30, $distribution['CM']['hours']);
        $this->assertEquals(50, $distribution['TD']['hours']);
        $this->assertEquals(15, $distribution['TP']['hours']);
        $this->assertEquals(95, $distribution['total']);

        // Vérifier les pourcentages
        $this->assertEquals(31.6, $distribution['CM']['percentage']); // 30/95 * 100
        $this->assertEquals(52.6, $distribution['TD']['percentage']); // 50/95 * 100
        $this->assertEquals(15.8, $distribution['TP']['percentage']); // 15/95 * 100
    }

    #[Test]
    public function it_handles_zero_total_hours_in_volume_distribution(): void
    {
        // Arrange - Modules sans heures
        Module::factory()->create([
            'hours_cm' => 0,
            'hours_td' => 0,
            'hours_tp' => 0,
        ]);

        // Act
        $distribution = $this->service->getVolumeDistribution();

        // Assert
        $this->assertEquals(0, $distribution['CM']['percentage']);
        $this->assertEquals(0, $distribution['TD']['percentage']);
        $this->assertEquals(0, $distribution['TP']['percentage']);
        $this->assertEquals(0, $distribution['total']);
    }

    #[Test]
    public function it_calculates_volumes_by_program_correctly(): void
    {
        // Arrange
        $programme = Programme::factory()->create();
        $module1 = Module::factory()->create([
            'hours_cm' => 20,
            'hours_td' => 30,
            'hours_tp' => 10,
            'credits_ects' => 5,
        ]);
        $module2 = Module::factory()->create([
            'hours_cm' => 15,
            'hours_td' => 25,
            'hours_tp' => 5,
            'credits_ects' => 4,
        ]);

        $programme->modules()->attach([$module1->id, $module2->id]);

        // Act
        $volumes = $this->service->getVolumesByProgram();

        // Assert
        $programData = collect($volumes)->firstWhere('id', $programme->id);
        $this->assertNotNull($programData);
        $this->assertEquals(35, $programData['hours_cm']);
        $this->assertEquals(55, $programData['hours_td']);
        $this->assertEquals(15, $programData['hours_tp']);
        $this->assertEquals(105, $programData['total_hours']);
        $this->assertEquals(9, $programData['total_credits']);
        $this->assertEquals(2, $programData['modules_count']);
    }

    #[Test]
    public function it_calculates_program_specific_stats_correctly(): void
    {
        // Arrange
        $programme = Programme::factory()->create();
        $module1 = Module::factory()->create([
            'level' => 'L1',
            'type' => 'Obligatoire',
            'credits_ects' => 5,
            'hours_cm' => 20,
            'hours_td' => 10,
            'hours_tp' => 5,
        ]);
        $module2 = Module::factory()->create([
            'level' => 'L1',
            'type' => 'Optionnel',
            'credits_ects' => 3,
            'hours_cm' => 15,
            'hours_td' => 10,
            'hours_tp' => 0,
        ]);

        $programme->modules()->attach([$module1->id, $module2->id]);

        // Affecter un enseignant à un module
        TeacherModuleAssignment::factory()->create(['module_id' => $module1->id]);

        // Act
        $stats = $this->service->getProgrammeDetails($programme);

        // Assert
        $this->assertEquals($programme->id, $stats['programme']['id']);
        $this->assertEquals(2, $stats['summary']['modules_count']);
        $this->assertEquals(8, $stats['summary']['total_credits']);
        $this->assertEquals(60, $stats['summary']['total_hours']); // 35 + 25
        $this->assertEquals(50.0, $stats['summary']['coverage_rate']); // 1/2 * 100

        $this->assertEquals(1, $stats['by_type']['obligatoire']);
        $this->assertEquals(1, $stats['by_type']['optionnel']);

        $this->assertCount(1, $stats['by_level']);
        $this->assertEquals('L1', $stats['by_level'][0]['level']);
        $this->assertEquals(2, $stats['by_level'][0]['modules_count']);
    }

    #[Test]
    public function it_calculates_credits_by_level_correctly(): void
    {
        // Arrange
        Module::factory()->create(['level' => 'L1', 'credits_ects' => 5]);
        Module::factory()->create(['level' => 'L1', 'credits_ects' => 3]);
        Module::factory()->create(['level' => 'L2', 'credits_ects' => 6]);
        Module::factory()->create(['level' => 'L3', 'credits_ects' => 4]);

        // Act
        $credits = $this->service->getCreditsByLevel();

        // Assert
        $this->assertEquals(8, $credits['L1']);
        $this->assertEquals(6, $credits['L2']);
        $this->assertEquals(4, $credits['L3']);
    }

    #[Test]
    public function it_caches_global_stats(): void
    {
        // Arrange
        Cache::flush();
        Programme::factory()->count(5)->create();

        // Act - Premier appel
        $stats1 = $this->service->getGlobalStats();

        // Créer de nouveaux programmes
        Programme::factory()->count(3)->create();

        // Act - Deuxième appel (devrait retourner les données en cache)
        $stats2 = $this->service->getGlobalStats();

        // Assert
        $this->assertEquals($stats1['programs']['total'], $stats2['programs']['total']);
        $this->assertEquals(5, $stats2['programs']['total']); // Pas 8, car en cache
    }

    #[Test]
    public function it_invalidates_cache(): void
    {
        // Arrange
        Cache::flush();
        Programme::factory()->count(5)->create();
        $this->service->getGlobalStats(); // Mettre en cache

        // Act
        $this->service->invalidateCache();
        Programme::factory()->count(3)->create();
        $stats = $this->service->getGlobalStats();

        // Assert
        $this->assertEquals(8, $stats['programs']['total']); // Cache invalidé
    }
}
