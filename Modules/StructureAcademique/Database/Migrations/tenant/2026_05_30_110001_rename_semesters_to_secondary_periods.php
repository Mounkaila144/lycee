<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Secondaire : les périodes ne sont plus libellées « S1 / S2 » (connotation
 * universitaire) mais « 1er Semestre / 2e Semestre ». La colonne enum est
 * élargie en chaîne pour permettre des libellés lisibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('semesters', function (Blueprint $table) {
            $table->string('name', 50)->comment('Période (ex: 1er Semestre)')->change();
        });

        DB::connection('tenant')->table('semesters')->where('name', 'S1')->update(['name' => '1er Semestre']);
        DB::connection('tenant')->table('semesters')->where('name', 'S2')->update(['name' => '2e Semestre']);
    }

    public function down(): void
    {
        DB::connection('tenant')->table('semesters')->where('name', '1er Semestre')->update(['name' => 'S1']);
        DB::connection('tenant')->table('semesters')->where('name', '2e Semestre')->update(['name' => 'S2']);

        Schema::connection('tenant')->table('semesters', function (Blueprint $table) {
            $table->enum('name', ['S1', 'S2'])->comment('Semestre 1 ou 2')->change();
        });
    }
};
