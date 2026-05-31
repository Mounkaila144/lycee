<?php

namespace Tests\Feature\Finance;

use Modules\Finance\Entities\ExpenseCategory;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
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

    private function firstCategoryId(): int
    {
        return ExpenseCategory::on('tenant')->value('id');
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/finance/expenses')->assertStatus(401);
    }

    #[Test]
    public function default_expense_categories_are_seeded(): void
    {
        $this->authGetJson('/api/admin/finance/expense-categories')
            ->assertOk()
            ->assertJsonFragment(['code' => 'ELECTRICITY'])
            ->assertJsonFragment(['code' => 'RENT']);
    }

    #[Test]
    public function it_records_an_expense(): void
    {
        $response = $this->authPostJson('/api/admin/finance/expenses', [
            'expense_category_id' => $this->firstCategoryId(),
            'label' => 'Facture SEEN mai',
            'amount' => 85000,
            'expense_date' => '2026-05-10',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amount', '85000.00');
    }

    #[Test]
    public function it_detects_a_potential_duplicate_in_the_same_month(): void
    {
        $cat = $this->firstCategoryId();
        $this->authPostJson('/api/admin/finance/expenses', [
            'expense_category_id' => $cat, 'label' => 'Électricité mai', 'amount' => 90000, 'expense_date' => '2026-05-05',
        ])->assertStatus(201);

        // Même catégorie, même mois → doublon potentiel signalé.
        $this->authGetJson("/api/admin/finance/expenses/check-duplicate?expense_category_id={$cat}&expense_date=2026-05-20&amount=90000")
            ->assertOk()
            ->assertJsonPath('data.has_duplicates', true);

        // Mois différent → pas de doublon.
        $this->authGetJson("/api/admin/finance/expenses/check-duplicate?expense_category_id={$cat}&expense_date=2026-07-20")
            ->assertOk()
            ->assertJsonPath('data.has_duplicates', false);
    }

    #[Test]
    public function it_filters_expenses_by_category(): void
    {
        $catA = $this->firstCategoryId();
        $catB = ExpenseCategory::on('tenant')->where('id', '!=', $catA)->value('id');

        $this->authPostJson('/api/admin/finance/expenses', ['expense_category_id' => $catA, 'label' => 'A', 'amount' => 1000, 'expense_date' => '2026-05-01'])->assertStatus(201);
        $this->authPostJson('/api/admin/finance/expenses', ['expense_category_id' => $catB, 'label' => 'B', 'amount' => 2000, 'expense_date' => '2026-05-01'])->assertStatus(201);

        $this->authGetJson("/api/admin/finance/expenses?expense_category_id={$catA}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }
}
