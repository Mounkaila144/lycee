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
        Schema::connection('tenant')->create('payslips', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('payroll_record_id')->constrained('payroll_records')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();

            // Payslip Details
            $table->string('payslip_number', 50)->unique();
            $table->date('issue_date');

            // PDF Generation
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable();

            // Digital Signature
            $table->boolean('is_digitally_signed')->default(false);
            $table->string('signature_hash')->nullable();
            $table->timestamp('signed_at')->nullable();

            // Distribution
            $table->enum('distribution_method', ['email', 'portal', 'print', 'not_sent'])->default('not_sent');
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_downloaded')->default(false);
            $table->timestamp('downloaded_at')->nullable();
            $table->integer('download_count')->default(0);

            // Employee Acknowledgment
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();

            // Status
            $table->enum('status', ['draft', 'generated', 'sent', 'delivered'])->default('draft');

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('payroll_record_id');
            $table->index('employee_id');
            $table->index('payroll_period_id');
            $table->index('payslip_number');
            $table->index('status');
            $table->index('issue_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payslips');
    }
};
