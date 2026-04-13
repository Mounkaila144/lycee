<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\PaymentReminder;
use Modules\Finance\Entities\ServiceBlock;

/**
 * Epic 3: Recouvrement (Stories 13-16)
 *
 * Story 13: Automatic reminders
 * Story 14: Service blocking (enrollment, exams, documents)
 * Story 15: Payment plans
 * Story 16: Bad debt write-offs
 */
class CollectionService
{
    /**
     * Story 13: Generate and schedule automatic reminders
     */
    public function generateAutomaticReminders(): array
    {
        if (! config('finance.collection.auto_reminders', true)) {
            return [
                'message' => 'Les relances automatiques sont désactivées',
                'reminders' => [],
            ];
        }

        $reminderSchedule = config('finance.collection.reminder_schedule', []);
        $reminders = [];

        foreach ($reminderSchedule as $config) {
            $daysAfterDue = $config['days_after_due'];
            $reminderType = $config['type'];

            // Find invoices that need this type of reminder
            $targetDate = now()->subDays($daysAfterDue)->startOfDay();

            $invoices = Invoice::on('tenant')
                ->with(['student', 'reminders'])
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->whereDate('due_date', $targetDate)
                ->get();

            foreach ($invoices as $invoice) {
                // Check if this reminder type was already sent
                $alreadySent = $invoice->reminders()
                    ->where('reminder_type', $reminderType)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                // Create reminder
                $reminder = PaymentReminder::on('tenant')->create([
                    'invoice_id' => $invoice->id,
                    'student_id' => $invoice->student_id,
                    'reminder_date' => now(),
                    'reminder_type' => $reminderType,
                    'status' => 'pending',
                    'send_methods' => $config['method'] === 'email_sms' ? ['email', 'sms'] : ['email'],
                ]);

                $reminders[] = $reminder;
            }
        }

        return [
            'message' => count($reminders).' relances générées',
            'reminders' => $reminders,
        ];
    }

    /**
     * Send pending reminders
     */
    public function sendPendingReminders(): array
    {
        $reminders = PaymentReminder::on('tenant')
            ->with(['invoice', 'student'])
            ->dueToday()
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($reminders as $reminder) {
            try {
                // Send reminder (email, SMS, etc.)
                // This would integrate with notification system
                // For now, just mark as sent

                $reminder->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                $sent++;
            } catch (\Exception $e) {
                $reminder->update([
                    'status' => 'failed',
                    'notes' => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'total' => $reminders->count(),
        ];
    }

    /**
     * Story 14: Block student services
     */
    public function blockStudentServices(
        int $studentId,
        string $blockType,
        string $reason,
        array $relatedInvoiceIds = []
    ): ServiceBlock {
        return DB::connection('tenant')->transaction(function () use ($studentId, $blockType, $reason, $relatedInvoiceIds) {
            // Check if already blocked
            $existingBlock = ServiceBlock::on('tenant')
                ->active()
                ->byStudent($studentId)
                ->byType($blockType)
                ->first();

            if ($existingBlock) {
                throw new \Exception("L'étudiant est déjà bloqué pour ce type de service");
            }

            return ServiceBlock::on('tenant')->create([
                'student_id' => $studentId,
                'block_type' => $blockType,
                'reason' => $reason,
                'blocked_at' => now(),
                'is_active' => true,
                'blocked_by' => Auth::id(),
                'related_invoices' => $relatedInvoiceIds,
            ]);
        });
    }

    /**
     * Unblock student services
     */
    public function unblockStudentServices(ServiceBlock $block): ServiceBlock
    {
        $block->update([
            'unblocked_at' => now(),
            'is_active' => false,
            'unblocked_by' => Auth::id(),
        ]);

        return $block->fresh();
    }

    /**
     * Automatic service blocking based on debt
     */
    public function processAutomaticBlocking(): array
    {
        $threshold = config('finance.collection.auto_block_threshold', 5000);
        $delayDays = config('finance.collection.auto_block_delay', 60);
        $blocked = [];

        // Find students with significant overdue amounts
        $overdueInvoices = Invoice::on('tenant')
            ->with(['student'])
            ->overdue()
            ->where('due_date', '<=', now()->subDays($delayDays))
            ->get()
            ->groupBy('student_id');

        foreach ($overdueInvoices as $studentId => $invoices) {
            $totalDebt = $invoices->sum('balance');

            if ($totalDebt >= $threshold) {
                // Check if not already blocked
                $hasActiveBlock = ServiceBlock::hasActiveBlock($studentId, 'all');

                if (! $hasActiveBlock) {
                    $block = $this->blockStudentServices(
                        $studentId,
                        'all',
                        "Blocage automatique: Impayés de {$totalDebt} FCFA depuis plus de {$delayDays} jours",
                        $invoices->pluck('id')->toArray()
                    );

                    $blocked[] = [
                        'student_id' => $studentId,
                        'total_debt' => $totalDebt,
                        'invoices_count' => $invoices->count(),
                        'block' => $block,
                    ];
                }
            }
        }

        return [
            'blocked_count' => count($blocked),
            'blocked' => $blocked,
        ];
    }

    /**
     * Story 15: Create payment plan
     */
    public function createPaymentPlan(
        Invoice $invoice,
        int $numberOfInstallments,
        ?Carbon $firstDueDate = null
    ): array {
        if (! config('finance.collection.allow_payment_plans', true)) {
            throw new \Exception('Les plans de paiement ne sont pas autorisés');
        }

        $billingService = new BillingService;

        return $billingService->createPaymentSchedule($invoice, $numberOfInstallments, $firstDueDate);
    }

    /**
     * Story 16: Write off bad debt
     */
    public function writeOffBadDebt(Invoice $invoice, string $reason): Invoice
    {
        return DB::connection('tenant')->transaction(function () use ($invoice, $reason) {
            if ($invoice->status === 'cancelled') {
                throw new \Exception('Cette facture est déjà annulée');
            }

            $invoice->update([
                'status' => 'cancelled',
                'notes' => ($invoice->notes ?? '')."\n\nCréance irrécouvrable: {$reason} (le ".now()->format('d/m/Y').')',
            ]);

            // Unblock related services
            $blocks = ServiceBlock::on('tenant')
                ->active()
                ->byStudent($invoice->student_id)
                ->get();

            foreach ($blocks as $block) {
                if (in_array($invoice->id, $block->related_invoices ?? [])) {
                    $this->unblockStudentServices($block);
                }
            }

            return $invoice->fresh();
        });
    }

    /**
     * Get collection statistics
     */
    public function getCollectionStatistics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        // Overdue invoices
        $overdueInvoices = Invoice::on('tenant')
            ->overdue()
            ->get();

        $totalOverdue = $overdueInvoices->sum('balance');
        $countOverdue = $overdueInvoices->count();

        // Reminders sent
        $remindersSent = PaymentReminder::on('tenant')
            ->sent()
            ->dateRange($startDate, $endDate)
            ->count();

        // Active blocks
        $activeBlocks = ServiceBlock::on('tenant')
            ->active()
            ->count();

        // Aging analysis
        $aging = $this->getAgingAnalysis();

        return [
            'total_overdue_amount' => $totalOverdue,
            'total_overdue_count' => $countOverdue,
            'reminders_sent' => $remindersSent,
            'active_blocks' => $activeBlocks,
            'aging' => $aging,
        ];
    }

    /**
     * Get aging analysis
     */
    protected function getAgingAnalysis(): array
    {
        $overdueInvoices = Invoice::on('tenant')
            ->overdue()
            ->get();

        $aging = [
            '0-30' => ['count' => 0, 'amount' => 0],
            '31-60' => ['count' => 0, 'amount' => 0],
            '61-90' => ['count' => 0, 'amount' => 0],
            '90+' => ['count' => 0, 'amount' => 0],
        ];

        foreach ($overdueInvoices as $invoice) {
            $daysOverdue = $invoice->due_date->diffInDays(now());
            $balance = $invoice->balance;

            if ($daysOverdue <= 30) {
                $aging['0-30']['count']++;
                $aging['0-30']['amount'] += $balance;
            } elseif ($daysOverdue <= 60) {
                $aging['31-60']['count']++;
                $aging['31-60']['amount'] += $balance;
            } elseif ($daysOverdue <= 90) {
                $aging['61-90']['count']++;
                $aging['61-90']['amount'] += $balance;
            } else {
                $aging['90+']['count']++;
                $aging['90+']['amount'] += $balance;
            }
        }

        return $aging;
    }

    /**
     * Check if student has active blocks
     */
    public function checkStudentBlocks(int $studentId, ?string $blockType = null): array
    {
        $query = ServiceBlock::on('tenant')
            ->with(['blockedBy'])
            ->active()
            ->byStudent($studentId);

        if ($blockType) {
            $query->where(function ($q) use ($blockType) {
                $q->where('block_type', $blockType)
                    ->orWhere('block_type', 'all');
            });
        }

        $blocks = $query->get();

        return [
            'has_blocks' => $blocks->isNotEmpty(),
            'blocks' => $blocks,
        ];
    }
}
