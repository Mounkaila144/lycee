<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\Services\CinetPayService;

/**
 * Story Parent 06 — Webhook public CinetPay.
 *
 * Endpoint NON authentifié (CinetPay POST direct). Sécurité = signature HMAC
 * vérifiée par CinetPayService::verifyWebhookSignature.
 *
 * Idempotence : un webhook reçu plusieurs fois sur le même transaction_id
 * ne re-déclenche pas la transition (état final préservé).
 */
class CinetPayWebhookController extends Controller
{
    public function __construct(private CinetPayService $cinetPay) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        $token = $request->header('x-token')
            ?? $request->header('X-Token')
            ?? $request->header('X-CinetPay-Signature');

        if (! $this->cinetPay->verifyWebhookSignature($payload, $token)) {
            return response()->json([
                'message' => 'Invalid signature.',
                'code' => 'CINETPAY_INVALID_SIGNATURE',
            ], 401);
        }

        $payment = $this->cinetPay->handleWebhook($payload);

        if (! $payment) {
            return response()->json([
                'message' => 'Transaction inconnue.',
                'code' => 'CINETPAY_UNKNOWN_TRANSACTION',
            ], 404);
        }

        return response()->json([
            'message' => 'Webhook traité.',
            'data' => [
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
            ],
        ]);
    }
}
