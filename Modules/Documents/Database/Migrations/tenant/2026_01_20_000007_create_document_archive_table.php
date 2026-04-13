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
        Schema::connection('tenant')->create('document_archive', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
            $table->timestamp('archived_at');
            $table->string('archive_location'); // Storage path or cloud location
            $table->string('archive_format')->default('pdf'); // pdf, pdf/a, etc.
            $table->string('checksum'); // SHA-256 hash for integrity verification
            $table->bigInteger('file_size')->nullable(); // In bytes
            $table->enum('storage_tier', ['hot', 'warm', 'cold', 'glacier'])->default('hot');
            $table->timestamp('last_accessed_at')->nullable();
            $table->integer('access_count')->default(0);
            $table->foreignId('archived_by')->constrained('users')->onDelete('cascade');
            $table->text('archive_notes')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->string('encryption_method')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('document_id');
            $table->index('archived_at');
            $table->index('checksum');
            $table->index('storage_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('document_archive');
    }
};
