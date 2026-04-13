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
        Schema::create('module_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->decimal('average', 4, 2)->nullable();
            $table->boolean('is_final')->default(false);
            $table->integer('missing_evaluations_count')->default(0);
            $table->string('status')->default('Provisoire'); // Provisoire, Final, ABS
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['student_id', 'module_id', 'semester_id'], 'module_grades_unique');
            $table->index('average');
            $table->index('is_final');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_grades');
    }
};
