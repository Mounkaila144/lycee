<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('affectations_professeurs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('professeur_id');
            $table->unsignedBigInteger('classe_id');
            $table->unsignedBigInteger('matiere_id');
            $table->unsignedBigInteger('annee_scolaire_id')->nullable();

            $table->decimal('volume_horaire_hebdomadaire', 8, 2)->nullable();
            $table->decimal('coefficient', 5, 2)->nullable();

            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();

            $table->boolean('actif')->default(true);

            $table->timestamps();

            $table->index('professeur_id');
            $table->index('classe_id');
            $table->index('matiere_id');
            $table->index('annee_scolaire_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('affectations_professeurs');
    }
};
