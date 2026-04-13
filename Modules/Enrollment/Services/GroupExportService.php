<?php

namespace Modules\Enrollment\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\GroupAssignment;
use Modules\Enrollment\Exports\GroupStudentsExport;
use Modules\Enrollment\Exports\MultipleGroupsExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class GroupExportService
{
    /**
     * Export group students to PDF
     *
     * @param  array<string, mixed>  $options
     */
    public function exportToPDF(Group $group, array $options = []): string
    {
        $students = $this->getGroupStudents($group, $options['sort_by'] ?? 'lastname');
        $template = $options['template'] ?? 'group_list';
        $orientation = $options['orientation'] ?? 'portrait';

        $pdf = Pdf::loadView("enrollment::exports.{$template}", [
            'group' => $group,
            'students' => $students,
            'module' => $group->module,
            'teacher' => $group->teacher,
            'options' => $options,
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', $orientation);

        $fileName = $this->generateFileName($group, 'pdf');
        $path = "exports/groups/{$group->id}/{$fileName}";

        Storage::disk('tenant')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Export group students to Excel
     *
     * @param  array<string, mixed>  $options
     */
    public function exportToExcel(Group $group, array $options = []): BinaryFileResponse
    {
        $fileName = $this->generateFileName($group, 'xlsx');

        return Excel::download(new GroupStudentsExport($group, $options), $fileName);
    }

    /**
     * Export group students to CSV
     *
     * @param  array<string, mixed>  $options
     */
    public function exportToCsv(Group $group, array $options = []): BinaryFileResponse
    {
        $fileName = $this->generateFileName($group, 'csv');

        return Excel::download(new GroupStudentsExport($group, $options), $fileName);
    }

    /**
     * Generate attendance sheet PDF
     */
    public function generateAttendanceSheet(Group $group, int $sessionCount = 12): string
    {
        $students = $this->getGroupStudents($group, 'lastname');

        $pdf = Pdf::loadView('enrollment::exports.attendance_sheet', [
            'group' => $group,
            'students' => $students,
            'session_count' => $sessionCount,
            'module' => $group->module,
            'teacher' => $group->teacher,
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', 'landscape');

        $fileName = $this->generateFileName($group, 'pdf', 'emargement');
        $path = "exports/groups/{$group->id}/{$fileName}";

        Storage::disk('tenant')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Export multiple groups
     *
     * @param  array<int>  $groupIds
     * @param  array<string, mixed>  $options
     */
    public function exportMultipleGroups(array $groupIds, string $format = 'pdf', array $options = []): string
    {
        $groups = Group::on('tenant')
            ->whereIn('id', $groupIds)
            ->with(['module', 'teacher', 'programme'])
            ->get();

        if ($format === 'pdf') {
            return $this->exportMultipleToPdfZip($groups, $options);
        }

        // Excel consolidated
        $fileName = 'listes_groupes_'.now()->format('Y-m-d_His').'.xlsx';
        $path = "exports/batch/{$fileName}";

        Excel::store(new MultipleGroupsExport($groups, $options), $path, 'tenant');

        return $path;
    }

    /**
     * Export by module (all groups of a module)
     *
     * @param  array<string, mixed>  $options
     */
    public function exportByModule(int $moduleId, string $format = 'pdf', array $options = []): string
    {
        $groupIds = Group::on('tenant')
            ->where('module_id', $moduleId)
            ->pluck('id')
            ->toArray();

        return $this->exportMultipleGroups($groupIds, $format, $options);
    }

    /**
     * Export by teacher (all groups of a teacher)
     *
     * @param  array<string, mixed>  $options
     */
    public function exportByTeacher(int $teacherId, string $format = 'pdf', array $options = []): string
    {
        $groupIds = Group::on('tenant')
            ->where('teacher_id', $teacherId)
            ->pluck('id')
            ->toArray();

        return $this->exportMultipleGroups($groupIds, $format, $options);
    }

    /**
     * Get available export templates
     *
     * @return array<int, array<string, string>>
     */
    public function getAvailableTemplates(): array
    {
        return [
            [
                'id' => 'group_list',
                'name' => 'Liste simple',
                'description' => 'Nom, Prénom, Matricule',
            ],
            [
                'id' => 'group_list_complete',
                'name' => 'Liste complète',
                'description' => 'Avec Email, Téléphone',
            ],
            [
                'id' => 'group_list_with_photos',
                'name' => 'Liste avec photos',
                'description' => 'Avec photos miniatures',
            ],
            [
                'id' => 'attendance_sheet',
                'name' => 'Feuille d\'émargement',
                'description' => 'Colonnes signatures',
            ],
        ];
    }

    /**
     * Get students for a group
     */
    private function getGroupStudents(Group $group, string $sortBy = 'lastname'): Collection
    {
        return GroupAssignment::on('tenant')
            ->where('group_id', $group->id)
            ->with(['student' => function ($q) {
                $q->select('id', 'matricule', 'firstname', 'lastname', 'email', 'phone', 'photo', 'birthdate');
            }])
            ->get()
            ->pluck('student')
            ->filter()
            ->sortBy($sortBy)
            ->values();
    }

    /**
     * Generate filename for export
     */
    private function generateFileName(Group $group, string $extension, string $type = 'liste'): string
    {
        $moduleCode = $group->module?->code ?? 'module';
        $groupCode = $group->code ?? $group->id;
        $date = now()->format('Y-m-d');

        return "{$type}_{$moduleCode}_{$groupCode}_{$date}.{$extension}";
    }

    /**
     * Export multiple groups to PDF in a ZIP file
     *
     * @param  array<string, mixed>  $options
     */
    private function exportMultipleToPdfZip(Collection $groups, array $options = []): string
    {
        $zipFileName = 'listes_groupes_'.now()->format('Y-m-d_His').'.zip';
        $tempPath = storage_path("app/temp/{$zipFileName}");

        // Ensure temp directory exists
        if (! is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive;

        if ($zip->open($tempPath, ZipArchive::CREATE) === true) {
            foreach ($groups as $group) {
                $pdfPath = $this->exportToPDF($group, $options);
                $pdfContent = Storage::disk('tenant')->get($pdfPath);
                $zip->addFromString(basename($pdfPath), $pdfContent);
            }
            $zip->close();
        }

        // Move to tenant storage
        $finalPath = "exports/batch/{$zipFileName}";
        Storage::disk('tenant')->put($finalPath, file_get_contents($tempPath));

        // Clean up temp file
        unlink($tempPath);

        return $finalPath;
    }

    /**
     * Clean up old exports
     */
    public function cleanupOldExports(int $daysOld = 7): int
    {
        $deleted = 0;
        $files = Storage::disk('tenant')->allFiles('exports/groups');
        $files = array_merge($files, Storage::disk('tenant')->allFiles('exports/batch'));

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
