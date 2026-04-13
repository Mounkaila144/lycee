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
        Schema::create('retake_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retake_enrollment_id')->constrained('retake_enrollments')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at')->nullable();
            $table->enum('status', ['draft', 'submitted', 'validated', 'published'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('status');
            $table->unique('retake_enrollment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retake_grades');
    }
};
