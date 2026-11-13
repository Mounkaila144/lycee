<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lycée : une séance d'appel est rattachée à une classe (et optionnellement
 * à une matière) plutôt qu'obligatoirement à un créneau d'emploi du temps.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasColumn('attendance_sessions', 'classe_id')) {
            return;
        }

        Schema::connection('tenant')->table('attendance_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('timetable_slot_id')->nullable()->change();
            $table->foreignId('classe_id')->nullable()->after('timetable_slot_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->after('classe_id')->constrained('subjects')->nullOnDelete();

            $table->index(['classe_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('attendance_sessions', function (Blueprint $table) {
            $table->dropForeign(['classe_id']);
            $table->dropForeign(['subject_id']);
            $table->dropIndex(['classe_id', 'session_date']);
            $table->dropColumn(['classe_id', 'subject_id']);
        });
    }
};
