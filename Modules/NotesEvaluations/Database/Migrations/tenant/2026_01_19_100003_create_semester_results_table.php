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
        Schema::create('semester_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->decimal('average', 4, 2)->nullable();
            $table->boolean('is_final')->default(false);
            $table->boolean('is_validated')->default(false);
            $table->boolean('validation_blocked_by_eliminatory')->default(false);
            $table->json('blocking_reasons')->nullable();
            $table->integer('total_credits')->default(0);
            $table->integer('acquired_credits')->default(0);
            $table->integer('missing_credits')->default(0);
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->integer('missing_modules_count')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['student_id', 'semester_id'], 'semester_results_unique');
            $table->index('average');
            $table->index('is_final');
            $table->index('is_validated');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semester_results');
    }
};
