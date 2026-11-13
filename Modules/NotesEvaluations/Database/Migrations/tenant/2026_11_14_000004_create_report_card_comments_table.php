<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes lycée — appréciations du bulletin (par matière, ou générale quand subject_id est NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_card_comments')) {
            return;
        }

        Schema::create('report_card_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->cascadeOnDelete();
            $table->string('comment', 300);
            $table->foreignId('author_id')->constrained('users');
            $table->timestamps();

            $table->unique(['student_id', 'semester_id', 'subject_id'], 'report_card_comments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_comments');
    }
};
