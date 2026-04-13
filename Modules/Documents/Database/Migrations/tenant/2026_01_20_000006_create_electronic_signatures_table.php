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
        Schema::connection('tenant')->create('electronic_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
            $table->string('signer_name');
            $table->string('signer_title'); // Directeur, Secrétaire Général, etc.
            $table->string('signer_role'); // For authorization tracking
            $table->timestamp('signature_date');
            $table->string('signature_image_path')->nullable(); // Path to signature image
            $table->string('certificate_path')->nullable(); // Digital certificate
            $table->string('signature_hash')->nullable(); // For verification
            $table->boolean('is_valid')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('signature_metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('document_id');
            $table->index('signature_date');
            $table->index('is_valid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('electronic_signatures');
    }
};
