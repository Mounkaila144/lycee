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
        Schema::connection('tenant')->create('exam_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->enum('type', ['cheating', 'disturbance', 'technical', 'medical', 'other'])->default('other');
            $table->string('title');
            $table->text('description');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->time('occurred_at_time');
            $table->enum('status', ['reported', 'under_review', 'resolved', 'escalated'])->default('reported');
            $table->text('action_taken')->nullable();
            $table->json('witnesses')->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index('exam_session_id');
            $table->index('student_id');
            $table->index('type');
            $table->index('status');
            $table->index('severity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('exam_incidents');
    }
};
