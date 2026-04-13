<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        Schema::create('pedagogical_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('program_id')->constrained('programmes')->onDelete('cascade');
            $table->string('level', 10); // L1, L2, L3, M1, M2
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('set null');
            $table->enum('status', ['Draft', 'Complete', 'Pending', 'Validated', 'Rejected', 'Cancelled'])->default('Draft');
            $table->integer('total_modules')->default(0);
            $table->integer('total_ects')->default(0);
            $table->boolean('modules_check')->default(false);
            $table->boolean('groups_check')->default(false);
            $table->boolean('options_check')->default(false);
            $table->boolean('prerequisites_check')->default(false);
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('contract_pdf_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'academic_year_id', 'semester_id'], 'ped_enrollment_unique');
            $table->index(['status', 'academic_year_id']);
            $table->index(['program_id', 'level']);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('pedagogical_enrollments');
    }
};
