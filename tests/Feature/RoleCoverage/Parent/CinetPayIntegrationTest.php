<?php

namespace Tests\Feature\RoleCoverage\Parent;

use Illuminate\Support\Facades\Http;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\ParentOnlinePayment;
use Modules\PortailParent\Entities\ParentModel;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Story Parent 06 — Intégration CinetPay complète.
 *
 * Couvre :
 *   - init transaction via API CinetPay (Http::fake)
 *   - persistence ParentOnlinePayment avec payment_url
 *   - webhook signature HMAC (vérification + rejet 401 si invalide)
 *   - idempotence (webhook reçu 2x → un seul changement de statut)
 *   - statut consultable côté Parent (filter owner)
 */
class CinetPayIntegrationTest extends TestCase
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

        // Config CinetPay test
        config([
            'services.cinetpay.api_key' => 'test_api_key',
            'services.cinetpay.site_id' => '999999',
            'services.cinetpay.secret' => 'test_secret_hmac',
            'services.cinetpay.base_url' => 'https://api-checkout.cinetpay.com',
            'services.cinetpay.currency' => 'XOF',
            'services.cinetpay.return_url' => 'https://app.test/return',
            'services.cinetpay.notify_url' => 'https://app.test/api/webhooks/cinetpay',
        ]);
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

    private function signPayload(array $payload, string $secret = 'test_secret_hmac'): string
    {
        ksort($payload);
        $canonical = '';
        foreach ($payload as $k => $v) {
            $canonical .= $k.'='.(is_scalar($v) ? (string) $v : json_encode($v));
        }

        return hash_hmac('sha256', $canonical, $secret);
    }

    #[Test]
    public function initiate_calls_cinetpay_and_returns_payment_url(): void
    {
        Http::fake([
            'api-checkout.cinetpay.com/v2/payment' => Http::response([
                'code' => '201',
                'message' => 'CREATED',
                'data' => [
                    'payment_token' => 'tok_test_abc',
                    'payment_url' => 'https://checkout.cinetpay.com/pay/tok_test_abc',
                ],
            ], 201),
        ]);

        [, $child, $token] = $this->financialParent();

        $response = $this->withToken($token)->postJson(
            "/api/admin/parent/children/{$child->id}/invoices/42/pay",
            ['method' => 'mobile_money', 'amount' => 50000, 'phone' => '+22790000000']
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.invoice_id', 42)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_url', 'https://checkout.cinetpay.com/pay/tok_test_abc')
            ->assertJsonStructure(['data' => ['transaction_id']]);

        $this->assertDatabaseHas('parent_online_payments', [
            'invoice_id' => 42,
            'status' => 'pending',
            'cinetpay_token' => 'tok_test_abc',
        ], 'tenant');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'cinetpay.com/v2/payment'));
    }

    #[Test]
    public function initiate_fails_if_cinetpay_returns_error(): void
    {
        Http::fake([
            'api-checkout.cinetpay.com/v2/payment' => Http::response(['error' => 'invalid_key'], 400),
        ]);

        [, $child, $token] = $this->financialParent();

        $response = $this->withToken($token)->postJson(
            "/api/admin/parent/children/{$child->id}/invoices/1/pay",
            ['method' => 'mobile_money', 'amount' => 1000]
        );

        $response->assertStatus(500);
        $this->assertSame('failed', ParentOnlinePayment::on('tenant')->latest('id')->value('status'));
    }

    #[Test]
    public function webhook_rejects_request_without_valid_signature(): void
    {
        $payload = ['transaction_id' => 'fake-uuid', 'cpm_result' => '00'];

        $this->postJson('/api/webhooks/cinetpay', $payload, ['x-token' => 'invalid_token'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'CINETPAY_INVALID_SIGNATURE');
    }

    #[Test]
    public function webhook_returns_404_when_transaction_id_unknown(): void
    {
        $payload = ['transaction_id' => 'unknown-uuid', 'cpm_result' => '00'];

        $this->postJson('/api/webhooks/cinetpay', $payload, [
            'x-token' => $this->signPayload($payload),
        ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'CINETPAY_UNKNOWN_TRANSACTION');
    }

    #[Test]
    public function webhook_transitions_pending_payment_to_success(): void
    {
        [$user, $child] = $this->financialParent();

        $payment = ParentOnlinePayment::create([
            'transaction_id' => 'uuid-success',
            'parent_user_id' => $user->id,
            'student_id' => $child->id,
            'invoice_id' => 99,
            'amount' => 25000,
            'currency' => 'XOF',
            'method' => 'mobile_money',
            'status' => 'pending',
        ]);

        $payload = [
            'transaction_id' => $payment->transaction_id,
            'cpm_trans_id' => 'CP-TX-2026-001',
            'cpm_result' => '00',
        ];

        $this->postJson('/api/webhooks/cinetpay', $payload, [
            'x-token' => $this->signPayload($payload),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'success');

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('CP-TX-2026-001', $payment->fresh()->cinetpay_transaction_id);
        $this->assertNotNull($payment->fresh()->notified_at);
    }

    #[Test]
    public function webhook_is_idempotent_on_already_finalized_payment(): void
    {
        [$user, $child] = $this->financialParent();

        $payment = ParentOnlinePayment::create([
            'transaction_id' => 'uuid-idem',
            'parent_user_id' => $user->id,
            'student_id' => $child->id,
            'invoice_id' => 50,
            'amount' => 10000,
            'currency' => 'XOF',
            'method' => 'mobile_money',
            'status' => 'success', // Déjà finalisé
        ]);

        // CinetPay rejoue le webhook avec un statut différent (ne doit RIEN changer)
        $payload = [
            'transaction_id' => $payment->transaction_id,
            'cpm_result' => 'REFUSED',
        ];

        $this->postJson('/api/webhooks/cinetpay', $payload, [
            'x-token' => $this->signPayload($payload),
        ])
            ->assertOk();

        $this->assertSame('success', $payment->fresh()->status, 'Idempotence violée : status final modifié');
    }

    #[Test]
    public function webhook_handles_refused_status(): void
    {
        [$user, $child] = $this->financialParent();

        $payment = ParentOnlinePayment::create([
            'transaction_id' => 'uuid-refused',
            'parent_user_id' => $user->id,
            'student_id' => $child->id,
            'invoice_id' => 51,
            'amount' => 5000,
            'currency' => 'XOF',
            'method' => 'mobile_money',
            'status' => 'pending',
        ]);

        $payload = [
            'transaction_id' => $payment->transaction_id,
            'cpm_result' => 'REFUSED',
        ];

        $this->postJson('/api/webhooks/cinetpay', $payload, [
            'x-token' => $this->signPayload($payload),
        ])->assertOk();

        $this->assertSame('refused', $payment->fresh()->status);
    }

    #[Test]
    public function parent_can_check_own_payment_status(): void
    {
        [$user, $child, $token] = $this->financialParent();

        $payment = ParentOnlinePayment::create([
            'transaction_id' => 'uuid-status',
            'parent_user_id' => $user->id,
            'student_id' => $child->id,
            'invoice_id' => 1,
            'amount' => 1000,
            'currency' => 'XOF',
            'method' => 'mobile_money',
            'status' => 'success',
        ]);

        $this->withToken($token)
            ->getJson("/api/admin/parent/payments/{$payment->id}/status")
            ->assertOk()
            ->assertJsonPath('data.transaction_id', 'uuid-status')
            ->assertJsonPath('data.status', 'success');
    }

    #[Test]
    public function parent_cannot_check_other_users_payment(): void
    {
        [$other, $child] = $this->financialParent();
        $payment = ParentOnlinePayment::create([
            'transaction_id' => 'uuid-cross',
            'parent_user_id' => $other->id,
            'student_id' => $child->id,
            'invoice_id' => 2,
            'amount' => 2000,
            'currency' => 'XOF',
            'method' => 'mobile_money',
            'status' => 'pending',
        ]);

        // Un autre Parent essaie de lire ce paiement
        $intruder = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($intruder, 'Parent');
        $intruderToken = $intruder->createToken('test')->plainTextToken;

        $this->withToken($intruderToken)
            ->getJson("/api/admin/parent/payments/{$payment->id}/status")
            ->assertStatus(404)
            ->assertJsonPath('code', 'PAYMENT_NOT_FOUND');
    }
}
