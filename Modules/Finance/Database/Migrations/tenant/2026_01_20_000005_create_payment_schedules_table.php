<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();

            // Facture
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            // Échéance
            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('amount', 15, 2);

            // Statut
            $table->enum('status', [
                'pending',         // En attente
                'paid',            // Payée
                'partial',         // Partiellement payée
                'overdue',         // En retard
            ])->default('pending');

            // Montant payé
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('paid_date')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
            $table->index('due_date');
            $table->index('status');
            $table->index(['invoice_id', 'installment_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
