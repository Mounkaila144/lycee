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
        Schema::create('ects_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('semester_result_id')->nullable()->constrained('semester_results')->onDelete('cascade');
            $table->integer('credits_allocated');
            $table->string('allocation_type'); // validated, compensated, equivalence
            $table->text('note')->nullable();
            $table->timestamp('allocated_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'module_id'], 'ects_allocations_unique');
            $table->index('allocation_type');
            $table->index('allocated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ects_allocations');
    }
};
