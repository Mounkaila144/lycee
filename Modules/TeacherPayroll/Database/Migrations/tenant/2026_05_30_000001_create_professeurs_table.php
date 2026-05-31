<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('professeurs', function (Blueprint $table) {
            $table->id();

            // Lien optionnel vers le compte utilisateur (role Professeur)
            $table->unsignedBigInteger('user_id')->nullable();

            // Identité
            $table->string('matricule', 50)->nullable()->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            $table->enum('sexe', ['M', 'F'])->nullable();
            $table->date('date_naissance')->nullable();

            // Statut professionnel (NE PAS confondre avec le mode de paiement)
            $table->enum('statut_professeur', [
                'PERMANENT',
                'CONTRACTUEL',
                'VACATAIRE',
                'FONCTIONNAIRE_INTERVENANT',
                'INTERVENANT_EXTERNE',
                'REMPLACANT',
            ])->default('PERMANENT');

            // Données académiques / administratives
            $table->string('specialite')->nullable();
            $table->string('niveau_enseignement')->nullable();
            $table->string('diplome')->nullable();
            $table->string('numero_piece_identite')->nullable();
            $table->date('date_recrutement')->nullable();

            $table->boolean('actif')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('statut_professeur');
            $table->index('actif');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('professeurs');
    }
};
