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
        Schema::create('teacher_module_assignments', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');

            // Group is optional - FK constraint will be added when groups table is created
            $table->unsignedBigInteger('group_id')->nullable();

            // Détails de l'affectation
            $table->enum('level', ['L1', 'L2', 'L3', 'M1', 'M2']);
            $table->enum('type', ['CM', 'TD', 'TP'])->comment('Type d\'intervention: Cours Magistral, Travaux Dirigés, Travaux Pratiques');
            $table->integer('hours_allocated')->unsigned()->comment('Heures affectées pour cette intervention');

            // Statut et remplacement
            $table->enum('status', ['Active', 'Replaced', 'Cancelled'])->default('Active');
            $table->foreignId('replaced_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('replacement_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Contrainte d'unicité
            $table->unique(
                ['teacher_id', 'module_id', 'programme_id', 'level', 'group_id', 'semester_id', 'type'],
                'unique_teacher_assignment'
            );

            // Indexes pour performance
            $table->index(['teacher_id', 'semester_id']);
            $table->index(['module_id', 'semester_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_module_assignments');
    }
};
