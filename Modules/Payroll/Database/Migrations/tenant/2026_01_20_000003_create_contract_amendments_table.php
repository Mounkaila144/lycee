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
        Schema::connection('tenant')->create('contract_amendments', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('contract_id')->constrained('employment_contracts')->cascadeOnDelete();

            // Amendment Details
            $table->string('amendment_number', 50)->unique();
            $table->enum('amendment_type', [
                'salary_change', // Modification de salaire
                'position_change', // Changement de poste
                'department_change', // Changement de département
                'schedule_change', // Modification horaires
                'benefits_change', // Modification avantages
                'extension', // Prolongation CDD
                'other',
            ]);

            // Changes
            $table->date('effective_date');
            $table->json('previous_values')->nullable()->comment('Valeurs avant modification');
            $table->json('new_values')->nullable()->comment('Nouvelles valeurs');
            $table->text('description')->nullable();

            // Approval
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'active'])->default('draft');
            $table->foreignId('requested_by')->nullable()->comment('Demandeur');
            $table->foreignId('approved_by')->nullable()->comment('Approbateur');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            // Document
            $table->string('amendment_document')->nullable()->comment('Avenant signé');
            $table->date('signature_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('contract_id');
            $table->index('amendment_number');
            $table->index('amendment_type');
            $table->index('status');
            $table->index('effective_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contract_amendments');
    }
};
