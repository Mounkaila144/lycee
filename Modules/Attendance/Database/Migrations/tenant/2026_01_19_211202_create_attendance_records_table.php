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
        Schema::connection('tenant')->create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('absent');
            $table->time('arrival_time')->nullable();
            $table->integer('delay_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('modified_by')->nullable()->constrained('users');
            $table->text('modification_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index pour recherches rapides
            $table->unique(['attendance_session_id', 'student_id']);
            $table->index('status');
            $table->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
