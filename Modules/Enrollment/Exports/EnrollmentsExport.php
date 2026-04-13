<?php

namespace Modules\Enrollment\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\StructureAcademique\Entities\AcademicYear;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EnrollmentsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private AcademicYear $year,
        private array $filters = []
    ) {}

    public function query(): Builder
    {
        $query = StudentEnrollment::on('tenant')
            ->where('academic_year_id', $this->year->id)
            ->with(['student', 'programme', 'academicYear']);

        if (! empty($this->filters['programme_id'])) {
            $query->where('programme_id', $this->filters['programme_id']);
        }

        if (! empty($this->filters['level'])) {
            $query->where('level', $this->filters['level']);
        }

        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'N°',
            'Matricule',
            'Nom',
            'Prénom',
            'Programme',
            'Niveau',
            'Statut Inscription',
            'Statut Étudiant',
            'Email',
            'Téléphone',
            'Sexe',
            'Date de naissance',
            'Nationalité',
            'Ville',
            'Date d\'inscription',
        ];
    }

    /**
     * @param  StudentEnrollment  $enrollment
     */
    public function map($enrollment): array
    {
        static $index = 0;
        $index++;

        $student = $enrollment->student;
        $programme = $enrollment->programme;

        return [
            $index,
            $student?->matricule ?? '-',
            $student?->lastname ?? '-',
            $student?->firstname ?? '-',
            $programme?->libelle ?? '-',
            $enrollment->level ?? '-',
            $enrollment->status ?? '-',
            $student?->status ?? '-',
            $student?->email ?? '-',
            $student?->phone ?? '-',
            $this->formatSex($student?->sex),
            $student?->birthdate?->format('d/m/Y') ?? '-',
            $student?->nationality ?? '-',
            $student?->city ?? '-',
            $enrollment->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return "Inscriptions {$this->year->name}";
    }

    private function formatSex(?string $sex): string
    {
        return match ($sex) {
            'M' => 'Masculin',
            'F' => 'Féminin',
            'O' => 'Autre',
            default => '-',
        };
    }
}
