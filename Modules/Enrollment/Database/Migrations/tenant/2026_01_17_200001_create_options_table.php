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
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->string('level', 10); // L1, L2, L3, M1, M2
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('capacity')->default(30);
            $table->json('prerequisites')->nullable(); // {module_id: min_grade}
            $table->boolean('is_mandatory')->default(false);
            $table->date('choice_start_date');
            $table->date('choice_end_date');
            $table->enum('status', ['Open', 'Closed', 'Archived'])->default('Open');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('programme_id');
            $table->index('level');
            $table->index('status');
            $table->index(['programme_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
