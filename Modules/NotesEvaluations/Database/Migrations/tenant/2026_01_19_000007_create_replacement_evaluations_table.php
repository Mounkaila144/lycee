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
        Schema::create('replacement_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_evaluation_id')->constrained('module_evaluation_configs');
            $table->foreignId('student_id')->constrained('students');
            $table->timestamp('scheduled_at');
            $table->string('location')->nullable();
            $table->enum('type', ['same', 'alternative'])->default('same');
            $table->timestamp('convocation_sent_at')->nullable();
            $table->foreignId('grade_id')->nullable()->constrained('grades');
            $table->text('comment')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('scheduled_at');
            $table->index('status');
            $table->index(['student_id', 'original_evaluation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replacement_evaluations');
    }
};
