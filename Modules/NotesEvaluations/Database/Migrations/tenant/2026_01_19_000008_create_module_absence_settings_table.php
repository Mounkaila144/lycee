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
        Schema::create('module_absence_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->boolean('unjustified_grade_is_zero')->default(true);
            $table->boolean('allow_replacement_evaluation')->default(true);
            $table->integer('justification_deadline_days')->default(7);
            $table->boolean('auto_reminder_enabled')->default(true);
            $table->timestamps();

            // Unique constraint
            $table->unique('module_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_absence_settings');
    }
};
