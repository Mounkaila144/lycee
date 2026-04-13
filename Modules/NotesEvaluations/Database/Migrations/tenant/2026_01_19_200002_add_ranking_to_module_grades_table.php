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
            $table->integer('rank')->nullable()->after('status');
            $table->integer('total_ranked')->nullable()->after('rank');

            // Index for ranking queries
            $table->index('rank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_grades', function (Blueprint $table) {
            $table->dropIndex(['rank']);
            $table->dropColumn(['rank', 'total_ranked']);
        });
    }
};
