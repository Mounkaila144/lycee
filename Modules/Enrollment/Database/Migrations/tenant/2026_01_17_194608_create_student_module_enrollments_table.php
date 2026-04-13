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
        Schema::create('student_module_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->date('enrollment_date');
            $table->enum('status', ['Inscrit', 'Validé', 'Non validé', 'Abandonné', 'Dispensé'])->default('Inscrit');
            $table->boolean('is_optional')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: a student can only be enrolled once per module/semester
            $table->unique(['student_id', 'module_id', 'semester_id'], 'student_module_enrollment_unique');

            // Indexes for common queries
            $table->index(['student_enrollment_id', 'status']);
            $table->index(['module_id', 'semester_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_module_enrollments');
    }
};
