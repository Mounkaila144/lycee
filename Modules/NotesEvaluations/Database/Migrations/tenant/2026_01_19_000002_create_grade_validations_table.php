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
        Schema::create('grade_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('evaluation_id')->nullable()->constrained('module_evaluation_configs')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('semester_id')->nullable()->constrained('semesters');
            $table->foreignId('submitted_by')->constrained('users');
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Published'])->default('Pending');
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at');
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_publish_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('statistics')->nullable();
            $table->json('anomalies')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index(['module_id', 'status']);
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_validations');
    }
};
