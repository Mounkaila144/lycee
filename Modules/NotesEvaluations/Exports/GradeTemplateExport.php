<?php

namespace Modules\NotesEvaluations\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GradeTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private ModuleEvaluationConfig $evaluation,
        private bool $includeExistingGrades = false
    ) {}

    public function collection(): Collection
    {
        $enrollments = StudentModuleEnrollment::with('student')
            ->forModule($this->evaluation->module_id)
            ->when($this->evaluation->semester_id, fn ($q) => $q->bySemester($this->evaluation->semester_id))
            ->inscrit()
            ->get();

        // Get existing grades if needed
        $existingGrades = [];
        if ($this->includeExistingGrades) {
            $existingGrades = Grade::where('evaluation_id', $this->evaluation->id)
                ->get()
                ->keyBy('student_id');
        }

        return $enrollments->map(function ($enrollment) use ($existingGrades) {
            $grade = $existingGrades[$enrollment->student_id] ?? null;

            return (object) [
                'student' => $enrollment->student,
                'grade' => $grade,
            ];
        })->sortBy('student.lastname');
    }

    public function headings(): array
    {
        return [
            'Matricule',
            'Nom',
            'Prénom',
            'Note (0-20 ou ABS)',
            'Commentaire',
        ];
    }

    public function map($row): array
    {
        $score = '';
        if ($this->includeExistingGrades && $row->grade) {
            $score = $row->grade->is_absent ? 'ABS' : $row->grade->score;
        }

        return [
            $row->student->matricule,
            $row->student->lastname,
            $row->student->firstname,
            $score,
            $row->grade?->comment ?? '',
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
        return substr($this->evaluation->name, 0, 31);
    }
}
