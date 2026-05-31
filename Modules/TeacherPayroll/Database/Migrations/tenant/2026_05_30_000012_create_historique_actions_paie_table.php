<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('historique_actions_paie', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('entite')->nullable();
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->json('ancienne_valeur')->nullable();
            $table->json('nouvelle_valeur')->nullable();
            $table->timestamp('date_action')->nullable();
            $table->string('adresse_ip', 45)->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index(['entite', 'entite_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('historique_actions_paie');
    }
};
