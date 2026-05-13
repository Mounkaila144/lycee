<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentAuditLog;
use Modules\Enrollment\Entities\StudentDocument;
use Modules\Enrollment\Exports\StudentsExport;
use Modules\Enrollment\Http\Requests\ChangeStudentStatusRequest;
use Modules\Enrollment\Http\Requests\ConfirmImportRequest;
use Modules\Enrollment\Http\Requests\ImportStudentsRequest;
use Modules\Enrollment\Http\Requests\StoreStudentRequest;
use Modules\Enrollment\Http\Requests\UpdateStudentRequest;
use Modules\Enrollment\Http\Requests\UploadDocumentRequest;
use Modules\Enrollment\Http\Resources\StudentResource;
use Modules\Enrollment\Services\CsvImportService;
use Modules\Enrollment\Services\MatriculeGeneratorService;
use Modules\Enrollment\Services\StudentStatusService;
use Modules\StructureAcademique\Entities\Programme;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentController extends Controller
{
    public function __construct(
        private MatriculeGeneratorService $matriculeGenerator,
        private StudentStatusService $statusService,
        private CsvImportService $csvImportService
    ) {}

    /**
     * Display a listing of students with filters and search
     */
    public function index(Request $request): JsonResponse
    {
        $query = Student::on('tenant')->with('documents');

        // Search
        if ($search = $request->input('search')) {
            $query->search($search);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by sex
        if ($sex = $request->input('sex')) {
            $query->where('sex', $sex);
        }

        // Filter by nationality
        if ($nationality = $request->input('nationality')) {
            $query->where('nationality', $nationality);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->input('per_page', 15);
        $students = $query->paginate($perPage);

        return response()->json([
            'data' => StudentResource::collection($students),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    /**
     * Story 7.1 — Création d'un élève (Étape 1 : données personnelles).
     *
     * - Pas de génération de matricule ici (Story 7.2 le fera en Étape 3).
     * - Détection de doublon (firstname + lastname + birthdate) → 409 si pas de force.
     * - Photo stockée sur disque tenant.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $force = (bool) ($validated['force'] ?? false);
        unset($validated['force']);

        $duplicate = Student::on('tenant')
            ->duplicateOf($validated['firstname'], $validated['lastname'], $validated['birthdate'])
            ->first();

        if ($duplicate && ! $force) {
            return response()->json([
                'message' => 'Un élève avec les mêmes nom, prénom et date de naissance existe déjà.',
                'code' => 'STUDENT_DUPLICATE_FOUND',
                'duplicate' => [
                    'id' => $duplicate->id,
                    'matricule' => $duplicate->matricule,
                ],
            ], 409);
        }

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('students/photos', 'tenant');
        }

        $validated['nationality'] = $validated['nationality'] ?? 'Nigérienne';
        $validated['city'] = $validated['city'] ?? 'Niamey';

        return DB::connection('tenant')->transaction(function () use ($validated) {
            $student = Student::on('tenant')->create([
                ...$validated,
                'matricule' => $this->matriculeGenerator->generateSimpleMatricule(),
                'status' => 'Actif',
            ]);

            return response()->json([
                'message' => 'Élève créé avec succès.',
                'data' => new StudentResource($student),
            ], 201);
        });
    }

    /**
     * Display the specified student
     */
    public function show(int $id): JsonResponse
    {
        $student = Student::on('tenant')
            ->with(['documents', 'documents.validator'])
            ->findOrFail($id);

        return response()->json([
            'data' => new StudentResource($student),
        ]);
    }

    /**
     * Update the specified student
     */
    public function update(UpdateStudentRequest $request, int $id): JsonResponse
    {
        $student = Student::on('tenant')->findOrFail($id);

        $student->update($request->validated());
        $student->load('documents');

        return response()->json([
            'message' => 'Dossier étudiant mis à jour avec succès',
            'data' => new StudentResource($student),
        ]);
    }

    /**
     * Remove the specified student (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $student = Student::on('tenant')->findOrFail($id);
        $student->delete();

        return response()->json([
            'message' => 'Dossier étudiant supprimé avec succès',
        ]);
    }

    /**
     * Check document completeness
     */
    public function checkCompleteness(int $id): JsonResponse
    {
        $student = Student::on('tenant')
            ->with('documents')
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'is_complete' => $student->hasCompleteDocuments(),
                'completeness_percentage' => $student->getCompletenessPercentage(),
                'missing_documents' => $student->getMissingDocuments(),
                'uploaded_documents' => $student->documents->pluck('type')->toArray(),
            ],
        ]);
    }

    /**
     * Check for potential duplicates
     */
    public function checkDuplicates(Request $request): JsonResponse
    {
        $request->validate([
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'birthdate' => 'required|date',
        ]);

        $duplicates = Student::findPotentialDuplicates(
            $request->firstname,
            $request->lastname,
            $request->birthdate
        );

        return response()->json([
            'data' => [
                'has_duplicates' => $duplicates->isNotEmpty(),
                'count' => $duplicates->count(),
                'duplicates' => StudentResource::collection($duplicates),
            ],
        ]);
    }

    /**
     * Upload a document for a student
     */
    public function uploadDocument(UploadDocumentRequest $request, int $id): JsonResponse
    {
        $student = Student::on('tenant')->findOrFail($id);
        $validated = $request->validated();

        // Get current tenant ID for file separation
        $tenantId = tenant()->id ?? 'default';

        $file = $request->file('file');
        $filename = time().'_'.$file->getClientOriginalName();

        // Store in tenant-specific directory: tenant_{id}/uploads/students/{student_id}/documents/
        $path = $file->storeAs(
            "tenant_{$tenantId}/uploads/students/{$student->id}/documents",
            $filename,
            'tenant'
        );

        $document = StudentDocument::on('tenant')->create([
            'student_id' => $student->id,
            'type' => $validated['type'],
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Document uploadé avec succès',
            'data' => $document,
        ], 201);
    }

    /**
     * Download a student document
     */
    public function downloadDocument(int $studentId, int $documentId): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $student = Student::on('tenant')->findOrFail($studentId);
        $document = StudentDocument::on('tenant')
            ->where('student_id', $student->id)
            ->findOrFail($documentId);

        $filePath = \Storage::disk('tenant')->path($document->file_path);

        if (! file_exists($filePath)) {
            return response()->json([
                'message' => 'Fichier non trouvé',
            ], 404);
        }

        return response()->download(
            $filePath,
            $document->original_filename,
            ['Content-Type' => $document->mime_type]
        );
    }

    /**
     * Get student statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Student::on('tenant')->count(),
            'by_status' => Student::on('tenant')
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_sex' => Student::on('tenant')
                ->selectRaw('sex, count(*) as count')
                ->groupBy('sex')
                ->pluck('count', 'sex'),
            'average_age' => Student::on('tenant')->avg(DB::raw('YEAR(CURDATE()) - YEAR(birthdate)')),
            'with_complete_documents' => Student::on('tenant')
                ->whereHas('documents', function ($query) {
                    $query->whereIn('type', [
                        'certificat_naissance',
                        'releve_baccalaureat',
                        'photo_identite',
                        'cni_passeport',
                    ]);
                }, '=', 4)
                ->count(),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Get audit log for a student
     */
    public function auditLog(int $id, Request $request): JsonResponse
    {
        $student = Student::on('tenant')->findOrFail($id);

        $query = StudentAuditLog::on('tenant')
            ->forStudent($id)
            ->with(['user:id,firstname,lastname,email'])
            ->orderBy('created_at', 'desc');

        // Filter by event type if provided
        if ($event = $request->input('event')) {
            $query->byEvent($event);
        }

        // Filter by field name if provided
        if ($fieldName = $request->input('field_name')) {
            $query->byField($fieldName);
        }

        // Paginate results
        $perPage = $request->input('per_page', 50);
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Autocomplete search for students
     * Returns quick suggestions for real-time search
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $query = $request->input('q');
        $limit = min($request->input('limit', 10), 20);

        $students = Student::on('tenant')
            ->select(['id', 'matricule', 'firstname', 'lastname', 'email', 'status'])
            ->where(function ($q) use ($query) {
                $q->where('matricule', 'like', "{$query}%")
                    ->orWhere('matricule', 'like', "%{$query}%")
                    ->orWhereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ["%{$query}%"])
                    ->orWhere('email', 'like', "{$query}%");
            })
            ->orderByRaw('CASE
                WHEN matricule LIKE ? THEN 1
                WHEN matricule LIKE ? THEN 2
                ELSE 3
            END', ["{$query}%", "%{$query}%"])
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $students->map(fn ($student) => [
                'id' => $student->id,
                'matricule' => $student->matricule,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'status' => $student->status,
                'label' => "{$student->matricule} - {$student->full_name}",
            ]),
        ]);
    }

    /**
     * Export students list to Excel
     * Applies same filters as index endpoint
     */
    public function export(Request $request): BinaryFileResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sex' => $request->input('sex'),
            'nationality' => $request->input('nationality'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        $filename = 'etudiants_'.date('Y-m-d_His').'.xlsx';

        return (new StudentsExport($filters))->download($filename);
    }

    /**
     * Change the status of a student
     */
    public function changeStatus(ChangeStudentStatusRequest $request, int $id): JsonResponse
    {
        $student = Student::on('tenant')->findOrFail($id);

        $history = $this->statusService->changeStatus(
            $student,
            $request->getNewStatus(),
            $request->getReason(),
            $request->getEffectiveDate(),
            $request->getDocument()
        );

        $student->refresh();
        $student->load('documents');

        return response()->json([
            'message' => 'Statut de l\'étudiant modifié avec succès',
            'data' => [
                'student' => new StudentResource($student),
                'history' => $history,
            ],
        ]);
    }

    /**
     * Get the status history of a student
     */
    public function statusHistory(int $id, Request $request): JsonResponse
    {
        $student = Student::on('tenant')->findOrFail($id);

        $perPage = $request->input('per_page', 20);
        $history = $this->statusService->getStatusHistory($id, $perPage);

        return response()->json([
            'data' => $history->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    /**
     * Get status statistics
     */
    public function statusStats(Request $request): JsonResponse
    {
        $statusStats = $this->statusService->getStatusStatistics();

        // Get transition statistics if dates provided
        $transitionStats = null;
        if ($request->has(['start_date', 'end_date'])) {
            $transitionStats = $this->statusService->getTransitionStatistics(
                $request->input('start_date'),
                $request->input('end_date')
            );
        }

        return response()->json([
            'data' => [
                'status_statistics' => $statusStats,
                'transition_statistics' => $transitionStats,
            ],
        ]);
    }

    /**
     * Get available transitions for a student
     */
    public function availableTransitions(int $id): JsonResponse
    {
        $student = Student::on('tenant')->findOrFail($id);

        $allowedTransitions = $this->statusService->getAllowedTransitions($student->status);
        $isFinal = $this->statusService->isFinalStatus($student->status);

        return response()->json([
            'data' => [
                'current_status' => $student->status,
                'is_final_status' => $isFinal,
                'allowed_transitions' => $allowedTransitions,
            ],
        ]);
    }

    /**
     * Download CSV import template
     */
    public function downloadImportTemplate(): BinaryFileResponse
    {
        $content = $this->csvImportService->generateTemplate();
        $filename = 'template_import_etudiants.csv';

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tempFile, "\xEF\xBB\xBF".$content); // Add BOM for Excel

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Parse and validate CSV file for preview
     */
    public function previewImport(ImportStudentsRequest $request): JsonResponse
    {
        $file = $request->file('file');

        $result = $this->csvImportService->parseAndValidate($file);

        // Check for general errors
        if (! empty($result['errors'])) {
            return response()->json([
                'message' => 'Erreur lors de l\'analyse du fichier CSV',
                'errors' => $result['errors'],
            ], 422);
        }

        return response()->json([
            'message' => 'Fichier CSV analysé avec succès',
            'data' => [
                'rows' => $result['rows'],
                'valid_count' => $result['valid_count'],
                'error_count' => $result['error_count'],
                'headers' => $result['headers'],
            ],
        ]);
    }

    /**
     * Revalidate a single row after inline correction
     */
    public function revalidateImportRow(Request $request): JsonResponse
    {
        $request->validate([
            'row' => 'required|array',
            'row.row_number' => 'required|integer',
            'all_rows' => 'required|array',
        ]);

        $row = $request->input('row');
        $allRows = $request->input('all_rows');

        $revalidatedRow = $this->csvImportService->revalidateRow($row, $allRows);

        return response()->json([
            'data' => $revalidatedRow,
        ]);
    }

    /**
     * Confirm and execute the import
     */
    public function confirmImport(ConfirmImportRequest $request): JsonResponse
    {
        $validRows = $request->getValidRows();

        if (empty($validRows)) {
            return response()->json([
                'message' => 'Aucune ligne valide à importer',
            ], 422);
        }

        // Get default programme if specified
        $defaultProgramme = null;
        if ($request->has('programme_id')) {
            $defaultProgramme = Programme::on('tenant')->find($request->input('programme_id'));
        }

        $result = $this->csvImportService->import($validRows, $defaultProgramme);

        return response()->json([
            'message' => $result['imported_count'].' étudiant(s) importé(s) avec succès',
            'data' => [
                'imported_count' => $result['imported_count'],
                'imported_students' => $result['imported_students'],
                'errors' => $result['errors'],
            ],
        ], $result['imported_count'] > 0 ? 201 : 422);
    }
}
