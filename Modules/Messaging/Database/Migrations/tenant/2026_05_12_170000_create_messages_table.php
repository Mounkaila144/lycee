<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story Parent 07 — Messages Parent ↔ Enseignants (mini-module Messaging).
 *
 * Modèle simple : sender_id → recipient_id, sujet, corps, lu/non lu.
 * Une conversation est implicite (mêmes participants + thread_id pour grouper).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('thread_id')->nullable()->comment('id du 1er message du fil');
            $table->foreignId('student_context_id')->nullable()->constrained('students')->nullOnDelete()
                ->comment('Enfant concerné par la conversation (optionnel)');
            $table->string('subject');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['recipient_id', 'read_at']);
            $table->index('thread_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
