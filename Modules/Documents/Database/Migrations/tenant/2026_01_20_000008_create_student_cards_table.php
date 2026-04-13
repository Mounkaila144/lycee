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
        if (Schema::connection('tenant')->hasTable('student_cards')) {
            return;
        }
        Schema::connection('tenant')->create('student_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('card_number')->unique();
            $table->string('card_type')->default('student_id'); // student_id, access_badge
            $table->date('issue_date');
            $table->date('expiry_date');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->string('photo_path')->nullable();
            $table->string('qr_code')->unique(); // QR code content for scanning
            $table->string('qr_code_path')->nullable(); // Path to QR code image
            $table->string('barcode')->nullable(); // Optional barcode
            $table->string('barcode_path')->nullable();
            $table->enum('status', ['active', 'expired', 'lost', 'stolen', 'suspended', 'replaced'])->default('active');
            $table->json('access_permissions')->nullable(); // Building access, library, etc.
            $table->boolean('is_printed')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('replaced_by_card_id')->nullable()->constrained('student_cards')->onDelete('set null');
            $table->text('replacement_reason')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index('student_id');
            $table->index('card_number');
            $table->index('card_type');
            $table->index('qr_code');
            $table->index('status');
            $table->index(['issue_date', 'expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_cards');
    }
};
