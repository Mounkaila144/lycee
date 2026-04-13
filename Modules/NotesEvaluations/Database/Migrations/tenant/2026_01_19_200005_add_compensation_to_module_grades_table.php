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
            $table->timestamp('compensation_applied_at')->nullable()->after('total_ranked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_grades', function (Blueprint $table) {
            $table->dropColumn('compensation_applied_at');
        });
    }
};
