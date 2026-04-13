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
        Schema::create('student_module_choices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id'); // FK vers table students (module Enrollment)
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('specialization_id')->constrained('specializations')->onDelete('cascade');
            $table->timestamp('choice_date');
            $table->enum('status', ['En attente', 'Confirmé', 'Refusé'])->default('En attente');
            $table->timestamps();

            $table->unique(['student_id', 'module_id', 'specialization_id'], 'student_module_choice_unique');
            $table->index(['specialization_id', 'module_id']);
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_module_choices');
    }
};
