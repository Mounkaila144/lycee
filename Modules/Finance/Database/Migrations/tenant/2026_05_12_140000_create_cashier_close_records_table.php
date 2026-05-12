<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story Caissier 05 — Clôture caisse journalière.
 *
 * Trace chaque clôture de caisse effectuée par un Caissier en fin de journée :
 * total déclaré, total système, écart, statut (clos / écart à investiguer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_close_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Caissier qui clôture');
            $table->date('close_date')->comment('Journée clôturée');
            $table->decimal('total_cash_declared', 12, 2)->default(0)->comment('Montant espèces déclaré par le caissier');
            $table->decimal('total_cash_system', 12, 2)->default(0)->comment('Montant calculé par le système');
            $table->decimal('total_cheque', 12, 2)->default(0);
            $table->decimal('total_mobile_money', 12, 2)->default(0);
            $table->decimal('total_card', 12, 2)->default(0);
            $table->decimal('total_transfer', 12, 2)->default(0);
            $table->decimal('variance', 12, 2)->default(0)->comment('declared - system');
            $table->enum('status', ['closed', 'variance_pending', 'variance_resolved'])->default('closed');
            $table->text('notes')->nullable();
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->unique(['cashier_user_id', 'close_date'], 'unique_cashier_close_date');
            $table->index('close_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_close_records');
    }
};
