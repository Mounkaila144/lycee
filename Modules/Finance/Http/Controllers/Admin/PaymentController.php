<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Finance\Entities\Discount;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\Payment;
use Modules\Finance\Services\BillingService;
use Modules\Finance\Services\PaymentService;

/**
 * Payment Controller
 * Handles Epic 2: Encaissement (Stories 07-12)
 * Also includes Epic 1, Story 06: Scholarships/discounts
 */
class PaymentController extends Controller
{
    /**
     * Columns of the recording user exposed in payment payloads (no tokens / secrets).
     */
    private const RECORDED_BY_COLUMNS = 'id,username,firstname,lastname';

    /**
     * @var list<string>
     */
    private const SORTABLE_COLUMNS = ['payment_date', 'amount', 'receipt_number', 'created_at', 'id'];

    public function __construct(
        private PaymentService $paymentService,
        private BillingService $billingService
    ) {}

    /**
     * List payments with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::on('tenant')
            ->with(['invoice', 'student', 'recordedBy:'.self::RECORDED_BY_COLUMNS]);

        // Filters
        if ($invoiceId = $request->input('invoice_id')) {
            $query->where('invoice_id', $invoiceId);
        }

        if ($studentId = $request->input('student_id')) {
            $query->byStudent($studentId);
        }

        if ($method = $request->input('payment_method')) {
            $query->byMethod($method);
        }

        if ($startDate = $request->input('start_date')) {
            $endDate = $request->input('end_date', now());
            $query->dateRange(Carbon::parse($startDate), Carbon::parse($endDate));
        }

        // Search by receipt / reference number, or by student matricule / name
        if ($search = $request->input('search')) {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search) {
                        $studentQuery->where('matricule', 'like', "%{$search}%")
                            ->orWhere('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->boolean('mine')) {
            $query->where('recorded_by', $request->user()?->id);
        }

        // Sort (whitelist to avoid SQL errors on unknown columns)
        $sortBy = in_array($request->input('sort_by'), self::SORTABLE_COLUMNS, true)
            ? $request->input('sort_by')
            : 'payment_date';
        $sortOrder = strtolower((string) $request->input('sort_order')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder)->orderBy('id', $sortOrder);

        $perPage = $request->input('per_page', 15);
        $payments = $query->paginate($perPage);

        return response()->json([
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Story 07 & 08: Record a payment
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:tenant.invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,check,bank_transfer,card,online,mobile_money',
            'payment_date' => 'nullable|date|before_or_equal:today',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invoice = Invoice::on('tenant')->findOrFail($request->invoice_id);
        $paymentDate = $request->payment_date ? Carbon::parse($request->payment_date) : null;

        try {
            $payment = $this->paymentService->recordPayment(
                $invoice,
                $request->amount,
                $request->payment_method,
                $paymentDate,
                $request->only(['reference_number', 'notes'])
            );

            return response()->json([
                'message' => 'Paiement enregistré avec succès',
                'data' => $payment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Story 10: Record partial payment
     */
    public function recordPartial(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Story 11: Process refund
     */
    public function refund(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payment = Payment::on('tenant')->findOrFail($id);

        try {
            $refund = $this->paymentService->processRefund(
                $payment,
                $request->amount,
                $request->reason
            );

            return response()->json([
                'message' => 'Remboursement effectué avec succès',
                'data' => $refund,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Show payment details
     */
    public function show(int $id): JsonResponse
    {
        $payment = Payment::on('tenant')
            ->with(['invoice.items', 'student', 'recordedBy:'.self::RECORDED_BY_COLUMNS])
            ->findOrFail($id);

        return response()->json(['data' => $payment]);
    }

    /**
     * Story 09: Get receipt data for PDF generation
     */
    public function getReceipt(int $id): JsonResponse
    {
        $payment = Payment::on('tenant')
            ->with(['invoice', 'student', 'recordedBy:'.self::RECORDED_BY_COLUMNS])
            ->findOrFail($id);

        $receiptData = $this->paymentService->generateReceiptData($payment);

        return response()->json(['data' => $receiptData]);
    }

    /**
     * Story 12: Get payments for bank reconciliation
     */
    public function reconciliation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'payment_method' => 'nullable|in:cash,check,bank_transfer,card,online,mobile_money',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->paymentService->getPaymentsForReconciliation(
            Carbon::parse($request->start_date),
            Carbon::parse($request->end_date),
            $request->payment_method
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Get daily payment summary
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->input('date') ? Carbon::parse($request->date) : now();

        $summary = $this->paymentService->getDailySummary($date);

        return response()->json(['data' => $summary]);
    }

    /**
     * Story 06: Apply discount/scholarship
     */
    public function applyDiscount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:tenant.students,id',
            'type' => 'required|in:scholarship,merit,sibling,early_payment,special',
            'percentage' => 'required_without:amount|nullable|numeric|min:0|max:100',
            'amount' => 'required_without:percentage|nullable|numeric|min:0',
            'fee_type_id' => 'nullable|exists:tenant.fee_types,id',
            'reason' => 'nullable|string',
            'valid_from' => 'required|date',
            'valid_until' => 'nullable|date|after:valid_from',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $student = \Modules\Enrollment\Entities\Student::on('tenant')->findOrFail($request->student_id);

        try {
            $discount = $this->billingService->applyDiscount(
                $student,
                $request->type,
                $request->percentage,
                $request->amount,
                $request->only(['fee_type_id', 'reason', 'valid_from', 'valid_until'])
            );

            return response()->json([
                'message' => 'Remise appliquée avec succès',
                'data' => $discount,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * List discounts
     */
    public function discounts(Request $request): JsonResponse
    {
        $query = Discount::on('tenant')
            ->with(['student', 'feeType', 'approvedBy']);

        if ($studentId = $request->input('student_id')) {
            $query->byStudent($studentId);
        }

        if ($type = $request->input('type')) {
            $query->byType($type);
        }

        if ($request->boolean('valid_only')) {
            $query->valid();
        }

        if ($request->boolean('approved_only')) {
            $query->approved();
        }

        $perPage = $request->input('per_page', 15);
        $discounts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $discounts->items(),
            'meta' => [
                'current_page' => $discounts->currentPage(),
                'last_page' => $discounts->lastPage(),
                'per_page' => $discounts->perPage(),
                'total' => $discounts->total(),
            ],
        ]);
    }

    /**
     * Approve discount
     */
    public function approveDiscount(int $id): JsonResponse
    {
        $discount = Discount::on('tenant')->findOrFail($id);

        if ($discount->isApproved()) {
            return response()->json(['message' => 'Cette remise est déjà approuvée'], 422);
        }

        $discount->update([
            'approved_by' => auth('tenant')->id() ?? auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Remise approuvée avec succès',
            'data' => $discount->fresh(),
        ]);
    }

    /**
     * Revoke discount
     */
    public function revokeDiscount(int $id): JsonResponse
    {
        $discount = Discount::on('tenant')->findOrFail($id);
        $discount->delete();

        return response()->json(['message' => 'Remise révoquée avec succès'], 204);
    }
}
