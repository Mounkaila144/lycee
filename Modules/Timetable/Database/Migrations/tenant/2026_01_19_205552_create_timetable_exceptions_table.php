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
        Schema::connection('tenant')->create('timetable_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_slot_id')->constrained('timetable_slots')->cascadeOnDelete();
            $table->date('exception_date');
            $table->enum('exception_type', [
                'cancellation',
                'room_change',
                'teacher_replacement',
                'time_change',
                'date_change',
                'exceptional_session',
            ]);
            $table->json('original_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason');
            $table->boolean('notify_students')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Index pour recherches rapides
            $table->index('exception_date');
            $table->index(['timetable_slot_id', 'exception_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_exceptions');
    }
};
