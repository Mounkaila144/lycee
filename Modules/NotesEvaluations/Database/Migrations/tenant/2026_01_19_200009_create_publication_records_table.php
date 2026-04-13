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
        Schema::create('publication_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $table->enum('publication_type', ['provisional', 'final', 'deliberation']);
            $table->enum('scope', ['semester', 'programme', 'level'])->default('semester');
            $table->string('level')->nullable(); // L1, L2, L3, M1, M2
            $table->dateTime('published_at');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('students_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->decimal('success_rate', 5, 2)->nullable();
            $table->boolean('notifications_sent')->default(false);
            $table->integer('notifications_count')->default(0);
            $table->json('statistics')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['semester_id', 'publication_type']);
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_records');
    }
};
