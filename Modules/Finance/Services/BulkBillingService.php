<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Finance\Entities\FeeType;
use Modules\Finance\Entities\InvoiceItem;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;

/**
 * Facturation en masse : une facture par élève inscrit (class_enrollments actif)
 * d'une classe, d'un niveau ou de toute l'année.
 *
 * Anti-doublon : un frais déjà facturé au même élève pour la même année
 * (facture non annulée, non supprimée) est ignoré ; un élève sans frais
 * restant à facturer est ignoré.
 */
class BulkBillingService
{
    public function __construct(
        private BillingService $billingService
    ) {}

    /**
     * Cibles disponibles : tous les niveaux et les classes de l'année, avec effectif inscrit actif.
     *
     * @return array{levels: list<array{id: int, code: string, name: string, students_count: int}>, classes: list<array{id: int, name: string, level_id: int, level_code: string|null, students_count: int}>, total_students: int}
     */
    public function targets(AcademicYear $academicYear): array
    {
        $classes = Classe::on('tenant')
            ->where('academic_year_id', $academicYear->id)
            ->with('level')
            ->withCount(['enrollments as students_count' => function ($query) use ($academicYear) {
                $query->active()->forAcademicYear($academicYear->id);
            }])
            ->get()
            ->sortBy(fn (Classe $classe): string => sprintf('%05d|%s', $classe->level?->order_index ?? 99999, $classe->name))
            ->values();

        $studentsByLevel = $classes->groupBy('level_id')->map(fn (Collection $levelClasses): int => (int) $levelClasses->sum('students_count'));

        $levels = Level::on('tenant')
            ->orderBy('order_index')
            ->get()
            ->map(fn (Level $level): array => [
                'id' => (int) $level->id,
                'code' => (string) $level->code,
                'name' => (string) $level->name,
                'students_count' => (int) ($studentsByLevel[$level->id] ?? 0),
            ])
            ->all();

        return [
            'levels' => $levels,
            'classes' => $classes->map(fn (Classe $classe): array => [
                'id' => (int) $classe->id,
                'name' => $classe->name,
                'level_id' => (int) $classe->level_id,
                'level_code' => $classe->level?->code,
                'students_count' => (int) $classe->students_count,
            ])->all(),
            'total_students' => (int) $classes->sum('students_count'),
        ];
    }

    /**
     * Aperçu (dry-run) : ce qui sera facturé, élève par élève.
     *
     * @param  array{scope: string, classe_id?: int|null, level_id?: int|null}  $target
     * @param  Collection<int, FeeType>  $feeTypes
     * @return array{summary: array<string, int|float>, students: list<array<string, mixed>>}
     */
    public function preview(AcademicYear $academicYear, array $target, Collection $feeTypes): array
    {
        $plan = $this->plan($academicYear, $target, $feeTypes);

        return [
            'summary' => $this->summarize($plan),
            'students' => array_map(function (array $row): array {
                unset($row['student'], $row['fee_types']);

                return $row;
            }, $plan),
        ];
    }

    /**
     * Génère les factures (tout ou rien, dans une transaction).
     *
     * @param  array{scope: string, classe_id?: int|null, level_id?: int|null}  $target
     * @param  Collection<int, FeeType>  $feeTypes
     * @param  array{invoice_date?: string|null, due_date?: string|null, notes?: string|null}  $options
     * @return array{summary: array<string, int|float>, invoices: list<array<string, mixed>>}
     */
    public function generate(AcademicYear $academicYear, array $target, Collection $feeTypes, array $options): array
    {
        return DB::connection('tenant')->transaction(function () use ($academicYear, $target, $feeTypes, $options) {
            $plan = $this->plan($academicYear, $target, $feeTypes);
            $invoices = [];

            foreach ($plan as $row) {
                if (! $row['will_invoice']) {
                    continue;
                }

                $invoice = $this->billingService->createInvoiceFromFeeTypes(
                    $row['student'],
                    $academicYear,
                    $row['fee_types'],
                    $row['level_code'],
                    $options
                );

                $invoices[] = [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'student_id' => $row['student_id'],
                    'student_name' => $row['student_name'],
                    'classe_name' => $row['classe_name'],
                    'total_amount' => (float) $invoice->total_amount,
                ];
            }

            return [
                'summary' => $this->summarize($plan),
                'invoices' => $invoices,
            ];
        });
    }

    /**
     * @param  array{scope: string, classe_id?: int|null, level_id?: int|null}  $target
     * @param  Collection<int, FeeType>  $feeTypes
     * @return list<array<string, mixed>>
     */
    private function plan(AcademicYear $academicYear, array $target, Collection $feeTypes): array
    {
        $enrollments = $this->targetEnrollments($academicYear, $target);
        $studentIds = $enrollments->pluck('student_id')->all();
        $alreadyBilled = $this->alreadyBilledPairs($academicYear, $studentIds, $feeTypes->pluck('id')->all());

        $plan = [];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $levelCode = $enrollment->classe?->level?->code;
            $billableFeeTypes = collect();
            $items = [];

            foreach ($feeTypes as $feeType) {
                $status = 'to_invoice';

                if (! $feeType->appliesToLevel($levelCode)) {
                    $status = 'not_applicable';
                } elseif (isset($alreadyBilled[$student->id.':'.$feeType->id])) {
                    $status = 'already_invoiced';
                } else {
                    $billableFeeTypes->push($feeType);
                }

                $items[$feeType->id] = [
                    'fee_type_id' => (int) $feeType->id,
                    'name' => $feeType->name,
                    'status' => $status,
                    'amount' => $feeType->amountForLevel($levelCode),
                    'discount_amount' => 0.0,
                    'net_amount' => 0.0,
                ];
            }

            $total = 0.0;

            if ($billableFeeTypes->isNotEmpty()) {
                foreach ($this->billingService->buildInvoiceLines($student, $billableFeeTypes, $levelCode) as $line) {
                    $items[$line['fee_type_id']]['amount'] = $line['amount'];
                    $items[$line['fee_type_id']]['discount_amount'] = $line['discount_amount'];
                    $items[$line['fee_type_id']]['net_amount'] = $line['net_amount'];
                    $total += $line['net_amount'];
                }
            }

            $plan[] = [
                'student' => $student,
                'fee_types' => $billableFeeTypes,
                'student_id' => (int) $student->id,
                'matricule' => $student->matricule,
                'student_name' => trim($student->lastname.' '.$student->firstname),
                'classe_id' => (int) $enrollment->classe_id,
                'classe_name' => $enrollment->classe?->name,
                'level_code' => $levelCode,
                'items' => array_values($items),
                'total_amount' => $total,
                'will_invoice' => $billableFeeTypes->isNotEmpty(),
            ];
        }

        return $plan;
    }

    /**
     * @param  array{scope: string, classe_id?: int|null, level_id?: int|null}  $target
     * @return Collection<int, ClassEnrollment>
     */
    private function targetEnrollments(AcademicYear $academicYear, array $target): Collection
    {
        $query = ClassEnrollment::on('tenant')
            ->active()
            ->forAcademicYear($academicYear->id)
            ->whereHas('student')
            ->with(['student', 'classe.level']);

        if ($target['scope'] === 'classe') {
            $query->where('classe_id', $target['classe_id']);
        } elseif ($target['scope'] === 'level') {
            $query->whereHas('classe', fn ($classeQuery) => $classeQuery->where('level_id', $target['level_id']));
        }

        return $query->get()
            ->sortBy(fn (ClassEnrollment $enrollment): string => sprintf(
                '%05d|%s|%s|%s',
                $enrollment->classe?->level?->order_index ?? 99999,
                (string) $enrollment->classe?->name,
                (string) $enrollment->student?->lastname,
                (string) $enrollment->student?->firstname
            ), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Couples « élève:type de frais » déjà facturés pour l'année (factures non annulées).
     *
     * @param  list<int>  $studentIds
     * @param  list<int>  $feeTypeIds
     * @return array<string, true>
     */
    private function alreadyBilledPairs(AcademicYear $academicYear, array $studentIds, array $feeTypeIds): array
    {
        if ($studentIds === [] || $feeTypeIds === []) {
            return [];
        }

        $pairs = [];

        InvoiceItem::on('tenant')
            ->whereIn('fee_type_id', $feeTypeIds)
            ->whereHas('invoice', function ($invoiceQuery) use ($academicYear, $studentIds) {
                $invoiceQuery->where('academic_year_id', $academicYear->id)
                    ->whereIn('student_id', $studentIds)
                    ->where('status', '!=', 'cancelled');
            })
            ->with('invoice:id,student_id')
            ->get(['id', 'invoice_id', 'fee_type_id'])
            ->each(function (InvoiceItem $item) use (&$pairs): void {
                $pairs[$item->invoice->student_id.':'.$item->fee_type_id] = true;
            });

        return $pairs;
    }

    /**
     * @param  list<array<string, mixed>>  $plan
     * @return array{students_count: int, invoices_count: int, skipped_students_count: int, already_invoiced_lines_count: int, not_applicable_lines_count: int, total_amount: float}
     */
    private function summarize(array $plan): array
    {
        $lines = collect($plan)->flatMap(fn (array $row) => $row['items']);

        return [
            'students_count' => count($plan),
            'invoices_count' => collect($plan)->where('will_invoice', true)->count(),
            'skipped_students_count' => collect($plan)->where('will_invoice', false)->count(),
            'already_invoiced_lines_count' => $lines->where('status', 'already_invoiced')->count(),
            'not_applicable_lines_count' => $lines->where('status', 'not_applicable')->count(),
            'total_amount' => (float) collect($plan)->sum('total_amount'),
        ];
    }
}
