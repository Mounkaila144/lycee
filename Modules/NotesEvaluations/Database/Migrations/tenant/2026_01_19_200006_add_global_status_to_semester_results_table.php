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
            $table->enum('global_status', ['validated', 'partially_validated', 'to_retake', 'deferred'])
                ->default('to_retake')->after('is_validated');
            $table->integer('validated_modules_count')->default(0)->after('global_status');
            $table->integer('compensated_modules_count')->default(0)->after('validated_modules_count');
            $table->integer('failed_modules_count')->default(0)->after('compensated_modules_count');
            $table->boolean('can_progress_next_year')->default(false)->after('failed_modules_count');
            $table->integer('rank')->nullable()->after('can_progress_next_year');
            $table->integer('total_ranked')->nullable()->after('rank');

            // Index for ranking queries
            $table->index('global_status');
            $table->index('rank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semester_results', function (Blueprint $table) {
            $table->dropIndex(['global_status']);
            $table->dropIndex(['rank']);
            $table->dropColumn([
                'global_status',
                'validated_modules_count',
                'compensated_modules_count',
                'failed_modules_count',
                'can_progress_next_year',
                'rank',
                'total_ranked',
            ]);
        });
    }
};
