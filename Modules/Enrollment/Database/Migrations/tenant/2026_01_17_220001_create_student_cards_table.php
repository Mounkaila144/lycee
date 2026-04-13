<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        Schema::create('student_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->string('card_number', 50)->unique();
            $table->text('qr_code_data');
            $table->string('qr_signature', 255);
            $table->string('pdf_path')->nullable();
            $table->enum('status', ['Active', 'Expired', 'Suspended', 'Revoked'])->default('Active');
            $table->date('issued_at');
            $table->date('valid_until');
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('original_card_id')->nullable()->constrained('student_cards')->onDelete('set null');
            $table->enum('print_status', ['Pending', 'Printed', 'Delivered'])->default('Pending');
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'academic_year_id']);
            $table->index(['status', 'academic_year_id']);
            $table->index('print_status');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('student_cards');
    }
};
