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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('evaluation_id')->constrained('module_evaluation_configs')->onDelete('cascade');
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('comment', 200)->nullable();
            $table->foreignId('entered_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('entered_at');
            $table->enum('status', ['Draft', 'Submitted', 'Validated', 'Published'])->default('Draft');
            $table->boolean('is_visible_to_students')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: one grade per student per evaluation
            $table->unique(['student_id', 'evaluation_id']);

            // Indexes
            $table->index('status');
            $table->index('is_visible_to_students');
            $table->index(['evaluation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
