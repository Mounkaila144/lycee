<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Modules\NotesEvaluations\Exports\GradeTemplateExport;
use Modules\NotesEvaluations\Imports\GradesImport;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\TenantUser;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeImportService
{
    /**
     * Generate and download a template Excel file
     */
    public function generateTemplate(ModuleEvaluationConfig $evaluation, bool $includeExistingGrades = false): BinaryFileResponse
    {
        $filename = sprintf(
            'template_notes_%s_%s.xlsx',
            $evaluation->module->code ?? 'module',
            $evaluation->name
        );

        return Excel::download(
            new GradeTemplateExport($evaluation, $includeExistingGrades),
            $filename
        );
    }

    /**
     * Validate an uploaded file's structure
     *
     * @return array{valid: bool, missing_columns: array, detected_columns: array}
     */
    public function validateFile(UploadedFile $file): array
    {
        try {
            $headings = (new HeadingRowImport)->toArray($file);
            $detectedColumns = $headings[0][0] ?? [];

            // Normalize column names
            $normalizedColumns = array_map(function ($col) {
                return strtolower(str_replace(' ', '_', trim($col)));
            }, $detectedColumns);

            $requiredColumns = ['matricule'];
            $noteColumns = ['note_0_20_ou_abs', 'note'];

            $missingColumns = array_diff($requiredColumns, $normalizedColumns);

            // Check if at least one note column exists
            $hasNoteColumn = ! empty(array_intersect($noteColumns, $normalizedColumns));

            return [
                'valid' => empty($missingColumns) && $hasNoteColumn,
                'missing_columns' => $missingColumns,
                'detected_columns' => $detectedColumns,
                'has_note_column' => $hasNoteColumn,
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'missing_columns' => [],
                'detected_columns' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Preview the contents of an uploaded file
     *
     * @return array<int, array>
     */
    public function preview(UploadedFile $file, int $limit = 50): array
    {
        $data = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray, \Maatwebsite\Excel\Concerns\WithHeadingRow
        {
            public function array(array $array): array
            {
                return $array;
            }
        }, $file)[0] ?? [];

        return array_slice($data, 0, $limit);
    }

    /**
     * Import grades from an uploaded file
     *
     * @return array{imported: int, updated: int, skipped: int, errors: array, total_processed: int}
     */
    public function import(
        UploadedFile $file,
        ModuleEvaluationConfig $evaluation,
        TenantUser $teacher,
        string $mode = 'add'
    ): array {
        $import = new GradesImport($evaluation, $teacher, $mode);

        Excel::import($import, $file);

        $report = $import->getReport();

        // Archive the imported file
        $this->archiveImportFile($file, $evaluation, $teacher);

        return $report;
    }

    /**
     * Archive an imported file for audit purposes
     */
    private function archiveImportFile(UploadedFile $file, ModuleEvaluationConfig $evaluation, TenantUser $teacher): void
    {
        $fileName = sprintf(
            'import_%s_%s_%s_%s.xlsx',
            $evaluation->module->code ?? 'module',
            $evaluation->id,
            $teacher->id,
            now()->format('YmdHis')
        );

        $path = "imports/grades/{$evaluation->module_id}";

        Storage::disk('tenant')->putFileAs($path, $file, $fileName);
    }
}
