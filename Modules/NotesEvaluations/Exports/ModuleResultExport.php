<?php

namespace Modules\NotesEvaluations\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\NotesEvaluations\Entities\ModuleResult;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ModuleResultExport implements WithMultipleSheets
{
    public function __construct(
        private ?ModuleResult $result,
        private array $studentsByStatus,
        private Module $module,
        private Semester $semester
    ) {}

    public function sheets(): array
    {
        return [
            new StatisticsSheet($this->result, $this->module, $this->semester),
            new StudentsSheet($this->studentsByStatus['validated'], 'Validés', $this->module),
            new StudentsSheet($this->studentsByStatus['failed'], 'Non validés', $this->module),
            new StudentsSheet($this->studentsByStatus['absent'], 'Absents', $this->module),
        ];
    }
}

class StatisticsSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private ?ModuleResult $result,
        private Module $module,
        private Semester $semester
    ) {}

    public function collection(): Collection
    {
        if (! $this->result) {
            return collect([['Aucune donnée disponible']]);
        }

        return collect([
            ['Module', $this->module->code.' - '.$this->module->name],
            ['Semestre', $this->semester->name],
            ['Date de génération', $this->result->generated_at?->format('d/m/Y H:i')],
            ['', ''],
            ['Effectif total', $this->result->total_students],
            ['Moyenne de classe', $this->result->class_average],
            ['Note minimale', $this->result->min_grade],
            ['Note maximale', $this->result->max_grade],
            ['Médiane', $this->result->median],
            ['Écart-type', $this->result->standard_deviation],
            ['Taux de réussite', $this->result->pass_rate.'%'],
            ['Taux d\'absence', $this->result->absence_rate.'%'],
            ['', ''],
            ['Distribution des notes', ''],
            ['0-5', $this->result->distribution['0-5'] ?? 0],
            ['5-10', $this->result->distribution['5-10'] ?? 0],
            ['10-15', $this->result->distribution['10-15'] ?? 0],
            ['15-20', $this->result->distribution['15-20'] ?? 0],
        ]);
    }

    public function headings(): array
    {
        return ['Statistiques', 'Valeur'];
    }

    public function title(): string
    {
        return 'Statistiques';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

class StudentsSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private Collection $students,
        private string $title,
        private Module $module
    ) {}

    public function collection(): Collection
    {
        return $this->students;
    }

    public function headings(): array
    {
        return [
            'Rang',
            'Matricule',
            'Nom',
            'Prénom',
            'Moyenne',
            'Mention',
            'Statut',
        ];
    }

    public function map($grade): array
    {
        return [
            $grade->rank_display ?? '-',
            $grade->student?->matricule ?? 'N/A',
            $grade->student?->lastname ?? 'N/A',
            $grade->student?->firstname ?? 'N/A',
            $grade->average ?? 'ABS',
            $grade->mention,
            $grade->status_label,
        ];
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
