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
        Schema::table('semester_results', function (Blueprint $table) {
            $table->enum('final_status', [
                'in_progress',
                'admitted',
                'admitted_with_debts',
                'deferred_final',
                'repeating',
            ])->default('in_progress')->after('retake_session_completed');
            $table->string('attestation_file_path')->nullable()->after('final_status');
            $table->timestamp('final_published_at')->nullable()->after('attestation_file_path');
            $table->timestamp('year_locked_at')->nullable()->after('final_published_at');

            // Index
            $table->index('final_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semester_results', function (Blueprint $table) {
            $table->dropColumn([
                'final_status',
                'attestation_file_path',
                'final_published_at',
                'year_locked_at',
            ]);
        });
    }
};
