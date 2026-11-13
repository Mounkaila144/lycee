<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes lycée — note d'un élève à une évaluation de classe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('class_evaluation_grades')) {
            return;
        }

        Schema::create('class_evaluation_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_evaluation_id')->constrained('class_evaluations')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students');
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('comment', 200)->nullable();
            $table->foreignId('entered_by')->constrained('users');
            $table->timestamps();

            $table->unique(['class_evaluation_id', 'student_id'], 'class_evaluation_grades_unique');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_evaluation_grades');
    }
};
