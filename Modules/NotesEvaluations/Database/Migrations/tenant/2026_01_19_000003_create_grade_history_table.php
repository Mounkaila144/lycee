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
        Schema::create('grade_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades')->onDelete('cascade');
            $table->decimal('old_value', 5, 2)->nullable();
            $table->decimal('new_value', 5, 2)->nullable();
            $table->boolean('old_is_absent')->default(false);
            $table->boolean('new_is_absent')->default(false);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');
            $table->text('reason')->nullable();
            $table->enum('change_type', ['creation', 'modification', 'correction'])->default('modification');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('grade_id');
            $table->index('changed_at');
            $table->index('change_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_history');
    }
};
