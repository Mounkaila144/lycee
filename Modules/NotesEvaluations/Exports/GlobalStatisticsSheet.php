<?php

namespace Modules\NotesEvaluations\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\StructureAcademique\Entities\Semester;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GlobalStatisticsSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private array $stats,
        private ?Semester $semester = null
    ) {}

    public function array(): array
    {
        $semesterName = $this->semester?->name ?? 'N/A';

        return [
            ['Statistiques Globales - '.$semesterName],
            [],
            ['Métrique', 'Valeur'],
            ['Total étudiants', $this->stats['total_students'] ?? 0],
            [],
            ['Taux', '%'],
            ['Taux de réussite', ($this->stats['rates']['success_rate'] ?? 0).'%'],
            ['Taux d\'admission complète', ($this->stats['rates']['admission_rate'] ?? 0).'%'],
            ['Taux admis avec dettes', ($this->stats['rates']['admitted_with_debts_rate'] ?? 0).'%'],
            ['Taux de compensation', ($this->stats['rates']['compensation_rate'] ?? 0).'%'],
            ['Taux de rattrapage', ($this->stats['rates']['retake_rate'] ?? 0).'%'],
            ['Taux d\'échec', ($this->stats['rates']['failure_rate'] ?? 0).'%'],
            [],
            ['Effectifs', 'Nombre'],
            ['Admis', $this->stats['counts']['admitted'] ?? 0],
            ['Admis avec dettes', $this->stats['counts']['admitted_with_debts'] ?? 0],
            ['Ajournés définitifs', $this->stats['counts']['deferred_final'] ?? 0],
            ['Redoublants', $this->stats['counts']['repeating'] ?? 0],
            ['En rattrapage', $this->stats['counts']['to_retake'] ?? 0],
            ['Avec compensation', $this->stats['counts']['with_compensation'] ?? 0],
            [],
            ['Moyennes', 'Valeur'],
            ['Moyenne de classe', $this->stats['averages']['class_average'] ?? 'N/A'],
            ['ECTS moyen acquis', $this->stats['averages']['average_ects'] ?? 0],
            ['Taux succès moyen', ($this->stats['averages']['average_success_rate'] ?? 0).'%'],
            [],
            ['Impact Rattrapage', 'Valeur'],
            ['Étudiants en rattrapage', $this->stats['retake_impact']['students_with_retake'] ?? 0],
            ['Améliorés par rattrapage', $this->stats['retake_impact']['improved_by_retake'] ?? 0],
            ['Taux réussite rattrapage', ($this->stats['retake_impact']['retake_success_rate'] ?? 0).'%'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            3 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'],
                ],
            ],
            6 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF70AD47'],
                ],
            ],
            14 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFED7D31'],
                ],
            ],
            22 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF5B9BD5'],
                ],
            ],
            27 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF7030A0'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Statistiques Globales';
    }
}
