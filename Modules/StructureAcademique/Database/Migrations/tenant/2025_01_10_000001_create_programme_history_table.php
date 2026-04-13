<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        Schema::create('programme_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')
                ->constrained('programmes')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->enum('action', ['created', 'updated', 'deleted', 'restored']);
            $table->string('field_changed')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes pour performance
            $table->index('programme_id');
            $table->index('created_at');
            $table->index(['programme_id', 'created_at']);
            $table->index('user_id');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('programme_history');
    }
};
