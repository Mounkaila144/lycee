<?php

namespace Tests\Feature\Messaging;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Messaging\Entities\Announcement;
use Modules\Messaging\Services\AnnouncementAudienceService;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\PortailParent\Entities\ParentModel;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Annonces de l'établissement : CRUD direction + lecture filtrée par audience.
 */
class AnnouncementApiTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as baseRunMigrationsOnce;
    }

    protected static bool $notesMigrationsRan = false;

    private AcademicYear $academicYear;

    private Classe $classeA;

    private Classe $classeB;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$notesMigrationsRan = false;
    }

    /**
     * Migrations partagées + teacher_assignments (NotesEvaluations).
     */
    protected function runMigrationsOnce(): void
    {
        $this->baseRunMigrationsOnce();

        if (self::$notesMigrationsRan) {
            return;
        }

        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => base_path('Modules/NotesEvaluations/Database/Migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);

        self::$notesMigrationsRan = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        Permission::firstOrCreate(['name' => 'view announcements', 'guard_name' => 'tenant']);
        Role::firstOrCreate(['name' => 'Parent', 'guard_name' => 'tenant'])->givePermissionTo('view announcements');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->academicYear = AcademicYear::factory()->create(['name' => '2091-2092', 'is_active' => true]);
        $this->classeA = Classe::factory()->create(['academic_year_id' => $this->academicYear->id, 'name' => '2nde A']);
        $this->classeB = Classe::factory()->create(['academic_year_id' => $this->academicYear->id, 'name' => '2nde B']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * @return array{0: TenantUser, 1: string}
     */
    private function userWithRole(string $role): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin', 'is_active' => true]);
        $this->assignRole($user, $role);

        return [$user, $user->createToken('test-token')->plainTextToken];
    }

    private function enrollInClasse(Student $student, Classe $classe): void
    {
        ClassEnrollment::query()->create([
            'student_id' => $student->id,
            'classe_id' => $classe->id,
            'academic_year_id' => $this->academicYear->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'Actif',
        ]);
    }

    /**
     * Crée un jeu d'annonces couvrant toutes les audiences et retourne leurs ids par clé.
     *
     * @return array<string, int>
     */
    private function seedAnnouncements(): array
    {
        $make = fn (string $audience, ?Classe $classe = null): int => Announcement::factory()
            ->forAudience($audience, $classe?->id)
            ->create(['title' => $audience.($classe ? ' '.$classe->name : '')])
            ->id;

        return [
            'all' => $make(Announcement::AUDIENCE_ALL),
            'all_classe_b' => $make(Announcement::AUDIENCE_ALL, $this->classeB),
            'parents' => $make(Announcement::AUDIENCE_PARENTS),
            'parents_classe_a' => $make(Announcement::AUDIENCE_PARENTS, $this->classeA),
            'parents_classe_b' => $make(Announcement::AUDIENCE_PARENTS, $this->classeB),
            'students' => $make(Announcement::AUDIENCE_STUDENTS),
            'students_classe_a' => $make(Announcement::AUDIENCE_STUDENTS, $this->classeA),
            'students_classe_b' => $make(Announcement::AUDIENCE_STUDENTS, $this->classeB),
            'teachers' => $make(Announcement::AUDIENCE_TEACHERS),
            'teachers_classe_a' => $make(Announcement::AUDIENCE_TEACHERS, $this->classeA),
            'teachers_classe_b' => $make(Announcement::AUDIENCE_TEACHERS, $this->classeB),
            'staff' => $make(Announcement::AUDIENCE_STAFF),
            'scheduled' => Announcement::factory()->scheduled()->create()->id,
            'expired' => Announcement::factory()->expired()->create()->id,
        ];
    }

    /**
     * @param  array<string, int>  $ids
     * @param  array<int, string>  $expectedKeys
     */
    private function assertFeedContainsExactly(TestResponse $response, array $ids, array $expectedKeys): void
    {
        $response->assertOk();

        $expected = collect($expectedKeys)->map(fn (string $key): int => $ids[$key])->sort()->values()->all();
        $actual = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function administrator_can_create_announcement_targeting_a_class(): void
    {
        [$admin, $token] = $this->userWithRole('Administrator');

        $response = $this->withToken($token)->postJson('/api/admin/announcements', [
            'title' => 'Réunion parents-professeurs',
            'body' => 'Samedi à 9h dans la salle polyvalente.',
            'audience' => 'parents',
            'classe_id' => $this->classeA->id,
            'is_pinned' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Réunion parents-professeurs')
            ->assertJsonPath('data.audience', 'parents')
            ->assertJsonPath('data.audience_label', 'Parents')
            ->assertJsonPath('data.classe.name', '2nde A')
            ->assertJsonPath('data.is_pinned', true)
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.author.id', $admin->id);

        $this->assertDatabaseHas('announcements', [
            'title' => 'Réunion parents-professeurs',
            'author_id' => $admin->id,
            'classe_id' => $this->classeA->id,
        ], 'tenant');
    }

    #[Test]
    public function manager_can_list_update_and_delete_announcements(): void
    {
        [, $token] = $this->userWithRole('Manager');
        $announcement = Announcement::factory()->create(['title' => 'Ancien titre', 'is_pinned' => true]);
        Announcement::factory()->forAudience(Announcement::AUDIENCE_STAFF)->create();

        $this->withToken($token)->getJson('/api/admin/announcements')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $announcement->id)
            ->assertJsonPath('meta.total', 2);

        $this->withToken($token)->getJson('/api/admin/announcements?audience=staff')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->putJson("/api/admin/announcements/{$announcement->id}", [
            'title' => 'Nouveau titre',
            'body' => 'Contenu mis à jour',
            'audience' => 'teachers',
            'published_at' => now()->subDay()->toDateTimeString(),
            'expires_at' => now()->addWeek()->toDateTimeString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Nouveau titre')
            ->assertJsonPath('data.audience', 'teachers')
            ->assertJsonPath('data.is_pinned', false);

        $this->withToken($token)->deleteJson("/api/admin/announcements/{$announcement->id}")->assertOk();

        $this->assertSoftDeleted('announcements', ['id' => $announcement->id], 'tenant');
        $this->withToken($token)->getJson("/api/admin/announcements/{$announcement->id}")->assertNotFound();
    }

    #[Test]
    public function store_validates_payload(): void
    {
        [, $token] = $this->userWithRole('Administrator');

        $this->withToken($token)->postJson('/api/admin/announcements', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'body', 'audience']);

        $this->withToken($token)->postJson('/api/admin/announcements', [
            'title' => 'Titre',
            'body' => 'Contenu',
            'audience' => 'everyone',
            'classe_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors(['audience', 'classe_id']);

        $this->withToken($token)->postJson('/api/admin/announcements', [
            'title' => 'Titre',
            'body' => 'Contenu',
            'audience' => 'staff',
            'classe_id' => $this->classeA->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['classe_id']);

        $this->withToken($token)->postJson('/api/admin/announcements', [
            'title' => 'Titre',
            'body' => 'Contenu',
            'audience' => 'all',
            'published_at' => '2026-12-10 08:00:00',
            'expires_at' => '2026-12-01 08:00:00',
        ])->assertStatus(422)->assertJsonValidationErrors(['expires_at']);
    }

    #[Test]
    public function update_and_delete_return_404_for_unknown_announcement(): void
    {
        [, $token] = $this->userWithRole('Administrator');

        $this->withToken($token)->putJson('/api/admin/announcements/999999', [
            'title' => 'Titre', 'body' => 'Contenu', 'audience' => 'all',
        ])->assertNotFound();
        $this->withToken($token)->deleteJson('/api/admin/announcements/999999')->assertNotFound();
    }

    #[Test]
    public function non_management_roles_cannot_manage_announcements(): void
    {
        $announcement = Announcement::factory()->create();

        foreach (['Professeur', 'Parent', 'Élève', 'Caissier', 'Comptable'] as $role) {
            [, $token] = $this->userWithRole($role);

            $this->withToken($token)->getJson('/api/admin/announcements')->assertForbidden();
            $this->withToken($token)->postJson('/api/admin/announcements', [
                'title' => 'Titre', 'body' => 'Contenu', 'audience' => 'all',
            ])->assertForbidden();
            $this->withToken($token)->deleteJson("/api/admin/announcements/{$announcement->id}")->assertForbidden();
        }

        $this->assertNotSoftDeleted('announcements', ['id' => $announcement->id], 'tenant');
    }

    #[Test]
    public function announcements_require_authentication(): void
    {
        $this->getJson('/api/admin/announcements/feed')->assertUnauthorized();
        $this->getJson('/api/admin/announcements')->assertUnauthorized();
    }

    #[Test]
    public function parent_sees_general_and_children_class_announcements_only(): void
    {
        $ids = $this->seedAnnouncements();
        [$parentUser, $token] = $this->userWithRole('Parent');
        $child = Student::factory()->create();
        ParentModel::factory()->create(['user_id' => $parentUser->id])->students()->attach($child->id);
        $this->enrollInClasse($child, $this->classeA);

        $expected = ['all', 'parents', 'parents_classe_a'];

        $this->assertFeedContainsExactly($this->withToken($token)->getJson('/api/admin/announcements/feed'), $ids, $expected);
        $this->assertFeedContainsExactly($this->withToken($token)->getJson('/api/admin/parent/announcements'), $ids, $expected);
    }

    #[Test]
    public function student_sees_general_and_own_class_announcements_only(): void
    {
        $ids = $this->seedAnnouncements();
        [$studentUser, $token] = $this->userWithRole('Élève');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $this->enrollInClasse($student, $this->classeB);

        $this->assertFeedContainsExactly(
            $this->withToken($token)->getJson('/api/admin/announcements/feed'),
            $ids,
            ['all', 'all_classe_b', 'students', 'students_classe_b']
        );
    }

    #[Test]
    public function teacher_sees_announcements_of_classes_taught_or_led(): void
    {
        $ids = $this->seedAnnouncements();
        [$teacher, $token] = $this->userWithRole('Professeur');

        $this->assertFeedContainsExactly(
            $this->withToken($token)->getJson('/api/admin/announcements/feed'),
            $ids,
            ['all', 'teachers']
        );

        $this->classeA->update(['head_teacher_id' => $teacher->id]);
        TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'classe_id' => $this->classeB->id]);

        $this->assertFeedContainsExactly(
            $this->withToken($token)->getJson('/api/admin/announcements/feed'),
            $ids,
            ['all', 'all_classe_b', 'teachers', 'teachers_classe_a', 'teachers_classe_b']
        );
    }

    #[Test]
    public function cashier_and_accountant_see_staff_and_general_announcements(): void
    {
        $ids = $this->seedAnnouncements();

        foreach (['Caissier', 'Comptable'] as $role) {
            [, $token] = $this->userWithRole($role);

            $this->assertFeedContainsExactly(
                $this->withToken($token)->getJson('/api/admin/announcements/feed'),
                $ids,
                ['all', 'staff']
            );
        }
    }

    #[Test]
    public function management_feed_contains_every_published_announcement_pinned_first(): void
    {
        $ids = $this->seedAnnouncements();
        $pinned = Announcement::factory()->forAudience(Announcement::AUDIENCE_STAFF)
            ->create(['is_pinned' => true, 'published_at' => now()->subYear()]);
        [, $token] = $this->userWithRole('Manager');

        $response = $this->withToken($token)->getJson('/api/admin/announcements/feed?limit=50')->assertOk();

        $this->assertSame($pinned->id, $response->json('data.0.id'));
        $this->assertCount(count($ids) - 2 + 1, $response->json('data'));
        $this->assertNotContains($ids['scheduled'], collect($response->json('data'))->pluck('id'));
        $this->assertNotContains($ids['expired'], collect($response->json('data'))->pluck('id'));
    }

    #[Test]
    public function audience_preview_counts_targeted_accounts(): void
    {
        [, $token] = $this->userWithRole('Administrator');
        [$parentUser] = $this->userWithRole('Parent');
        [$studentUser] = $this->userWithRole('Élève');
        [$teacher] = $this->userWithRole('Professeur');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        ParentModel::factory()->create(['user_id' => $parentUser->id])->students()->attach($student->id);
        $this->enrollInClasse($student, $this->classeA);
        $this->classeA->update(['head_teacher_id' => $teacher->id]);

        $this->withToken($token)
            ->getJson("/api/admin/announcements/audience-preview?audience=all&classe_id={$this->classeA->id}")
            ->assertOk()
            ->assertJsonPath('data.label', 'Tout le monde — classe 2nde A')
            ->assertJsonPath('data.counts.parents', 1)
            ->assertJsonPath('data.counts.students', 1)
            ->assertJsonPath('data.counts.teachers', 1)
            ->assertJsonPath('data.counts.staff', 0)
            ->assertJsonPath('data.total', 3);

        $this->withToken($token)
            ->getJson("/api/admin/announcements/audience-preview?audience=parents&classe_id={$this->classeB->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    #[Test]
    public function reads_return_empty_list_when_table_is_missing(): void
    {
        [, $adminToken] = $this->userWithRole('Administrator');
        [$parentUser, $parentToken] = $this->userWithRole('Parent');
        ParentModel::factory()->create(['user_id' => $parentUser->id]);

        $this->partialMock(AnnouncementAudienceService::class, function ($mock): void {
            $mock->shouldReceive('tableExists')->andReturn(false);
        });

        $this->withToken($adminToken)->getJson('/api/admin/announcements')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($adminToken)->getJson('/api/admin/announcements/feed')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($parentToken)->getJson('/api/admin/parent/announcements')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($adminToken)->postJson('/api/admin/announcements', [
            'title' => 'Titre', 'body' => 'Contenu', 'audience' => 'all',
        ])->assertStatus(503);
    }
}
