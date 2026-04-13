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
        Schema::create('module_programs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('module_id')
                ->constrained('modules')
                ->onDelete('cascade');

            $table->foreignId('programme_id')
                ->constrained('programmes')
                ->onDelete('cascade');

            $table->timestamps();

            // Contrainte d'unicité pour éviter les doublons
            $table->unique(['module_id', 'programme_id']);

            // Indexes pour performance
            $table->index('module_id');
            $table->index('programme_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_programs');
    }
};
