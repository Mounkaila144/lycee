<?php

namespace Tests\Feature\RoleCoverage\Parent;

use Modules\Enrollment\Entities\Student;
use Modules\PortailParent\Entities\ParentModel;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Story Parent 01 — Home & Mes Enfants.
 *
 * Couvre les scénarios :
 *   - GET /api/admin/parent/me                 — profil Parent
 *   - GET /api/admin/parent/me/children        — liste de SES enfants (pivot owner)
 *   - GET /api/admin/parent/children/{student} — détail enfant avec ChildPolicy
 *   - Cross-parent : un Parent ne peut PAS voir l'enfant d'un autre Parent
 */
class HomeMesEnfantsTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();
        // Le rôle Parent est créé via seedRolesAndPermissions (config/role-routes.php#hierarchy).
        // On lui (re-)attache la permission view children pour la Policy.
        $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'view children', 'guard_name' => 'tenant'],
        );
        \Spatie\Permission\Models\Role::where('name', 'Parent')->where('guard_name', 'tenant')->first()?->givePermissionTo($perm);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function parentWithChildren(int $childrenCount = 2): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');

        $parent = ParentModel::factory()->create(['user_id' => $user->id]);

        $children = Student::factory()->count($childrenCount)->create();
        foreach ($children as $child) {
            $parent->students()->attach($child->id, [
                'is_primary_contact' => true,
                'is_financial_responsible' => true,
            ]);
        }

        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $parent, $children, $token];
    }

    #[Test]
    public function parent_can_view_own_profile(): void
    {
        [$user, $parent, , $token] = $this->parentWithChildren();

        $this->withToken($token)
            ->getJson('/api/admin/parent/me')
            ->assertOk()
            ->assertJsonPath('data.id', $parent->id)
            ->assertJsonPath('data.firstname', $parent->firstname)
            ->assertJsonPath('data.children_count', 2);
    }

    #[Test]
    public function parent_without_profile_returns_404(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/parent/me')
            ->assertStatus(404)
            ->assertJsonPath('code', 'PARENT_PROFILE_MISSING');
    }

    #[Test]
    public function parent_can_list_own_children(): void
    {
        [, , $children, $token] = $this->parentWithChildren(3);

        $response = $this->withToken($token)->getJson('/api/admin/parent/me/children');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $expectedIds = $children->pluck('id')->sort()->values()->all();
        $this->assertSame($expectedIds, $returnedIds);
    }

    #[Test]
    public function parent_can_view_own_child_details(): void
    {
        [, , $children, $token] = $this->parentWithChildren(1);
        $child = $children->first();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $child->id);
    }

    #[Test]
    public function parent_cannot_view_other_parents_child(): void
    {
        [, , , $token] = $this->parentWithChildren(1);

        // Créer un enfant d'un autre Parent
        $otherParentUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherParentUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherParentUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}")
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_parent_endpoints(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Étudiant');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/parent/me')
            ->assertForbidden();
    }

    #[Test]
    public function administrator_cannot_access_parent_endpoints(): void
    {
        // Le rôle Admin n'est PAS Parent — il ne doit pas voir le portail Parent
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Administrator');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/parent/me')
            ->assertForbidden();
    }
}
