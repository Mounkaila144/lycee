<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teaching assignments: which subject a teacher teaches in which class.
 *
 * A teacher (user with the Professeur role) may teach several subjects in the
 * same class, and may teach across several classes. Each (teacher, class,
 * subject) triple is one row. These assignments drive what a teacher is
 * allowed to see and grade in the grade-entry screens.
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
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['teacher_id', 'class_id', 'subject_id'], 'teacher_class_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
