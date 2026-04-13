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
        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();

            // Facture
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            // Étudiant
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Date de relance
            $table->date('reminder_date');

            // Type de relance
            $table->enum('reminder_type', [
                'first_reminder',           // Première relance
                'second_reminder',          // Deuxième relance
                'final_notice',             // Mise en demeure
                'service_block_warning',    // Avertissement blocage
            ]);

            // Statut
            $table->enum('status', [
                'pending',      // En attente
                'sent',         // Envoyée
                'failed',       // Échec d'envoi
            ])->default('pending');

            // Date d'envoi
            $table->timestamp('sent_at')->nullable();

            // Méthode d'envoi
            $table->json('send_methods')->nullable()->comment('email, sms, etc.');

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
            $table->index('student_id');
            $table->index('reminder_date');
            $table->index('reminder_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
    }
};
