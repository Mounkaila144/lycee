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
        Schema::connection('tenant')->create('payroll_periods', function (Blueprint $table) {
            $table->id();

            // Period Details
            $table->string('period_code', 50)->unique();
            $table->string('name'); // Example: "Janvier 2026", "Mois 01/2026"
            $table->enum('period_type', ['monthly', 'bi_weekly', 'weekly', 'annual'])->default('monthly');

            // Date Range
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('year');
            $table->integer('month')->nullable();

            // Payment Date
            $table->date('payment_date');
            $table->date('cutoff_date')->nullable()->comment('Date limite pour les modifications');

            // Status
            $table->enum('status', ['draft', 'in_progress', 'calculated', 'validated', 'paid', 'closed'])->default('draft');

            // Statistics
            $table->integer('total_employees')->default(0);
            $table->decimal('total_gross_salary', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net_salary', 15, 2)->default(0);
            $table->decimal('total_employer_charges', 15, 2)->default(0)->comment('Charges patronales');

            // Processing
            $table->foreignId('calculated_by')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('period_code');
            $table->index('status');
            $table->index(['year', 'month']);
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payroll_periods');
    }
};
