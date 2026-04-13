<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds additional indexes for search and filter performance
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Index for nationality filtering
            $table->index('nationality');

            // Index for sorting by inscription date
            $table->index('created_at');

            // Composite index for common filter combinations
            $table->index(['status', 'sex']);
            $table->index(['status', 'nationality']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['nationality']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'sex']);
            $table->dropIndex(['status', 'nationality']);
        });
    }
};
