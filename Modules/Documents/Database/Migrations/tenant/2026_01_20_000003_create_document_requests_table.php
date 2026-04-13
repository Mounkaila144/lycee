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
        Schema::connection('tenant')->create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('document_type'); // enrollment_certificate, status_certificate, etc.
            $table->integer('quantity')->default(1);
            $table->text('reason')->nullable();
            $table->enum('urgency', ['normal', 'urgent'])->default('normal');
            $table->date('request_date');
            $table->date('expected_delivery_date')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'processing', 'completed', 'delivered'])->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('generated_document_id')->nullable()->constrained('documents')->onDelete('set null');
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->boolean('fee_paid')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('student_id');
            $table->index('document_type');
            $table->index('status');
            $table->index('request_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('document_requests');
    }
};
