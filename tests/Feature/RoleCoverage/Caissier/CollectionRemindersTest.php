<?php

namespace Tests\Feature\RoleCoverage\Caissier;

use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\PaymentReminder;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Recouvrement & relances (écran /admin/finance/collection).
 *
 * Couvre :
 *   - génération des relances (calendrier par défaut, rattrapage des retards)
 *   - envoi des relances en attente
 *   - blocage / déblocage de services
 *   - Caissier : lecture seule (génération / blocage interdits)
 */
class CollectionRemindersTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();
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

    private function invoiceDueDaysAgo(int $days, string $status = 'pending'): Invoice
    {
        return Invoice::on('tenant')->create([
            'student_id' => Student::factory()->create()->id,
            'academic_year_id' => (AcademicYear::query()->first() ?? AcademicYear::factory()->create())->id,
            'invoice_number' => 'INV-REL-'.uniqid(),
            'invoice_date' => now()->subDays($days + 30)->toDateString(),
            'due_date' => now()->subDays($days)->toDateString(),
            'total_amount' => 50000,
            'paid_amount' => 0,
            'status' => $status,
        ]);
    }

    #[Test]
    public function generate_creates_one_reminder_per_overdue_invoice_at_the_right_level(): void
    {
        $token = $this->tokenFor('Comptable');
        $tenDays = $this->invoiceDueDaysAgo(10);
        $fortyDays = $this->invoiceDueDaysAgo(40);
        $notYet = $this->invoiceDueDaysAgo(2);
        $paid = $this->invoiceDueDaysAgo(40, 'paid');

        $this->withToken($token)
            ->postJson('/api/admin/finance/collection/reminders/generate')
            ->assertOk()
            ->assertJsonPath('data.reminders_count', 2);

        $this->assertSame('first_reminder', PaymentReminder::query()->where('invoice_id', $tenDays->id)->value('reminder_type'));
        $this->assertSame('final_notice', PaymentReminder::query()->where('invoice_id', $fortyDays->id)->value('reminder_type'));
        $this->assertSame(0, PaymentReminder::query()->whereIn('invoice_id', [$notYet->id, $paid->id])->count());

        // Ré-exécution : pas de doublon
        $this->withToken($token)
            ->postJson('/api/admin/finance/collection/reminders/generate')
            ->assertOk()
            ->assertJsonPath('data.reminders_count', 0);
    }

    #[Test]
    public function send_marks_pending_reminders_as_sent(): void
    {
        $token = $this->tokenFor('Administrator');
        $this->invoiceDueDaysAgo(20);

        $this->withToken($token)->postJson('/api/admin/finance/collection/reminders/generate')->assertOk();

        $this->withToken($token)
            ->postJson('/api/admin/finance/collection/reminders/send')
            ->assertOk()
            ->assertJsonPath('data.sent', 1);

        $this->withToken($token)
            ->getJson('/api/admin/finance/collection/reminders?status=sent')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function block_and_unblock_services_without_leaking_user_secrets(): void
    {
        $token = $this->tokenFor('Comptable');
        $student = Student::factory()->create();

        $blockId = $this->withToken($token)->postJson('/api/admin/finance/collection/blocks', [
            'student_id' => $student->id,
            'block_type' => 'documents',
            'reason' => 'Impayés',
        ])->assertStatus(201)->json('data.id');

        $this->withToken($token)
            ->getJson('/api/admin/finance/collection/blocks?active_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.blocked_by.remember_token');

        $this->withToken($token)
            ->postJson("/api/admin/finance/collection/blocks/{$blockId}/unblock")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function caissier_reads_collection_but_cannot_act(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)->getJson('/api/admin/finance/collection/statistics')->assertOk();
        $this->withToken($token)->getJson('/api/admin/finance/collection/reminders')->assertOk();
        $this->withToken($token)->getJson('/api/admin/finance/collection/blocks')->assertOk();
        $this->withToken($token)->getJson('/api/admin/finance/invoices?overdue=1')->assertOk();

        $this->withToken($token)->postJson('/api/admin/finance/collection/reminders/generate')->assertForbidden();
        $this->withToken($token)->postJson('/api/admin/finance/collection/blocks', [
            'student_id' => Student::factory()->create()->id,
            'block_type' => 'documents',
            'reason' => 'x',
        ])->assertForbidden();
    }
}
