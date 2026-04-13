<?php

namespace Modules\NotesEvaluations\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Entities\GradeHistory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GradeAuditExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private int $moduleId
    ) {}

    public function collection(): Collection
    {
        $gradeIds = Grade::whereHas('evaluation', function ($q) {
            $q->where('module_id', $this->moduleId);
        })->pluck('id');

        return GradeHistory::whereIn('grade_id', $gradeIds)
            ->with(['grade.student', 'grade.evaluation', 'changedBy'])
            ->orderByDesc('changed_at')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Date/Heure',
            'Matricule',
            'Nom Étudiant',
            'Évaluation',
            'Type',
            'Ancienne Valeur',
            'Nouvelle Valeur',
            'Modifié Par',
            'Motif',
            'Adresse IP',
        ];
    }

    public function map($history): array
    {
        return [
            $history->changed_at->format('d/m/Y H:i:s'),
            $history->grade->student->matricule ?? 'N/A',
            $history->grade->student->full_name ?? 'N/A',
            $history->grade->evaluation->name ?? 'N/A',
            $this->translateChangeType($history->change_type),
            $history->old_is_absent ? 'ABS' : ($history->old_value ?? '-'),
            $history->new_is_absent ? 'ABS' : ($history->new_value ?? '-'),
            $history->changedBy->name ?? 'Système',
            $history->reason ?? '-',
            $history->ip_address ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE0E0E0'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Audit Trail';
    }

    private function translateChangeType(string $type): string
    {
        return match ($type) {
            'creation' => 'Création',
            'modification' => 'Modification',
            'correction' => 'Correction',
            default => $type,
        };
    }
}
