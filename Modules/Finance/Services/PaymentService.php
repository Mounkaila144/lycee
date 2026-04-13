<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\Payment;
use Modules\Finance\Entities\PaymentSchedule;

/**
 * Epic 2: Encaissement (Stories 07-12)
 *
 * Story 07: Payment recording
 * Story 08: Payment methods (cash, check, transfer, card, online)
 * Story 09: PDF receipts generation
 * Story 10: Partial payments
 * Story 11: Refunds
 * Story 12: Bank reconciliation
 */
class PaymentService
{
    /**
     * Story 07 & 08: Record a payment
     */
    public function recordPayment(
        Invoice $invoice,
        float $amount,
        string $paymentMethod,
        ?Carbon $paymentDate = null,
        array $options = []
    ): Payment {
        return DB::connection('tenant')->transaction(function () use ($invoice, $amount, $paymentMethod, $paymentDate, $options) {
            // Validate payment amount
            if ($amount <= 0) {
                throw new \Exception('Le montant du paiement doit être supérieur à zéro');
            }

            $balance = $invoice->balance;
            if ($amount > $balance) {
                throw new \Exception("Le montant du paiement ({$amount}) dépasse le solde dû ({$balance})");
            }

            // Check if partial payments are allowed
            $allowPartial = config('finance.payments.allow_partial_payments', true);
            if (! $allowPartial && $amount < $balance) {
                throw new \Exception('Les paiements partiels ne sont pas autorisés');
            }

            // Create payment
            $payment = Payment::on('tenant')->create([
                'invoice_id' => $invoice->id,
                'student_id' => $invoice->student_id,
                'payment_date' => $paymentDate ?? now(),
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'reference_number' => $options['reference_number'] ?? null,
                'receipt_number' => $this->generateReceiptNumber(),
                'notes' => $options['notes'] ?? null,
                'recorded_by' => Auth::id(),
            ]);

            // Update invoice paid amount and status
            $newPaidAmount = $invoice->paid_amount + $amount;
            $invoice->update([
                'paid_amount' => $newPaidAmount,
                'status' => $this->calculateInvoiceStatus($invoice->total_amount, $newPaidAmount),
            ]);

            // Update payment schedules if they exist
            $this->updatePaymentSchedules($invoice, $amount);

            return $payment->fresh(['invoice', 'student']);
        });
    }

    /**
     * Story 10: Record partial payment
     */
    public function recordPartialPayment(
        Invoice $invoice,
        float $amount,
        string $paymentMethod,
        array $options = []
    ): Payment {
        if (! config('finance.payments.allow_partial_payments', true)) {
            throw new \Exception('Les paiements partiels ne sont pas autorisés');
        }

        return $this->recordPayment($invoice, $amount, $paymentMethod, null, $options);
    }

    /**
     * Story 11: Process refund
     */
    public function processRefund(
        Payment $payment,
        float $refundAmount,
        string $reason
    ): Payment {
        return DB::connection('tenant')->transaction(function () use ($payment, $refundAmount, $reason) {
            if ($refundAmount <= 0) {
                throw new \Exception('Le montant du remboursement doit être supérieur à zéro');
            }

            if ($refundAmount > $payment->amount) {
                throw new \Exception('Le montant du remboursement ne peut pas dépasser le montant du paiement');
            }

            $invoice = $payment->invoice;

            // Create refund payment (negative amount)
            $refund = Payment::on('tenant')->create([
                'invoice_id' => $invoice->id,
                'student_id' => $payment->student_id,
                'payment_date' => now(),
                'amount' => -$refundAmount,
                'payment_method' => $payment->payment_method,
                'reference_number' => 'REFUND-'.$payment->receipt_number,
                'receipt_number' => $this->generateReceiptNumber(),
                'notes' => "Remboursement: {$reason}",
                'recorded_by' => Auth::id(),
            ]);

            // Update invoice paid amount and status
            $newPaidAmount = $invoice->paid_amount - $refundAmount;
            $invoice->update([
                'paid_amount' => max(0, $newPaidAmount),
                'status' => $this->calculateInvoiceStatus($invoice->total_amount, $newPaidAmount),
            ]);

            return $refund->fresh(['invoice', 'student']);
        });
    }

    /**
     * Story 12: Get payments for bank reconciliation
     */
    public function getPaymentsForReconciliation(
        Carbon $startDate,
        Carbon $endDate,
        ?string $paymentMethod = null
    ): array {
        $query = Payment::on('tenant')
            ->with(['invoice', 'student'])
            ->dateRange($startDate, $endDate);

        if ($paymentMethod) {
            $query->byMethod($paymentMethod);
        }

        $payments = $query->orderBy('payment_date')->get();

        // Group by payment method
        $grouped = $payments->groupBy('payment_method');

        // Calculate totals
        $summary = [
            'total_amount' => $payments->sum('amount'),
            'total_count' => $payments->count(),
            'by_method' => [],
        ];

        foreach ($grouped as $method => $methodPayments) {
            $summary['by_method'][$method] = [
                'count' => $methodPayments->count(),
                'amount' => $methodPayments->sum('amount'),
                'payments' => $methodPayments,
            ];
        }

        return [
            'summary' => $summary,
            'payments' => $payments,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * Story 09: Generate receipt data for PDF
     */
    public function generateReceiptData(Payment $payment): array
    {
        $invoice = $payment->invoice;
        $student = $payment->student;

        return [
            'receipt_number' => $payment->receipt_number,
            'payment_date' => $payment->payment_date->format('d/m/Y'),
            'amount' => $payment->amount,
            'amount_words' => $this->numberToWords($payment->amount),
            'payment_method' => $this->getPaymentMethodLabel($payment->payment_method),
            'reference_number' => $payment->reference_number,
            'invoice_number' => $invoice->invoice_number,
            'student' => [
                'matricule' => $student->matricule,
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'email' => $student->email,
            ],
            'invoice_total' => $invoice->total_amount,
            'invoice_paid' => $invoice->paid_amount,
            'invoice_balance' => $invoice->balance,
            'recorded_by' => $payment->recordedBy?->name,
            'notes' => $payment->notes,
        ];
    }

    /**
     * Get daily payment summary
     */
    public function getDailySummary(Carbon $date): array
    {
        $payments = Payment::on('tenant')
            ->with(['invoice', 'student'])
            ->whereDate('payment_date', $date)
            ->get();

        $byMethod = $payments->groupBy('payment_method');

        return [
            'date' => $date->format('d/m/Y'),
            'total_amount' => $payments->sum('amount'),
            'total_count' => $payments->count(),
            'by_method' => $byMethod->map(function ($methodPayments, $method) {
                return [
                    'method' => $this->getPaymentMethodLabel($method),
                    'count' => $methodPayments->count(),
                    'amount' => $methodPayments->sum('amount'),
                ];
            }),
            'payments' => $payments,
        ];
    }

    /**
     * Update payment schedules after payment
     */
    protected function updatePaymentSchedules(Invoice $invoice, float $paidAmount): void
    {
        $schedules = PaymentSchedule::on('tenant')
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('installment_number')
            ->get();

        if ($schedules->isEmpty()) {
            return;
        }

        $remainingAmount = $paidAmount;

        foreach ($schedules as $schedule) {
            if ($remainingAmount <= 0) {
                break;
            }

            $scheduleDue = $schedule->balance;

            if ($remainingAmount >= $scheduleDue) {
                // Fully pay this installment
                $schedule->update([
                    'paid_amount' => $schedule->amount,
                    'status' => 'paid',
                    'paid_date' => now(),
                ]);
                $remainingAmount -= $scheduleDue;
            } else {
                // Partially pay this installment
                $schedule->update([
                    'paid_amount' => $schedule->paid_amount + $remainingAmount,
                    'status' => 'partial',
                    'paid_date' => now(),
                ]);
                $remainingAmount = 0;
            }
        }
    }

    /**
     * Calculate invoice status based on amounts
     */
    protected function calculateInvoiceStatus(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'pending';
        }

        if ($paid >= $total) {
            return 'paid';
        }

        return 'partial';
    }

    /**
     * Generate unique receipt number
     */
    protected function generateReceiptNumber(): string
    {
        $format = config('finance.payments.receipt_number_format', 'REC-{year}-{sequence}');
        $year = now()->year;

        $lastPayment = Payment::on('tenant')
            ->where('receipt_number', 'like', "REC-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastPayment) {
            preg_match('/REC-\d+-(\d+)/', $lastPayment->receipt_number, $matches);
            $sequence = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        }

        return str_replace(
            ['{year}', '{sequence}'],
            [$year, str_pad($sequence, 6, '0', STR_PAD_LEFT)],
            $format
        );
    }

    /**
     * Get payment method label
     */
    protected function getPaymentMethodLabel(string $method): string
    {
        $methods = config('finance.payments.methods', []);

        return $methods[$method] ?? $method;
    }

    /**
     * Convert number to words (French)
     */
    protected function numberToWords(float $number): string
    {
        // Simple implementation - can be enhanced with a proper library
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::SPELLOUT);

        return ucfirst($formatter->format($number));
    }
}
