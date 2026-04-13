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
        Schema::create('retake_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->decimal('original_average', 5, 2)->nullable();
            $table->enum('status', ['pending', 'scheduled', 'graded', 'validated', 'cancelled'])->default('pending');
            $table->timestamp('identified_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index pour les recherches fréquentes
            $table->index(['student_id', 'semester_id']);
            $table->index(['module_id', 'semester_id']);
            $table->index('status');

            // Contrainte d'unicité: un étudiant ne peut avoir qu'une inscription rattrapage par module/semestre
            $table->unique(['student_id', 'module_id', 'semester_id'], 'retake_enrollment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retake_enrollments');
    }
};
