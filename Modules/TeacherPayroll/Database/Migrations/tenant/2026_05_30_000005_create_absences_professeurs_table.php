<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('absences_professeurs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('professeur_id');

            $table->date('date_absence');
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->decimal('nombre_heures', 6, 2)->default(0);

            $table->string('motif')->nullable();
            $table->boolean('justifiee')->default(false);
            $table->boolean('impact_sur_paie')->default(false);
            $table->decimal('montant_retenue', 15, 2)->default(0);

            $table->timestamps();

            $table->index('professeur_id');
            $table->index('date_absence');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('absences_professeurs');
    }
};
