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
 * Story Parent 06 — Paiement en ligne (CinetPay) — scaffold V2.
 *
 * Vérifie le contrat d'API et l'enforcement de ChildPolicy::payInvoices :
 *   - Parent financialResponsible peut initier
 *   - Parent NON financialResponsible bloqué
 *   - Parent d'un autre enfant bloqué
 */
class PaymentScaffoldTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        foreach (['view children', 'pay children invoices'] as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'tenant']);
        }
        \Spatie\Permission\Models\Role::where('name', 'Parent')->where('guard_name', 'tenant')->first()
            ?->givePermissionTo(['view children', 'pay children invoices']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function financialParent(): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $child = Student::factory()->create();
        $parent->students()->attach($child->id, [
            'is_primary_contact' => true,
            'is_financial_responsible' => true,
        ]);

        return [$user, $child, $user->createToken('test-token')->plainTextToken];
    }

    #[Test]
    public function financial_parent_can_initiate_payment(): void
    {
        [, $child, $token] = $this->financialParent();

        $this->withToken($token)
            ->postJson("/api/admin/parent/children/{$child->id}/invoices/1/pay", [
                'method' => 'mobile_money',
                'phone' => '+22790000000',
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['payment_id', 'invoice_id', 'student_id', 'status']]);
    }

    #[Test]
    public function non_financial_parent_cannot_initiate_payment(): void
    {
        // Parent attaché à l'enfant mais SANS is_financial_responsible
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $child = Student::factory()->create();
        $parent->students()->attach($child->id, [
            'is_primary_contact' => false,
            'is_financial_responsible' => false,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/admin/parent/children/{$child->id}/invoices/1/pay", [
                'method' => 'mobile_money',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function other_parent_cannot_pay_invoice(): void
    {
        [, , $token] = $this->financialParent();

        // Enfant d'un autre Parent
        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->postJson("/api/admin/parent/children/{$otherChild->id}/invoices/1/pay", [
                'method' => 'mobile_money',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function parent_can_check_payment_status(): void
    {
        [, , $token] = $this->financialParent();

        $this->withToken($token)
            ->getJson('/api/admin/parent/payments/123/status')
            ->assertOk()
            ->assertJsonPath('data.payment_id', 123);
    }

    #[Test]
    public function etudiant_cannot_initiate_payment(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Étudiant');
        $token = $user->createToken('test-token')->plainTextToken;
        $child = Student::factory()->create();

        $this->withToken($token)
            ->postJson("/api/admin/parent/children/{$child->id}/invoices/1/pay", [
                'method' => 'mobile_money',
            ])
            ->assertForbidden();
    }
}
