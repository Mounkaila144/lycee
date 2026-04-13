<?php

namespace Modules\NotesEvaluations\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\StructureAcademique\Entities\Semester;

class StatisticsExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private array $globalStats,
        private Collection $moduleStats,
        private Collection $programmeStats,
        private array $distribution,
        private ?Semester $semester = null
    ) {}

    public function sheets(): array
    {
        return [
            new GlobalStatisticsSheet($this->globalStats, $this->semester),
            new ModuleStatisticsSheet($this->moduleStats),
            new ProgrammeStatisticsSheet($this->programmeStats),
            new DistributionSheet($this->distribution),
        ];
    }
}
