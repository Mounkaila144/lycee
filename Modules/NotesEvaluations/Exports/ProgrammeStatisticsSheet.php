<?php

namespace Modules\NotesEvaluations\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProgrammeStatisticsSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private Collection $stats
    ) {}

    public function collection(): Collection
    {
        return $this->stats;
    }

    public function headings(): array
    {
        return [
            'Code Programme',
            'Nom Programme',
            'Effectif',
            'Moyenne Classe',
            'Taux Réussite',
            'Taux Admission Complète',
            'Taux Échec',
            'ECTS Moyen Acquis',
            'ECTS Total',
            'Taux Complétion ECTS',
            'Indicateur',
        ];
    }

    /**
     * @param  array  $row
     */
    public function map($row): array
    {
        return [
            $row['programme_code'] ?? 'N/A',
            $row['programme_name'] ?? 'N/A',
            $row['total_students'],
            $row['class_average'] ?? 'N/A',
            $row['success_rate'].'%',
            $row['full_admission_rate'].'%',
            $row['failure_rate'].'%',
            $row['avg_credits_acquired'],
            $row['avg_credits_total'],
            $row['credits_completion_rate'].'%',
            $this->getIndicatorLabel($row['status_indicator']),
        ];
    }

    protected function getIndicatorLabel(string $indicator): string
    {
        return match ($indicator) {
            'red' => 'Critique (<50%)',
            'orange' => 'Attention (50-70%)',
            'green' => 'Bon (≥70%)',
            default => 'N/A',
        };
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
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
        return 'Statistiques Programmes';
    }
}
