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
        Schema::create('option_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('option_id')->constrained('options')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->tinyInteger('choice_rank_obtained'); // Which choice was fulfilled (1, 2, 3)
            $table->enum('assignment_method', ['Automatic', 'Manual'])->default('Automatic');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('assignment_notes')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();

            // Unique constraint: one assignment per student per year
            $table->unique(['student_id', 'academic_year_id'], 'unique_student_assignment_year');

            // Indexes
            $table->index('student_id');
            $table->index('option_id');
            $table->index('academic_year_id');
            $table->index('assignment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_assignments');
    }
};
