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
            $table->decimal('min_compensable_grade', 4, 2)->nullable()->after('compensation_enabled');
            $table->integer('max_compensated_modules')->nullable()->after('min_compensable_grade');
            $table->boolean('allow_professional_module_compensation')->default(true)->after('max_compensated_modules');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grade_configs', function (Blueprint $table) {
            $table->dropColumn([
                'min_compensable_grade',
                'max_compensated_modules',
                'allow_professional_module_compensation',
            ]);
        });
    }
};
