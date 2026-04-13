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
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();

            // Informations de base
            $table->string('code', 20)->unique();
            $table->string('libelle');
            $table->enum('type', ['Licence', 'Master', 'Doctorat']);
            $table->integer('duree_annees')->unsigned();
            $table->text('description')->nullable();

            // Responsable du programme
            $table->foreignId('responsable_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Statut du programme
            $table->enum('statut', ['Brouillon', 'Actif', 'Inactif', 'Archivé'])
                ->default('Brouillon');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index('type');
            $table->index('statut');
            $table->index('responsable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
