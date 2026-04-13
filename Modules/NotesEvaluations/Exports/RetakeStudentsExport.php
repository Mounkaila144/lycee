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
use Modules\NotesEvaluations\Entities\RetakeEnrollment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RetakeStudentsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        private int $semesterId,
        private ?int $moduleId = null
    ) {}

    public function collection(): Collection
    {
        $query = RetakeEnrollment::where('semester_id', $this->semesterId)
            ->active()
            ->with(['student', 'module']);

        if ($this->moduleId) {
            $query->where('module_id', $this->moduleId);
        }

        return $query->orderBy('student_id')->get();
    }

    public function headings(): array
    {
        return [
            'Matricule',
            'Nom',
            'Prénom',
            'Module (Code)',
            'Module (Nom)',
            'Moyenne Initiale',
            'Écart à 10',
            'Statut',
            'Date Identification',
            'Date Programmation',
        ];
    }

    public function map($retake): array
    {
        return [
            $retake->student->matricule ?? 'N/A',
            $retake->student->lastname ?? 'N/A',
            $retake->student->firstname ?? 'N/A',
            $retake->module->code ?? 'N/A',
            $retake->module->name ?? 'N/A',
            $retake->original_average !== null ? number_format($retake->original_average, 2) : 'ABS',
            $retake->gap_to_validation !== null ? number_format($retake->gap_to_validation, 2) : '-',
            $retake->status_label,
            $retake->identified_at?->format('d/m/Y H:i') ?? '-',
            $retake->scheduled_at?->format('d/m/Y H:i') ?? '-',
        ];
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
        return $this->moduleId ? 'Rattrapages Module' : 'Rattrapages Semestre';
    }
}
