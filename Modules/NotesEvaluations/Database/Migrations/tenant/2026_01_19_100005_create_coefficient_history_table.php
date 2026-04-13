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
        Schema::create('coefficient_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('module_evaluation_configs')->onDelete('cascade');
            $table->decimal('old_coefficient', 4, 2);
            $table->decimal('new_coefficient', 4, 2);
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            $table->text('reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index('evaluation_id');
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coefficient_history');
    }
};
