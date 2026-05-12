<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story Comptable 03 — Rapprochement bancaire.
 *
 * Crée les 4 tables prévues par le DEV-AGENT-PROMPT §A.4 :
 *   - bank_accounts                      : comptes bancaires de l'établissement
 *   - bank_transactions                  : import des relevés (CSV/OFX)
 *   - payment_bank_transaction_matches   : pivot rapprochement paiement ↔ transaction
 *   - reconciliation_periods             : périodes mensuelles de rapprochement
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('iban', 34)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2)->comment('positif = crédit, négatif = débit');
            $table->enum('status', ['pending', 'matched', 'ignored'])->default('pending');
            $table->timestamps();

            $table->index(['bank_account_id', 'transaction_date']);
            $table->index('status');
        });

        Schema::create('payment_bank_transaction_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignId('matched_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('matched_amount', 14, 2);
            $table->enum('match_type', ['auto', 'manual'])->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('payment_id');
            $table->unique(['payment_id', 'bank_transaction_id'], 'unique_payment_transaction');
        });

        Schema::create('reconciliation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('expected_balance', 14, 2);
            $table->decimal('actual_balance', 14, 2);
            $table->decimal('variance', 14, 2);
            $table->enum('status', ['open', 'in_progress', 'closed', 'variance_unresolved'])->default('open');
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['bank_account_id', 'period_start', 'period_end'], 'unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_periods');
        Schema::dropIfExists('payment_bank_transaction_matches');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
    }
};
