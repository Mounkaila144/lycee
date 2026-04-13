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
        Schema::create('option_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('option_id')->constrained('options')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->tinyInteger('choice_rank'); // 1, 2, 3 (1er, 2e, 3e vœu)
            $table->enum('status', ['Pending', 'Validated', 'Rejected'])->default('Pending');
            $table->text('motivation')->nullable();
            $table->timestamps();

            // Unique constraint: one choice per student per option per year
            $table->unique(['student_id', 'option_id', 'academic_year_id'], 'unique_student_option_year');

            // Unique constraint: one rank per student per year
            $table->unique(['student_id', 'academic_year_id', 'choice_rank'], 'unique_student_year_rank');

            // Indexes
            $table->index('student_id');
            $table->index('option_id');
            $table->index('academic_year_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_choices');
    }
};
