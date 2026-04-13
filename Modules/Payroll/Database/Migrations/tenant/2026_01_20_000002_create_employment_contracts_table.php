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
        Schema::connection('tenant')->create('employment_contracts', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Contract Details
            $table->string('contract_number', 50)->unique();
            $table->enum('contract_type', [
                'permanent', // CDI - Contrat à Durée Indéterminée
                'fixed_term', // CDD - Contrat à Durée Déterminée
                'temporary', // Temporaire
                'internship', // Stage
                'consultant', // Consultant
                'part_time', // Temps partiel
            ]);

            // Dates
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('Obligatoire pour CDD');
            $table->date('probation_end_date')->nullable();

            // Work Schedule
            $table->integer('weekly_hours')->default(40);
            $table->enum('work_schedule', ['full_time', 'part_time', 'flexible'])->default('full_time');

            // Compensation
            $table->decimal('base_salary', 15, 2)->comment('Salaire de base mensuel');
            $table->foreignId('salary_scale_id')->nullable()->constrained('salary_scales')->nullOnDelete();
            $table->string('salary_scale_grade', 50)->nullable();

            // Benefits
            $table->json('benefits')->nullable()->comment('Avantages: voiture, logement, etc.');

            // Contract Terms
            $table->text('contract_terms')->nullable()->comment('Clauses contractuelles');
            $table->text('job_description')->nullable();

            // Document
            $table->string('contract_document')->nullable()->comment('PDF du contrat signé');

            // Status
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'renewed'])->default('draft');
            $table->date('signature_date')->nullable();
            $table->foreignId('signed_by')->nullable()->comment('Responsable ayant signé');

            // Renewal
            $table->boolean('is_renewable')->default(false);
            $table->foreignId('renewed_from_contract_id')->nullable()->constrained('employment_contracts')->nullOnDelete();
            $table->foreignId('renewed_to_contract_id')->nullable()->constrained('employment_contracts')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('employee_id');
            $table->index('contract_number');
            $table->index('contract_type');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('employment_contracts');
    }
};
