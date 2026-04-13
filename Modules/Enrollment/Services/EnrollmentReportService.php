<?php

namespace Modules\Enrollment\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Enrollment\Exports\EnrollmentsExport;
use Modules\StructureAcademique\Entities\AcademicYear;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EnrollmentReportService
{
    public function __construct(
        private EnrollmentStatisticsService $statisticsService
    ) {}

    /**
     * Generate executive summary PDF report
     */
    public function generateExecutiveSummary(AcademicYear $year): string
    {
        $kpis = $this->statisticsService->getGlobalKPIs($year);
        $byProgram = $this->statisticsService->getEnrollmentsByProgram($year);
        $demographics = $this->statisticsService->getDemographicAnalysis($year);
        $trends = $this->statisticsService->getEnrollmentTrends(5);
        $alerts = $this->statisticsService->getAlerts($year);

        $pdf = Pdf::loadView('enrollment::reports.executive_summary', [
            'year' => $year,
            'kpis' => $kpis,
            'programs' => $byProgram,
            'demographics' => $demographics,
            'trends' => $trends,
            'alerts' => $alerts,
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        $fileName = "rapport_executif_inscriptions_{$year->name}.pdf";
        $path = "reports/enrollments/{$year->id}/{$fileName}";

        Storage::disk('tenant')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Generate detailed dashboard PDF report
     */
    public function generateDashboardReport(AcademicYear $year): string
    {
        $kpis = $this->statisticsService->getGlobalKPIs($year);
        $byProgram = $this->statisticsService->getEnrollmentsByProgram($year);
        $demographics = $this->statisticsService->getDemographicAnalysis($year);
        $pedagogical = $this->statisticsService->getPedagogicalAnalysis($year);
        $monthlyTrends = $this->statisticsService->getMonthlyTrends($year);
        $statusStats = $this->statisticsService->getStatusStatistics();

        $pdf = Pdf::loadView('enrollment::reports.dashboard', [
            'year' => $year,
            'kpis' => $kpis,
            'programs' => $byProgram,
            'demographics' => $demographics,
            'pedagogical' => $pedagogical,
            'monthlyTrends' => $monthlyTrends,
            'statusStats' => $statusStats,
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', 'landscape');

        $fileName = "tableau_bord_inscriptions_{$year->name}.pdf";
        $path = "reports/enrollments/{$year->id}/{$fileName}";

        Storage::disk('tenant')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Export enrollments to Excel
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportToExcel(AcademicYear $year, array $filters = []): BinaryFileResponse
    {
        $fileName = "inscriptions_{$year->name}_".now()->format('Y-m-d').'.xlsx';

        return Excel::download(new EnrollmentsExport($year, $filters), $fileName);
    }

    /**
     * Generate statistics export to Excel
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportStatisticsToExcel(AcademicYear $year, array $filters = []): string
    {
        $fileName = "statistiques_inscriptions_{$year->name}_".now()->format('Y-m-d').'.xlsx';
        $path = "reports/enrollments/{$year->id}/{$fileName}";

        Excel::store(new EnrollmentsExport($year, $filters), $path, 'tenant');

        return $path;
    }

    /**
     * Get download URL for a report
     */
    public function getDownloadUrl(string $path): ?string
    {
        if (! Storage::disk('tenant')->exists($path)) {
            return null;
        }

        return Storage::disk('tenant')->url($path);
    }

    /**
     * Delete old reports (cleanup)
     */
    public function cleanupOldReports(int $daysOld = 30): int
    {
        $deleted = 0;
        $files = Storage::disk('tenant')->allFiles('reports/enrollments');

        foreach ($files as $file) {
            $lastModified = Storage::disk('tenant')->lastModified($file);

            if ($lastModified < now()->subDays($daysOld)->timestamp) {
                Storage::disk('tenant')->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
