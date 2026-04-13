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
        Schema::table('module_grades', function (Blueprint $table) {
            $table->boolean('has_retake_grade')->default(false)->after('status');
            $table->boolean('retake_improved')->default(false)->after('has_retake_grade');
            $table->decimal('original_average_before_retake', 5, 2)->nullable()->after('retake_improved');
        });

        Schema::table('semester_results', function (Blueprint $table) {
            $table->boolean('retake_session_completed')->default(false)->after('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_grades', function (Blueprint $table) {
            $table->dropColumn(['has_retake_grade', 'retake_improved', 'original_average_before_retake']);
        });

        Schema::table('semester_results', function (Blueprint $table) {
            $table->dropColumn('retake_session_completed');
        });
    }
};
