<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\Payment;
use Modules\Finance\Entities\PaymentReminder;
use Modules\Finance\Entities\ServiceBlock;
use Modules\StructureAcademique\Entities\AcademicYear;

/**
 * Epic 4: Rapports (Stories 17-23)
 *
 * Story 17: Treasury dashboard
 * Story 18: Payment journal
 * Story 19: Aging balance
 * Story 20: Unpaid statements
 * Story 21: Cash flow forecast
 * Story 22: Collection statistics
 * Story 23: Accounting exports
 */
class FinanceReportService
{
    protected bool $cacheEnabled;

    protected int $cacheDuration;

    public function __construct()
    {
        $this->cacheEnabled = config('finance.reports.cache_enabled', true);
        $this->cacheDuration = config('finance.reports.cache_duration', 60);
    }

    /**
     * Story 17: Get treasury dashboard data
     */
    public function getTreasuryDashboard(?AcademicYear $academicYear = null): array
    {
        $cacheKey = 'finance.dashboard.'.($academicYear?->id ?? 'all');

        return $this->cached($cacheKey, function () use ($academicYear) {
            $query = Invoice::on('tenant');

            if ($academicYear) {
                $query->byAcademicYear($academicYear->id);
            }

            // Total invoiced
            $totalInvoiced = $query->sum('total_amount');
            $totalPaid = $query->sum('paid_amount');
            $totalOutstanding = $totalInvoiced - $totalPaid;

            // Current month stats
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();

            $monthlyInvoiced = (clone $query)
                ->whereBetween('invoice_date', [$monthStart, $monthEnd])
                ->sum('total_amount');

            $monthlyCollected = Payment::on('tenant')
                ->dateRange($monthStart, $monthEnd)
                ->sum('amount');

            // Overdue
            $overdueInvoices = Invoice::on('tenant')->overdue();
            if ($academicYear) {
                $overdueInvoices->byAcademicYear($academicYear->id);
            }
            $totalOverdue = $overdueInvoices->sum(DB::raw('total_amount - paid_amount'));

            // Payment methods breakdown (current month)
            $paymentsByMethod = Payment::on('tenant')
                ->dateRange($monthStart, $monthEnd)
                ->select('payment_method', DB::raw('SUM(amount) as total'))
                ->groupBy('payment_method')
                ->get()
                ->pluck('total', 'payment_method')
                ->toArray();

            // Collection rate
            $collectionRate = $totalInvoiced > 0 ? ($totalPaid / $totalInvoiced) * 100 : 0;

            return [
                'summary' => [
                    'total_invoiced' => $totalInvoiced,
                    'total_paid' => $totalPaid,
                    'total_outstanding' => $totalOutstanding,
                    'total_overdue' => $totalOverdue,
                    'collection_rate' => round($collectionRate, 2),
                ],
                'current_month' => [
                    'invoiced' => $monthlyInvoiced,
                    'collected' => $monthlyCollected,
                    'by_method' => $paymentsByMethod,
                ],
                'trends' => $this->getCollectionTrends($academicYear),
            ];
        });
    }

    /**
     * Story 18: Get payment journal
     */
    public function getPaymentJournal(
        Carbon $startDate,
        Carbon $endDate,
        ?string $paymentMethod = null
    ): array {
        $query = Payment::on('tenant')
            ->with(['invoice.student', 'recordedBy'])
            ->dateRange($startDate, $endDate)
            ->orderBy('payment_date')
            ->orderBy('id');

        if ($paymentMethod) {
            $query->byMethod($paymentMethod);
        }

        $payments = $query->get();

        // Daily totals
        $dailyTotals = $payments->groupBy(function ($payment) {
            return $payment->payment_date->format('Y-m-d');
        })->map(function ($dayPayments) {
            return [
                'count' => $dayPayments->count(),
                'amount' => $dayPayments->sum('amount'),
            ];
        });

        return [
            'payments' => $payments,
            'summary' => [
                'total_count' => $payments->count(),
                'total_amount' => $payments->sum('amount'),
                'daily_totals' => $dailyTotals,
            ],
            'period' => [
                'start_date' => $startDate->format('d/m/Y'),
                'end_date' => $endDate->format('d/m/Y'),
            ],
        ];
    }

    /**
     * Story 19: Get aging balance report
     */
    public function getAgingBalance(): array
    {
        $overdueInvoices = Invoice::on('tenant')
            ->with(['student', 'academicYear'])
            ->overdue()
            ->get();

        $aging = [
            'current' => ['count' => 0, 'amount' => 0, 'invoices' => []],
            '0-30' => ['count' => 0, 'amount' => 0, 'invoices' => []],
            '31-60' => ['count' => 0, 'amount' => 0, 'invoices' => []],
            '61-90' => ['count' => 0, 'amount' => 0, 'invoices' => []],
            '91-120' => ['count' => 0, 'amount' => 0, 'invoices' => []],
            '120+' => ['count' => 0, 'amount' => 0, 'invoices' => []],
        ];

        foreach ($overdueInvoices as $invoice) {
            $daysOverdue = $invoice->due_date->diffInDays(now());
            $balance = $invoice->balance;

            $category = match (true) {
                $daysOverdue <= 30 => '0-30',
                $daysOverdue <= 60 => '31-60',
                $daysOverdue <= 90 => '61-90',
                $daysOverdue <= 120 => '91-120',
                default => '120+',
            };

            $aging[$category]['count']++;
            $aging[$category]['amount'] += $balance;
            $aging[$category]['invoices'][] = [
                'invoice_number' => $invoice->invoice_number,
                'student' => $invoice->student->firstname.' '.$invoice->student->lastname,
                'amount' => $balance,
                'days_overdue' => $daysOverdue,
            ];
        }

        // Current (not overdue)
        $currentInvoices = Invoice::on('tenant')
            ->pending()
            ->where('due_date', '>=', now())
            ->get();

        $aging['current']['count'] = $currentInvoices->count();
        $aging['current']['amount'] = $currentInvoices->sum('balance');

        return [
            'aging' => $aging,
            'total_outstanding' => array_sum(array_column($aging, 'amount')),
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * Story 20: Get unpaid statements by student
     */
    public function getUnpaidStatements(?int $studentId = null): array
    {
        $query = Invoice::on('tenant')
            ->with(['student', 'items', 'payments', 'academicYear'])
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_date');

        if ($studentId) {
            $query->byStudent($studentId);
        }

        $invoices = $query->get();

        $statements = $invoices->groupBy('student_id')->map(function ($studentInvoices) {
            $student = $studentInvoices->first()->student;

            return [
                'student' => [
                    'id' => $student->id,
                    'matricule' => $student->matricule,
                    'firstname' => $student->firstname,
                    'lastname' => $student->lastname,
                    'email' => $student->email,
                ],
                'total_debt' => $studentInvoices->sum('balance'),
                'invoices_count' => $studentInvoices->count(),
                'oldest_due_date' => $studentInvoices->min('due_date')->format('d/m/Y'),
                'invoices' => $studentInvoices->map(function ($invoice) {
                    return [
                        'invoice_number' => $invoice->invoice_number,
                        'invoice_date' => $invoice->invoice_date->format('d/m/Y'),
                        'due_date' => $invoice->due_date->format('d/m/Y'),
                        'total_amount' => $invoice->total_amount,
                        'paid_amount' => $invoice->paid_amount,
                        'balance' => $invoice->balance,
                        'status' => $invoice->status,
                        'days_overdue' => $invoice->isOverdue() ? $invoice->due_date->diffInDays(now()) : 0,
                    ];
                }),
            ];
        });

        return [
            'statements' => $statements->values(),
            'summary' => [
                'total_students' => $statements->count(),
                'total_invoices' => $invoices->count(),
                'total_amount' => $invoices->sum('balance'),
            ],
        ];
    }

    /**
     * Story 21: Cash flow forecast
     */
    public function getCashFlowForecast(int $months = 6): array
    {
        $forecast = [];
        $startDate = now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            // Expected collections (invoices due)
            $expectedCollections = Invoice::on('tenant')
                ->pending()
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->sum(DB::raw('total_amount - paid_amount'));

            // Payment schedules due
            $scheduledPayments = DB::connection('tenant')
                ->table('payment_schedules')
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->whereIn('status', ['pending', 'partial'])
                ->sum(DB::raw('amount - paid_amount'));

            // Historical average collections for this month (last 3 years)
            $historicalAvg = Payment::on('tenant')
                ->whereMonth('payment_date', $monthStart->month)
                ->whereYear('payment_date', '>=', now()->year - 3)
                ->avg(DB::raw('amount'));

            $forecast[] = [
                'month' => $monthStart->format('F Y'),
                'expected_collections' => $expectedCollections,
                'scheduled_payments' => $scheduledPayments,
                'historical_average' => $historicalAvg ?? 0,
                'confidence' => $i === 0 ? 'high' : ($i <= 2 ? 'medium' : 'low'),
            ];
        }

        return [
            'forecast' => $forecast,
            'total_expected' => array_sum(array_column($forecast, 'expected_collections')),
        ];
    }

    /**
     * Story 22: Collection statistics
     */
    public function getCollectionStatistics(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?AcademicYear $academicYear = null
    ): array {
        $startDate = $startDate ?? now()->startOfYear();
        $endDate = $endDate ?? now()->endOfYear();

        // Reminders sent
        $reminders = PaymentReminder::on('tenant')
            ->dateRange($startDate, $endDate)
            ->get();

        $remindersByType = $reminders->groupBy('reminder_type')->map(function ($typeReminders) {
            return [
                'count' => $typeReminders->count(),
                'sent' => $typeReminders->where('status', 'sent')->count(),
                'pending' => $typeReminders->where('status', 'pending')->count(),
            ];
        });

        // Service blocks
        $blocks = ServiceBlock::on('tenant')
            ->whereBetween('blocked_at', [$startDate, $endDate])
            ->get();

        $blocksByType = $blocks->groupBy('block_type')->map->count();

        // Collection effectiveness
        $overdueInvoices = Invoice::on('tenant')->overdue();
        if ($academicYear) {
            $overdueInvoices->byAcademicYear($academicYear->id);
        }

        $totalOverdue = $overdueInvoices->sum(DB::raw('total_amount - paid_amount'));
        $countOverdue = $overdueInvoices->count();

        return [
            'period' => [
                'start_date' => $startDate->format('d/m/Y'),
                'end_date' => $endDate->format('d/m/Y'),
            ],
            'reminders' => [
                'total' => $reminders->count(),
                'by_type' => $remindersByType,
            ],
            'blocks' => [
                'total' => $blocks->count(),
                'active' => $blocks->where('is_active', true)->count(),
                'by_type' => $blocksByType,
            ],
            'overdue' => [
                'amount' => $totalOverdue,
                'count' => $countOverdue,
                'average' => $countOverdue > 0 ? $totalOverdue / $countOverdue : 0,
            ],
        ];
    }

    /**
     * Story 23: Generate accounting export data
     */
    public function generateAccountingExport(
        Carbon $startDate,
        Carbon $endDate,
        string $format = 'custom'
    ): array {
        $payments = Payment::on('tenant')
            ->with(['invoice.items', 'student'])
            ->dateRange($startDate, $endDate)
            ->get();

        $defaultAccounts = config('finance.accounting.default_accounts', []);

        $entries = [];

        foreach ($payments as $payment) {
            $invoice = $payment->invoice;

            // Revenue entry
            $entries[] = [
                'date' => $payment->payment_date->format('Y-m-d'),
                'reference' => $payment->receipt_number,
                'account_debit' => $defaultAccounts['receivables'] ?? '4110',
                'account_credit' => $defaultAccounts['revenue'] ?? '7011',
                'amount' => $payment->amount,
                'description' => "Paiement facture {$invoice->invoice_number}",
                'student_matricule' => $payment->student->matricule,
            ];
        }

        return [
            'format' => $format,
            'period' => [
                'start_date' => $startDate->format('d/m/Y'),
                'end_date' => $endDate->format('d/m/Y'),
            ],
            'entries' => $entries,
            'summary' => [
                'total_entries' => count($entries),
                'total_amount' => array_sum(array_column($entries, 'amount')),
            ],
        ];
    }

    /**
     * Get collection trends
     */
    protected function getCollectionTrends(?AcademicYear $academicYear = null, int $months = 12): array
    {
        $trends = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $monthlyCollected = Payment::on('tenant')
                ->dateRange($monthStart, $monthEnd)
                ->sum('amount');

            $trends[] = [
                'month' => $monthStart->format('M Y'),
                'collected' => $monthlyCollected,
            ];
        }

        return $trends;
    }

    /**
     * Cache helper
     */
    protected function cached(string $key, callable $callback)
    {
        if (! $this->cacheEnabled) {
            return $callback();
        }

        return Cache::remember($key, $this->cacheDuration * 60, $callback);
    }

    /**
     * Clear all finance report caches
     */
    public function clearCache(): void
    {
        Cache::flush();
    }
}
