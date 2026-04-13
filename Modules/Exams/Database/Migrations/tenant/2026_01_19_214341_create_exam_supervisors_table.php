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
        Schema::connection('tenant')->create('exam_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('exam_room_assignment_id')->nullable()->constrained('exam_room_assignments')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->enum('role', ['principal', 'assistant', 'reserve'])->default('principal');
            $table->time('actual_start_time')->nullable();
            $table->time('actual_end_time')->nullable();
            $table->enum('status', ['assigned', 'confirmed', 'present', 'absent', 'replaced'])->default('assigned');
            $table->boolean('is_notified')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('exam_session_id');
            $table->index('teacher_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('exam_supervisors');
    }
};
