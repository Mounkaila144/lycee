<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('icon', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['RENT', 'Loyer', '🏠'],
            ['ELECTRICITY', 'Électricité', '💡'],
            ['WATER', 'Eau', '🚰'],
            ['SUPPLIES', 'Fournitures', '📦'],
            ['MAINTENANCE', 'Entretien & maintenance', '🔧'],
            ['INSURANCE', 'Assurance', '🛡️'],
            ['TRANSPORT', 'Transport & carburant', '🚐'],
            ['COMMUNICATION', 'Téléphone & internet', '📞'],
            ['TAXES', 'Impôts & taxes', '🏛️'],
            ['OTHER', 'Autres charges', '📌'],
        ];

        foreach ($defaults as $i => [$code, $name, $icon]) {
            DB::connection('tenant')->table('expense_categories')->insert([
                'code' => $code,
                'name' => $name,
                'icon' => $icon,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('expense_categories');
    }
};
