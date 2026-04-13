<?php

namespace Modules\Enrollment\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Modules\Enrollment\Entities\Student;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    private ?string $search = null;

    private ?string $status = null;

    private ?string $sex = null;

    private ?string $nationality = null;

    private string $sortBy = 'created_at';

    private string $sortOrder = 'desc';

    public function __construct(array $filters = [])
    {
        $this->search = $filters['search'] ?? null;
        $this->status = $filters['status'] ?? null;
        $this->sex = $filters['sex'] ?? null;
        $this->nationality = $filters['nationality'] ?? null;
        $this->sortBy = $filters['sort_by'] ?? 'created_at';
        $this->sortOrder = $filters['sort_order'] ?? 'desc';
    }

    public function query(): Builder
    {
        $query = Student::on('tenant');

        if ($this->search) {
            $query->search($this->search);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->sex) {
            $query->where('sex', $this->sex);
        }

        if ($this->nationality) {
            $query->where('nationality', $this->nationality);
        }

        return $query->orderBy($this->sortBy, $this->sortOrder);
    }

    public function headings(): array
    {
        return [
            'Matricule',
            'Nom',
            'Prénom',
            'Date de naissance',
            'Lieu de naissance',
            'Sexe',
            'Nationalité',
            'Email',
            'Téléphone',
            'Mobile',
            'Adresse',
            'Ville',
            'Pays',
            'Statut',
            'Contact urgence',
            'Tél. urgence',
            'Date d\'inscription',
        ];
    }

    /**
     * @param  Student  $student
     */
    public function map($student): array
    {
        return [
            $student->matricule,
            $student->lastname,
            $student->firstname,
            $student->birthdate?->format('d/m/Y'),
            $student->birthplace,
            $this->formatSex($student->sex),
            $student->nationality,
            $student->email,
            $student->phone,
            $student->mobile,
            $student->address,
            $student->city,
            $student->country,
            $student->status,
            $student->emergency_contact_name,
            $student->emergency_contact_phone,
            $student->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Style the first row (headers) as bold
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function formatSex(?string $sex): string
    {
        return match ($sex) {
            'M' => 'Masculin',
            'F' => 'Féminin',
            'O' => 'Autre',
            default => '',
        };
    }
}
