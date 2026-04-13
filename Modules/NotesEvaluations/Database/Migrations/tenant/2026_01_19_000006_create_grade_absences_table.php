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
        Schema::create('grade_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades')->onDelete('cascade');
            $table->enum('absence_type', ['unjustified', 'pending', 'justified'])->default('unjustified');
            $table->foreignId('justification_id')->nullable()->constrained('absence_justifications');
            $table->timestamp('justification_deadline');
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('absence_type');
            $table->index('justification_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_absences');
    }
};
