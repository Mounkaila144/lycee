<?php

namespace Modules\Enrollment\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MultipleGroupsExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        private Collection $groups,
        private array $options = []
    ) {}

    /**
     * @return array<int, GroupStudentsExport>
     */
    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->groups as $group) {
            $sheets[] = new GroupStudentsExport($group, $this->options);
        }

        return $sheets;
    }
}
