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
        Schema::connection('tenant')->create('exam_attendance_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('exam_room_assignment_id')->nullable()->constrained('exam_room_assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('seat_number')->nullable();
            $table->enum('status', ['present', 'absent', 'late', 'excluded'])->default('present');
            $table->time('arrival_time')->nullable();
            $table->time('submission_time')->nullable();
            $table->boolean('has_submitted')->default(false);
            $table->text('notes')->nullable();
            $table->string('signature_path')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_session_id', 'student_id']);
            $table->index('exam_session_id');
            $table->index('student_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('exam_attendance_sheets');
    }
};
