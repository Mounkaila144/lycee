<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story Admin 13 — Réglages de l'établissement.
 *
 * Storage clé/valeur typé pour les configurations tenant (logo, nom, devise,
 * format matricule, échelles de notes, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'integer', 'boolean', 'json', 'file'])->default('string');
            $table->string('category', 50)->nullable()->comment('general, finance, notes, documents, etc.');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
