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
        Schema::create('module_exemptions', function (Blueprint $table) {
            $table->id();
            $table->string('exemption_number', 50)->unique();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->enum('exemption_type', ['Full', 'Partial', 'Exemption']);
            $table->enum('reason_category', [
                'VAE',
                'Prior_Training',
                'Professional_Certification',
                'Special_Situation',
                'Double_Degree',
                'Other',
            ]);
            $table->text('reason_details');
            $table->json('uploaded_documents')->nullable();
            $table->enum('status', [
                'Pending',
                'Under_Review',
                'Approved',
                'Partially_Approved',
                'Rejected',
                'Revoked',
            ])->default('Pending');
            $table->foreignId('reviewed_by_teacher')->nullable()->constrained('users')->onDelete('set null');
            $table->text('teacher_opinion')->nullable();
            $table->timestamp('teacher_reviewed_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('validation_notes')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('grants_ects')->default(false);
            $table->integer('ects_granted')->default(0);
            $table->decimal('grade_granted', 5, 2)->nullable();
            $table->string('certificate_path')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'module_id', 'academic_year_id'], 'exemption_unique');
            $table->index('exemption_number');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_exemptions');
    }
};
