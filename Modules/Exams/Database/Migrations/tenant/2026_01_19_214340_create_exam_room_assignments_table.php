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
        Schema::connection('tenant')->create('exam_room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->integer('capacity');
            $table->integer('assigned_students')->default(0);
            $table->integer('seat_start_number')->nullable();
            $table->integer('seat_end_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['exam_session_id', 'room_id']);
            $table->index('exam_session_id');
            $table->index('room_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('exam_room_assignments');
    }
};
