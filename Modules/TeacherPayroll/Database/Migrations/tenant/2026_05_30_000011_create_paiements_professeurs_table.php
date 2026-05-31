<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('paiements_professeurs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('paie_professeur_id');
            $table->decimal('montant', 15, 2)->default(0);
            $table->date('date_paiement');

            $table->enum('mode_reglement', ['ESPECES', 'MOBILE_MONEY', 'VIREMENT_BANCAIRE', 'CHEQUE', 'AUTRE'])->default('ESPECES');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();

            $table->unsignedBigInteger('enregistre_par')->nullable();

            $table->timestamps();

            $table->index('paie_professeur_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('paiements_professeurs');
    }
};
