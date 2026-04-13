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

class RetakeGradeTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        private int $moduleId,
        private int $semesterId
    ) {}

    public function collection(): Collection
    {
        return RetakeEnrollment::where('module_id', $this->moduleId)
            ->where('semester_id', $this->semesterId)
            ->active()
            ->with(['student', 'retakeGrade'])
            ->orderBy('student_id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID Inscription',
            'Matricule',
            'Nom',
            'Prénom',
            'Moyenne Initiale',
            'Note Rattrapage',
            'Absent (O/N)',
            'Nouvelle Moyenne',
            'Commentaire',
        ];
    }

    public function map($enrollment): array
    {
        $retakeGrade = $enrollment->retakeGrade;
        $newAverage = $retakeGrade?->new_average ?? $enrollment->original_average;

        return [
            $enrollment->id,
            $enrollment->student?->matricule ?? 'N/A',
            $enrollment->student?->lastname ?? 'N/A',
            $enrollment->student?->firstname ?? 'N/A',
            $enrollment->original_average !== null ? number_format($enrollment->original_average, 2) : 'ABS',
            $retakeGrade?->score !== null ? number_format($retakeGrade->score, 2) : '',
            $retakeGrade?->is_absent ? 'O' : '',
            $newAverage !== null ? number_format($newAverage, 2) : '',
            $retakeGrade?->comment ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Lock columns A-E (ID, Matricule, Nom, Prénom, Moyenne Initiale)
        // Columns F, G, I are editable (Note Rattrapage, Absent, Commentaire)
        // Column H (Nouvelle Moyenne) is calculated

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'],
                ],
            ],
            // Highlight editable columns
            'F' => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFFCC'],
                ],
            ],
            'G' => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFFCC'],
                ],
            ],
            'I' => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFFCC'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Notes Rattrapage';
    }
}
