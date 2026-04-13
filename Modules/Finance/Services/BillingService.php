<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\Discount;
use Modules\Finance\Entities\FeeType;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\InvoiceItem;
use Modules\Finance\Entities\PaymentSchedule;
use Modules\StructureAcademique\Entities\AcademicYear;

/**
 * Epic 1: Facturation (Stories 01-06)
 *
 * Story 01: Automated billing
 * Story 02: Fee types management
 * Story 03: Custom invoicing
 * Story 04: Payment schedules
 * Story 05: Late fees
 * Story 06: Scholarships/discounts
 */
class BillingService
{
    /**
     * Story 01: Generate automated invoice for a student
     */
    public function generateAutomatedInvoice(
        Student $student,
        AcademicYear $academicYear,
        array $feeTypeIds = [],
        array $options = []
    ): Invoice {
        return DB::connection('tenant')->transaction(function () use ($student, $academicYear, $feeTypeIds, $options) {
            // Get applicable fee types
            $feeTypes = $this->getApplicableFeeTypes($student, $academicYear, $feeTypeIds);

            if ($feeTypes->isEmpty()) {
                throw new \Exception('Aucun frais applicable trouvé pour cet étudiant');
            }

            // Create invoice
            $invoice = Invoice::on('tenant')->create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'invoice_number' => $this->generateInvoiceNumber($academicYear),
                'invoice_date' => $options['invoice_date'] ?? now(),
                'due_date' => $options['due_date'] ?? now()->addDays(config('finance.billing.default_payment_deadline', 30)),
                'status' => 'pending',
                'notes' => $options['notes'] ?? null,
            ]);

            // Get applicable discounts
            $discounts = $this->getApplicableDiscounts($student);

            $totalAmount = 0;

            // Create invoice items
            foreach ($feeTypes as $feeType) {
                $quantity = $options['quantities'][$feeType->id] ?? 1;
                $unitPrice = $feeType->default_amount;
                $amount = $unitPrice * $quantity;

                // Apply discount if applicable
                $discountAmount = 0;
                $applicableDiscount = $discounts->first(function ($discount) use ($feeType) {
                    return $discount->fee_type_id === null || $discount->fee_type_id === $feeType->id;
                });

                if ($applicableDiscount) {
                    $discountAmount = $applicableDiscount->calculateDiscountAmount($amount);
                }

                InvoiceItem::on('tenant')->create([
                    'invoice_id' => $invoice->id,
                    'fee_type_id' => $feeType->id,
                    'description' => $feeType->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                    'discount_amount' => $discountAmount,
                ]);

                $totalAmount += ($amount - $discountAmount);
            }

            // Update invoice total
            $invoice->update(['total_amount' => $totalAmount]);

            return $invoice->fresh(['items', 'student', 'academicYear']);
        });
    }

    /**
     * Story 03: Create custom invoice with custom items
     */
    public function createCustomInvoice(
        Student $student,
        AcademicYear $academicYear,
        array $items,
        array $options = []
    ): Invoice {
        return DB::connection('tenant')->transaction(function () use ($student, $academicYear, $items, $options) {
            $invoice = Invoice::on('tenant')->create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'invoice_number' => $this->generateInvoiceNumber($academicYear),
                'invoice_date' => $options['invoice_date'] ?? now(),
                'due_date' => $options['due_date'] ?? now()->addDays(config('finance.billing.default_payment_deadline', 30)),
                'status' => 'pending',
                'notes' => $options['notes'] ?? null,
            ]);

            $totalAmount = 0;

            foreach ($items as $item) {
                $amount = $item['unit_price'] * $item['quantity'];
                $discountAmount = $item['discount_amount'] ?? 0;

                InvoiceItem::on('tenant')->create([
                    'invoice_id' => $invoice->id,
                    'fee_type_id' => $item['fee_type_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'amount' => $amount,
                    'discount_amount' => $discountAmount,
                ]);

                $totalAmount += ($amount - $discountAmount);
            }

            $invoice->update(['total_amount' => $totalAmount]);

            return $invoice->fresh(['items', 'student', 'academicYear']);
        });
    }

    /**
     * Story 04: Create payment schedule for an invoice
     */
    public function createPaymentSchedule(
        Invoice $invoice,
        int $numberOfInstallments,
        ?Carbon $firstDueDate = null
    ): array {
        return DB::connection('tenant')->transaction(function () use ($invoice, $numberOfInstallments, $firstDueDate) {
            // Check if amount qualifies for payment plan
            $minAmount = config('finance.collection.min_payment_plan_amount', 10000);
            if ($invoice->total_amount < $minAmount) {
                throw new \Exception("Le montant minimum pour un échéancier est {$minAmount}");
            }

            $maxInstallments = config('finance.collection.max_installments', 12);
            if ($numberOfInstallments > $maxInstallments) {
                throw new \Exception("Le nombre maximum d'échéances est {$maxInstallments}");
            }

            // Delete existing schedules
            PaymentSchedule::on('tenant')->where('invoice_id', $invoice->id)->delete();

            $firstDueDate = $firstDueDate ?? now()->addMonth();
            $installmentAmount = round($invoice->total_amount / $numberOfInstallments, 2);
            $remainder = $invoice->total_amount - ($installmentAmount * $numberOfInstallments);

            $schedules = [];

            for ($i = 1; $i <= $numberOfInstallments; $i++) {
                $amount = $installmentAmount;

                // Add remainder to last installment
                if ($i === $numberOfInstallments) {
                    $amount += $remainder;
                }

                $schedules[] = PaymentSchedule::on('tenant')->create([
                    'invoice_id' => $invoice->id,
                    'installment_number' => $i,
                    'due_date' => $firstDueDate->copy()->addMonths($i - 1),
                    'amount' => $amount,
                    'status' => 'pending',
                ]);
            }

            return $schedules;
        });
    }

    /**
     * Story 05: Calculate and apply late fees
     */
    public function calculateLateFees(Invoice $invoice): float
    {
        if (! config('finance.late_fees.enabled', true)) {
            return 0;
        }

        if (! $invoice->isOverdue()) {
            return 0;
        }

        $gracePeriod = config('finance.late_fees.grace_period', 7);
        $daysOverdue = $invoice->due_date->diffInDays(now());

        if ($daysOverdue <= $gracePeriod) {
            return 0;
        }

        $type = config('finance.late_fees.type', 'percentage');
        $amount = config('finance.late_fees.amount', 2.5);
        $maxPercentage = config('finance.late_fees.max_penalty_percentage', 25);

        $lateFee = 0;

        if ($type === 'percentage') {
            $lateFee = $invoice->balance * ($amount / 100);

            // Apply frequency
            $frequency = config('finance.late_fees.frequency', 'monthly');
            if ($frequency === 'monthly') {
                $monthsOverdue = max(1, floor($daysOverdue / 30));
                $lateFee *= $monthsOverdue;
            }

            // Apply maximum cap
            $maxLateFee = $invoice->total_amount * ($maxPercentage / 100);
            $lateFee = min($lateFee, $maxLateFee);
        } else {
            $lateFee = $amount;
        }

        return round($lateFee, 2);
    }

    /**
     * Story 06: Apply scholarship/discount to student
     */
    public function applyDiscount(
        Student $student,
        string $type,
        ?float $percentage = null,
        ?float $amount = null,
        array $options = []
    ): Discount {
        // Validate discount amount
        $maxPercentage = config('finance.discounts.max_discount_percentage', 100);
        if ($percentage && $percentage > $maxPercentage) {
            throw new \Exception("Le pourcentage maximum de remise est {$maxPercentage}%");
        }

        $validationRequired = config('finance.discounts.validation_required_above', 50);
        $needsApproval = $percentage && $percentage > $validationRequired;

        $discount = Discount::on('tenant')->create([
            'student_id' => $student->id,
            'fee_type_id' => $options['fee_type_id'] ?? null,
            'type' => $type,
            'percentage' => $percentage,
            'amount' => $amount,
            'reason' => $options['reason'] ?? null,
            'valid_from' => $options['valid_from'] ?? now(),
            'valid_until' => $options['valid_until'] ?? null,
            'approved_by' => ! $needsApproval ? Auth::id() : null,
            'approved_at' => ! $needsApproval ? now() : null,
        ]);

        return $discount;
    }

    /**
     * Get applicable fee types for a student
     */
    protected function getApplicableFeeTypes(Student $student, AcademicYear $academicYear, array $feeTypeIds = [])
    {
        $query = FeeType::on('tenant')->active();

        if (! empty($feeTypeIds)) {
            $query->whereIn('id', $feeTypeIds);
        }

        return $query->get();
    }

    /**
     * Get applicable discounts for a student
     */
    protected function getApplicableDiscounts(Student $student)
    {
        return Discount::on('tenant')
            ->where('student_id', $student->id)
            ->valid()
            ->approved()
            ->get();
    }

    /**
     * Generate unique invoice number
     */
    protected function generateInvoiceNumber(AcademicYear $academicYear): string
    {
        $format = config('finance.billing.invoice_number_format', 'INV-{year}-{sequence}');
        $year = $academicYear->start_year;

        // Get next sequence number for this year
        $lastInvoice = Invoice::on('tenant')
            ->where('invoice_number', 'like', "INV-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastInvoice) {
            preg_match('/INV-\d+-(\d+)/', $lastInvoice->invoice_number, $matches);
            $sequence = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        }

        return str_replace(
            ['{year}', '{sequence}'],
            [$year, str_pad($sequence, 5, '0', STR_PAD_LEFT)],
            $format
        );
    }
}
