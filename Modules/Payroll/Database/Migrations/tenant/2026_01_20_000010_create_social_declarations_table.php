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
        Schema::connection('tenant')->create('social_declarations', function (Blueprint $table) {
            $table->id();

            // Period
            $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete();
            $table->integer('year');
            $table->integer('month')->nullable();
            $table->enum('period_type', ['monthly', 'quarterly', 'annual'])->default('monthly');

            // Declaration Type
            $table->enum('declaration_type', [
                'cnss', // Caisse Nationale de Sécurité Sociale
                'amo', // Assurance Maladie Obligatoire
                'cimr', // Caisse Interprofessionnelle Marocaine de Retraite
                'income_tax', // Impôt sur le revenu (IR)
                'professional_tax', // Taxe professionnelle
                'training_tax', // Taxe de formation professionnelle
                'annual_tax_summary', // Récapitulatif annuel (État 9421)
            ]);

            // Declaration Details
            $table->string('declaration_number', 50)->unique();
            $table->date('declaration_date');
            $table->date('due_date')->nullable();

            // Employer Information
            $table->string('employer_name')->nullable();
            $table->string('employer_ice', 50)->nullable()->comment('Identifiant Commun de l\'Entreprise');
            $table->string('employer_cnss', 50)->nullable();
            $table->string('employer_tax_id', 50)->nullable();

            // Totals
            $table->integer('total_employees')->default(0);
            $table->decimal('total_gross_salary', 15, 2)->default(0);
            $table->decimal('total_taxable_salary', 15, 2)->default(0);
            $table->decimal('total_employee_contributions', 15, 2)->default(0)->comment('Cotisations salariales');
            $table->decimal('total_employer_contributions', 15, 2)->default(0)->comment('Cotisations patronales');
            $table->decimal('total_amount_due', 15, 2)->default(0)->comment('Montant total à payer');

            // CNSS Specific
            $table->decimal('cnss_employee_rate', 5, 2)->nullable();
            $table->decimal('cnss_employer_rate', 5, 2)->nullable();
            $table->decimal('cnss_employee_amount', 15, 2)->default(0);
            $table->decimal('cnss_employer_amount', 15, 2)->default(0);

            // AMO Specific
            $table->decimal('amo_employee_rate', 5, 2)->nullable();
            $table->decimal('amo_employer_rate', 5, 2)->nullable();
            $table->decimal('amo_employee_amount', 15, 2)->default(0);
            $table->decimal('amo_employer_amount', 15, 2)->default(0);

            // Tax Specific
            $table->decimal('income_tax_withheld', 15, 2)->default(0)->comment('IR retenu à la source');
            $table->decimal('professional_tax_amount', 15, 2)->default(0);
            $table->decimal('training_tax_amount', 15, 2)->default(0);

            // Detailed Data
            $table->json('employee_details')->nullable()->comment('Détail par employé');
            $table->json('calculation_details')->nullable()->comment('Détails des calculs');

            // Files
            $table->string('declaration_file')->nullable()->comment('Fichier XML/PDF de déclaration');
            $table->string('supporting_documents')->nullable()->comment('Documents justificatifs');

            // Submission
            $table->enum('status', ['draft', 'validated', 'submitted', 'accepted', 'rejected', 'paid'])->default('draft');
            $table->date('submission_date')->nullable();
            $table->string('submission_reference', 100)->nullable();
            $table->text('submission_response')->nullable();

            // Payment
            $table->date('payment_date')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->decimal('payment_amount', 15, 2)->nullable();
            $table->enum('payment_method', ['bank_transfer', 'check', 'online', 'other'])->nullable();

            // Validation
            $table->foreignId('prepared_by')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_notes')->nullable();

            // Penalties/Adjustments
            $table->decimal('late_penalty', 15, 2)->default(0);
            $table->decimal('adjustments', 15, 2)->default(0);
            $table->text('adjustment_reason')->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('declaration_number');
            $table->index('declaration_type');
            $table->index(['year', 'month']);
            $table->index('status');
            $table->index('declaration_date');
            $table->index('due_date');
            $table->index('payroll_period_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('social_declarations');
    }
};
