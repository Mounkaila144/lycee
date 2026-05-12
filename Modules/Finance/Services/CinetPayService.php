<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\ParentOnlinePayment;
use Modules\UsersGuard\Entities\TenantUser;
use RuntimeException;

/**
 * Story Parent 06 — Intégration gateway CinetPay (Mobile Money + Card).
 *
 * Responsabilités :
 *   - init() : créer une transaction côté CinetPay et stocker un
 *     ParentOnlinePayment local (idempotent via transaction_id UUID).
 *   - verifyWebhookSignature() : valider la signature HMAC envoyée
 *     par CinetPay lors du callback.
 *   - handleWebhook() : appliquer la transition de statut (idempotente).
 *
 * Doc : https://docs.cinetpay.com/api/1.0-fr/
 */
class CinetPayService
{
    /**
     * Initie une transaction CinetPay pour un paiement parent.
     */
    public function init(
        TenantUser $parent,
        Student $student,
        int $invoiceId,
        float $amount,
        string $method,
        ?string $phone = null,
    ): ParentOnlinePayment {
        $payment = ParentOnlinePayment::create([
            'transaction_id' => (string) Str::uuid(),
            'parent_user_id' => $parent->id,
            'student_id' => $student->id,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => (string) config('services.cinetpay.currency'),
            'method' => $method,
            'status' => 'pending',
        ]);

        $payload = [
            'apikey' => (string) config('services.cinetpay.api_key'),
            'site_id' => (string) config('services.cinetpay.site_id'),
            'transaction_id' => $payment->transaction_id,
            'amount' => (int) round($amount),
            'currency' => $payment->currency,
            'description' => "Paiement facture #{$invoiceId} — élève #{$student->id}",
            'customer_name' => $parent->lastname ?? 'Parent',
            'customer_surname' => $parent->firstname ?? '',
            'customer_phone_number' => $phone,
            'channels' => $method === 'mobile_money' ? 'MOBILE_MONEY' : 'CREDIT_CARD',
            'return_url' => (string) config('services.cinetpay.return_url'),
            'notify_url' => (string) config('services.cinetpay.notify_url'),
        ];

        $response = Http::asJson()
            ->post(rtrim((string) config('services.cinetpay.base_url'), '/').'/v2/payment', $payload);

        if (! $response->successful()) {
            $payment->update([
                'status' => 'failed',
                'init_payload' => $payload,
                'webhook_payload' => ['init_error' => $response->body()],
            ]);
            throw new RuntimeException('CinetPay init failed: HTTP '.$response->status());
        }

        $data = $response->json('data') ?? [];

        $payment->update([
            'init_payload' => $payload,
            'cinetpay_token' => $data['payment_token'] ?? null,
            'payment_url' => $data['payment_url'] ?? null,
        ]);

        return $payment->fresh();
    }

    /**
     * Vérifie la signature HMAC envoyée par CinetPay dans le header.
     *
     * Algorithme officiel : HMAC-SHA256(secret, concat(payload sorted by key)).
     * Le header attendu est `x-token` (variantes acceptées : `X-Token`,
     * `X-CinetPay-Signature`).
     */
    public function verifyWebhookSignature(array $payload, ?string $providedToken): bool
    {
        $secret = (string) config('services.cinetpay.secret');
        if ($secret === '' || $providedToken === null || $providedToken === '') {
            return false;
        }

        // Trier alphabétiquement pour signature reproductible
        ksort($payload);
        $canonical = '';
        foreach ($payload as $k => $v) {
            $canonical .= $k.'='.(is_scalar($v) ? (string) $v : json_encode($v));
        }

        $expected = hash_hmac('sha256', $canonical, $secret);

        return hash_equals($expected, $providedToken);
    }

    /**
     * Traite un webhook CinetPay : transitionne le ParentOnlinePayment.
     *
     * Idempotence : si le payment est déjà dans un état final, on no-op
     * (renvoie l'enregistrement courant sans modification).
     */
    public function handleWebhook(array $payload): ?ParentOnlinePayment
    {
        // `transaction_id` = notre UUID interne (envoyé à CinetPay lors du init).
        // `cpm_trans_id`   = identifiant externe CinetPay (retourné dans le webhook).
        // On retrouve le ParentOnlinePayment par notre UUID — robuste à toute
        // variante de payload car le UUID est forcément présent (relayé par CinetPay).
        $transactionId = $payload['transaction_id']
            ?? $payload['cpm_trans_id']
            ?? null;

        if (! $transactionId) {
            return null;
        }

        $payment = ParentOnlinePayment::where('transaction_id', $transactionId)->first();
        if (! $payment) {
            return null;
        }

        // Idempotence : si déjà finalisé, on conserve le state (anti-double-traitement)
        if ($payment->is_final) {
            $payment->update(['webhook_payload' => $payload, 'notified_at' => now()]);

            return $payment;
        }

        $cpmStatus = strtoupper((string) ($payload['cpm_result'] ?? $payload['status'] ?? ''));
        $status = match ($cpmStatus) {
            '00', 'ACCEPTED', 'SUCCESS' => 'success',
            'REFUSED', '01' => 'refused',
            'CANCELLED', 'CANCELED' => 'cancelled',
            default => 'failed',
        };

        $payment->update([
            'status' => $status,
            'cinetpay_transaction_id' => $payload['cpm_trans_id'] ?? $payment->cinetpay_transaction_id,
            'webhook_payload' => $payload,
            'notified_at' => now(),
        ]);

        return $payment->fresh();
    }
}
