<?php

namespace Tests\Feature\Payroll;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Modules\Payroll\Entities\PayrollRecord;
use Modules\Payroll\Services\PayrollSettingsService;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Paramètres de paie paramétrables (versions datées), simulateur, moteur de calcul,
 * déclarations agrégées par code et droits par rôle.
 */
class PayrollSettingsApiTest extends TestCase
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

    /**
     * Paramètres simples et lisibles pour les assertions.
     *
     * @return array<string, mixed>
     */
    private function settingsPayload(float $pensionEmployeeRate = 5, float $pensionEmployerRate = 6, array $overrides = []): array
    {
        return array_replace_recursive([
            'currency' => 'XOF',
            'currency_label' => 'FCFA',
            'rounding_precision' => 0,
            'contributions' => [
                [
                    'code' => 'pension',
                    'label' => 'Retraite',
                    'employee_rate' => $pensionEmployeeRate,
                    'employer_rate' => $pensionEmployerRate,
                    'ceiling' => 500000,
                    'floor' => null,
                    'active' => true,
                    'tax_deductible' => true,
                ],
                [
                    'code' => 'family',
                    'label' => 'Prestations familiales',
                    'employee_rate' => 0,
                    'employer_rate' => 8,
                    'ceiling' => 500000,
                    'floor' => null,
                    'active' => true,
                    'tax_deductible' => false,
                ],
            ],
            'income_tax' => [
                'label' => 'IUTS',
                'brackets' => [
                    ['from' => 0, 'to' => 100000, 'rate' => 0],
                    ['from' => 100000, 'to' => null, 'rate' => 10],
                ],
                'flat_allowance_rate' => 0,
                'deduct_employee_contributions' => true,
            ],
            'employer_charges' => [
                ['code' => 'apprenticeship', 'label' => "Taxe d'apprentissage", 'rate' => 2, 'active' => true],
            ],
        ], $overrides);
    }

    private function saveVersion(string $effectiveDate, array $settings, ?string $token = null): TestResponse
    {
        return $this->authPostJson('/api/admin/payroll/settings', [
            'effective_date' => $effectiveDate,
            'settings' => $settings,
            'notes' => 'Test',
        ], $token);
    }

    private function createEmployeeWithActiveContract(float $baseSalary): int
    {
        $employeeId = $this->authPostJson('/api/admin/payroll/employees', [
            'first_name' => 'Amadou',
            'last_name' => 'Issa',
            'email' => fake()->unique()->safeEmail(),
            'hire_date' => '2024-09-01',
        ])->assertCreated()->json('data.id');

        $contractId = $this->authPostJson("/api/admin/payroll/employees/{$employeeId}/contracts", [
            'contract_type' => 'permanent',
            'start_date' => '2024-09-01',
            'base_salary' => $baseSalary,
        ])->assertCreated()->json('data.id');

        $this->authPostJson("/api/admin/payroll/employees/{$employeeId}/contracts/{$contractId}/activate")->assertOk();

        return $employeeId;
    }

    private function calculatedPeriod(int $year, int $month, bool $validate = false): int
    {
        $periodId = $this->authPostJson('/api/admin/payroll/payroll-periods', [
            'year' => $year,
            'month' => $month,
            'payment_date' => sprintf('%04d-%02d-28', $year, $month),
        ])->assertCreated()->json('data.id');

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/calculate")->assertOk();

        if ($validate) {
            $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/validate")->assertOk();
        }

        return $periodId;
    }

    #[Test]
    public function it_returns_the_niger_defaults_when_no_version_exists(): void
    {
        $response = $this->authGetJson('/api/admin/payroll/settings')->assertOk();

        $response->assertJsonPath('data.current.is_default', true)
            ->assertJsonPath('data.storage_available', true)
            ->assertJsonPath('data.current.settings.currency_label', 'FCFA')
            ->assertJsonPath('data.current.settings.rounding_precision', 0)
            ->assertJsonPath('data.current.settings.requires_review', true)
            ->assertJsonPath('data.current.settings.contributions.0.code', 'cnss_pension');

        $contributions = collect($response->json('data.current.settings.contributions'))->keyBy('code');
        $this->assertEquals(5.25, $contributions['cnss_pension']['employee_rate']);
        $this->assertEquals(6.25, $contributions['cnss_pension']['employer_rate']);
        $this->assertEquals(8.40, $contributions['cnss_family']['employer_rate']);
        $this->assertEquals(1.75, $contributions['cnss_work_injury']['employer_rate']);
        $this->assertEquals(500000, $contributions['cnss_pension']['ceiling']);
        $this->assertEquals(0, $response->json('data.current.settings.income_tax.brackets.0.rate'));
        $this->assertEquals(35, collect($response->json('data.current.settings.income_tax.brackets'))->last()['rate']);

        $encoded = strtolower(json_encode($response->json()));
        $this->assertStringNotContainsString('"amo', $encoded);
        $this->assertStringNotContainsString('cimr', $encoded);
        $this->assertStringNotContainsString('maroc', $encoded);

        $this->authGetJson('/api/admin/payroll/settings/defaults')->assertOk()->assertJsonPath('data.currency', 'XOF');
        $this->authGetJson('/api/admin/payroll/settings/versions')->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function writerRolesProvider(): array
    {
        return [
            'Administrator' => ['Administrator'],
            'Comptable' => ['Comptable'],
        ];
    }

    #[Test]
    #[DataProvider('writerRolesProvider')]
    public function writer_roles_can_save_versions_and_history_is_kept(string $role): void
    {
        $token = $this->tokenFor($role);

        $this->saveVersion('2026-01-01', $this->settingsPayload(), $token)
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.effective_date', '2026-01-01');

        $this->saveVersion('2099-01-01', $this->settingsPayload(7, 7), $token)
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->authGetJson('/api/admin/payroll/settings/versions', $token)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version', 2);

        $this->authGetJson('/api/admin/payroll/settings', $token)
            ->assertOk()
            ->assertJsonPath('data.current.version', 1)
            ->assertJsonPath('data.current.is_default', false)
            ->assertJsonPath('data.upcoming.0.version', 2);
    }

    #[Test]
    public function manager_can_read_and_simulate_but_cannot_save(): void
    {
        $token = $this->tokenFor('Manager');

        $this->authGetJson('/api/admin/payroll/settings', $token)->assertOk();
        $this->authGetJson('/api/admin/payroll/settings/versions', $token)->assertOk();
        $this->authPostJson('/api/admin/payroll/settings/simulate', ['gross_salary' => 200000], $token)->assertOk();
        $this->saveVersion('2026-01-01', $this->settingsPayload(), $token)->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenRolesProvider(): array
    {
        return [
            'Caissier' => ['Caissier'],
            'Professeur' => ['Professeur'],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenRolesProvider')]
    public function other_roles_cannot_access_payroll_settings(string $role): void
    {
        $token = $this->tokenFor($role);

        $this->authGetJson('/api/admin/payroll/settings', $token)->assertForbidden();
        $this->authPostJson('/api/admin/payroll/settings/simulate', ['gross_salary' => 200000], $token)->assertForbidden();
        $this->saveVersion('2026-01-01', $this->settingsPayload(), $token)->assertForbidden();
    }

    #[Test]
    public function it_rejects_inconsistent_settings(): void
    {
        $settings = $this->settingsPayload();
        $settings['income_tax']['brackets'] = [
            ['from' => 0, 'to' => 100000, 'rate' => 0],
            ['from' => 50000, 'to' => null, 'rate' => 10],
        ];
        $settings['contributions'][0]['floor'] = 600000;
        $settings['contributions'][1]['code'] = 'pension';

        $this->saveVersion('2026-01-01', $settings)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'settings.income_tax.brackets.1.from',
                'settings.contributions.0.floor',
                'settings.contributions.1.code',
            ]);

        $settings = $this->settingsPayload();
        $settings['income_tax']['brackets'] = [
            ['from' => 0, 'to' => null, 'rate' => 0],
            ['from' => 100000, 'to' => null, 'rate' => 10],
        ];
        $settings['contributions'][0]['employee_rate'] = 150;

        $this->saveVersion('2026-01-01', $settings)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.income_tax.brackets.0.to', 'settings.contributions.0.employee_rate']);

        $this->authPostJson('/api/admin/payroll/settings', ['settings' => $this->settingsPayload()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['effective_date']);
    }

    #[Test]
    public function the_simulator_details_each_line_with_saved_or_draft_settings(): void
    {
        $this->saveVersion('2026-01-01', $this->settingsPayload())->assertCreated();

        // Brut 600 000 : assiette plafonnée à 500 000 → retraite 25 000 (sal.) / 30 000 (pat.), familiales 40 000.
        // Imposable = 600 000 − 25 000 = 575 000 → impôt = (575 000 − 100 000) × 10 % = 47 500.
        $saved = $this->authPostJson('/api/admin/payroll/settings/simulate', [
            'gross_salary' => 600000,
            'date' => '2026-03-01',
        ])->assertOk();

        $saved->assertJsonPath('data.settings_version.version', 1)
            ->assertJsonPath('data.contributions.0.base', 500000)
            ->assertJsonPath('data.contributions.0.employee_amount', 25000)
            ->assertJsonPath('data.contributions.0.employer_amount', 30000)
            ->assertJsonPath('data.contributions.1.employer_amount', 40000)
            ->assertJsonPath('data.taxable_income', 575000)
            ->assertJsonPath('data.income_tax', 47500)
            ->assertJsonPath('data.net_salary', 527500)
            ->assertJsonPath('data.total_additional_employer_charges', 12000)
            ->assertJsonPath('data.employer_cost', 682000);

        $draft = $this->settingsPayload(10, 0);
        $draft['contributions'][0]['tax_deductible'] = false;

        $this->authPostJson('/api/admin/payroll/settings/simulate', [
            'gross_salary' => 200000,
            'settings' => $draft,
        ])->assertOk()
            ->assertJsonPath('data.settings_version.is_draft', true)
            ->assertJsonPath('data.contributions.0.employee_amount', 20000)
            ->assertJsonPath('data.taxable_income', 200000)
            ->assertJsonPath('data.income_tax', 10000)
            ->assertJsonPath('data.net_salary', 170000);

        $this->authPostJson('/api/admin/payroll/settings/simulate', ['gross_salary' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gross_salary']);
    }

    #[Test]
    public function a_period_uses_the_version_in_force_at_its_start_date(): void
    {
        $this->saveVersion('2026-01-01', $this->settingsPayload(5, 6))->assertCreated();
        $this->saveVersion('2026-06-01', $this->settingsPayload(10, 12))->assertCreated();
        $employeeId = $this->createEmployeeWithActiveContract(300000);

        $march = $this->calculatedPeriod(2026, 3);
        $july = $this->calculatedPeriod(2026, 7);

        $marchRecord = PayrollRecord::on('tenant')->where('payroll_period_id', $march)->where('employee_id', $employeeId)->firstOrFail();
        $julyRecord = PayrollRecord::on('tenant')->where('payroll_period_id', $july)->where('employee_id', $employeeId)->firstOrFail();

        $this->assertSame(1, $marchRecord->deductions_breakdown['settings_version']);
        $this->assertEquals(15000, (float) $marchRecord->cnss_employee);
        $this->assertSame(2, $julyRecord->deductions_breakdown['settings_version']);
        $this->assertEquals(30000, (float) $julyRecord->cnss_employee);

        // Juillet : retraite 30 000 + familiales 0 sal. ; imposable 270 000 → impôt 17 000.
        $this->assertEquals(17000, (float) $julyRecord->income_tax);
        $this->assertEquals(300000 - 30000 - 17000, (float) $julyRecord->net_salary);
        $this->assertEquals(36000 + 24000, (float) $julyRecord->cnss_employer);
        $this->assertEquals(6000, (float) $julyRecord->training_tax);
        $this->assertEquals(300000 + 36000 + 24000 + 6000, (float) $julyRecord->total_cost);
        $this->assertEquals(0, (float) $julyRecord->amo_employee);
        $this->assertEquals(0, (float) $julyRecord->cimr_employer);
    }

    #[Test]
    public function validated_periods_are_not_recalculated_when_settings_change(): void
    {
        $this->saveVersion('2026-01-01', $this->settingsPayload(5, 6))->assertCreated();
        $this->createEmployeeWithActiveContract(300000);
        $periodId = $this->calculatedPeriod(2026, 2, true);

        $before = PayrollRecord::on('tenant')->where('payroll_period_id', $periodId)->firstOrFail()->toArray();

        $this->saveVersion('2025-01-01', $this->settingsPayload(20, 20))->assertCreated();
        $this->saveVersion('2026-02-01', $this->settingsPayload(20, 20))->assertCreated();

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/calculate")->assertStatus(422);

        $after = PayrollRecord::on('tenant')->where('payroll_period_id', $periodId)->firstOrFail()->toArray();
        $this->assertSame($before, $after);

        $this->authGetJson("/api/admin/payroll/reports/social-charges/{$periodId}")
            ->assertOk()
            ->assertJsonPath('data.summary.total_employee_contributions', 15000)
            ->assertJsonPath('data.contributions.0.code', 'pension');
    }

    #[Test]
    public function declarations_aggregate_amounts_by_configured_code(): void
    {
        $this->saveVersion('2026-01-01', $this->settingsPayload(5, 6))->assertCreated();
        $this->createEmployeeWithActiveContract(300000);
        $this->createEmployeeWithActiveContract(600000);
        $periodId = $this->calculatedPeriod(2026, 4, true);

        $social = $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/declarations/social-contributions")
            ->assertCreated()
            ->assertJsonPath('data.declaration_type', 'cnss');

        $lines = collect($social->json('data.calculation_details.lines'))->keyBy('code');
        // Assiettes : 300 000 + 500 000 (plafond) = 800 000
        $this->assertEquals(800000, $lines['pension']['base_total']);
        $this->assertEquals(40000, $lines['pension']['employee_amount']);
        $this->assertEquals(48000, $lines['pension']['employer_amount']);
        $this->assertEquals(64000, $lines['family']['employer_amount']);
        $this->assertEquals(40000 + 48000 + 64000, (float) $social->json('data.total_amount_due'));

        $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/declarations/employer-charges")
            ->assertCreated()
            ->assertJsonPath('data.declaration_type', 'training_tax')
            ->assertJsonPath('data.calculation_details.lines.0.code', 'apprenticeship');

        $charges = $this->authGetJson("/api/admin/payroll/reports/social-charges/{$periodId}")->assertOk();
        $this->assertEquals(18000, (float) $charges->json('data.employer_charges.0.amount'));

        $tax = $this->authPostJson("/api/admin/payroll/payroll-periods/{$periodId}/declarations/income-tax")
            ->assertCreated()
            ->assertJsonPath('data.calculation_details.label', 'IUTS');
        // (285 000 − 100 000) × 10 % + (575 000 − 100 000) × 10 % = 18 500 + 47 500
        $this->assertEquals(66000, (float) $tax->json('data.total_amount_due'));
    }

    #[Test]
    public function it_falls_back_to_defaults_when_the_settings_table_is_missing(): void
    {
        $this->partialMock(PayrollSettingsService::class, function ($mock): void {
            $mock->shouldReceive('storageAvailable')->andReturn(false);
        });

        $this->authGetJson('/api/admin/payroll/settings')
            ->assertOk()
            ->assertJsonPath('data.storage_available', false)
            ->assertJsonPath('data.current.is_default', true);

        $this->saveVersion('2026-01-01', $this->settingsPayload())->assertStatus(422);

        $this->createEmployeeWithActiveContract(300000);
        $periodId = $this->calculatedPeriod(2026, 5);
        $record = PayrollRecord::on('tenant')->where('payroll_period_id', $periodId)->firstOrFail();

        $this->assertTrue($record->deductions_breakdown['settings_is_default']);
        // Défaut Niger : 5,25 % salarié sur 300 000
        $this->assertEquals(15750, (float) $record->cnss_employee);
    }
}
