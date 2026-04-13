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
        Schema::create('equivalences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->onDelete('cascade');
            $table->string('origin_module_code', 50);
            $table->string('origin_module_name');
            $table->integer('origin_ects')->default(0);
            $table->integer('origin_hours')->default(0);
            $table->decimal('origin_grade', 5, 2)->nullable();
            $table->foreignId('target_module_id')->nullable()->constrained('modules')->onDelete('set null');
            $table->enum('equivalence_type', ['Full', 'Partial', 'None', 'Exemption'])->default('None');
            $table->integer('equivalence_percentage')->default(0);
            $table->integer('granted_ects')->default(0);
            $table->decimal('granted_grade', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->integer('similarity_score')->default(0);
            $table->enum('status', ['Proposed', 'Validated', 'Rejected'])->default('Proposed');
            $table->timestamps();

            $table->index(['transfer_id', 'status']);
            $table->index('target_module_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equivalences');
    }
};
