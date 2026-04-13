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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('program_id')->constrained('programmes')->onDelete('cascade');
            $table->string('level', 10);
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('set null');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->enum('type', ['CM', 'TD', 'TP']);
            $table->unsignedInteger('capacity_min')->default(20);
            $table->unsignedInteger('capacity_max')->default(35);
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('room_id', 100)->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['module_id', 'academic_year_id']);
            $table->index(['program_id', 'level']);
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
