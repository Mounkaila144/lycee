<?php

namespace Modules\NotesEvaluations\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PublicationSummaryExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private array $summary
    ) {}

    public function sheets(): array
    {
        return [
            new PublicationResultsSheet($this->summary['results']),
            new PublicationStatisticsSheet($this->summary),
        ];
    }
}

class PublicationResultsSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private array $results
    ) {}

    public function array(): array
    {
        return array_map(fn ($r) => [
            $r['rank'],
            $r['matricule'],
            $r['full_name'],
            $r['average'],
            $r['mention'],
            $r['global_status'],
            $r['validated_modules'],
            $r['compensated_modules'],
            $r['failed_modules'],
            $r['acquired_credits'],
            $r['total_credits'],
        ], $this->results);
    }

    public function headings(): array
    {
        return [
            'Rang',
            'Matricule',
            'Nom complet',
            'Moyenne',
            'Mention',
            'Statut',
            'Modules validés',
            'Modules compensés',
            'Modules échoués',
            'Crédits acquis',
            'Crédits totaux',
        ];
    }

    public function title(): string
    {
        return 'Résultats';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

class PublicationStatisticsSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private array $summary
    ) {}

    public function array(): array
    {
        $pub = $this->summary['publication'];
        $stats = $this->summary['statistics'];

        return [
            ['Type de publication', $pub['type']],
            ['Portée', $pub['scope']],
            ['Publié le', $pub['published_at']],
            ['Publié par', $pub['published_by']],
            ['', ''],
            ['Statistiques générales', ''],
            ['Nombre total d\'étudiants', $stats['total_students']],
            ['Étudiants validés', $stats['validated_count']],
            ['Étudiants non validés', $stats['failed_count']],
            ['Taux de réussite', $stats['success_rate'].'%'],
            ['Moyenne générale', $stats['average']],
            ['Note minimale', $stats['min_average']],
            ['Note maximale', $stats['max_average']],
            ['', ''],
            ['Par statut global', ''],
            ['Validés', $stats['by_global_status']['validated']],
            ['Partiellement validés', $stats['by_global_status']['partially_validated']],
            ['À rattraper', $stats['by_global_status']['to_retake']],
            ['Ajournés', $stats['by_global_status']['deferred']],
            ['', ''],
            ['Par mention', ''],
            ['Très Bien (≥16)', $stats['by_mention']['tres_bien']],
            ['Bien (14-16)', $stats['by_mention']['bien']],
            ['Assez Bien (12-14)', $stats['by_mention']['assez_bien']],
            ['Passable (10-12)', $stats['by_mention']['passable']],
            ['Insuffisant (<10)', $stats['by_mention']['insuffisant']],
        ];
    }

    public function headings(): array
    {
        return ['Indicateur', 'Valeur'];
    }

    public function title(): string
    {
        return 'Statistiques';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            6 => ['font' => ['bold' => true]],
            15 => ['font' => ['bold' => true]],
            21 => ['font' => ['bold' => true]],
        ];
    }
}
