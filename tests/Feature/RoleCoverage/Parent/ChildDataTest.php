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
 * Stories Parent 02 (Notes), 03 (Présences), 05 (Factures).
 *
 * Couvre :
 *   - Endpoints `/api/admin/parent/children/{student}/grades|attendance|invoices`
 *   - ChildPolicy::viewGrades / viewAttendance / viewInvoices appliquée
 *   - Cross-parent : un Parent ne peut PAS voir les notes/présences/factures
 *     de l'enfant d'un autre Parent → 403
 */
class ChildDataTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        // Permissions Parent requises par ChildPolicy (Stories 02, 03, 05)
        foreach (['view children', 'view children grades', 'view children attendance', 'view children invoices'] as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'tenant']);
        }
        $parentRole = \Spatie\Permission\Models\Role::where('name', 'Parent')->where('guard_name', 'tenant')->first();
        $parentRole?->givePermissionTo([
            'view children', 'view children grades', 'view children attendance', 'view children invoices',
        ]);
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
        $parent->students()->attach($child->id, [
            'is_primary_contact' => true,
            'is_financial_responsible' => true,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $parent, $child, $token];
    }

    #[Test]
    public function parent_can_view_own_child_grades(): void
    {
        [, , $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/grades")
            ->assertOk()
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function parent_can_view_own_child_attendance(): void
    {
        [, , $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/attendance")
            ->assertOk()
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function parent_can_view_own_child_invoices(): void
    {
        [, , $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/invoices")
            ->assertOk()
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function parent_cannot_view_other_parents_child_grades(): void
    {
        [, , , $token] = $this->parentWithChild();

        // Enfant d'un autre Parent
        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}/grades")
            ->assertForbidden();
    }

    #[Test]
    public function parent_cannot_view_other_parents_child_attendance(): void
    {
        [, , , $token] = $this->parentWithChild();

        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}/attendance")
            ->assertForbidden();
    }

    #[Test]
    public function parent_cannot_view_other_parents_child_invoices(): void
    {
        [, , , $token] = $this->parentWithChild();

        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}/invoices")
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_parent_child_data(): void
    {
        $student = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($student, 'Étudiant');
        $token = $student->createToken('test-token')->plainTextToken;

        $someChild = Student::factory()->create();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$someChild->id}/grades")
            ->assertForbidden();
    }

    #[Test]
    public function parent_without_specific_permission_is_blocked(): void
    {
        // Parent qui a `view children` mais PAS `view children grades`
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');

        // On retire la permission spécifique de ce rôle pour ce test
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $child = Student::factory()->create();
        $parent->students()->attach($child->id);

        // Retire view children grades du rôle Parent temporairement
        \Spatie\Permission\Models\Role::where('name', 'Parent')->where('guard_name', 'tenant')->first()
            ?->revokePermissionTo('view children grades');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/grades")
            ->assertForbidden();
    }
}
