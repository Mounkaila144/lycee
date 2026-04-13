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
        Schema::create('student_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('old_status', 50);
            $table->string('new_status', 50);
            $table->text('reason');
            $table->date('effective_date');
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('document_path')->nullable();
            $table->timestamps();

            // Index for fast lookups
            $table->index(['student_id', 'created_at']);
            $table->index('effective_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_status_histories');
    }
};
