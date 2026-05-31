<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('paies_professeurs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('professeur_id');
            $table->unsignedBigInteger('contrat_professeur_id')->nullable();
            $table->unsignedBigInteger('periode_paie_id');

            // Copie figée des paramètres au moment du calcul
            $table->enum('mode_paiement', ['MENSUEL', 'HORAIRE', 'HYBRIDE', 'FORFAIT']);
            $table->decimal('salaire_base', 15, 2)->default(0);
            $table->decimal('taux_horaire', 15, 2)->default(0);
            $table->decimal('taux_heure_supplementaire', 15, 2)->default(0);
            $table->decimal('nombre_heures_validees', 8, 2)->default(0);
            $table->decimal('nombre_heures_supplementaires', 8, 2)->default(0);
            $table->decimal('montant_heures', 15, 2)->default(0);
            $table->decimal('montant_heures_supplementaires', 15, 2)->default(0);
            $table->decimal('montant_forfait', 15, 2)->default(0);

            $table->decimal('total_primes', 15, 2)->default(0);
            $table->decimal('total_retenues', 15, 2)->default(0);
            $table->decimal('total_avances', 15, 2)->default(0);

            $table->decimal('montant_brut', 15, 2)->default(0);
            $table->decimal('net_a_payer', 15, 2)->default(0);

            // Solde reporté si le net calculé serait négatif (plafonné à 0)
            $table->decimal('solde_reporte', 15, 2)->default(0);

            // Montant déjà réglé (paiements partiels)
            $table->decimal('montant_paye', 15, 2)->default(0);

            $table->enum('statut', [
                'BROUILLON',
                'CALCULEE',
                'VALIDEE',
                'PARTIELLEMENT_PAYEE',
                'PAYEE',
                'ANNULEE',
            ])->default('BROUILLON');

            $table->timestamp('date_calcul')->nullable();
            $table->timestamp('date_validation')->nullable();
            $table->timestamp('date_paiement')->nullable();

            $table->enum('mode_reglement', ['ESPECES', 'MOBILE_MONEY', 'VIREMENT_BANCAIRE', 'CHEQUE', 'AUTRE'])->nullable();
            $table->string('reference_paiement')->nullable();

            // Détail figé des primes/retenues utilisées au calcul
            $table->json('details_primes')->nullable();
            $table->json('details_retenues')->nullable();

            $table->unsignedBigInteger('calcule_par')->nullable();
            $table->unsignedBigInteger('valide_par')->nullable();

            $table->timestamps();

            $table->unique(['professeur_id', 'periode_paie_id']);
            $table->index('periode_paie_id');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('paies_professeurs');
    }
};
