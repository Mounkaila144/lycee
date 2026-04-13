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
        Schema::connection('tenant')->create('employee_advances', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Advance Details
            $table->string('advance_number', 50)->unique();
            $table->enum('advance_type', ['salary_advance', 'loan', 'emergency', 'other'])->default('salary_advance');
            $table->decimal('amount', 15, 2);
            $table->text('reason')->nullable();

            // Request
            $table->date('request_date');
            $table->foreignId('requested_by')->nullable();

            // Approval
            $table->enum('status', ['pending', 'approved', 'rejected', 'disbursed', 'repaying', 'repaid', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            // Disbursement
            $table->date('disbursement_date')->nullable();
            $table->enum('disbursement_method', ['cash', 'bank_transfer', 'check'])->nullable();
            $table->string('disbursement_reference', 100)->nullable();

            // Repayment Terms
            $table->integer('number_of_installments')->default(1);
            $table->decimal('installment_amount', 15, 2)->nullable();
            $table->date('first_deduction_date')->nullable();
            $table->decimal('total_repaid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->nullable();

            // Interest (if applicable)
            $table->boolean('has_interest')->default(false);
            $table->decimal('interest_rate', 5, 2)->nullable()->comment('Annual interest rate percentage');
            $table->decimal('total_interest', 15, 2)->default(0);

            // Completion
            $table->date('completion_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('employee_id');
            $table->index('advance_number');
            $table->index('advance_type');
            $table->index('status');
            $table->index('request_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('employee_advances');
    }
};
