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

class ModuleStatisticsSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
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
            'Code Module',
            'Nom Module',
            'Crédits ECTS',
            'Éliminatoire',
            'Enseignant',
            'Effectif',
            'Réussis',
            'Échoués',
            'Absents',
            'Compensés',
            'Moyenne Classe',
            'Taux Réussite',
            'Taux Échec',
            'Note Min',
            'Note Max',
            'Écart-Type',
            'Indicateur',
        ];
    }

    /**
     * @param  array  $row
     */
    public function map($row): array
    {
        return [
            $row['module']['code'] ?? 'N/A',
            $row['module']['name'] ?? 'N/A',
            $row['module']['credits_ects'] ?? 0,
            $row['module']['is_eliminatory'] ? 'Oui' : 'Non',
            $row['teacher']['name'] ?? 'N/A',
            $row['total_students'],
            $row['passed'],
            $row['failed'],
            $row['absent'],
            $row['compensated'],
            $row['class_average'] ?? 'N/A',
            $row['success_rate'].'%',
            $row['failure_rate'].'%',
            $row['min_grade'] ?? 'N/A',
            $row['max_grade'] ?? 'N/A',
            $row['std_deviation'] ?? 'N/A',
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
                    'startColor' => ['argb' => 'FF4472C4'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Statistiques Modules';
    }
}
