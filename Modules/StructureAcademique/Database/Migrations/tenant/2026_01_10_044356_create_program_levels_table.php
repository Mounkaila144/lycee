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
        Schema::create('program_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')
                ->constrained('programmes')
                ->onDelete('cascade');
            $table->enum('level', ['L1', 'L2', 'L3', 'M1', 'M2']);
            $table->timestamps();

            // Unique constraint: un programme ne peut avoir le même niveau qu'une seule fois
            $table->unique(['program_id', 'level']);

            // Index pour améliorer les performances de recherche
            $table->index('program_id');
            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_levels');
    }
};
