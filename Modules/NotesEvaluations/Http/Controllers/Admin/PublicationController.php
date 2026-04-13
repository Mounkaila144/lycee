<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\PublicationRecord;
use Modules\NotesEvaluations\Exports\PublicationSummaryExport;
use Modules\NotesEvaluations\Http\Requests\PublishResultsRequest;
use Modules\NotesEvaluations\Services\PublicationService;
use Modules\StructureAcademique\Entities\Semester;

class PublicationController extends Controller
{
    public function __construct(
        private PublicationService $publicationService
    ) {}

    /**
     * Get publication status for a semester
     */
    public function status(int $semester): JsonResponse
    {
        $semester = Semester::findOrFail($semester);
        $status = $this->publicationService->getPublicationStatus($semester->id);

        return response()->json([
            'data' => $status,
        ]);
    }

    /**
     * Get publication history for a semester
     */
    public function history(int $semester): JsonResponse
    {
        $semester = Semester::findOrFail($semester);
        $history = $this->publicationService->getPublicationHistory($semester->id);

        return response()->json([
            'data' => $history->map(fn ($record) => $this->formatRecord($record)),
        ]);
    }

    /**
     * Check if results can be published
     */
    public function canPublish(Request $request, int $semester): JsonResponse
    {
        $semester = Semester::findOrFail($semester);
        $result = $this->publicationService->canPublish(
            $semester->id,
            $request->query('programme_id')
        );

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Publish results
     */
    public function publish(PublishResultsRequest $request, int $semester): JsonResponse
    {
        $semester = Semester::findOrFail($semester);

        try {
            $record = $this->publicationService->publishSemesterResults(
                $semester->id,
                $request->publication_type ?? 'final',
                $request->programme_id,
                $request->level,
                [
                    'send_notifications' => $request->send_notifications ?? false,
                    'notes' => $request->notes,
                ]
            );

            return response()->json([
                'message' => sprintf(
                    'Résultats publiés avec succès. %d étudiant(s) concerné(s).',
                    $record->students_count
                ),
                'data' => $this->formatRecord($record),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get a specific publication record
     */
    public function show(int $publication): JsonResponse
    {
        $publication = PublicationRecord::findOrFail($publication);
        $publication->load(['semester.academicYear', 'programme', 'publishedByUser']);

        return response()->json([
            'data' => $this->formatRecord($publication),
        ]);
    }

    /**
     * Unpublish results (for provisional only)
     */
    public function unpublish(int $publication): JsonResponse
    {
        $publication = PublicationRecord::findOrFail($publication);

        try {
            $this->publicationService->unpublishResults($publication);

            return response()->json([
                'message' => 'Publication annulée avec succès.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Export publication summary
     */
    public function export(int $publication): mixed
    {
        $publication = PublicationRecord::findOrFail($publication);
        $summary = $this->publicationService->generatePublicationSummary($publication);

        $filename = sprintf(
            'resultats_%s_%s_%s.xlsx',
            $publication->semester->code ?? $publication->semester_id,
            $publication->publication_type,
            $publication->published_at->format('Y-m-d')
        );

        return (new PublicationSummaryExport($summary))->download($filename);
    }

    /**
     * Get student published results
     */
    public function studentResults(int $studentId): JsonResponse
    {
        $results = $this->publicationService->getStudentPublishedResults($studentId);

        return response()->json([
            'data' => $results->map(fn ($result) => [
                'id' => $result->id,
                'semester_id' => $result->semester_id,
                'semester_name' => $result->semester->name,
                'academic_year' => $result->semester->academicYear?->name,
                'average' => $result->average,
                'mention' => $result->mention,
                'global_status' => $result->global_status,
                'global_status_label' => $result->global_status_label,
                'is_validated' => $result->is_validated,
                'rank' => $result->rank,
                'rank_display' => $result->rank_display,
                'acquired_credits' => $result->acquired_credits,
                'total_credits' => $result->total_credits,
                'success_rate' => $result->success_rate,
                'validated_modules_count' => $result->validated_modules_count,
                'compensated_modules_count' => $result->compensated_modules_count,
                'failed_modules_count' => $result->failed_modules_count,
                'published_at' => $result->published_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * List all publication records
     */
    public function index(Request $request): JsonResponse
    {
        $records = PublicationRecord::with(['semester.academicYear', 'programme', 'publishedByUser'])
            ->when($request->semester_id, fn ($q, $id) => $q->where('semester_id', $id))
            ->when($request->programme_id, fn ($q, $id) => $q->where('programme_id', $id))
            ->when($request->publication_type, fn ($q, $type) => $q->where('publication_type', $type))
            ->orderBy('published_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $records->map(fn ($r) => $this->formatRecord($r)),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * Format publication record for response
     */
    private function formatRecord(PublicationRecord $record): array
    {
        return [
            'id' => $record->id,
            'semester_id' => $record->semester_id,
            'programme_id' => $record->programme_id,
            'publication_type' => $record->publication_type,
            'publication_type_label' => $record->publication_type_label,
            'scope' => $record->scope,
            'scope_label' => $record->scope_label,
            'level' => $record->level,
            'published_at' => $record->published_at?->toIso8601String(),
            'students_count' => $record->students_count,
            'success_count' => $record->success_count,
            'failure_count' => $record->failure_count,
            'success_rate' => $record->success_rate,
            'failure_rate' => $record->failure_rate,
            'notifications_sent' => $record->notifications_sent,
            'notifications_count' => $record->notifications_count,
            'statistics' => $record->statistics,
            'notes' => $record->notes,
            'semester' => $record->relationLoaded('semester') ? [
                'id' => $record->semester->id,
                'name' => $record->semester->name,
                'academic_year' => $record->semester->academicYear?->name,
            ] : null,
            'programme' => $record->relationLoaded('programme') && $record->programme ? [
                'id' => $record->programme->id,
                'name' => $record->programme->name,
                'code' => $record->programme->code,
            ] : null,
            'published_by' => $record->relationLoaded('publishedByUser') && $record->publishedByUser ? [
                'id' => $record->publishedByUser->id,
                'name' => $record->publishedByUser->name,
            ] : null,
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }
}
