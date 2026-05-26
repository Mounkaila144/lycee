<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secondary-school model: attendance is taken per (class + subject), not per
 * LMD timetable slot (module/group). Adds class/subject linkage to students
 * and attendance sessions, and relaxes the legacy timetable_slot_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasColumn('students', 'class_id')) {
            Schema::connection('tenant')->table('students', function (Blueprint $table) {
                $table->foreignId('class_id')->nullable()->after('user_id')
                    ->constrained('classes')->nullOnDelete();
            });
        }

        Schema::connection('tenant')->table('attendance_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('timetable_slot_id')->nullable()->change();

            if (! Schema::connection('tenant')->hasColumn('attendance_sessions', 'class_id')) {
                $table->foreignId('class_id')->nullable()->after('timetable_slot_id')
                    ->constrained('classes')->nullOnDelete();
            }
            if (! Schema::connection('tenant')->hasColumn('attendance_sessions', 'subject_id')) {
                $table->foreignId('subject_id')->nullable()->after('class_id')
                    ->constrained('subjects')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('attendance_sessions', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('attendance_sessions', 'subject_id')) {
                $table->dropConstrainedForeignId('subject_id');
            }
            if (Schema::connection('tenant')->hasColumn('attendance_sessions', 'class_id')) {
                $table->dropConstrainedForeignId('class_id');
            }
        });

        if (Schema::connection('tenant')->hasColumn('students', 'class_id')) {
            Schema::connection('tenant')->table('students', function (Blueprint $table) {
                $table->dropConstrainedForeignId('class_id');
            });
        }
    }
};
