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
        Schema::create('level_credit_configurations', function (Blueprint $table) {
            $table->id();

            // Program association (null = global configuration)
            $table->foreignId('program_id')
                ->nullable()
                ->constrained('programmes')
                ->onDelete('cascade');

            // Level (L1, L2, L3, M1, M2)
            $table->enum('level', ['L1', 'L2', 'L3', 'M1', 'M2']);

            // Credits per semester (default 30 each = 60 total/year)
            $table->unsignedTinyInteger('semester_1_credits')->default(30);
            $table->unsignedTinyInteger('semester_2_credits')->default(30);

            $table->timestamps();

            // Unique constraint: one config per level per program (or global)
            $table->unique(['program_id', 'level']);

            // Indexes for performance
            $table->index('program_id');
            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('level_credit_configurations');
    }
};
