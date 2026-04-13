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
        Schema::create('recalculation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('trigger', [
                'retake_grades_published',
                'grade_correction',
                'manual_recalculation',
                'compensation_applied',
            ]);
            $table->decimal('old_module_average', 5, 2)->nullable();
            $table->decimal('new_module_average', 5, 2)->nullable();
            $table->decimal('old_semester_average', 5, 2)->nullable();
            $table->decimal('new_semester_average', 5, 2)->nullable();
            $table->string('old_module_status')->nullable();
            $table->string('new_module_status')->nullable();
            $table->string('old_semester_status')->nullable();
            $table->string('new_semester_status')->nullable();
            $table->integer('credits_before')->nullable();
            $table->integer('credits_after')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('recalculated_at');
            $table->timestamps();

            // Index
            $table->index(['student_id', 'semester_id']);
            $table->index('recalculated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recalculation_logs');
    }
};
