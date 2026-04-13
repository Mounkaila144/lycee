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
        Schema::connection('tenant')->create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // transcript, diploma, certificate, attestation, card
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('content_template'); // HTML template with placeholders
            $table->longText('header_html')->nullable();
            $table->longText('footer_html')->nullable();
            $table->string('watermark')->nullable(); // Path to watermark image
            $table->json('variables')->nullable(); // Available template variables
            $table->json('settings')->nullable(); // PDF settings, margins, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('document_templates');
    }
};
