<?php

namespace Tests\Feature\Payroll;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Modules\Payroll\Entities\Employee;
use Modules\Payroll\Entities\EmploymentContract;
use Modules\Payroll\Entities\PayrollPeriod;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Paie : employés, contrats, éléments de paie, calcul, validation,
 * bulletins, paiement et accès par rôle.
 */
class PayrollApiTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as baseRunMigrationsOnce;
    }

    protected static bool $payrollMigrationsRan = false;

    private string $token;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$payrollMigrationsRan = false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->token = $this->tokenFor('Administrator');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Run the shared tenant migrations, then the Payroll module migrations.
     */
    protected function runMigrationsOnce(): void
    {
        $this->baseRunMigrationsOnce();

        if (self::$payrollMigrationsRan) {
            return;
        }

        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => base_path('Modules/Payroll/Database/Migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);

        self::$payrollMigrationsRan = true;
    }

    private function tokenFor(string $role): string
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user->createToken('test-token')->plainTextToken;
    }

    private function authGetJson(string $uri, ?string $token = null): TestResponse
    {
        return $this->withToken($token ?? $this->token)->getJson($uri);
    }

    private function authPostJson(string $uri, array $data = [], ?string $token = null): TestResponse
    {
        return $this->withToken($token ?? $this->token)->postJson($uri, $data);
    }

    private function authPutJson(string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    private function authDeleteJson(string $uri): TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri);
    }

    /**
     * Create an employee with an active contract through the API.
     */
    private function createEmployeeWithActiveContract(float $baseSalary = 300000, array $overrides = []): Employee
    {
        $employeeId = $this->authPostJson('/api/admin/payroll/employees', array_merge([
            'first_name' => 'Amadou',
            'last_name' => 'Diallo',
            'email' => fake()->unique()->safeEmail(),
            'hire_date' => '2024-09-01',
            'department' => 'Administration',
            'position' => 'Secrétaire',
            'bank_account_number' => 'NE123456789',
        ], $overrides))->assertCreated()->json('data.id');

        $contractId = $this->authPostJson("/api/admin/payroll/employees/{$employeeId}/contracts", [
            'contract_type' => 'permanent',
            'start_date' => '2024-09-01',
            'base_salary' => $baseSalary,
        ])->assertCreated()->json('data.id');

        $this->authPostJson("/api/admin/payroll/employees/{$employeeId}/contracts/{$contractId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        return Employee::on('tenant')->findOrFail($employeeId);
    }

    #[Test]
    public function it_creates_an_employee_with_generated_code(): void
    {
        $response = $this->authPostJson('/api/admin/payroll/employees', [
            'first_name' => 'Fatima',
            'last_name' => 'Moussa',
            'email' => 'fatima@example.test',
            'hire_date' => '2025-01-10',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'Fatima')
            ->assertJsonPath('data.status', 'active');

        $this->assertMatchesRegularExpression('/^EMP-\d{4}-\d{5}$/', $response->json('data.employee_code'));
        $this->assertDatabaseHas('employees', ['email' => 'fatima@example.test'], 'tenant');
    }

    #[Test]
    public function it_validates_employee_creation(): void
    {
        $this->authPostJson('/api/admin/payroll/employees', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'hire_date'], 'errors');
    }

    #[Test]
    public function it_lists_shows_updates_and_deletes_employees(): void
    {
        $employee = $this->createEmployeeWithActiveContract();

        $this->authGetJson('/api/admin/payroll/employees?search=Diallo')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $employee->id);

        $this->authGetJson("/api/admin/payroll/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.contracts.0.status', 'active');

        $this->authPutJson("/api/admin/payroll/employees/{$employee->id}", ['position' => 'Intendant'])
            ->assertOk()
            ->assertJsonPath('data.position', 'Intendant');

        $this->authDeleteJson("/api/admin/payroll/employees/{$employee->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('employees', ['id' => $employee->id], 'tenant');
    }

    #[Test]
    public function it_returns_422_when_activating_a_non_draft_contract(): void
    {
        $employee = $this->createEmployeeWithActiveContract();
        $contract = $employee->contracts()->first();

        $this->authPostJson("/api/admin/payroll/employees/{$employee->id}/contracts/{$contract->id}/activate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Seuls les contrats en brouillon peuvent être activés');
    }

    #[Test]
    public function it_terminates_a_contract(): void
    {
        $employee = $this->createEmployeeWithActiveContract();
        $contract = $employee->contracts()->first();

        $this->authPostJson("/api/admin/payroll/employees/{$employee->id}/contracts/{$contract->id}/terminate", [
            'termination_date' => '2026-06-30',
            'reason' => 'Démission',
        ])->assertOk()->assertJsonPath('data.status', 'terminated');

        $this->assertSame('terminated', $employee->fresh()->status);
    }

    #[Test]
    public function it_creates_payroll_components_and_lists_them(): void
    {
        $employee = $this->createEmployeeWithActiveContract();

        $this->authPostJson('/api/admin/payroll/components', [
            'employee_id' => $employee->id,
            'component_type' => 'allowance',
            'name' => 'Prime de logement',
            'calculation_type' => 'fixed',
            'amount' => 50000,
        ])->assertCreated()->assertJsonPath('data.component_type', 'allowance');

        $this->authGetJson("/api/admin/payroll/components?employee_id={$employee->id}")
            ->assertOk()
            ->assertJsonCount(1);
    }

    #[Test]
    public function it_runs_the_full_payroll_cycle(): void
    {
        $employee = $this->createEmployeeWithActiveContract(300000);

        $this->authPostJson('/api/admin/payroll/components', [
            'employee_id' => $employee->id,
            'component_type' => 'allowance',
            'name' => 'Prime de logement',
            'calculation_type' => 'fixed',
            'amount' => 50000,
        ])->assertCreated();

        $periodId = $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => 2026,
            'month' => 10,
            'payment_date' => '2026-10-31',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        $calculated = $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/calculate")
            ->assertOk()
            ->assertJsonPath('data.status', 'calculated')
            ->assertJsonCount(1, 'data.payroll_records');

        $record = $calculated->json('data.payroll_records.0');
        $this->assertEquals(350000, (float) $record['gross_salary']);
        $this->assertEquals(300000, (float) $record['base_salary']);
        $this->assertEqualsWithDelta(
            (float) $record['gross_salary'] - (float) $record['total_deductions'],
            (float) $record['net_salary'],
            0.01
        );

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', 'validated');

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/generate-payslips")
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.status', 'generated');

        $this->authGetJson("/api/admin/payroll/payroll-periods/{$periodId}/bank-transfers")
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.bank_account', 'NE123456789');

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/mark-as-paid", [
            'payment_date' => '2026-10-31',
        ])->assertOk()->assertJsonPath('data.status', 'paid');

        $this->authGetJson("/api/admin/payroll/payroll-periods/{$periodId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.payslips');

        $this->authGetJson("/api/admin/payroll/reports/payroll-journal/{$periodId}")->assertOk();
        $this->authGetJson("/api/admin/payroll/reports/social-charges/{$periodId}")->assertOk();
        $this->authGetJson('/api/admin/payroll/reports/salary-statistics?year=2026&month=10')->assertOk();
        $this->authGetJson('/api/admin/payroll/reports/dashboard?year=2026&month=10')
            ->assertOk()
            ->assertJsonPath('data.current_period.id', $periodId);
    }

    #[Test]
    public function it_rejects_a_duplicate_payroll_period_with_422(): void
    {
        $payload = ['year' => 2026, 'month' => 9, 'payment_date' => '2026-09-30'];

        $this->authPostJson('/api/admin/payroll/payroll-periods', $payload)->assertCreated();

        $this->authPostJson('/api/admin/payroll/payroll-periods', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La paie pour 9/2026 existe déjà');
    }

    #[Test]
    public function it_refuses_to_generate_payslips_before_validation(): void
    {
        $this->createEmployeeWithActiveContract();

        $periodId = $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => 2026, 'month' => 8, 'payment_date' => '2026-08-31',
        ])->json('data.id');

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/calculate")->assertOk();

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/generate-payslips")
            ->assertStatus(422)
            ->assertJsonPath('message', 'La paie doit être validée avant de générer les bulletins');

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/mark-as-paid", ['payment_date' => '2026-08-31'])
            ->assertStatus(422);
    }

    #[Test]
    public function it_skips_active_employees_without_active_contract_when_calculating(): void
    {
        $this->createEmployeeWithActiveContract();
        $this->authPostJson('/api/admin/payroll/employees', [
            'first_name' => 'Sans',
            'last_name' => 'Contrat',
            'email' => 'sans.contrat@example.test',
            'hire_date' => '2026-01-01',
        ])->assertCreated();

        $periodId = $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => 2026, 'month' => 7, 'payment_date' => '2026-07-31',
        ])->json('data.id');

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/calculate")
            ->assertOk()
            ->assertJsonCount(1, 'data.payroll_records');
    }

    #[Test]
    public function it_deletes_a_draft_payroll_period_only(): void
    {
        $periodId = $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => 2026, 'month' => 6, 'payment_date' => '2026-06-30',
        ])->json('data.id');

        $this->authDeleteJson("/api/admin/payroll/payroll-periods/{$periodId}")->assertOk();

        $this->assertNull(PayrollPeriod::on('tenant')->withTrashed()->find($periodId));

        $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => 2026, 'month' => 6, 'payment_date' => '2026-06-30',
        ])->assertCreated();
    }

    #[Test]
    public function it_refuses_to_delete_a_calculated_payroll_period(): void
    {
        $this->createEmployeeWithActiveContract();

        $periodId = $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => 2026, 'month' => 4, 'payment_date' => '2026-04-30',
        ])->json('data.id');

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/calculate")->assertOk();

        $this->authDeleteJson("/api/admin/payroll/payroll-periods/{$periodId}")->assertStatus(422);
    }

    #[Test]
    public function it_returns_422_when_no_employee_has_an_active_contract(): void
    {
        $periodId = $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => 2026, 'month' => 3, 'payment_date' => '2026-03-31',
        ])->json('data.id');

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/calculate")
            ->assertStatus(422);

        $this->assertSame('draft', PayrollPeriod::on('tenant')->find($periodId)->status);
    }

    #[Test]
    public function validation_records_the_authenticated_user(): void
    {
        $this->createEmployeeWithActiveContract();

        $periodId = $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => 2026, 'month' => 2, 'payment_date' => '2026-02-28',
        ])->json('data.id');

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/calculate")->assertOk();
        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/validate")->assertOk();

        $period = PayrollPeriod::on('tenant')->find($periodId);
        $this->assertNotNull($period->calculated_by);
        $this->assertNotNull($period->validated_by);
    }

    #[Test]
    public function it_returns_404_for_unknown_payroll_period(): void
    {
        $this->authGetJson('/api/admin/payroll/payroll-periods/999999')->assertNotFound();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedRolesProvider(): array
    {
        return [
            'Administrator' => ['Administrator'],
            'Manager' => ['Manager'],
            'Comptable' => ['Comptable'],
        ];
    }

    #[Test]
    #[DataProvider('allowedRolesProvider')]
    public function allowed_roles_can_read_payroll_endpoints(string $role): void
    {
        $token = $this->tokenFor($role);

        foreach (['employees', 'salary-scales', 'components', 'advances', 'payroll-periods', 'declarations', 'reports/dashboard', 'reports/salary-statistics'] as $endpoint) {
            $this->authGetJson("/api/admin/payroll/{$endpoint}", $token)->assertOk();
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenRolesProvider(): array
    {
        return [
            'Caissier' => ['Caissier'],
            'Professeur' => ['Professeur'],
            'Élève' => ['Élève'],
            'Parent' => ['Parent'],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenRolesProvider')]
    public function other_roles_cannot_access_payroll(string $role): void
    {
        $token = $this->tokenFor($role);

        $this->authGetJson('/api/admin/payroll/employees', $token)->assertForbidden();
        $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => 2026, 'month' => 5, 'payment_date' => '2026-05-31',
        ], $token)->assertForbidden();
    }

    #[Test]
    public function unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/admin/payroll/employees')->assertUnauthorized();
    }

    #[Test]
    public function an_employee_contract_cannot_be_activated_through_another_employee(): void
    {
        $employee = $this->createEmployeeWithActiveContract();
        $other = $this->createEmployeeWithActiveContract(200000, ['first_name' => 'Autre']);
        $contract = EmploymentContract::on('tenant')->where('employee_id', $other->id)->first();

        $this->authPostJson("/api/admin/payroll/employees/{$employee->id}/contracts/{$contract->id}/activate")
            ->assertNotFound();
    }

    #[Test]
    public function it_generates_social_declarations_for_a_validated_period(): void
    {
        $this->createEmployeeWithActiveContract();

        $periodId = $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => 2026, 'month' => 1, 'payment_date' => '2026-01-31',
        ])->json('data.id');

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/declarations/cnss")->assertStatus(422);

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/calculate")->assertOk();
        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/validate")->assertOk();

        $declarationId = $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/declarations/cnss")
            ->assertCreated()
            ->json('data.id');
        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/declarations/income-tax")->assertCreated();
        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/declarations/employer-charges")->assertCreated();
        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/declarations/amo")->assertNotFound();

        $this->authGetJson('/api/admin/payroll/declarations')->assertOk()->assertJsonCount(3);
        $this->authGetJson("/api/admin/payroll/declarations/{$declarationId}")->assertOk();
        $this->authPostJson("/api/admin/payroll/declarations/{$declarationId}/validate")->assertOk();
        $this->authPostJson('/api/admin/payroll/declarations/annual-summary', ['year' => 2026])->assertCreated();
    }

    #[Test]
    public function it_handles_salary_scales_and_advances(): void
    {
        $this->authPostJson('/api/admin/payroll/salary-scales', [
            'code' => 'ENS-A',
            'name' => 'Enseignants catégorie A',
            'type' => 'teaching',
            'grades' => [['grade' => 'A1', 'min_salary' => 200000, 'max_salary' => 300000]],
            'effective_from' => '2026-01-01',
        ])->assertCreated();

        $this->authGetJson('/api/admin/payroll/salary-scales')->assertOk()->assertJsonCount(1);

        $employee = $this->createEmployeeWithActiveContract(300000);

        $this->authPostJson('/api/admin/payroll/advances', [
            'employee_id' => $employee->id,
            'amount' => 5000000,
            'advance_type' => 'salary_advance',
            'reason' => 'Trop élevé',
        ])->assertStatus(422);

        $advanceId = $this->authPostJson('/api/admin/payroll/advances', [
            'employee_id' => $employee->id,
            'amount' => 100000,
            'advance_type' => 'salary_advance',
            'reason' => 'Frais médicaux',
            'number_of_installments' => 2,
        ])->assertCreated()->json('data.id');

        $this->authPostJson("/api/admin/payroll/advances/{$advanceId}/approve")->assertOk();
        $this->authPostJson("/api/admin/payroll/advances/{$advanceId}/disburse", ['method' => 'cash'])->assertOk();
        $this->authGetJson("/api/admin/payroll/advances?employee_id={$employee->id}")->assertOk()->assertJsonCount(1);
    }
}
