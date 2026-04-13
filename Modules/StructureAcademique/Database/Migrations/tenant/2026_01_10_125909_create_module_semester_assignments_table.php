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
        Schema::create('module_semester_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')
                ->constrained('modules')
                ->onDelete('cascade');
            $table->foreignId('semester_id')
                ->constrained('semesters')
                ->onDelete('cascade');
            $table->foreignId('programme_id')
                ->nullable()
                ->constrained('programmes')
                ->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('module_id');
            $table->index('semester_id');
            $table->index('programme_id');
            $table->index('is_active');

            // Unique constraint: un module ne peut être assigné qu'une seule fois à un semestre pour un programme donné
            $table->unique(['module_id', 'semester_id', 'programme_id'], 'module_semester_programme_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_semester_assignments');
    }
};
