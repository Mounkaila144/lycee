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
        Schema::create('module_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->enum('level', ['L1', 'L2', 'L3', 'M1', 'M2']);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Un code de groupe doit être unique par semestre/programme/niveau
            $table->unique(['code', 'semester_id', 'programme_id', 'level'], 'module_groups_unique');

            $table->index('semester_id');
            $table->index('programme_id');
            $table->index(['programme_id', 'level', 'semester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_groups');
    }
};
