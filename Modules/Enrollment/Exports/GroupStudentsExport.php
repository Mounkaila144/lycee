<?php

namespace Modules\Enrollment\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\GroupAssignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GroupStudentsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    private int $index = 0;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        private Group $group,
        private array $options = []
    ) {}

    public function collection(): Collection
    {
        $sortBy = $this->options['sort_by'] ?? 'lastname';

        return GroupAssignment::on('tenant')
            ->where('group_id', $this->group->id)
            ->with(['student' => function ($q) {
                $q->select('id', 'matricule', 'firstname', 'lastname', 'email', 'phone', 'birthdate');
            }])
            ->get()
            ->pluck('student')
            ->filter()
            ->sortBy($sortBy)
            ->values();
    }

    public function headings(): array
    {
        $baseHeadings = ['N°', 'Matricule', 'Nom', 'Prénom'];

        if ($this->options['include_email'] ?? true) {
            $baseHeadings[] = 'Email';
        }

        if ($this->options['include_phone'] ?? true) {
            $baseHeadings[] = 'Téléphone';
        }

        if ($this->options['include_birthdate'] ?? false) {
            $baseHeadings[] = 'Date Naissance';
        }

        return $baseHeadings;
    }

    /**
     * @param  \Modules\Enrollment\Entities\Student  $student
     */
    public function map($student): array
    {
        $this->index++;

        $data = [
            $this->index,
            $student->matricule ?? '-',
            $student->lastname ?? '-',
            $student->firstname ?? '-',
        ];

        if ($this->options['include_email'] ?? true) {
            $data[] = $student->email ?? '-';
        }

        if ($this->options['include_phone'] ?? true) {
            $data[] = $student->phone ?? '-';
        }

        if ($this->options['include_birthdate'] ?? false) {
            $data[] = $student->birthdate?->format('d/m/Y') ?? '-';
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        $moduleCode = $this->group->module?->code ?? 'module';
        $groupCode = $this->group->code ?? $this->group->id;

        return substr("{$moduleCode}_{$groupCode}", 0, 31);
    }
}
