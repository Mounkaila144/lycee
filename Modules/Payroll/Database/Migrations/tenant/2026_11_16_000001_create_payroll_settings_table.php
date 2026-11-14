<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paramètres de paie versionnés par date d'effet (historique conservé).
     */
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('payroll_settings')) {
            return;
        }

        Schema::connection('tenant')->create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique()->comment('Numéro de version croissant');
            $table->date('effective_date')->comment("Date d'effet : une période de paie utilise la dernière version en vigueur à sa date de début");
            $table->json('settings')->comment('Devise, cotisations, barème d\'impôt, charges patronales');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payroll_settings');
    }
};
