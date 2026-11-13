<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes lycée — affectation d'un professeur à une matière d'une classe pour une année.
 * Un seul professeur par (classe, matière, année).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teacher_assignments')) {
            return;
        }

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users');
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->timestamps();

            $table->unique(['classe_id', 'subject_id', 'academic_year_id'], 'teacher_assignments_unique');
            $table->index(['teacher_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
