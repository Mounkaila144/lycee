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
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained('programmes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->string('level', 10); // L1, L2, L3, M1, M2
            $table->unsignedBigInteger('group_id')->nullable(); // Optional group assignment
            $table->date('enrollment_date');
            $table->enum('status', ['Actif', 'Suspendu', 'Annulé', 'Terminé'])->default('Actif');
            $table->text('notes')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: a student can only have one enrollment per programme/academic_year/semester
            $table->unique(['student_id', 'programme_id', 'academic_year_id', 'semester_id'], 'student_enrollment_unique');

            // Indexes for common queries
            $table->index(['programme_id', 'level', 'semester_id']);
            $table->index(['academic_year_id', 'semester_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
