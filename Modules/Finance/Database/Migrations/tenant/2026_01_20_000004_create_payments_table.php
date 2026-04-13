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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Facture
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            // Étudiant
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Date et montant
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);

            // Méthode de paiement
            $table->enum('payment_method', [
                'cash',
                'check',
                'bank_transfer',
                'card',
                'online',
                'mobile_money',
            ]);

            // Référence
            $table->string('reference_number', 100)->nullable();

            // Reçu
            $table->string('receipt_number', 100)->unique();

            // Notes
            $table->text('notes')->nullable();

            // Enregistré par
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('invoice_id');
            $table->index('student_id');
            $table->index('receipt_number');
            $table->index('payment_date');
            $table->index('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
