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
        Schema::connection('tenant')->create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('document_type'); // transcript_semester, transcript_global, diploma, certificate, etc.
            $table->foreignId('template_id')->nullable()->constrained('document_templates')->onDelete('set null');
            $table->string('document_number')->unique();
            $table->date('issue_date');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->onDelete('set null');
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('set null');
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->onDelete('set null');
            $table->string('pdf_path');
            $table->string('verification_code')->unique(); // QR code content
            $table->string('qr_code_path')->nullable(); // Path to QR code image
            $table->enum('status', ['draft', 'issued', 'cancelled', 'replaced'])->default('issued');
            $table->json('metadata')->nullable(); // Additional data (honors, GPA, etc.)
            $table->foreignId('issued_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('replaced_by_document_id')->nullable()->constrained('documents')->onDelete('set null');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('student_id');
            $table->index('document_type');
            $table->index('document_number');
            $table->index('verification_code');
            $table->index('status');
            $table->index('issue_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('documents');
    }
};
