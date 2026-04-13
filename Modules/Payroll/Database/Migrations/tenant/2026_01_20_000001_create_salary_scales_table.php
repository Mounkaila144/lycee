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
        Schema::connection('tenant')->create('salary_scales', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Scale Type
            $table->enum('type', ['teaching', 'administrative', 'technical', 'management', 'other'])->default('other');

            // Grades/Levels Configuration
            $table->json('grades')->comment('Structure: [{grade: "A1", min_salary: 5000, max_salary: 8000, annual_increment: 200}]');

            // Validity
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);

            // Approval
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index('type');
            $table->index('is_active');
            $table->index(['effective_from', 'effective_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('salary_scales');
    }
};
