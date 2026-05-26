<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secondary-school timetable: a slot is (class + subject + teacher + day + time
 * + room), not the LMD module/group/room-id model. Add class/subject/room_name
 * and relax the legacy LMD columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('timetable_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->change();
            $table->unsignedBigInteger('group_id')->nullable()->change();
            $table->unsignedBigInteger('room_id')->nullable()->change();
            $table->unsignedBigInteger('semester_id')->nullable()->change();
            $table->enum('type', ['CM', 'TD', 'TP'])->nullable()->change();

            if (! Schema::connection('tenant')->hasColumn('timetable_slots', 'class_id')) {
                $table->foreignId('class_id')->nullable()->after('id')->constrained('classes')->nullOnDelete();
            }
            if (! Schema::connection('tenant')->hasColumn('timetable_slots', 'subject_id')) {
                $table->foreignId('subject_id')->nullable()->after('class_id')->constrained('subjects')->nullOnDelete();
            }
            if (! Schema::connection('tenant')->hasColumn('timetable_slots', 'room_name')) {
                $table->string('room_name')->nullable()->after('room_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('timetable_slots', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('timetable_slots', 'subject_id')) {
                $table->dropConstrainedForeignId('subject_id');
            }
            if (Schema::connection('tenant')->hasColumn('timetable_slots', 'class_id')) {
                $table->dropConstrainedForeignId('class_id');
            }
            if (Schema::connection('tenant')->hasColumn('timetable_slots', 'room_name')) {
                $table->dropColumn('room_name');
            }
        });
    }
};
