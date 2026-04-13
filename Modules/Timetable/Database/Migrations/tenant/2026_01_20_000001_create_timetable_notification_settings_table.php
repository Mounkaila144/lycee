<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('timetable_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('notify_changes')->default(true);
            $table->boolean('notify_cancellations')->default(true);
            $table->boolean('notify_replacements')->default(true);
            $table->boolean('notify_reminders')->default(true);
            $table->enum('reminder_timing', ['1h', '2h', '24h', '48h'])->default('24h');
            $table->json('channels')->default('["database", "mail"]');
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::connection('tenant')->create('timetable_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // change, cancellation, replacement, reminder
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->foreignId('timetable_slot_id')->nullable()->constrained('timetable_slots')->nullOnDelete();
            $table->foreignId('exception_id')->nullable()->constrained('timetable_exceptions')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('timetable_notifications');
        Schema::connection('tenant')->dropIfExists('timetable_notification_settings');
    }
};
