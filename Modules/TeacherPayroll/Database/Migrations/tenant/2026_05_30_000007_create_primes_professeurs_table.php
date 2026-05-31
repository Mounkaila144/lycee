<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('primes_professeurs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('professeur_id');
            $table->unsignedBigInteger('periode_paie_id')->nullable();

            $table->string('type_prime')->nullable();
            $table->string('libelle');
            $table->decimal('montant', 15, 2)->default(0);
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index('professeur_id');
            $table->index('periode_paie_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('primes_professeurs');
    }
};
