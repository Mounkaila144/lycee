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
        Schema::create('deliberation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $table->string('session_type')->default('regular'); // regular, retake, exceptional
            $table->string('status')->default('pending'); // pending, in_progress, completed, cancelled
            $table->dateTime('scheduled_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->string('location')->nullable();
            $table->text('agenda')->nullable();
            $table->json('jury_members')->nullable(); // Array of user IDs
            $table->foreignId('president_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('minutes')->nullable(); // Procès-verbal
            $table->json('summary')->nullable(); // Summary statistics
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['semester_id', 'status']);
            $table->index('session_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliberation_sessions');
    }
};
