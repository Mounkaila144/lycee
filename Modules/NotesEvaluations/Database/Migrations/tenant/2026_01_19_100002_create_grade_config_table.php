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
        Schema::create('grade_configs', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_module_average', 4, 2)->default(10.00);
            $table->decimal('min_semester_average', 4, 2)->default(10.00);
            $table->boolean('compensation_enabled')->default(true);
            $table->decimal('eliminatory_threshold', 4, 2)->default(10.00);
            $table->boolean('allow_eliminatory_compensation')->default(false);
            $table->integer('year_progression_threshold')->default(80); // Percentage
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_configs');
    }
};
