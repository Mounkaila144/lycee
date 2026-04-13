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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('restrict');
            $table->foreignId('level_id')->constrained('levels')->onDelete('restrict');
            $table->foreignId('series_id')->nullable()->constrained('series')->onDelete('set null');
            $table->string('section', 10)->nullable();
            $table->string('name', 50);
            $table->smallInteger('max_capacity')->unsigned()->default(60);
            $table->string('classroom')->nullable();
            $table->foreignId('head_teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: name must be unique per academic year
            $table->unique(['academic_year_id', 'name']);

            // Indexes
            $table->index('level_id');
            $table->index('series_id');
            $table->index('head_teacher_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
