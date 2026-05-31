<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-year class enrollment history for secondary school.
 *
 * `students.class_id` still holds the CURRENT class (denormalized for
 * convenience); this table records one row per student per academic year so
 * the school can track the full path (6e → 5e → 4e…), repeats and promotions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_class_enrollments')) {
            return;
        }

        Schema::create('student_class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->enum('enrollment_type', ['initial', 'promotion', 'repeat'])->default('initial');
            $table->date('enrolled_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // One enrollment per student per academic year.
            $table->unique(['student_id', 'academic_year_id']);
            $table->index(['academic_year_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class_enrollments');
    }
};
