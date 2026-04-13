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
        Schema::create('subject_class_coefficients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('level_id')->constrained('levels')->onDelete('cascade');
            $table->foreignId('series_id')->nullable()->constrained('series')->onDelete('set null');
            $table->decimal('coefficient', 3, 1);
            $table->unsignedTinyInteger('hours_per_week')->nullable();
            $table->timestamps();

            $table->unique(['subject_id', 'level_id', 'series_id'], 'scc_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_class_coefficients');
    }
};
