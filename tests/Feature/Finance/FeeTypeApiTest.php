<?php

namespace Tests\Feature\Finance;

use Modules\Finance\Entities\FeeType;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class FeeTypeApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authGetJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function authPutJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/finance/fee-types')->assertStatus(401);
    }

    #[Test]
    public function it_creates_and_lists_a_fee_type(): void
    {
        $create = $this->authPostJson('/api/admin/finance/fee-types', [
            'code' => 'INSC',
            'name' => "Frais d'inscription",
            'default_amount' => 15000,
            'category' => 'registration',
            'is_mandatory' => true,
        ]);

        $create->assertStatus(201)->assertJsonPath('data.code', 'INSC');

        $this->authGetJson('/api/admin/finance/fee-types')
            ->assertOk()
            ->assertJsonFragment(['code' => 'INSC']);

        $this->assertTrue(FeeType::on('tenant')->where('code', 'INSC')->exists());
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $this->authPostJson('/api/admin/finance/fee-types', ['name' => 'Sans code'])
            ->assertStatus(422);
    }

    #[Test]
    public function it_updates_a_fee_type(): void
    {
        $id = $this->authPostJson('/api/admin/finance/fee-types', [
            'code' => 'LAB',
            'name' => 'Laboratoire',
            'default_amount' => 5000,
            'category' => 'lab',
        ])->json('data.id');

        $this->authPutJson("/api/admin/finance/fee-types/{$id}", ['default_amount' => 7500])
            ->assertOk();

        $this->assertEqualsWithDelta(7500, (float) FeeType::on('tenant')->find($id)->default_amount, 0.01);
    }
}
