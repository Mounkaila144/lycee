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
        Schema::create('service_blocks', function (Blueprint $table) {
            $table->id();

            // Étudiant
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Type de blocage
            $table->enum('block_type', [
                'enrollment',       // Inscription pédagogique
                'exam_access',      // Accès aux examens
                'documents',        // Délivrance de documents
                'reenrollment',     // Réinscription
                'all',              // Tous les services
            ]);

            // Raison
            $table->text('reason');

            // Dates
            $table->timestamp('blocked_at');
            $table->timestamp('unblocked_at')->nullable();

            // Statut actif
            $table->boolean('is_active')->default(true);

            // Bloqué par / débloqué par
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unblocked_by')->nullable()->constrained('users')->nullOnDelete();

            // Factures liées
            $table->json('related_invoices')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('student_id');
            $table->index('block_type');
            $table->index('is_active');
            $table->index(['student_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_blocks');
    }
};
