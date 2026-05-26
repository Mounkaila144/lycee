<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secondary-school exams: an exam session is for a (class + subject), not an
 * LMD module. Add class/subject/room_name and relax the LMD columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('exam_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->change();
            $table->unsignedBigInteger('academic_year_id')->nullable()->change();

            if (! Schema::connection('tenant')->hasColumn('exam_sessions', 'class_id')) {
                $table->foreignId('class_id')->nullable()->after('id')->constrained('classes')->nullOnDelete();
            }
            if (! Schema::connection('tenant')->hasColumn('exam_sessions', 'subject_id')) {
                $table->foreignId('subject_id')->nullable()->after('class_id')->constrained('subjects')->nullOnDelete();
            }
            if (! Schema::connection('tenant')->hasColumn('exam_sessions', 'room_name')) {
                $table->string('room_name')->nullable()->after('total_capacity');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('exam_sessions', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('exam_sessions', 'subject_id')) {
                $table->dropConstrainedForeignId('subject_id');
            }
            if (Schema::connection('tenant')->hasColumn('exam_sessions', 'class_id')) {
                $table->dropConstrainedForeignId('class_id');
            }
            if (Schema::connection('tenant')->hasColumn('exam_sessions', 'room_name')) {
                $table->dropColumn('room_name');
            }
        });
    }
};
