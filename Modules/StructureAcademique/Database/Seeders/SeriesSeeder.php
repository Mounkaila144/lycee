<?php

namespace Modules\StructureAcademique\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\StructureAcademique\Entities\Series;

class SeriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seriesData = [
            [
                'code' => 'A',
                'name' => 'Littéraire',
                'description' => 'Lettres et Sciences Humaines',
            ],
            [
                'code' => 'C',
                'name' => 'Maths-Physique',
                'description' => 'Mathématiques et Sciences Physiques',
            ],
            [
                'code' => 'D',
                'name' => 'Sciences Naturelles',
                'description' => 'Sciences de la Vie et de la Terre',
            ],
        ];

        foreach ($seriesData as $series) {
            Series::on('tenant')->updateOrCreate(
                ['code' => $series['code']],
                $series
            );
        }
    }
}
