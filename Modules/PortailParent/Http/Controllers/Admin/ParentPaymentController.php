<?php

namespace Modules\PortailParent\Http\Controllers\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\ParentOnlinePayment;
use Modules\Finance\Services\CinetPayService;

/**
 * Story Parent 06 — Paiement en ligne d'une facture enfant (CinetPay).
 *
 * Endpoints :
 *   - POST /api/admin/parent/children/{student}/invoices/{invoice}/pay
 *           → initie une transaction CinetPay + renvoie le payment_url
 *   - GET  /api/admin/parent/payments/{paymentId}/status
 *           → retourne le statut d'un paiement initié par le parent connecté
 *
 * Ownership : ChildPolicy::payInvoices vérifie `is_financial_responsible`.
 */
class ParentPaymentController extends Controller
{
    public function __construct(private CinetPayService $cinetPay) {}

    public function initiate(Request $request, Student $student, int $invoiceId): JsonResponse
    {
        $this->ensurePolicy($request, 'payInvoices', $student);

        $validated = $request->validate([
            'method' => ['required', 'in:mobile_money,card'],
            'amount' => ['required', 'numeric', 'min:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $payment = $this->cinetPay->init(
            parent: $request->user(),
            student: $student,
            invoiceId: $invoiceId,
            amount: (float) $validated['amount'],
            method: $validated['method'],
            phone: $validated['phone'] ?? null,
        );

        return response()->json([
            'data' => [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'invoice_id' => $payment->invoice_id,
                'student_id' => $payment->student_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method,
                'status' => $payment->status,
                'payment_url' => $payment->payment_url,
            ],
        ], 201);
    }

    /**
     * Statut d'un paiement — filtre owner (parent_user_id === auth()->user()->id).
     */
    public function status(Request $request, int $paymentId): JsonResponse
    {
        $payment = ParentOnlinePayment::where('id', $paymentId)
            ->where('parent_user_id', $request->user()->id)
            ->first();

        if (! $payment) {
            return response()->json([
                'message' => 'Paiement introuvable.',
                'code' => 'PAYMENT_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'data' => [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'cinetpay_transaction_id' => $payment->cinetpay_transaction_id,
                'notified_at' => $payment->notified_at?->toIso8601String(),
            ],
        ]);
    }

    private function ensurePolicy(Request $request, string $ability, Student $student): void
    {
        if (! Gate::forUser($request->user())->allows($ability, $student)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }
}
