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
        Schema::connection('tenant')->create('verification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
            $table->timestamp('verified_at');
            $table->enum('verification_method', ['qr_code', 'document_number', 'api', 'manual'])->default('qr_code');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('verification_successful')->default(true);
            $table->text('verification_notes')->nullable();
            $table->json('request_data')->nullable();
            $table->timestamps();

            $table->index('document_id');
            $table->index('verified_at');
            $table->index('verification_method');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('verification_log');
    }
};
