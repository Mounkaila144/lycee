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
        Schema::create('reenrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('reenrollment_campaigns')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('previous_enrollment_id')->nullable()->constrained('pedagogical_enrollments')->onDelete('set null');
            $table->string('previous_level', 10);
            $table->string('target_level', 10);
            $table->foreignId('target_program_id')->constrained('programmes')->onDelete('cascade');
            $table->boolean('is_redoing')->default(false);
            $table->boolean('is_reorientation')->default(false);
            $table->json('personal_data_updates')->nullable();
            $table->json('uploaded_documents')->nullable();
            $table->boolean('has_accepted_rules')->default(false);
            $table->enum('eligibility_status', ['Eligible', 'Not_Eligible', 'Pending'])->default('Pending');
            $table->text('eligibility_notes')->nullable();
            $table->enum('status', ['Draft', 'Submitted', 'Validated', 'Rejected'])->default('Draft');
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('confirmation_pdf_path')->nullable();
            $table->foreignId('new_enrollment_id')->nullable()->constrained('pedagogical_enrollments')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['campaign_id', 'student_id']);
            $table->index('status');
            $table->index('eligibility_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reenrollments');
    }
};
