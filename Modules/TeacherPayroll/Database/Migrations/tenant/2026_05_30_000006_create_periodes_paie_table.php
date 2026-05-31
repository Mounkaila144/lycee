<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('periodes_paie', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('annee_scolaire_id')->nullable();
            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('annee');

            $table->date('date_debut');
            $table->date('date_fin');

            $table->enum('statut', ['OUVERTE', 'EN_CALCUL', 'VALIDEE', 'PAYEE', 'CLOTUREE'])->default('OUVERTE');

            // Période correspondant aux vacances scolaires (impacte le paiement mensuel)
            $table->boolean('est_vacances')->default(false);

            $table->timestamps();

            $table->unique(['annee', 'mois']);
            $table->index('annee_scolaire_id');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('periodes_paie');
    }
};
