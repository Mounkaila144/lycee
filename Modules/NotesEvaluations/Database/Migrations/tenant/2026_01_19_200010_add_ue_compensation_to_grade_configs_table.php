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
        Schema::table('grade_configs', function (Blueprint $table) {
            $table->boolean('ue_compensation_enabled')->default(true)->after('allow_professional_module_compensation');
            $table->decimal('ue_min_average', 4, 2)->default(10.00)->after('ue_compensation_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grade_configs', function (Blueprint $table) {
            $table->dropColumn(['ue_compensation_enabled', 'ue_min_average']);
        });
    }
};
