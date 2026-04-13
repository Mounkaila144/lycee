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
        Schema::table('reenrollments', function (Blueprint $table) {
            // Drop the old foreign key constraint pointing to pedagogical_enrollments
            $table->dropForeign(['previous_enrollment_id']);

            // Add new foreign key constraint pointing to student_enrollments
            $table->foreign('previous_enrollment_id')
                ->references('id')
                ->on('student_enrollments')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reenrollments', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['previous_enrollment_id']);

            // Restore the old foreign key pointing to pedagogical_enrollments
            $table->foreign('previous_enrollment_id')
                ->references('id')
                ->on('pedagogical_enrollments')
                ->onDelete('set null');
        });
    }
};
