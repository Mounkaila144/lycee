<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\ClassEnrollment;
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
        $levelCode = array_key_exists('level_code', $options)
            ? $options['level_code']
            : $this->resolveStudentLevelCode($student, $academicYear);

        $feeTypes = $this->getApplicableFeeTypes($student, $academicYear, $feeTypeIds, $levelCode);

        if ($feeTypes->isEmpty()) {
            throw new \DomainException('Aucun frais applicable trouvé pour cet élève');
        }

        return DB::connection('tenant')->transaction(
            fn (): Invoice => $this->createInvoiceFromFeeTypes($student, $academicYear, $feeTypes, $levelCode, $options)
        );
    }

    /**
     * Crée une facture à partir d'une liste de types de frais (montant selon le niveau, remises appliquées).
     *
     * @param  Collection<int, FeeType>  $feeTypes
     * @param  array{invoice_date?: mixed, due_date?: mixed, notes?: string|null, quantities?: array<int, int>}  $options
     */
    public function createInvoiceFromFeeTypes(
        Student $student,
        AcademicYear $academicYear,
        Collection $feeTypes,
        ?string $levelCode,
        array $options = []
    ): Invoice {
        $invoice = Invoice::on('tenant')->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'invoice_number' => $this->generateInvoiceNumber($academicYear),
            'invoice_date' => $options['invoice_date'] ?? now(),
            'due_date' => $options['due_date'] ?? now()->addDays(config('finance.billing.default_payment_deadline', 30)),
            'status' => 'pending',
            'notes' => $options['notes'] ?? null,
        ]);

        $lines = $this->buildInvoiceLines($student, $feeTypes, $levelCode, $options['quantities'] ?? []);

        foreach ($lines as $line) {
            InvoiceItem::on('tenant')->create([
                'invoice_id' => $invoice->id,
                'fee_type_id' => $line['fee_type_id'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'amount' => $line['amount'],
                'discount_amount' => $line['discount_amount'],
            ]);
        }

        $invoice->update(['total_amount' => array_sum(array_column($lines, 'net_amount'))]);

        return $invoice->fresh(['items', 'student', 'academicYear']);
    }

    /**
     * Calcule les lignes de facture (montant du niveau, quantité, remise de l'élève).
     *
     * @param  Collection<int, FeeType>  $feeTypes
     * @param  array<int, int>  $quantities
     * @param  Collection<int, Discount>|null  $discounts
     * @return list<array{fee_type_id: int, description: string, quantity: int, unit_price: float, amount: float, discount_amount: float, net_amount: float}>
     */
    public function buildInvoiceLines(
        Student $student,
        Collection $feeTypes,
        ?string $levelCode,
        array $quantities = [],
        ?Collection $discounts = null
    ): array {
        $discounts ??= $this->getApplicableDiscounts($student);
        $lines = [];

        foreach ($feeTypes as $feeType) {
            $quantity = (int) ($quantities[$feeType->id] ?? 1);
            $unitPrice = $feeType->amountForLevel($levelCode);
            $amount = $unitPrice * $quantity;

            $applicableDiscount = $discounts->first(function ($discount) use ($feeType) {
                return $discount->fee_type_id === null || (int) $discount->fee_type_id === (int) $feeType->id;
            });

            $discountAmount = $applicableDiscount ? (float) $applicableDiscount->calculateDiscountAmount($amount) : 0.0;

            $lines[] = [
                'fee_type_id' => (int) $feeType->id,
                'description' => $feeType->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'discount_amount' => $discountAmount,
                'net_amount' => $amount - $discountAmount,
            ];
        }

        return $lines;
    }

    /**
     * Code du niveau (levels.code) de la classe de l'élève pour l'année.
     */
    public function resolveStudentLevelCode(Student $student, AcademicYear $academicYear): ?string
    {
        $enrollment = ClassEnrollment::on('tenant')
            ->where('student_id', $student->id)
            ->forAcademicYear($academicYear->id)
            ->with('classe.level')
            ->first();

        return $enrollment?->classe?->level?->code;
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
            'approved_by' => ! $needsApproval ? (Auth::guard('tenant')->id() ?? Auth::id()) : null,
            'approved_at' => ! $needsApproval ? now() : null,
        ]);

        return $discount;
    }

    /**
     * Types de frais actifs applicables à l'année (sans année ou année ciblée).
     * Sans sélection explicite, seuls les frais du niveau de l'élève sont retenus.
     *
     * @return Collection<int, FeeType>
     */
    protected function getApplicableFeeTypes(Student $student, AcademicYear $academicYear, array $feeTypeIds = [], ?string $levelCode = null): Collection
    {
        $query = FeeType::on('tenant')->where(function ($yearQuery) use ($academicYear) {
            $yearQuery->whereNull('academic_year_id')
                ->orWhere('academic_year_id', $academicYear->id);
        });

        if (! empty($feeTypeIds)) {
            return $query->whereIn('id', $feeTypeIds)->get();
        }

        return $query->get()->filter(fn (FeeType $feeType): bool => $feeType->appliesToLevel($levelCode))->values();
    }

    /**
     * Get applicable discounts for a student
     */
    public function getApplicableDiscounts(Student $student): Collection
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

        // AcademicYear stocke start_date (Carbon) ; on dérive l'année calendaire
        // de début. `start_year` n'existe pas sur le modèle — utiliser cette
        // valeur produisait des numéros « INV--00001 » non uniques.
        $year = $academicYear->start_date?->year ?? (int) now()->year;

        // withTrashed : l'index unique couvre aussi les factures supprimées.
        $lastInvoice = Invoice::on('tenant')
            ->withTrashed()
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
