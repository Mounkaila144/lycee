<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Intention de réinscription exprimée par un élève pendant une campagne (oui / non).
     */
    public function up(): void
    {
        Schema::create('reenrollment_intentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('reenrollment_campaigns')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->boolean('wants_to_reenroll');
            $table->string('comment', 500)->nullable();
            $table->timestamp('responded_at');
            $table->timestamps();

            $table->unique(['campaign_id', 'student_id'], 'reenroll_intentions_campaign_student_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reenrollment_intentions');
    }
};
