<?php

namespace Modules\NotesEvaluations\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DistributionSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private array $distribution
    ) {}

    public function array(): array
    {
        $dist = $this->distribution['distribution'] ?? [];
        $percentages = $this->distribution['percentages'] ?? [];
        $stats = $this->distribution['statistics'] ?? [];

        $rows = [
            ['Distribution des Notes'],
            [],
            ['Tranche', 'Effectif', 'Pourcentage'],
        ];

        foreach ($dist as $range => $count) {
            $percentage = $percentages[$range] ?? 0;
            $rows[] = [$range, $count, $percentage.'%'];
        }

        $rows[] = [];
        $rows[] = ['Statistiques Descriptives'];
        $rows[] = [];
        $rows[] = ['Métrique', 'Valeur'];
        $rows[] = ['Moyenne', $stats['mean'] ?? 'N/A'];
        $rows[] = ['Médiane', $stats['median'] ?? 'N/A'];
        $rows[] = ['Écart-type', $stats['std_deviation'] ?? 'N/A'];
        $rows[] = ['Note minimale', $stats['min'] ?? 'N/A'];
        $rows[] = ['Note maximale', $stats['max'] ?? 'N/A'];
        $rows[] = ['Nombre de notes', $stats['count'] ?? 0];

        return $rows;
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
            12 => ['font' => ['bold' => true, 'size' => 12]],
            14 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF70AD47'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Distribution Notes';
    }
}
