<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Examens lycée : une session porte sur une matière (subject_id) et cible
 * soit une classe (classe_id : composition de classe), soit tout un niveau
 * (level_id : examen blanc commun à toutes les classes du niveau).
 * Le module universitaire (module_id) devient facultatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasColumn('exam_sessions', 'subject_id')) {
            $schema->table('exam_sessions', function (Blueprint $table) {
                $table->dropForeign(['module_id']);
            });

            $schema->table('exam_sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('module_id')->nullable()->change();
                $table->foreign('module_id')->references('id')->on('modules')->nullOnDelete();

                $table->foreignId('subject_id')->nullable()->after('module_id')->constrained('subjects')->nullOnDelete();
                $table->foreignId('classe_id')->nullable()->after('subject_id')->constrained('classes')->nullOnDelete();
                $table->foreignId('level_id')->nullable()->after('classe_id')->constrained('levels')->nullOnDelete();
                $table->foreignId('semester_id')->nullable()->after('level_id')->constrained('semesters')->nullOnDelete();
                $table->text('cancellation_reason')->nullable()->after('status');
                $table->json('class_evaluation_ids')->nullable()->after('cancellation_reason');

                $table->index(['classe_id', 'subject_id']);
                $table->index(['level_id', 'academic_year_id']);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasColumn('exam_sessions', 'subject_id')) {
            return;
        }

        $schema->table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['classe_id']);
            $table->dropForeign(['level_id']);
            $table->dropForeign(['semester_id']);
        });

        $schema->table('exam_sessions', function (Blueprint $table) {
            $table->dropIndex(['classe_id', 'subject_id']);
            $table->dropIndex(['level_id', 'academic_year_id']);
            $table->dropColumn(['subject_id', 'classe_id', 'level_id', 'semester_id', 'cancellation_reason', 'class_evaluation_ids']);
        });
    }
};
