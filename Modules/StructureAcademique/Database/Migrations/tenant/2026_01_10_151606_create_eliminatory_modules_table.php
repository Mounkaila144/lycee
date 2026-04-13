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
        Schema::connection('tenant')->create('eliminatory_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')
                ->constrained('programmes')
                ->onDelete('cascade');
            $table->foreignId('module_id')
                ->constrained('modules')
                ->onDelete('cascade');
            $table->enum('level', ['L1', 'L2', 'L3', 'M1', 'M2']);
            $table->timestamps();

            // Contraintes
            $table->unique(['programme_id', 'module_id', 'level'], 'unique_eliminatory_module');
            $table->index(['programme_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('eliminatory_modules');
    }
};
