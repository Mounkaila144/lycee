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
        Schema::create('group_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->enum('assignment_method', ['Automatic', 'Manual'])->default('Manual');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('assignment_reason')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint - student can only be in one group per module per year
            $table->unique(['student_id', 'group_id', 'academic_year_id'], 'unique_student_group_year');

            // Indexes for performance
            $table->index(['student_id', 'academic_year_id']);
            $table->index(['group_id', 'academic_year_id']);
            $table->index('module_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_assignments');
    }
};
