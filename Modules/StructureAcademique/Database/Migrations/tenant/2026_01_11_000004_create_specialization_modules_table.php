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
        Schema::create('specialization_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialization_id')->constrained('specializations')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->enum('type', ['Obligatoire', 'Optionnel'])->default('Obligatoire');
            $table->unsignedInteger('capacity')->nullable(); // Places pour modules optionnels
            $table->timestamps();

            $table->unique(['specialization_id', 'module_id']);
            $table->index(['specialization_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialization_modules');
    }
};
