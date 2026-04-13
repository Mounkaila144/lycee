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
        Schema::connection('tenant')->create('diploma_register', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->string('diploma_number')->unique();
            $table->string('register_number')->unique(); // Physical register entry number
            $table->date('issue_date');
            $table->date('graduation_date');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->enum('honors', ['none', 'passable', 'assez_bien', 'bien', 'tres_bien', 'excellent'])->nullable();
            $table->decimal('final_gpa', 5, 2)->nullable();
            $table->string('diploma_type'); // licence, master, doctorat
            $table->string('specialization')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->onDelete('set null');
            $table->boolean('supplement_generated')->default(false);
            $table->foreignId('supplement_document_id')->nullable()->constrained('documents')->onDelete('set null');
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('original_diploma_id')->nullable()->constrained('diploma_register')->onDelete('set null');
            $table->text('duplicate_reason')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('delivered_at')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_id_type')->nullable();
            $table->string('recipient_id_number')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('student_id');
            $table->index('programme_id');
            $table->index('diploma_number');
            $table->index('register_number');
            $table->index('graduation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('diploma_register');
    }
};
