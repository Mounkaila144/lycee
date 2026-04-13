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
        Schema::connection('tenant')->create('attendance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('semester_id')->constrained('semesters');
            $table->enum('alert_type', ['threshold_warning', 'threshold_critical', 'repeated_absences'])->default('threshold_warning');
            $table->integer('absence_count');
            $table->decimal('absence_rate', 5, 2);
            $table->integer('threshold_value');
            $table->text('message');
            $table->enum('status', ['pending', 'notified', 'acknowledged', 'resolved'])->default('pending');
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Index pour recherches rapides
            $table->index(['student_id', 'semester_id']);
            $table->index('status');
            $table->index('alert_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_alerts');
    }
};
