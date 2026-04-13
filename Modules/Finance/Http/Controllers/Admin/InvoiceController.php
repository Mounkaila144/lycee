<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\FeeType;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Services\BillingService;
use Modules\StructureAcademique\Entities\AcademicYear;

/**
 * Invoice Controller
 * Handles Epic 1: Facturation (Stories 01-06)
 */
class InvoiceController extends Controller
{
    public function __construct(
        private BillingService $billingService
    ) {}

    /**
     * List invoices with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::on('tenant')
            ->with(['student', 'items.feeType', 'academicYear', 'payments']);

        // Filters
        if ($studentId = $request->input('student_id')) {
            $query->byStudent($studentId);
        }

        if ($academicYearId = $request->input('academic_year_id')) {
            $query->byAcademicYear($academicYearId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->has('overdue') && $request->boolean('overdue')) {
            $query->overdue();
        }

        // Search by invoice number
        if ($search = $request->input('search')) {
            $query->where('invoice_number', 'like', "%{$search}%");
        }

        // Sort
        $sortBy = $request->input('sort_by', 'invoice_date');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        $invoices = $query->paginate($perPage);

        return response()->json([
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Story 01: Generate automated invoice
     */
    public function generateAutomated(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'fee_type_ids' => 'nullable|array',
            'fee_type_ids.*' => 'exists:tenant.fee_types,id',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date|after:invoice_date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $student = Student::on('tenant')->findOrFail($request->student_id);
        $academicYear = AcademicYear::on('tenant')->findOrFail($request->academic_year_id);

        $invoice = $this->billingService->generateAutomatedInvoice(
            $student,
            $academicYear,
            $request->fee_type_ids ?? [],
            $request->only(['invoice_date', 'due_date', 'notes'])
        );

        return response()->json([
            'message' => 'Facture générée avec succès',
            'data' => $invoice,
        ], 201);
    }

    /**
     * Story 03: Create custom invoice
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'items' => 'required|array|min:1',
            'items.*.fee_type_id' => 'nullable|exists:tenant.fee_types,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $student = Student::on('tenant')->findOrFail($request->student_id);
        $academicYear = AcademicYear::on('tenant')->findOrFail($request->academic_year_id);

        $invoice = $this->billingService->createCustomInvoice(
            $student,
            $academicYear,
            $request->items,
            $request->only(['invoice_date', 'due_date', 'notes'])
        );

        return response()->json([
            'message' => 'Facture créée avec succès',
            'data' => $invoice,
        ], 201);
    }

    /**
     * Show invoice details
     */
    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::on('tenant')
            ->with(['student', 'items.feeType', 'academicYear', 'payments', 'paymentSchedules', 'reminders'])
            ->findOrFail($id);

        return response()->json(['data' => $invoice]);
    }

    /**
     * Update invoice
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:draft,pending,partial,paid,overdue,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invoice = Invoice::on('tenant')->findOrFail($id);
        $invoice->update($request->only(['due_date', 'notes', 'status']));

        return response()->json([
            'message' => 'Facture mise à jour avec succès',
            'data' => $invoice->fresh(),
        ]);
    }

    /**
     * Delete invoice
     */
    public function destroy(int $id): JsonResponse
    {
        $invoice = Invoice::on('tenant')->findOrFail($id);

        if ($invoice->paid_amount > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer une facture avec des paiements',
            ], 422);
        }

        $invoice->delete();

        return response()->json(['message' => 'Facture supprimée avec succès'], 204);
    }

    /**
     * Story 04: Create payment schedule
     */
    public function createPaymentSchedule(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'number_of_installments' => 'required|integer|min:2|max:'.config('finance.collection.max_installments', 12),
            'first_due_date' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invoice = Invoice::on('tenant')->findOrFail($id);

        $firstDueDate = $request->first_due_date ? Carbon::parse($request->first_due_date) : null;

        $schedules = $this->billingService->createPaymentSchedule(
            $invoice,
            $request->number_of_installments,
            $firstDueDate
        );

        return response()->json([
            'message' => 'Échéancier créé avec succès',
            'data' => $schedules,
        ], 201);
    }

    /**
     * Story 05: Calculate late fees
     */
    public function calculateLateFees(int $id): JsonResponse
    {
        $invoice = Invoice::on('tenant')->findOrFail($id);

        $lateFees = $this->billingService->calculateLateFees($invoice);

        return response()->json([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'balance' => $invoice->balance,
            'due_date' => $invoice->due_date->format('d/m/Y'),
            'days_overdue' => $invoice->isOverdue() ? $invoice->due_date->diffInDays(now()) : 0,
            'late_fees' => $lateFees,
        ]);
    }

    /**
     * Story 02: Get fee types
     */
    public function getFeeTypes(Request $request): JsonResponse
    {
        $query = FeeType::on('tenant');

        if ($category = $request->input('category')) {
            $query->byCategory($category);
        }

        if ($request->boolean('mandatory_only')) {
            $query->mandatory();
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $feeTypes = $query->orderBy('name')->get();

        return response()->json(['data' => $feeTypes]);
    }

    /**
     * Story 02: Manage fee types
     */
    public function storeFeeType(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:tenant.fee_types,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'default_amount' => 'required|numeric|min:0',
            'category' => 'required|in:tuition,registration,exam,library,lab,sports,insurance,card,other',
            'is_mandatory' => 'boolean',
            'applies_to' => 'nullable|array',
            'academic_year_id' => 'nullable|exists:tenant.academic_years,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $feeType = FeeType::on('tenant')->create($request->all());

        return response()->json([
            'message' => 'Type de frais créé avec succès',
            'data' => $feeType,
        ], 201);
    }

    /**
     * Update fee type
     */
    public function updateFeeType(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|unique:tenant.fee_types,code,'.$id,
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'default_amount' => 'sometimes|numeric|min:0',
            'category' => 'sometimes|in:tuition,registration,exam,library,lab,sports,insurance,card,other',
            'is_mandatory' => 'boolean',
            'applies_to' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $feeType = FeeType::on('tenant')->findOrFail($id);
        $feeType->update($request->all());

        return response()->json([
            'message' => 'Type de frais mis à jour avec succès',
            'data' => $feeType,
        ]);
    }
}
