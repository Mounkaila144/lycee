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
        Schema::create('reenrollment_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->foreignId('to_academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->json('eligible_programs')->nullable();
            $table->json('eligible_levels')->nullable();
            $table->json('required_documents')->nullable();
            $table->json('fees_config')->nullable();
            $table->integer('min_ects_required')->default(24);
            $table->boolean('check_financial_clearance')->default(false);
            $table->enum('status', ['Draft', 'Active', 'Closed'])->default('Draft');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['from_academic_year_id', 'to_academic_year_id'], 'reenroll_camps_year_ids_idx');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reenrollment_campaigns');
    }
};
