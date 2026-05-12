<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story Parent 06 — Paiements en ligne CinetPay.
 *
 * Trace TOUTES les transactions initiées par un parent depuis le portail :
 *  - transaction_id (UUID interne, idempotent)
 *  - cinetpay_token / cinetpay_transaction_id (référence côté gateway)
 *  - status (pending → success / failed / cancelled)
 *  - payload du webhook (audit complet)
 *
 * Le couple `(transaction_id)` est unique → empêche le double traitement
 * d'un même webhook (idempotence).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_online_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_id')->unique()->comment('UUID interne, transmis à CinetPay');
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedBigInteger('invoice_id')->comment('Facture concernée — FK logique vers Finance');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('XOF');
            $table->enum('method', ['mobile_money', 'card']);
            $table->enum('status', ['pending', 'success', 'failed', 'cancelled', 'refused'])->default('pending');
            $table->string('cinetpay_token')->nullable()->comment('Token retourné par CinetPay::init');
            $table->string('cinetpay_transaction_id')->nullable()->comment('Code transaction CinetPay');
            $table->string('payment_url')->nullable()->comment('URL de paiement à présenter au Parent');
            $table->json('init_payload')->nullable()->comment('Payload envoyé à CinetPay');
            $table->json('webhook_payload')->nullable()->comment('Dernier payload webhook reçu');
            $table->timestamp('notified_at')->nullable()->comment('Date de réception du webhook');
            $table->timestamps();

            $table->index('parent_user_id');
            $table->index('status');
            $table->index('invoice_id');
            $table->index('cinetpay_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_online_payments');
    }
};
