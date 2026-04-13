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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();

            // Étudiant
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Type de frais (optionnel - peut s'appliquer à tous)
            $table->foreignId('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();

            // Type de remise
            $table->enum('type', [
                'scholarship',      // Bourse
                'merit',           // Mérite académique
                'sibling',         // Fratrie
                'early_payment',   // Paiement anticipé
                'special',         // Remise spéciale
            ]);

            // Montant de la remise
            $table->decimal('percentage', 5, 2)->nullable()->comment('Pourcentage de remise (0-100)');
            $table->decimal('amount', 15, 2)->nullable()->comment('Montant fixe de remise');

            // Raison
            $table->text('reason')->nullable();

            // Période de validité
            $table->date('valid_from');
            $table->date('valid_until')->nullable();

            // Approbation
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('student_id');
            $table->index('fee_type_id');
            $table->index('type');
            $table->index(['valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
