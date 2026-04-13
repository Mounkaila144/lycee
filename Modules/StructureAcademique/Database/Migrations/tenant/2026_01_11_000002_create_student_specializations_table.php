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
        Schema::create('student_specializations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id'); // FK vers table students (module Enrollment)
            $table->foreignId('specialization_id')->constrained('specializations')->onDelete('cascade');
            $table->timestamp('application_date');
            $table->enum('status', ['En attente', 'Accepté', 'Refusé', 'Liste attente'])->default('En attente');
            $table->decimal('average_at_application', 5, 2)->nullable();
            $table->unsignedTinyInteger('preference_order')->default(1); // Ordre de préférence
            $table->timestamp('assigned_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'specialization_id']);
            $table->index(['specialization_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_specializations');
    }
};
