<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Http\Requests\BatchGroupExportRequest;
use Modules\Enrollment\Http\Requests\GroupExportRequest;
use Modules\Enrollment\Services\GroupExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GroupExportController extends Controller
{
    public function __construct(
        private GroupExportService $exportService
    ) {}

    /**
     * Get available export templates
     */
    public function templates(): JsonResponse
    {
        $templates = $this->exportService->getAvailableTemplates();

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Export group to PDF
     */
    public function exportPdf(int $group, GroupExportRequest $request): JsonResponse|BinaryFileResponse
    {
        $group = Group::findOrFail($group);
        $options = $request->validated();

        $path = $this->exportService->exportToPDF($group, $options);

        if ($request->boolean('download', false)) {
            return response()->download(
                Storage::disk('tenant')->path($path),
                basename($path)
            );
        }

        return response()->json([
            'message' => 'Liste PDF générée avec succès.',
            'data' => [
                'path' => $path,
                'download_url' => Storage::disk('tenant')->url($path),
            ],
        ]);
    }

    /**
     * Export group to Excel
     */
    public function exportExcel(int $group, GroupExportRequest $request): BinaryFileResponse
    {
        $group = Group::findOrFail($group);
        $options = $request->validated();

        return $this->exportService->exportToExcel($group, $options);
    }

    /**
     * Export group to CSV
     */
    public function exportCsv(int $group, GroupExportRequest $request): BinaryFileResponse
    {
        $group = Group::findOrFail($group);
        $options = $request->validated();

        return $this->exportService->exportToCsv($group, $options);
    }

    /**
     * Generate attendance sheet
     */
    public function attendanceSheet(int $group, GroupExportRequest $request): JsonResponse|BinaryFileResponse
    {
        $group = Group::findOrFail($group);
        $sessionCount = (int) $request->input('session_count', 12);

        $path = $this->exportService->generateAttendanceSheet($group, $sessionCount);

        if ($request->boolean('download', false)) {
            return response()->download(
                Storage::disk('tenant')->path($path),
                basename($path)
            );
        }

        return response()->json([
            'message' => 'Feuille d\'émargement générée avec succès.',
            'data' => [
                'path' => $path,
                'download_url' => Storage::disk('tenant')->url($path),
            ],
        ]);
    }

    /**
     * Batch export multiple groups
     */
    public function batchExport(BatchGroupExportRequest $request): JsonResponse|BinaryFileResponse
    {
        $groupIds = $request->input('group_ids', []);
        $format = $request->input('format', 'pdf');
        $options = $request->only([
            'template',
            'orientation',
            'sort_by',
            'include_email',
            'include_phone',
            'include_photo',
        ]);

        // Handle export by module or teacher
        if ($request->has('module_id')) {
            $path = $this->exportService->exportByModule(
                $request->input('module_id'),
                $format,
                $options
            );
        } elseif ($request->has('teacher_id')) {
            $path = $this->exportService->exportByTeacher(
                $request->input('teacher_id'),
                $format,
                $options
            );
        } else {
            $path = $this->exportService->exportMultipleGroups($groupIds, $format, $options);
        }

        if ($request->boolean('download', false)) {
            return response()->download(
                Storage::disk('tenant')->path($path),
                basename($path)
            );
        }

        return response()->json([
            'message' => 'Export batch généré avec succès.',
            'data' => [
                'path' => $path,
                'download_url' => Storage::disk('tenant')->url($path),
            ],
        ]);
    }
}
