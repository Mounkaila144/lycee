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
        Schema::create('module_evaluation_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->string('name', 100);
            $table->enum('type', ['CC', 'TP', 'Projet', 'Examen', 'Rattrapage']);
            $table->decimal('coefficient', 5, 2);
            $table->decimal('max_score', 4, 2)->default(20.00);
            $table->date('planned_date')->nullable();
            $table->boolean('is_eliminatory')->default(false);
            $table->decimal('elimination_threshold', 4, 2)->nullable();
            $table->integer('order')->default(0);
            $table->enum('status', ['Draft', 'Published'])->default('Draft');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['module_id', 'semester_id']);
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_evaluation_configs');
    }
};
