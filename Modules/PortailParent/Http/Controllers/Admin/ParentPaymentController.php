<?php

namespace Modules\PortailParent\Http\Controllers\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Enrollment\Entities\Student;

/**
 * Story Parent 06 — Paiement en ligne d'une facture enfant (CinetPay).
 *
 * Scaffold V2 : la gateway CinetPay (init transaction, webhooks, idempotence)
 * sera implémentée dans un Service dédié `CinetPayService`. Ce contrôleur
 * pose la signature des 2 endpoints prévus par la story + ChildPolicy::payInvoices.
 */
class ParentPaymentController extends Controller
{
    /**
     * Initie un paiement en ligne pour une facture de l'enfant.
     *
     * Returns 200 + payment_id + redirect_url (vers CinetPay).
     */
    public function initiate(Request $request, Student $student, int $invoiceId): JsonResponse
    {
        $this->ensurePolicy($request, 'payInvoices', $student);

        $validated = $request->validate([
            'method' => ['required', 'in:mobile_money,card'],
            'phone' => ['nullable', 'string', 'max:30'],
            'return_url' => ['nullable', 'url'],
        ]);

        // V2 : appel CinetPayService::initTransaction()
        return response()->json([
            'data' => [
                'payment_id' => null,
                'invoice_id' => $invoiceId,
                'student_id' => $student->id,
                'redirect_url' => null,
                'status' => 'pending',
                'method' => $validated['method'],
            ],
            'meta' => [
                'note' => 'Scaffold V2 — intégration CinetPay (init + webhooks + idempotence) à implémenter dans Modules/Finance/Services/CinetPayService.',
            ],
        ]);
    }

    /**
     * Vérifie le statut d'un paiement initié par le Parent.
     */
    public function status(Request $request, int $paymentId): JsonResponse
    {
        // V2 : vérifier que payment.parent_user_id === auth()->user()->id
        return response()->json([
            'data' => [
                'payment_id' => $paymentId,
                'status' => 'unknown',
            ],
            'meta' => [
                'note' => 'Scaffold V2 — récupération status via CinetPayService::checkTransaction().',
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
