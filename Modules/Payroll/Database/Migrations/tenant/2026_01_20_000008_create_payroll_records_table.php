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
        Schema::connection('tenant')->create('payroll_records', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('employment_contracts')->nullOnDelete();

            // Basic Salary
            $table->decimal('base_salary', 15, 2)->comment('Salaire de base');
            $table->integer('days_worked')->default(0);
            $table->integer('days_absent')->default(0);
            $table->decimal('hours_worked', 10, 2)->default(0);
            $table->decimal('overtime_hours', 10, 2)->default(0);

            // Earnings (Gains)
            $table->decimal('bonuses', 15, 2)->default(0)->comment('Total des primes');
            $table->decimal('allowances', 15, 2)->default(0)->comment('Total des indemnités');
            $table->decimal('overtime_pay', 15, 2)->default(0)->comment('Heures supplémentaires');
            $table->decimal('commissions', 15, 2)->default(0);
            $table->decimal('other_earnings', 15, 2)->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0);

            // Gross Salary
            $table->decimal('gross_salary', 15, 2)->comment('Salaire brut total');

            // Employee Deductions (Retenues salariales)
            $table->decimal('cnss_employee', 15, 2)->default(0)->comment('Part salariale CNSS');
            $table->decimal('cimr_employee', 15, 2)->default(0)->comment('Part salariale CIMR');
            $table->decimal('amo_employee', 15, 2)->default(0)->comment('Assurance Maladie Obligatoire');
            $table->decimal('income_tax', 15, 2)->default(0)->comment('Impôt sur le revenu (IR)');
            $table->decimal('advance_deductions', 15, 2)->default(0)->comment('Remboursement avances');
            $table->decimal('loan_deductions', 15, 2)->default(0)->comment('Remboursement prêts');
            $table->decimal('other_deductions', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);

            // Net Salary
            $table->decimal('net_salary', 15, 2)->comment('Salaire net à payer');
            $table->decimal('net_taxable', 15, 2)->default(0)->comment('Net imposable');

            // Employer Charges (Charges patronales)
            $table->decimal('cnss_employer', 15, 2)->default(0)->comment('Part patronale CNSS');
            $table->decimal('cimr_employer', 15, 2)->default(0)->comment('Part patronale CIMR');
            $table->decimal('amo_employer', 15, 2)->default(0);
            $table->decimal('professional_tax', 15, 2)->default(0)->comment('Taxe professionnelle');
            $table->decimal('training_tax', 15, 2)->default(0)->comment('Taxe de formation professionnelle');
            $table->decimal('total_employer_charges', 15, 2)->default(0);

            // Total Cost to Employer
            $table->decimal('total_cost', 15, 2)->comment('Coût total employeur');

            // Breakdown Details
            $table->json('earnings_breakdown')->nullable()->comment('Détail des gains');
            $table->json('deductions_breakdown')->nullable()->comment('Détail des retenues');
            $table->json('charges_breakdown')->nullable()->comment('Détail des charges');

            // Payment Information
            $table->enum('payment_status', ['pending', 'processed', 'paid', 'failed'])->default('pending');
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 100)->nullable();

            // Status
            $table->enum('status', ['draft', 'calculated', 'validated', 'paid'])->default('draft');
            $table->boolean('is_locked')->default(false);

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('payroll_period_id');
            $table->index('employee_id');
            $table->index('status');
            $table->index('payment_status');
            $table->unique(['payroll_period_id', 'employee_id'], 'unique_employee_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payroll_records');
    }
};
