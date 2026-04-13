<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\PaymentReminder;
use Modules\Finance\Entities\ServiceBlock;
use Modules\Finance\Services\CollectionService;

/**
 * Collection Controller
 * Handles Epic 3: Recouvrement (Stories 13-16)
 */
class CollectionController extends Controller
{
    public function __construct(
        private CollectionService $collectionService
    ) {}

    /**
     * Story 13: Generate automatic reminders
     */
    public function generateReminders(): JsonResponse
    {
        $result = $this->collectionService->generateAutomaticReminders();

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'reminders_count' => count($result['reminders']),
                'reminders' => $result['reminders'],
            ],
        ]);
    }

    /**
     * Send pending reminders
     */
    public function sendReminders(): JsonResponse
    {
        $result = $this->collectionService->sendPendingReminders();

        return response()->json([
            'message' => "Relances envoyées: {$result['sent']}, Échecs: {$result['failed']}",
            'data' => $result,
        ]);
    }

    /**
     * List payment reminders
     */
    public function reminders(Request $request): JsonResponse
    {
        $query = PaymentReminder::on('tenant')
            ->with(['invoice', 'student']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('reminder_type')) {
            $query->byType($type);
        }

        if ($request->boolean('due_today')) {
            $query->dueToday();
        }

        $perPage = $request->input('per_page', 15);
        $reminders = $query->orderBy('reminder_date', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $reminders->items(),
            'meta' => [
                'current_page' => $reminders->currentPage(),
                'last_page' => $reminders->lastPage(),
                'per_page' => $reminders->perPage(),
                'total' => $reminders->total(),
            ],
        ]);
    }

    /**
     * Story 14: Block student services
     */
    public function blockServices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:tenant.students,id',
            'block_type' => 'required|in:enrollment,exam_access,documents,reenrollment,all',
            'reason' => 'required|string',
            'related_invoice_ids' => 'nullable|array',
            'related_invoice_ids.*' => 'exists:tenant.invoices,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $block = $this->collectionService->blockStudentServices(
                $request->student_id,
                $request->block_type,
                $request->reason,
                $request->related_invoice_ids ?? []
            );

            return response()->json([
                'message' => 'Services bloqués avec succès',
                'data' => $block,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Unblock student services
     */
    public function unblockServices(int $id): JsonResponse
    {
        $block = ServiceBlock::on('tenant')->findOrFail($id);

        $unblocked = $this->collectionService->unblockStudentServices($block);

        return response()->json([
            'message' => 'Services débloqués avec succès',
            'data' => $unblocked,
        ]);
    }

    /**
     * List service blocks
     */
    public function blocks(Request $request): JsonResponse
    {
        $query = ServiceBlock::on('tenant')
            ->with(['student', 'blockedBy', 'unblockedBy']);

        if ($studentId = $request->input('student_id')) {
            $query->byStudent($studentId);
        }

        if ($blockType = $request->input('block_type')) {
            $query->byType($blockType);
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $perPage = $request->input('per_page', 15);
        $blocks = $query->orderBy('blocked_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $blocks->items(),
            'meta' => [
                'current_page' => $blocks->currentPage(),
                'last_page' => $blocks->lastPage(),
                'per_page' => $blocks->perPage(),
                'total' => $blocks->total(),
            ],
        ]);
    }

    /**
     * Check if student has active blocks
     */
    public function checkBlocks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:tenant.students,id',
            'block_type' => 'nullable|in:enrollment,exam_access,documents,reenrollment,all',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->collectionService->checkStudentBlocks(
            $request->student_id,
            $request->block_type
        );

        return response()->json(['data' => $result]);
    }

    /**
     * Process automatic blocking based on debt
     */
    public function processAutomaticBlocking(): JsonResponse
    {
        $result = $this->collectionService->processAutomaticBlocking();

        return response()->json([
            'message' => "{$result['blocked_count']} étudiants bloqués automatiquement",
            'data' => $result,
        ]);
    }

    /**
     * Story 15: Create payment plan
     */
    public function createPaymentPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:tenant.invoices,id',
            'number_of_installments' => 'required|integer|min:2|max:'.config('finance.collection.max_installments', 12),
            'first_due_date' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invoice = Invoice::on('tenant')->findOrFail($request->invoice_id);
        $firstDueDate = $request->first_due_date ? Carbon::parse($request->first_due_date) : null;

        try {
            $schedules = $this->collectionService->createPaymentPlan(
                $invoice,
                $request->number_of_installments,
                $firstDueDate
            );

            return response()->json([
                'message' => 'Plan de paiement créé avec succès',
                'data' => $schedules,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Story 16: Write off bad debt
     */
    public function writeOffDebt(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invoice = Invoice::on('tenant')->findOrFail($id);

        try {
            $updated = $this->collectionService->writeOffBadDebt($invoice, $request->reason);

            return response()->json([
                'message' => 'Créance irrécouvrable enregistrée avec succès',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get collection statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->start_date) : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->end_date) : null;

        $stats = $this->collectionService->getCollectionStatistics($startDate, $endDate);

        return response()->json(['data' => $stats]);
    }
}
