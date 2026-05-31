<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('heures_cours_professeurs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('professeur_id');
            $table->unsignedBigInteger('classe_id')->nullable();
            $table->unsignedBigInteger('matiere_id')->nullable();
            $table->unsignedBigInteger('annee_scolaire_id')->nullable();

            $table->date('date_cours');
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->decimal('nombre_heures', 6, 2)->default(0);

            $table->enum('statut', [
                'PREVU',
                'EFFECTUE',
                'ABSENT',
                'ANNULE',
                'REMPLACE',
                'VALIDE',
                'REJETE',
            ])->default('PREVU');

            // Si remplacé, le professeur remplaçant
            $table->unsignedBigInteger('remplace_par_professeur_id')->nullable();
            $table->string('motif')->nullable();

            $table->unsignedBigInteger('valide_par')->nullable();
            $table->timestamp('date_validation')->nullable();

            $table->timestamps();

            $table->index('professeur_id');
            $table->index('annee_scolaire_id');
            $table->index('statut');
            $table->index('date_cours');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('heures_cours_professeurs');
    }
};
