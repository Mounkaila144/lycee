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
        Schema::connection('tenant')->create('progression_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')
                ->nullable()
                ->constrained('programmes')
                ->onDelete('cascade')
                ->comment('NULL = règle globale pour tous programmes');
            $table->enum('from_level', ['L1', 'L2', 'L3', 'M1', 'M2']);
            $table->enum('to_level', ['L1', 'L2', 'L3', 'M1', 'M2']);
            $table->integer('min_credits_required')->comment('Crédits minimum pour passage');
            $table->integer('max_debt_allowed')->default(15)->comment('Dette pédagogique max autorisée');
            $table->boolean('allow_conditional_pass')->default(true)->comment('Autoriser passage avec dette');
            $table->integer('max_repeats_before_exclusion')->default(2)->comment('Nombre max redoublements avant exclusion');
            $table->timestamps();

            // Contraintes
            $table->unique(['programme_id', 'from_level', 'to_level'], 'unique_progression_rule');
            $table->index(['programme_id', 'from_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('progression_rules');
    }
};
