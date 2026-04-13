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
        Schema::table('modules', function (Blueprint $table) {
            $table->foreignId('module_group_id')
                ->nullable()
                ->after('is_eliminatory')
                ->constrained('module_groups')
                ->onDelete('set null');

            $table->index('module_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropForeign(['module_group_id']);
            $table->dropColumn('module_group_id');
        });
    }
};
