<?php

namespace Tests\Feature\Finance;

use Modules\Finance\Entities\Expense;
use Modules\Finance\Entities\ExpenseCategory;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class GeneralLedgerApiTest extends TestCase
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

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/finance/general-ledger/resultat')->assertStatus(401);
    }

    #[Test]
    public function expenses_reduce_the_net_result(): void
    {
        $cat = ExpenseCategory::on('tenant')->value('id');

        Expense::on('tenant')->create([
            'expense_category_id' => $cat, 'label' => 'Loyer mai', 'amount' => 150000,
            'expense_date' => '2026-05-05', 'paid_date' => '2026-05-05', 'status' => 'paid',
        ]);
        Expense::on('tenant')->create([
            'expense_category_id' => $cat, 'label' => 'Électricité mai', 'amount' => 50000,
            'expense_date' => '2026-05-10', 'paid_date' => '2026-05-10', 'status' => 'paid',
        ]);
        // Dépense non payée → ne compte pas (base trésorerie).
        Expense::on('tenant')->create([
            'expense_category_id' => $cat, 'label' => 'Devis', 'amount' => 999999,
            'expense_date' => '2026-05-12', 'status' => 'pending',
        ]);

        $response = $this->authGetJson('/api/admin/finance/general-ledger/resultat?date_debut=2026-05-01&date_fin=2026-05-31');

        $response->assertOk()
            ->assertJsonPath('data.sorties.depenses.total', 200000)
            ->assertJsonPath('data.sorties.total', 200000)
            ->assertJsonPath('data.resultat_net', -200000)
            ->assertJsonPath('data.est_benefice', false);
    }

    #[Test]
    public function it_returns_the_consolidated_structure(): void
    {
        $this->authGetJson('/api/admin/finance/general-ledger/resultat?date_debut=2026-01-01&date_fin=2026-12-31')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'periode' => ['debut', 'fin'],
                    'recettes' => ['total', 'par_methode'],
                    'sorties' => ['salaires_employes', 'salaires_professeurs', 'depenses' => ['total', 'par_categorie'], 'total'],
                    'resultat_net',
                    'est_benefice',
                    'tendance_mensuelle',
                ],
            ]);
    }
}
