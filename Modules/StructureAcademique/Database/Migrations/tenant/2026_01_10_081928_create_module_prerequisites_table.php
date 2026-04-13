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
        Schema::connection('tenant')->create('module_prerequisites', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('module_id')
                ->constrained('modules')
                ->onDelete('cascade');

            $table->foreignId('prerequisite_module_id')
                ->constrained('modules')
                ->onDelete('cascade');

            // Type de prérequis
            $table->enum('type', ['Strict', 'Recommandé'])->default('Strict');

            $table->timestamps();

            // Contrainte d'unicité
            $table->unique(['module_id', 'prerequisite_module_id'], 'unique_module_prerequisite');

            // Indexes pour performance
            $table->index('module_id');
            $table->index('prerequisite_module_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('module_prerequisites');
    }
};
