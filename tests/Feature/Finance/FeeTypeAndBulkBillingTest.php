<?php

namespace Tests\Feature\Finance;

use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\FeeType;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\InvoiceItem;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Paramétrage des types de frais (montants par niveau, activation)
 * et facturation en masse (aperçu, génération, anti-doublon, droits).
 */
class FeeTypeAndBulkBillingTest extends TestCase
{
    use InteractsWithTenancy;

    private AcademicYear $year;

    private Level $sixieme;

    private Level $terminale;

    private Classe $classe6A;

    private Classe $classeTA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->year = AcademicYear::factory()->create(['is_active' => false]);
        $this->sixieme = Level::factory()->create(['code' => 'T6E', 'name' => 'Sixième', 'order_index' => 1]);
        $this->terminale = Level::factory()->create(['code' => 'TTL', 'name' => 'Terminale', 'order_index' => 7]);
        $this->classe6A = Classe::factory()->create(['academic_year_id' => $this->year->id, 'level_id' => $this->sixieme->id]);
        $this->classeTA = Classe::factory()->create(['academic_year_id' => $this->year->id, 'level_id' => $this->terminale->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function tokenFor(string $role): string
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user->createToken('test-token')->plainTextToken;
    }

    private function enroll(Classe $classe, string $status = 'Actif'): Student
    {
        $student = Student::factory()->create();

        ClassEnrollment::on('tenant')->create([
            'student_id' => $student->id,
            'classe_id' => $classe->id,
            'academic_year_id' => $classe->academic_year_id,
            'enrolled_at' => now()->toDateString(),
            'status' => $status,
        ]);

        return $student;
    }

    private function feeType(array $attributes = []): FeeType
    {
        return FeeType::on('tenant')->create(array_merge([
            'code' => 'F'.uniqid(),
            'name' => 'Scolarité',
            'default_amount' => 60000,
            'category' => 'tuition',
            'is_mandatory' => true,
            'academic_year_id' => null,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bulkPayload(array $feeTypeIds, array $overrides = []): array
    {
        return array_merge([
            'academic_year_id' => $this->year->id,
            'scope' => 'year',
            'fee_type_ids' => $feeTypeIds,
            'due_date' => now()->addMonth()->toDateString(),
        ], $overrides);
    }

    private function postAs(string $role, string $uri, array $data): TestResponse
    {
        return $this->withToken($this->tokenFor($role))->postJson($uri, $data);
    }

    // ───────────── Types de frais ─────────────

    #[Test]
    public function comptable_creates_fee_type_with_level_amounts(): void
    {
        $response = $this->postAs('Comptable', '/api/admin/finance/fee-types', [
            'code' => 'APE',
            'name' => 'Cotisation APE',
            'default_amount' => 5000,
            'category' => 'other',
            'is_mandatory' => false,
            'academic_year_id' => $this->year->id,
            'levels' => ['T6E', 'TTL'],
            'level_amounts' => ['TTL' => 7500, 'T6E' => null],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'APE')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.applies_to.levels', ['T6E', 'TTL'])
            ->assertJsonPath('data.applies_to.level_amounts.TTL', 7500);

        $feeType = FeeType::on('tenant')->where('code', 'APE')->firstOrFail();
        $this->assertSame(7500.0, $feeType->amountForLevel('TTL'));
        $this->assertSame(5000.0, $feeType->amountForLevel('T6E'));
    }

    #[Test]
    public function fee_type_validation_rejects_unknown_level_and_duplicate_code(): void
    {
        $this->feeType(['code' => 'DUP']);

        $this->postAs('Administrator', '/api/admin/finance/fee-types', [
            'code' => 'DUP',
            'name' => 'Doublon',
            'default_amount' => 1000,
            'category' => 'other',
            'level_amounts' => ['XXX' => 1000],
        ])->assertStatus(422)->assertJsonValidationErrors(['code', 'level_amounts']);
    }

    #[Test]
    public function update_keeps_other_applies_to_keys_and_can_change_year(): void
    {
        $feeType = $this->feeType(['applies_to' => ['levels' => ['T6E'], 'note' => 'x']]);

        $this->withToken($this->tokenFor('Comptable'))
            ->putJson("/api/admin/finance/fee-types/{$feeType->id}", [
                'name' => 'Scolarité annuelle',
                'level_amounts' => ['T6E' => 55000],
                'academic_year_id' => $this->year->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Scolarité annuelle')
            ->assertJsonPath('data.applies_to.levels', ['T6E'])
            ->assertJsonPath('data.applies_to.note', 'x')
            ->assertJsonPath('data.applies_to.level_amounts.T6E', 55000)
            ->assertJsonPath('data.academic_year_id', $this->year->id);
    }

    #[Test]
    public function fee_type_can_be_deactivated_and_reactivated(): void
    {
        $feeType = $this->feeType();
        $token = $this->tokenFor('Administrator');

        $this->withToken($token)->patchJson("/api/admin/finance/fee-types/{$feeType->id}/status", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $listed = $this->withToken($token)->getJson('/api/admin/finance/fee-types')->json('data.*.id');
        $this->assertNotContains($feeType->id, $listed);

        $all = $this->withToken($token)->getJson('/api/admin/finance/fee-types?include_inactive=1')->json('data.*.id');
        $this->assertContains($feeType->id, $all);

        // Un frais désactivé ne peut pas être facturé en masse
        $this->withToken($token)->postJson('/api/admin/finance/bulk-billing/preview', $this->bulkPayload([$feeType->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['fee_type_ids.0']);

        $this->withToken($token)->patchJson("/api/admin/finance/fee-types/{$feeType->id}/status", ['is_active' => true])
            ->assertOk()->assertJsonPath('data.is_active', true);
    }

    #[Test]
    public function caissier_and_manager_read_fee_types_but_cannot_write(): void
    {
        $feeType = $this->feeType();

        foreach (['Caissier', 'Manager'] as $role) {
            $token = $this->tokenFor($role);

            $this->withToken($token)->getJson('/api/admin/finance/fee-types')->assertOk();
            $this->withToken($token)->postJson('/api/admin/finance/fee-types', ['code' => 'X'])->assertForbidden();
            $this->withToken($token)->putJson("/api/admin/finance/fee-types/{$feeType->id}", ['name' => 'X'])->assertForbidden();
            $this->withToken($token)->patchJson("/api/admin/finance/fee-types/{$feeType->id}/status", ['is_active' => false])->assertForbidden();
        }
    }

    // ───────────── Facturation en masse ─────────────

    #[Test]
    public function targets_list_levels_and_classes_with_active_enrollment_counts(): void
    {
        $this->enroll($this->classe6A);
        $this->enroll($this->classe6A);
        $this->enroll($this->classe6A, 'Transféré');
        $this->enroll($this->classeTA);

        $response = $this->withToken($this->tokenFor('Comptable'))
            ->getJson("/api/admin/finance/bulk-billing/targets?academic_year_id={$this->year->id}");

        $response->assertOk()->assertJsonPath('data.total_students', 3);

        $classes = collect($response->json('data.classes'))->keyBy('id');
        $this->assertSame(2, $classes[$this->classe6A->id]['students_count']);
        $this->assertSame(1, $classes[$this->classeTA->id]['students_count']);
        $levels = collect($response->json('data.levels'))->keyBy('code');
        $this->assertSame(2, $levels['T6E']['students_count']);
        $this->assertSame(1, $levels['TTL']['students_count']);

        $codes = $levels->keys()->all();
        $this->assertLessThan(array_search('TTL', $codes, true), array_search('T6E', $codes, true));
    }

    #[Test]
    public function preview_computes_level_amounts_without_creating_invoices(): void
    {
        $this->enroll($this->classe6A);
        $this->enroll($this->classeTA);
        $this->enroll($this->classeTA, 'Annulé');

        $tuition = $this->feeType(['applies_to' => ['level_amounts' => ['TTL' => 80000]]]);
        $registration = $this->feeType(['name' => 'Inscription', 'default_amount' => 10000, 'category' => 'registration']);

        $response = $this->postAs('Comptable', '/api/admin/finance/bulk-billing/preview', $this->bulkPayload([$tuition->id, $registration->id]));

        $response->assertOk()
            ->assertJsonPath('data.summary.students_count', 2)
            ->assertJsonPath('data.summary.invoices_count', 2);

        $this->assertEquals(60000 + 10000 + 80000 + 10000, $response->json('data.summary.total_amount'));

        $students = collect($response->json('data.students'))->keyBy('level_code');
        $this->assertEquals(70000, $students['T6E']['total_amount']);
        $this->assertEquals(90000, $students['TTL']['total_amount']);
        $this->assertSame(0, Invoice::on('tenant')->count());
    }

    #[Test]
    public function generate_creates_one_invoice_per_student_and_skips_duplicates(): void
    {
        $alreadyBilled = $this->enroll($this->classe6A);
        $fresh = $this->enroll($this->classe6A);
        $this->enroll($this->classeTA);

        $tuition = $this->feeType();
        $uniform = $this->feeType(['name' => 'Tenue', 'default_amount' => 8000, 'category' => 'other']);

        // Scolarité déjà facturée à cet élève pour l'année
        $existing = Invoice::on('tenant')->create([
            'student_id' => $alreadyBilled->id,
            'academic_year_id' => $this->year->id,
            'invoice_number' => 'INV-EXIST-1',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'total_amount' => 60000,
            'status' => 'pending',
        ]);
        InvoiceItem::on('tenant')->create([
            'invoice_id' => $existing->id, 'fee_type_id' => $tuition->id, 'description' => 'Scolarité',
            'quantity' => 1, 'unit_price' => 60000, 'amount' => 60000,
        ]);

        $dueDate = now()->addMonths(2)->toDateString();
        $response = $this->postAs('Administrator', '/api/admin/finance/bulk-billing/generate', $this->bulkPayload(
            [$tuition->id, $uniform->id],
            ['scope' => 'classe', 'classe_id' => $this->classe6A->id, 'due_date' => $dueDate]
        ));

        $response->assertCreated()
            ->assertJsonPath('data.summary.invoices_count', 2)
            ->assertJsonPath('data.summary.already_invoiced_lines_count', 1);

        $billedInvoice = Invoice::on('tenant')->where('student_id', $alreadyBilled->id)->where('id', '!=', $existing->id)->with('items')->firstOrFail();
        $this->assertEquals(8000, (float) $billedInvoice->total_amount);
        $this->assertSame([$uniform->id], $billedInvoice->items->pluck('fee_type_id')->all());
        $this->assertSame($dueDate, $billedInvoice->due_date->toDateString());

        $freshInvoice = Invoice::on('tenant')->where('student_id', $fresh->id)->with('items')->firstOrFail();
        $this->assertEquals(68000, (float) $freshInvoice->total_amount);
        $this->assertSame('pending', $freshInvoice->status);

        // Seconde exécution : tout est déjà facturé → aucune facture
        $second = $this->postAs('Administrator', '/api/admin/finance/bulk-billing/generate', $this->bulkPayload(
            [$tuition->id, $uniform->id],
            ['scope' => 'classe', 'classe_id' => $this->classe6A->id]
        ));

        $second->assertCreated()
            ->assertJsonPath('data.summary.invoices_count', 0)
            ->assertJsonPath('data.summary.skipped_students_count', 2);
        $this->assertSame(3, Invoice::on('tenant')->count());
    }

    #[Test]
    public function cancelled_invoice_does_not_block_rebilling(): void
    {
        $student = $this->enroll($this->classe6A);
        $tuition = $this->feeType();

        $cancelled = Invoice::on('tenant')->create([
            'student_id' => $student->id, 'academic_year_id' => $this->year->id, 'invoice_number' => 'INV-CANCEL-1',
            'invoice_date' => now()->toDateString(), 'due_date' => now()->addMonth()->toDateString(),
            'total_amount' => 60000, 'status' => 'cancelled',
        ]);
        InvoiceItem::on('tenant')->create([
            'invoice_id' => $cancelled->id, 'fee_type_id' => $tuition->id, 'description' => 'Scolarité',
            'quantity' => 1, 'unit_price' => 60000, 'amount' => 60000,
        ]);

        $this->postAs('Comptable', '/api/admin/finance/bulk-billing/preview', $this->bulkPayload([$tuition->id]))
            ->assertOk()->assertJsonPath('data.summary.invoices_count', 1);
    }

    #[Test]
    public function level_scope_and_level_restricted_fee_types(): void
    {
        $this->enroll($this->classe6A);
        $terminaleStudent = $this->enroll($this->classeTA);

        $bacFee = $this->feeType(['name' => 'Frais BAC', 'category' => 'exam', 'default_amount' => 15000, 'applies_to' => ['levels' => ['TTL']]]);

        // Toute l'année : le frais BAC n'est facturé qu'en Terminale
        $preview = $this->postAs('Comptable', '/api/admin/finance/bulk-billing/preview', $this->bulkPayload([$bacFee->id]));
        $preview->assertOk()
            ->assertJsonPath('data.summary.invoices_count', 1)
            ->assertJsonPath('data.summary.not_applicable_lines_count', 1);

        // Ciblage niveau
        $this->postAs('Comptable', '/api/admin/finance/bulk-billing/generate', $this->bulkPayload(
            [$bacFee->id],
            ['scope' => 'level', 'level_id' => $this->terminale->id]
        ))->assertCreated()->assertJsonPath('data.invoices.0.student_id', $terminaleStudent->id);

        $this->assertSame(1, Invoice::on('tenant')->count());
    }

    #[Test]
    public function bulk_billing_validates_target_year_and_due_date(): void
    {
        $otherYear = AcademicYear::factory()->create();
        $otherYearFee = $this->feeType(['academic_year_id' => $otherYear->id]);
        $otherYearClasse = Classe::factory()->create(['academic_year_id' => $otherYear->id, 'level_id' => $this->sixieme->id]);
        $fee = $this->feeType();
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)->postJson('/api/admin/finance/bulk-billing/preview', $this->bulkPayload([$otherYearFee->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['fee_type_ids']);

        $this->withToken($token)->postJson('/api/admin/finance/bulk-billing/preview', $this->bulkPayload([$fee->id], ['scope' => 'classe', 'classe_id' => $otherYearClasse->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['classe_id']);

        $this->withToken($token)->postJson('/api/admin/finance/bulk-billing/preview', $this->bulkPayload([$fee->id], ['scope' => 'classe']))
            ->assertStatus(422)->assertJsonValidationErrors(['classe_id']);

        $this->withToken($token)->postJson('/api/admin/finance/bulk-billing/preview', $this->bulkPayload([$fee->id], ['due_date' => now()->subDay()->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors(['due_date']);

        $this->withToken($token)->postJson('/api/admin/finance/bulk-billing/preview', $this->bulkPayload([]))
            ->assertStatus(422)->assertJsonValidationErrors(['fee_type_ids']);
    }

    #[Test]
    public function only_administrator_and_comptable_can_bulk_bill(): void
    {
        $fee = $this->feeType();

        foreach (['Caissier', 'Manager', 'Professeur'] as $role) {
            $token = $this->tokenFor($role);

            $this->withToken($token)->getJson("/api/admin/finance/bulk-billing/targets?academic_year_id={$this->year->id}")->assertForbidden();
            $this->withToken($token)->postJson('/api/admin/finance/bulk-billing/preview', $this->bulkPayload([$fee->id]))->assertForbidden();
            $this->withToken($token)->postJson('/api/admin/finance/bulk-billing/generate', $this->bulkPayload([$fee->id]))->assertForbidden();
        }

        $this->withoutToken()->postJson('/api/admin/finance/bulk-billing/generate', $this->bulkPayload([$fee->id]))->assertUnauthorized();
        $this->assertSame(0, Invoice::on('tenant')->count());
    }

    #[Test]
    public function generate_automated_uses_level_amount_and_returns_422_without_applicable_fee(): void
    {
        $student = $this->enroll($this->classeTA);
        $tuition = $this->feeType(['applies_to' => ['level_amounts' => ['TTL' => 80000]]]);
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)->postJson('/api/admin/finance/invoices/generate-automated', [
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'fee_type_ids' => [$tuition->id],
        ])->assertCreated()->assertJsonPath('data.total_amount', '80000.00');

        $otherYear = AcademicYear::factory()->create();
        $this->withToken($token)->postJson('/api/admin/finance/invoices/generate-automated', [
            'student_id' => $student->id,
            'academic_year_id' => $otherYear->id,
            'fee_type_ids' => [$this->feeType(['academic_year_id' => $this->year->id])->id],
        ])->assertStatus(422);
    }
}
