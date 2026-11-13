<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptation lycée : un créneau est rattaché à une classe (classes) et une matière (subjects).
 * Les colonnes héritées de l'université (module_id, group_id) deviennent optionnelles,
 * ainsi que la salle (au lycée chaque classe a souvent sa propre salle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->foreignId('classe_id')->nullable()->after('group_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->after('module_id')->constrained('subjects')->cascadeOnDelete();

            $table->unsignedBigInteger('module_id')->nullable()->change();
            $table->unsignedBigInteger('group_id')->nullable()->change();
            $table->unsignedBigInteger('room_id')->nullable()->change();

            $table->index(['classe_id', 'semester_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropIndex(['classe_id', 'semester_id', 'day_of_week']);
            $table->dropConstrainedForeignId('classe_id');
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
