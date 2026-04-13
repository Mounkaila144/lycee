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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->foreignId('student_id')->nullable()->constrained('students')->onDelete('set null');
            $table->string('firstname');
            $table->string('lastname');
            $table->date('birthdate');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('origin_institution');
            $table->string('origin_program');
            $table->string('origin_level', 10);
            $table->foreignId('target_program_id')->constrained('programmes')->onDelete('cascade');
            $table->string('target_level', 10);
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->text('transfer_reason');
            $table->integer('total_ects_claimed')->default(0);
            $table->integer('total_ects_granted')->default(0);
            $table->enum('status', [
                'Submitted',
                'Under_Review',
                'Equivalences_Proposed',
                'Validated',
                'Integrated',
                'Rejected',
            ])->default('Submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('equivalence_certificate_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('transfer_number');
            $table->index('status');
            $table->index('academic_year_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
