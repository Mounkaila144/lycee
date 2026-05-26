<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secondary-school evaluations: a graded assessment for a (class + subject)
 * within a school period (semester/trimester). Grades (/20) hang off it.
 * Replaces the LMD module_evaluation_configs linkage on the grades table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('evaluations')) {
            Schema::connection('tenant')->create('evaluations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
                $table->enum('type', ['Devoir', 'Composition', 'Interrogation'])->default('Devoir');
                $table->string('name');
                $table->date('evaluation_date');
                $table->decimal('max_score', 5, 2)->default(20);
                $table->decimal('coefficient', 4, 2)->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('status', ['Draft', 'Published'])->default('Draft');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['class_id', 'subject_id', 'semester_id']);
            });
        }

        // Re-point grades.evaluation_id from the LMD module_evaluation_configs
        // to the new secondary evaluations table (grades is empty at this point).
        Schema::connection('tenant')->table('grades', function (Blueprint $table) {
            try {
                $table->dropForeign(['evaluation_id']);
            } catch (\Throwable $e) {
                // FK may not exist; ignore.
            }
            $table->foreign('evaluation_id')->references('id')->on('evaluations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('grades', function (Blueprint $table) {
            try {
                $table->dropForeign(['evaluation_id']);
            } catch (\Throwable $e) {
            }
        });

        Schema::connection('tenant')->dropIfExists('evaluations');
    }
};
