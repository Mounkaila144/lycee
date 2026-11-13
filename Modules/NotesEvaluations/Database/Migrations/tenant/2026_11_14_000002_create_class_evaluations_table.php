<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes lycée — évaluations (devoir, interrogation, composition) d'une matière d'une classe sur un semestre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('class_evaluations')) {
            return;
        }

        Schema::create('class_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('teacher_id')->constrained('users');
            $table->string('title', 150);
            $table->enum('type', ['devoir', 'interrogation', 'composition']);
            $table->date('evaluation_date');
            $table->decimal('max_score', 5, 2)->default(20);
            $table->decimal('weight', 4, 2)->default(1);
            $table->enum('status', ['draft', 'submitted', 'published'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['classe_id', 'subject_id', 'semester_id'], 'class_evaluations_lookup');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_evaluations');
    }
};
