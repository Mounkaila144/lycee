<?php

namespace Modules\StructureAcademique\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\StructureAcademique\Entities\Cycle;
use Modules\StructureAcademique\Entities\Level;

class CycleAndLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $college = Cycle::on('tenant')->updateOrCreate(
            ['code' => 'COL'],
            [
                'name' => 'Collège (1er cycle)',
                'description' => 'Enseignement du 1er cycle',
                'display_order' => 1,
                'is_active' => true,
            ]
        );

        $lycee = Cycle::on('tenant')->updateOrCreate(
            ['code' => 'LYC'],
            [
                'name' => 'Lycée (2nd cycle)',
                'description' => 'Enseignement du 2nd cycle',
                'display_order' => 2,
                'is_active' => true,
            ]
        );

        // Niveaux Collège
        $collegeLevels = [
            ['code' => '6E', 'name' => 'Sixième', 'order_index' => 1],
            ['code' => '5E', 'name' => 'Cinquième', 'order_index' => 2],
            ['code' => '4E', 'name' => 'Quatrième', 'order_index' => 3],
            ['code' => '3E', 'name' => 'Troisième', 'order_index' => 4],
        ];

        foreach ($collegeLevels as $level) {
            Level::on('tenant')->updateOrCreate(
                ['code' => $level['code']],
                array_merge($level, ['cycle_id' => $college->id])
            );
        }

        // Niveaux Lycée
        $lyceeLevels = [
            ['code' => '2NDE', 'name' => 'Seconde', 'order_index' => 5],
            ['code' => '1ERE', 'name' => 'Première', 'order_index' => 6],
            ['code' => 'TLE', 'name' => 'Terminale', 'order_index' => 7],
        ];

        foreach ($lyceeLevels as $level) {
            Level::on('tenant')->updateOrCreate(
                ['code' => $level['code']],
                array_merge($level, ['cycle_id' => $lycee->id])
            );
        }
    }
}
