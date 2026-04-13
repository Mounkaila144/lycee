<?php

namespace Modules\StructureAcademique\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Series;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;

class SubjectCoefficientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Format: 'SUBJECT_CODE' => [coefficient, hours_per_week]
     */
    public function run(): void
    {
        // Ensure prerequisites exist
        (new CycleAndLevelSeeder)->run();
        (new SeriesSeeder)->run();
        (new SubjectSeeder)->run();

        $levels = Level::on('tenant')->pluck('id', 'code');
        $series = Series::on('tenant')->pluck('id', 'code');
        $subjects = Subject::on('tenant')->pluck('id', 'code');

        // 6e/5e (Tronc commun - no series)
        $sixCinq = [
            'FRAN' => [4, 6], 'MATH' => [4, 5], 'ANG' => [2, 3],
            'HG' => [2, 3], 'SVT' => [2, 2], 'EC' => [1, 1], 'EPS' => [1, 2],
        ];

        foreach (['6E', '5E'] as $levelCode) {
            $this->seedCoefficients($subjects, $levels[$levelCode], null, $sixCinq);
        }

        // 4e/3e (Tronc commun - no series)
        $quatTrois = [
            'FRAN' => [4, 5], 'MATH' => [4, 5], 'PHYS' => [3, 3],
            'SVT' => [2, 2], 'ANG' => [2, 3], 'HG' => [2, 3],
            'EC' => [1, 1], 'EPS' => [1, 2],
        ];

        foreach (['4E', '3E'] as $levelCode) {
            $this->seedCoefficients($subjects, $levels[$levelCode], null, $quatTrois);
        }

        // 2nde (Tronc commun - no series, same as 4e/3e)
        $this->seedCoefficients($subjects, $levels['2NDE'], null, $quatTrois);

        // Tle A
        $tleA = [
            'PHIL' => [5, 6], 'FRAN' => [4, 5], 'HG' => [4, 4],
            'ANG' => [3, 3], 'MATH' => [2, 3], 'EPS' => [1, 2],
        ];
        $this->seedCoefficients($subjects, $levels['TLE'], $series['A'], $tleA);

        // Tle C
        $tleC = [
            'MATH' => [5, 6], 'PHYS' => [5, 5], 'SVT' => [2, 2],
            'FRAN' => [2, 3], 'PHIL' => [2, 3], 'ANG' => [2, 3],
            'HG' => [2, 2], 'EPS' => [1, 2],
        ];
        $this->seedCoefficients($subjects, $levels['TLE'], $series['C'], $tleC);

        // Tle D
        $tleD = [
            'SVT' => [5, 5], 'MATH' => [4, 5], 'PHYS' => [4, 4],
            'FRAN' => [2, 3], 'PHIL' => [2, 3], 'ANG' => [2, 3],
            'HG' => [2, 2], 'EPS' => [1, 2],
        ];
        $this->seedCoefficients($subjects, $levels['TLE'], $series['D'], $tleD);

        // 1ere A/C/D (same as Tle respectively)
        $this->seedCoefficients($subjects, $levels['1ERE'], $series['A'], $tleA);
        $this->seedCoefficients($subjects, $levels['1ERE'], $series['C'], $tleC);
        $this->seedCoefficients($subjects, $levels['1ERE'], $series['D'], $tleD);
    }

    /**
     * Seed coefficients for a given level/series combination.
     *
     * @param  \Illuminate\Support\Collection  $subjects  subject code => id map
     * @param  int  $levelId
     * @param  int|null  $seriesId
     * @param  array<string, array{0: int|float, 1: int}>  $data  subject_code => [coefficient, hours]
     */
    private function seedCoefficients($subjects, int $levelId, ?int $seriesId, array $data): void
    {
        foreach ($data as $subjectCode => [$coefficient, $hours]) {
            if (! isset($subjects[$subjectCode])) {
                continue;
            }

            SubjectClassCoefficient::on('tenant')->updateOrCreate(
                [
                    'subject_id' => $subjects[$subjectCode],
                    'level_id' => $levelId,
                    'series_id' => $seriesId,
                ],
                [
                    'coefficient' => $coefficient,
                    'hours_per_week' => $hours,
                ]
            );
        }
    }
}
