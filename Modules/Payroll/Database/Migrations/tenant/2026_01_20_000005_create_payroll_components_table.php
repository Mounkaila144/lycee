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
        Schema::connection('tenant')->create('payroll_components', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();

            // Component Identification
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();

            // Component Type
            $table->enum('component_type', [
                'bonus', // Prime
                'allowance', // Indemnité
                'deduction', // Retenue
                'overtime', // Heures supplémentaires
                'commission', // Commission
                'benefit', // Avantage en nature
                'reimbursement', // Remboursement
            ]);

            // Category (for specific bonus/deduction types)
            $table->string('category', 100)->nullable()->comment('transport, housing, meal, etc.');

            // Calculation
            $table->enum('calculation_type', ['fixed', 'percentage', 'hourly', 'daily'])->default('fixed');
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable()->comment('Percentage of base salary');
            $table->decimal('rate', 15, 2)->nullable()->comment('Hourly/daily rate');

            // Tax Settings
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_subject_to_cnss')->default(true);
            $table->boolean('is_subject_to_cimr')->default(false)->comment('Caisse Interprofessionnelle Marocaine de Retraite');

            // Recurrence
            $table->enum('frequency', ['one_time', 'monthly', 'quarterly', 'annual'])->default('monthly');
            $table->boolean('is_recurring')->default(false);

            // Validity Period
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // Approval
            $table->enum('status', ['draft', 'active', 'inactive'])->default('active');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('employee_id');
            $table->index('code');
            $table->index('component_type');
            $table->index('status');
            $table->index(['valid_from', 'valid_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payroll_components');
    }
};
