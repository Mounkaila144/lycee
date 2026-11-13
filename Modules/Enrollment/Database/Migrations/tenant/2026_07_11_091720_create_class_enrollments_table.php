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
        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('previous_class_enrollment_id')->nullable()->constrained('class_enrollments')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('enrolled_at');
            $table->enum('assignment_type', [
                'Nouvelle inscription',
                'Passage',
                'Redoublement',
                'Transfert',
                'Réorientation',
            ])->default('Nouvelle inscription');
            $table->enum('status', [
                'Actif',
                'Terminé',
                'Transféré',
                'Annulé',
            ])->default('Actif');
            $table->enum('final_decision', [
                'Passage',
                'Redoublement',
                'Exclusion',
                'Diplôme',
                'Abandon',
                'Transfert',
            ])->nullable();
            $table->date('final_decision_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'academic_year_id'], 'class_enrollments_student_year_unique');
            $table->index(['classe_id', 'academic_year_id']);
            $table->index(['academic_year_id', 'status']);
            $table->index('final_decision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
    }
};
