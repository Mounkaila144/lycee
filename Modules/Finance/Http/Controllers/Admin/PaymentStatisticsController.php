<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\Invoice;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;

/**
 * School-fee payment statistics: who paid (tuition/registration) vs who didn't,
 * globally and per class, for a given academic year. Useful to track collection
 * of the main mandatory fee.
 */
class PaymentStatisticsController extends Controller
{
    /**
     * Aggregate per student per academic year, broken down by class.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => 'nullable|integer|exists:tenant.academic_years,id',
            'class_id' => 'nullable|integer|exists:tenant.classes,id',
        ]);

        $yearId = isset($validated['academic_year_id'])
            ? (int) $validated['academic_year_id']
            : (AcademicYear::on('tenant')->where('is_active', true)->value('id') ?? AcademicYear::on('tenant')->max('id'));

        // Invoice totals per student for the year (all fee types combined).
        $invoiceAgg = Invoice::on('tenant')
            ->where('academic_year_id', $yearId)
            ->where('status', '!=', 'cancelled')
            ->select('student_id', DB::raw('SUM(total_amount) as billed'), DB::raw('SUM(paid_amount) as paid'))
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        // Students scoped to the classes of that year (optionally one class).
        $classQuery = Classe::on('tenant')->where('academic_year_id', $yearId);
        if (! empty($validated['class_id'])) {
            $classQuery->where('id', $validated['class_id']);
        }
        $classes = $classQuery->orderBy('name')->get(['id', 'name']);
        $classIds = $classes->pluck('id');

        $students = Student::on('tenant')
            ->whereIn('class_id', $classIds)
            ->get(['id', 'class_id']);

        $perClass = [];
        foreach ($classes as $class) {
            $perClass[$class->id] = [
                'class_id' => $class->id,
                'class_name' => $class->name,
                'total_students' => 0,
                'paid' => 0, 'partial' => 0, 'unpaid' => 0, 'not_billed' => 0,
                'amount_billed' => 0.0, 'amount_collected' => 0.0, 'amount_outstanding' => 0.0,
            ];
        }

        $global = ['total_students' => 0, 'paid' => 0, 'partial' => 0, 'unpaid' => 0, 'not_billed' => 0,
            'amount_billed' => 0.0, 'amount_collected' => 0.0, 'amount_outstanding' => 0.0];

        foreach ($students as $student) {
            $bucket = &$perClass[$student->class_id];
            $agg = $invoiceAgg->get($student->id);

            $billed = (float) ($agg->billed ?? 0);
            $paid = (float) ($agg->paid ?? 0);

            $status = $this->statusFor($billed, $paid);

            $bucket['total_students']++;
            $bucket[$status]++;
            $bucket['amount_billed'] += $billed;
            $bucket['amount_collected'] += $paid;
            $bucket['amount_outstanding'] += max(0, $billed - $paid);

            $global['total_students']++;
            $global[$status]++;
            $global['amount_billed'] += $billed;
            $global['amount_collected'] += $paid;
            $global['amount_outstanding'] += max(0, $billed - $paid);
            unset($bucket);
        }

        $round = function (array $row): array {
            foreach (['amount_billed', 'amount_collected', 'amount_outstanding'] as $k) {
                $row[$k] = round($row[$k], 2);
            }
            $billedCount = $row['paid'] + $row['partial'] + $row['unpaid'];
            $row['payment_rate'] = $billedCount > 0 ? round(($row['paid'] / $billedCount) * 100, 1) : 0.0;

            return $row;
        };

        return response()->json([
            'data' => [
                'academic_year_id' => $yearId,
                'global' => $round($global),
                'classes' => array_map($round, array_values($perClass)),
            ],
        ]);
    }

    private function statusFor(float $billed, float $paid): string
    {
        if ($billed <= 0) {
            return 'not_billed';
        }
        if ($paid >= $billed) {
            return 'paid';
        }
        if ($paid > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    /**
     * List students with an outstanding balance (unpaid / partial / not billed)
     * for a given year — optionally scoped to one class. Used by the "voir les
     * restants à payer" drill-down.
     */
    public function outstandingStudents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => 'nullable|integer|exists:tenant.academic_years,id',
            'class_id' => 'nullable|integer|exists:tenant.classes,id',
            'status' => 'nullable|in:unpaid,partial,not_billed,with_balance',
        ]);

        $yearId = isset($validated['academic_year_id'])
            ? (int) $validated['academic_year_id']
            : (AcademicYear::on('tenant')->where('is_active', true)->value('id') ?? AcademicYear::on('tenant')->max('id'));

        $status = $validated['status'] ?? 'with_balance';

        $invoiceAgg = Invoice::on('tenant')
            ->where('academic_year_id', $yearId)
            ->where('status', '!=', 'cancelled')
            ->select('student_id', DB::raw('SUM(total_amount) as billed'), DB::raw('SUM(paid_amount) as paid'))
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $classQuery = Classe::on('tenant')->where('academic_year_id', $yearId);
        if (! empty($validated['class_id'])) {
            $classQuery->where('id', $validated['class_id']);
        }
        $classNames = $classQuery->pluck('name', 'id');

        $students = Student::on('tenant')
            ->whereIn('class_id', $classNames->keys())
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['id', 'class_id', 'matricule', 'firstname', 'lastname']);

        $rows = [];
        foreach ($students as $student) {
            $agg = $invoiceAgg->get($student->id);
            $billed = (float) ($agg->billed ?? 0);
            $paid = (float) ($agg->paid ?? 0);
            $rowStatus = $this->statusFor($billed, $paid);

            $keep = match ($status) {
                'unpaid' => $rowStatus === 'unpaid',
                'partial' => $rowStatus === 'partial',
                'not_billed' => $rowStatus === 'not_billed',
                default => $rowStatus !== 'paid', // with_balance: everyone not fully paid
            };
            if (! $keep) {
                continue;
            }

            $rows[] = [
                'student_id' => $student->id,
                'matricule' => $student->matricule,
                'name' => trim($student->lastname.' '.$student->firstname),
                'class_name' => $classNames->get($student->class_id),
                'billed' => round($billed, 2),
                'paid' => round($paid, 2),
                'outstanding' => round(max(0, $billed - $paid), 2),
                'status' => $rowStatus,
            ];
        }

        return response()->json([
            'data' => [
                'academic_year_id' => $yearId,
                'status' => $status,
                'count' => count($rows),
                'total_outstanding' => round(array_sum(array_column($rows, 'outstanding')), 2),
                'students' => $rows,
            ],
        ]);
    }
}
