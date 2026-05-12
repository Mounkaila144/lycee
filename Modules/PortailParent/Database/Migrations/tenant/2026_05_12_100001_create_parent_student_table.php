<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_financial_responsible')->default(false);
            $table->timestamps();

            $table->unique(['parent_id', 'student_id'], 'unique_parent_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_student');
    }
};
