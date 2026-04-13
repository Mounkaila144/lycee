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
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Montant
            $table->decimal('default_amount', 15, 2)->default(0);

            // Catégorie
            $table->enum('category', [
                'tuition',
                'registration',
                'exam',
                'library',
                'lab',
                'sports',
                'insurance',
                'card',
                'other',
            ])->default('other');

            // Paramètres
            $table->boolean('is_mandatory')->default(false);
            $table->json('applies_to')->nullable()->comment('Conditions d\'application: programmes, niveaux, etc.');

            // Année académique (optionnel - si le frais est spécifique à une année)
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index('category');
            $table->index('academic_year_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_types');
    }
};
