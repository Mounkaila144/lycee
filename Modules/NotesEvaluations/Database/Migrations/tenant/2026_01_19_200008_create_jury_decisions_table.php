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
        Schema::create('jury_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliberation_session_id')->constrained('deliberation_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('semester_result_id')->constrained('semester_results')->cascadeOnDelete();

            // Decision type
            $table->enum('decision', [
                'validated',           // Semestre validé
                'compensated',         // Admis par compensation
                'retake',              // Rattrapage
                'repeat_year',         // Redoublement
                'exclusion',           // Exclusion
                'conditional',         // Admission conditionnelle
                'deferred',            // Ajourné (en attente de plus d'informations)
            ]);

            // Computed values at decision time
            $table->decimal('average_at_decision', 5, 2)->nullable();
            $table->integer('acquired_credits_at_decision')->default(0);
            $table->integer('missing_credits_at_decision')->default(0);

            // Deliberation details
            $table->text('justification')->nullable();
            $table->json('conditions')->nullable(); // For conditional decisions
            $table->boolean('is_exceptional')->default(false);
            $table->text('exceptional_reason')->nullable();

            // Voting (optional)
            $table->integer('votes_for')->nullable();
            $table->integer('votes_against')->nullable();
            $table->integer('abstentions')->nullable();

            // Approval workflow
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->boolean('requires_review')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['deliberation_session_id', 'student_id'], 'jury_decision_unique');
            $table->index('decision');
            $table->index('is_exceptional');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jury_decisions');
    }
};
