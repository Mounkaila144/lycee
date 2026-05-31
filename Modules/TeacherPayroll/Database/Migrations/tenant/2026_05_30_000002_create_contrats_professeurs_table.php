<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('contrats_professeurs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('professeur_id');
            $table->unsignedBigInteger('annee_scolaire_id')->nullable();

            $table->date('date_debut');
            $table->date('date_fin')->nullable();

            // Type de contrat (miroir possible du statut professeur)
            $table->string('type_contrat')->nullable();

            // Mode de paiement (indépendant du statut)
            $table->enum('mode_paiement', ['MENSUEL', 'HORAIRE', 'HYBRIDE', 'FORFAIT'])->default('MENSUEL');

            // Paramètres de rémunération
            $table->decimal('salaire_base', 15, 2)->default(0);
            $table->decimal('taux_horaire', 15, 2)->default(0);
            $table->decimal('taux_heure_supplementaire', 15, 2)->default(0);
            $table->decimal('montant_forfait', 15, 2)->default(0);

            $table->decimal('volume_horaire_mensuel_prevu', 8, 2)->nullable();
            $table->decimal('volume_horaire_hebdomadaire_prevu', 8, 2)->nullable();

            $table->boolean('paiement_pendant_vacances')->default(false);
            $table->string('devise', 10)->default('FCFA');

            $table->enum('statut', ['ACTIF', 'SUSPENDU', 'TERMINE'])->default('ACTIF');

            $table->timestamps();
            $table->softDeletes();

            $table->index('professeur_id');
            $table->index('annee_scolaire_id');
            $table->index('mode_paiement');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contrats_professeurs');
    }
};
