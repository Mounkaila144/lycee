<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasColumn('periodes_paie', 'est_vacances')) {
            Schema::connection('tenant')->table('periodes_paie', function (Blueprint $table) {
                $table->boolean('est_vacances')->default(false)->after('statut');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasColumn('periodes_paie', 'est_vacances')) {
            Schema::connection('tenant')->table('periodes_paie', function (Blueprint $table) {
                $table->dropColumn('est_vacances');
            });
        }
    }
};
