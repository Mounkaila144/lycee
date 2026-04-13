<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('tenant')->create('teacher_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->enum('day_of_week', ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_preferred')->default(true)->comment('True si créneau préféré, False si à éviter');
            $table->tinyInteger('priority')->default(5)->comment('Priorité 1-10, 10 = max');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index pour recherches rapides
            $table->index(['teacher_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_preferences');
    }
};
