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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Étudiant
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Année académique
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();

            // Numéro de facture
            $table->string('invoice_number', 100)->unique();

            // Dates
            $table->date('invoice_date');
            $table->date('due_date');

            // Montants
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);

            // Statut
            $table->enum('status', [
                'draft',           // Brouillon
                'pending',         // En attente de paiement
                'partial',         // Partiellement payée
                'paid',            // Payée
                'overdue',         // En retard
                'cancelled',       // Annulée
            ])->default('draft');

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('invoice_number');
            $table->index('student_id');
            $table->index('academic_year_id');
            $table->index('status');
            $table->index('due_date');
            $table->index(['student_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
