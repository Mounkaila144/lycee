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
        if (Schema::connection('tenant')->hasTable('absence_justifications')) {
            return;
        }
        Schema::connection('tenant')->create('absence_justifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users');
            $table->date('absence_date_from');
            $table->date('absence_date_to');
            $table->enum('type', ['medical', 'family', 'administrative', 'other'])->default('other');
            $table->text('reason');
            $table->string('document_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->dateTime('validated_at')->nullable();
            $table->text('validation_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index pour recherches rapides
            $table->index(['student_id', 'status']);
            $table->index('status');
            $table->index('absence_date_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absence_justifications');
    }
};
