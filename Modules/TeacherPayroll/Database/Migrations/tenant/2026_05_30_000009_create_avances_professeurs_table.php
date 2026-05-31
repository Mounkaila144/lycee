<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('avances_professeurs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('professeur_id');
            $table->decimal('montant', 15, 2)->default(0);
            $table->date('date_avance');
            $table->string('motif')->nullable();

            $table->enum('statut', ['EN_ATTENTE', 'APPROUVEE', 'DEDUITE', 'ANNULEE'])->default('EN_ATTENTE');

            // Période sur laquelle l'avance est déduite
            $table->unsignedBigInteger('periode_deduction_id')->nullable();

            // Montant déjà déduit (gestion de la déduction partielle)
            $table->decimal('montant_deduit', 15, 2)->default(0);

            $table->timestamps();

            $table->index('professeur_id');
            $table->index('statut');
            $table->index('periode_deduction_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('avances_professeurs');
    }
};
