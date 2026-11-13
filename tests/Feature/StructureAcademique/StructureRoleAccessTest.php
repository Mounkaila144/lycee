<?php

namespace Tests\Feature\StructureAcademique;

use Modules\StructureAcademique\Database\Seeders\CycleAndLevelSeeder;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Series;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StructureRoleAccessTest extends TestCase
{
    use InteractsWithTenancy;

    private AcademicYear $activeYear;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        // Rôles créés avant toute lecture : la transaction tenant doit les voir.
        $this->seedRolesAndPermissions();

        (new CycleAndLevelSeeder)->run();
        $this->activeYear = AcademicYear::factory()->active()->create(['name' => '2025-2026']);
        $this->classe = Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => Level::on('tenant')->where('code', '6E')->value('id'),
            'section' => 'A',
            'name' => '6ème A',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function tokenFor(string $role): string
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user->createToken('test-token')->plainTextToken;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function structureReaders(): array
    {
        return [
            'Administrator' => ['Administrator'],
            'Manager' => ['Manager'],
            'Professeur' => ['Professeur'],
        ];
    }

    #[Test]
    #[DataProvider('structureReaders')]
    public function readers_can_list_structure_resources(string $role): void
    {
        $token = $this->tokenFor($role);

        foreach (['academic-years', 'semesters', 'cycles', 'levels', 'series', 'subjects', 'classes', 'classes/stats'] as $uri) {
            $this->withToken($token)->getJson("/api/admin/{$uri}")->assertOk();
        }

        $this->withToken($token)->getJson("/api/admin/classes/{$this->classe->id}/students")->assertOk();
    }

    #[Test]
    public function professeur_cannot_modify_the_academic_structure(): void
    {
        $token = $this->tokenFor('Professeur');
        $subject = Subject::factory()->create();

        $this->withToken($token)->postJson('/api/admin/academic-years', [
            'name' => '2031-2032',
            'start_date' => '2031-10-01',
            'end_date' => '2032-06-30',
        ])->assertForbidden();
        $this->withToken($token)->postJson("/api/admin/academic-years/{$this->activeYear->id}/activate")->assertForbidden();
        $this->withToken($token)->postJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $this->classe->level_id,
            'section' => 'B',
        ])->assertForbidden();
        $this->withToken($token)->putJson("/api/admin/classes/{$this->classe->id}", ['max_capacity' => 10])->assertForbidden();
        $this->withToken($token)->deleteJson("/api/admin/classes/{$this->classe->id}")->assertForbidden();
        $this->withToken($token)->deleteJson("/api/admin/subjects/{$subject->id}")->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/classes/available-head-teachers')->assertForbidden();

        $this->assertDatabaseHas('classes', ['id' => $this->classe->id, 'deleted_at' => null], 'tenant');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id], 'tenant');
    }

    #[Test]
    public function manager_can_modify_the_academic_structure(): void
    {
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->putJson("/api/admin/classes/{$this->classe->id}", ['max_capacity' => 42])
            ->assertOk()
            ->assertJsonPath('data.max_capacity', 42);
    }

    #[Test]
    public function eleve_cannot_list_students_of_a_class(): void
    {
        $token = $this->tokenFor('Élève');

        $this->withToken($token)->getJson("/api/admin/classes/{$this->classe->id}/students")->assertForbidden();
        $this->withToken($token)->postJson('/api/admin/series', ['code' => 'Z', 'name' => 'Test'])->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function portalRoles(): array
    {
        return [
            'Élève' => ['Élève'],
            'Parent' => ['Parent'],
        ];
    }

    #[Test]
    #[DataProvider('portalRoles')]
    public function portal_roles_cannot_write_or_read_the_academic_structure(string $role): void
    {
        $token = $this->tokenFor($role);
        $subject = Subject::factory()->create();
        $seriesId = Series::factory()->create()->id;
        $cycleId = Level::on('tenant')->where('code', '6E')->value('cycle_id');

        $writes = [
            ['postJson', '/api/admin/classes', ['academic_year_id' => $this->activeYear->id, 'level_id' => $this->classe->level_id, 'section' => 'Z']],
            ['putJson', "/api/admin/classes/{$this->classe->id}", ['max_capacity' => 5]],
            ['deleteJson', "/api/admin/classes/{$this->classe->id}", []],
            ['postJson', '/api/admin/subjects', ['code' => 'ZZZ', 'name' => 'Test', 'category' => 'sciences']],
            ['putJson', "/api/admin/subjects/{$subject->id}", ['name' => 'Piraté']],
            ['deleteJson', "/api/admin/subjects/{$subject->id}", []],
            ['postJson', '/api/admin/series', ['code' => 'Z', 'name' => 'Test']],
            ['putJson', "/api/admin/series/{$seriesId}", ['name' => 'Piraté']],
            ['putJson', "/api/admin/cycles/{$cycleId}", ['name' => 'Piraté']],
            ['postJson', '/api/admin/academic-years', ['name' => '2031-2032', 'start_date' => '2031-10-01', 'end_date' => '2032-06-30']],
            ['postJson', "/api/admin/academic-years/{$this->activeYear->id}/activate", []],
            ['deleteJson', "/api/admin/academic-years/{$this->activeYear->id}", []],
            ['postJson', '/api/admin/subject-class-coefficients', ['subject_id' => $subject->id, 'level_id' => $this->classe->level_id, 'coefficient' => 2]],
        ];

        foreach ($writes as [$method, $uri, $payload]) {
            $this->withToken($token)->{$method}($uri, $payload)->assertForbidden();
        }

        foreach (['academic-years', 'academic-years/active', 'classes', 'classes/stats', 'subjects', "classes/{$this->classe->id}/students"] as $uri) {
            $this->withToken($token)->getJson("/api/admin/{$uri}")->assertForbidden();
        }

        $this->assertDatabaseHas('classes', ['id' => $this->classe->id, 'max_capacity' => $this->classe->fresh()->max_capacity, 'deleted_at' => null], 'tenant');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => $subject->name], 'tenant');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function financeRoles(): array
    {
        return [
            'Comptable' => ['Comptable'],
            'Caissier' => ['Caissier'],
        ];
    }

    #[Test]
    #[DataProvider('financeRoles')]
    public function finance_roles_can_only_read_academic_years(string $role): void
    {
        $token = $this->tokenFor($role);

        $this->withToken($token)->getJson('/api/admin/academic-years')->assertOk();
        $this->withToken($token)->getJson('/api/admin/academic-years/active')->assertOk();
        $this->withToken($token)->getJson('/api/admin/classes')->assertForbidden();
        $this->withToken($token)->postJson('/api/admin/academic-years', [
            'name' => '2031-2032',
            'start_date' => '2031-10-01',
            'end_date' => '2032-06-30',
        ])->assertForbidden();
    }

    #[Test]
    public function administrator_can_create_and_delete_structure_items(): void
    {
        $token = $this->tokenFor('Administrator');

        $created = $this->withToken($token)->postJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $this->classe->level_id,
            'section' => 'B',
        ])->assertCreated();

        $this->withToken($token)->deleteJson('/api/admin/classes/'.$created->json('data.id'))->assertOk();
    }
}
