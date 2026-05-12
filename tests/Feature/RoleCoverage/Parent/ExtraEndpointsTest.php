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
 * Stories Parent 04 (EDT enfant), 08 (Annonces), 09 (Documents enfant).
 *
 * Suit le pattern de ChildDataTest : ownership via ChildPolicy + cross-parent
 * blocking + permission Spatie pour les annonces (sans ownership enfant).
 */
class ExtraEndpointsTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        foreach (['view children', 'view children timetable', 'view children documents', 'view announcements'] as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'tenant']);
        }
        \Spatie\Permission\Models\Role::where('name', 'Parent')->where('guard_name', 'tenant')->first()
            ?->givePermissionTo(['view children', 'view children timetable', 'view children documents', 'view announcements']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function parentWithChild(): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $child = Student::factory()->create();
        $parent->students()->attach($child->id);
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $child, $token];
    }

    #[Test]
    public function parent_can_view_own_child_timetable(): void
    {
        [, $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/timetable")
            ->assertOk()
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function parent_cannot_view_other_parents_child_timetable(): void
    {
        [, , $token] = $this->parentWithChild();

        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}/timetable")
            ->assertForbidden();
    }

    #[Test]
    public function parent_can_view_own_child_documents(): void
    {
        [, $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/documents")
            ->assertOk()
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function parent_cannot_view_other_parents_child_documents(): void
    {
        [, , $token] = $this->parentWithChild();

        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}/documents")
            ->assertForbidden();
    }

    #[Test]
    public function parent_can_view_announcements(): void
    {
        [, , $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson('/api/admin/parent/announcements')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    #[Test]
    public function parent_without_announcements_permission_is_blocked(): void
    {
        [, , $token] = $this->parentWithChild();

        \Spatie\Permission\Models\Role::where('name', 'Parent')->where('guard_name', 'tenant')->first()
            ?->revokePermissionTo('view announcements');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->withToken($token)
            ->getJson('/api/admin/parent/announcements')
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_parent_endpoints(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Étudiant');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/parent/announcements')
            ->assertForbidden();
    }
}
