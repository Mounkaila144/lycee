<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();

            // Informations de base
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->integer('credits_ects')->unsigned();
            $table->decimal('coefficient', 3, 1);

            // Classification
            $table->enum('type', ['Obligatoire', 'Optionnel']);
            $table->enum('semester', ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8', 'S9', 'S10']);
            $table->enum('level', ['L1', 'L2', 'L3', 'M1', 'M2']);

            // Contenu pédagogique
            $table->text('description')->nullable();

            // Volume horaire
            $table->integer('hours_cm')->unsigned()->nullable()->comment('Cours Magistral');
            $table->integer('hours_td')->unsigned()->nullable()->comment('Travaux Dirigés');
            $table->integer('hours_tp')->unsigned()->nullable()->comment('Travaux Pratiques');

            // Caractéristiques
            $table->boolean('is_eliminatory')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indexes pour performance
            $table->index('code');
            $table->index(['level', 'semester']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
