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
        Schema::create('module_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->integer('total_students')->default(0);
            $table->decimal('class_average', 4, 2)->nullable();
            $table->decimal('min_grade', 4, 2)->nullable();
            $table->decimal('max_grade', 4, 2)->nullable();
            $table->decimal('median', 4, 2)->nullable();
            $table->decimal('standard_deviation', 4, 2)->nullable();
            $table->decimal('pass_rate', 5, 2)->nullable();
            $table->decimal('absence_rate', 5, 2)->nullable();
            $table->json('distribution')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['module_id', 'semester_id'], 'module_results_unique');
            $table->index('generated_at');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_results');
    }
};
