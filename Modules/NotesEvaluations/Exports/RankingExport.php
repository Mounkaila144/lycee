<?php

namespace Modules\NotesEvaluations\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RankingExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        private Collection $ranking,
        private array $mentionDistribution
    ) {}

    public function collection(): Collection
    {
        return $this->ranking;
    }

    public function headings(): array
    {
        return [
            'Rang',
            'Matricule',
            'Nom Complet',
            'Programme',
            'Moyenne',
            'Mention',
            'Crédits Acquis',
            'Crédits Total',
            'Taux Réussite',
        ];
    }

    /**
     * @param  \Modules\NotesEvaluations\Entities\SemesterResult  $result
     */
    public function map($result): array
    {
        return [
            $result->rank,
            $result->student?->matricule ?? 'N/A',
            $result->student?->full_name ?? 'N/A',
            $result->student?->programme?->name ?? 'N/A',
            $result->average ? number_format($result->average, 2) : 'N/A',
            $result->mention,
            $result->acquired_credits,
            $result->total_credits,
            ($result->success_rate ?? 0).'%',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'],
                ],
            ],
        ];

        // Highlight top 3
        $rowCount = $this->ranking->count();

        if ($rowCount >= 1) {
            $styles[2] = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFD700'], // Gold
                ],
            ];
        }

        if ($rowCount >= 2) {
            $styles[3] = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFC0C0C0'], // Silver
                ],
            ];
        }

        if ($rowCount >= 3) {
            $styles[4] = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFCD7F32'], // Bronze
                ],
            ];
        }

        return $styles;
    }

    public function title(): string
    {
        return 'Classement';
    }
}
