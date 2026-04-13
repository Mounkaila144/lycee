<?php

namespace Modules\Documents\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\DocumentArchive;
use Modules\Documents\Entities\ElectronicSignature;
use Modules\Documents\Services\DocumentVerificationService;

/**
 * Controller for Epic 5: Vérification (Stories 21-24)
 */
class VerificationController extends Controller
{
    public function __construct(
        private DocumentVerificationService $verificationService
    ) {}

    /**
     * Story 21: Verify document by QR code
     */
    public function verifyByQrCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_code' => 'required|string',
        ]);

        $result = $this->verificationService->verifyByQrCode(
            $validated['verification_code'],
            $request->all()
        );

        return response()->json($result);
    }

    /**
     * Story 21: Verify document by document number
     */
    public function verifyByDocumentNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_number' => 'required|string',
        ]);

        $result = $this->verificationService->verifyByDocumentNumber(
            $validated['document_number'],
            $request->all()
        );

        return response()->json($result);
    }

    /**
     * Story 22: Get document register
     */
    public function getDocumentRegister(Request $request): JsonResponse
    {
        $filters = $request->only([
            'document_type',
            'student_id',
            'academic_year_id',
            'status',
            'date_from',
            'date_to',
            'search',
            'per_page',
        ]);

        $register = $this->verificationService->getDocumentRegister($filters);

        return response()->json($register);
    }

    /**
     * Get verification statistics
     */
    public function getVerificationStatistics(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to']);

        $statistics = $this->verificationService->getVerificationStatistics($filters);

        return response()->json($statistics);
    }

    /**
     * Story 23: Archive document
     */
    public function archiveDocument(Request $request, int $documentId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $document = Document::findOrFail($documentId);

        $archive = $this->verificationService->archiveDocument(
            $document,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Document archived successfully',
            'archive' => $archive->load(['document', 'archivedBy']),
        ], 201);
    }

    /**
     * Story 23: Verify archive integrity
     */
    public function verifyArchiveIntegrity(int $archiveId): JsonResponse
    {
        $archive = DocumentArchive::findOrFail($archiveId);

        $isValid = $this->verificationService->verifyArchiveIntegrity($archive);

        return response()->json([
            'archive_id' => $archive->id,
            'document_number' => $archive->document->document_number,
            'integrity_valid' => $isValid,
            'message' => $isValid ? 'Archive integrity verified' : 'Archive integrity check failed',
        ]);
    }

    /**
     * Story 23: Move to cold storage
     */
    public function moveToColdStorage(int $archiveId): JsonResponse
    {
        $archive = DocumentArchive::findOrFail($archiveId);

        $this->verificationService->moveToColdStorage($archive);

        return response()->json([
            'message' => 'Archive moved to cold storage',
            'archive' => $archive->fresh(),
        ]);
    }

    /**
     * List archives
     */
    public function listArchives(Request $request): JsonResponse
    {
        $query = DocumentArchive::with(['document.student', 'archivedBy'])
            ->orderBy('archived_at', 'desc');

        if ($request->has('storage_tier')) {
            $query->where('storage_tier', $request->storage_tier);
        }

        if ($request->has('is_encrypted')) {
            $query->where('is_encrypted', $request->boolean('is_encrypted'));
        }

        if ($request->has('document_type')) {
            $query->whereHas('document', function ($q) use ($request) {
                $q->where('document_type', $request->document_type);
            });
        }

        $archives = $query->paginate($request->per_page ?? 50);

        return response()->json($archives);
    }

    /**
     * Story 24: Add electronic signature
     */
    public function addElectronicSignature(Request $request, int $documentId): JsonResponse
    {
        $validated = $request->validate([
            'signer_name' => 'required|string',
            'signer_title' => 'required|string',
            'signer_role' => 'required|string',
            'signature_image_path' => 'nullable|string',
            'certificate_path' => 'nullable|string',
        ]);

        $document = Document::findOrFail($documentId);

        $signature = $this->verificationService->addElectronicSignature(
            $document,
            $validated['signer_name'],
            $validated['signer_title'],
            $validated['signer_role'],
            $validated['signature_image_path'] ?? null,
            $validated['certificate_path'] ?? null
        );

        return response()->json([
            'message' => 'Electronic signature added successfully',
            'signature' => $signature->load(['document', 'signedBy']),
        ], 201);
    }

    /**
     * Story 24: Verify electronic signature
     */
    public function verifyElectronicSignature(int $signatureId): JsonResponse
    {
        $signature = ElectronicSignature::findOrFail($signatureId);

        $verification = $this->verificationService->verifyElectronicSignature($signature);

        return response()->json([
            'signature_id' => $signature->id,
            'document_number' => $signature->document->document_number,
            'verification' => $verification,
        ]);
    }

    /**
     * Story 24: Invalidate electronic signature
     */
    public function invalidateElectronicSignature(int $signatureId): JsonResponse
    {
        $signature = ElectronicSignature::findOrFail($signatureId);

        $this->verificationService->invalidateElectronicSignature($signature);

        return response()->json([
            'message' => 'Electronic signature invalidated',
            'signature' => $signature->fresh(),
        ]);
    }

    /**
     * List electronic signatures
     */
    public function listElectronicSignatures(Request $request): JsonResponse
    {
        $query = ElectronicSignature::with(['document.student', 'signedBy'])
            ->orderBy('signature_date', 'desc');

        if ($request->has('document_id')) {
            $query->where('document_id', $request->document_id);
        }

        if ($request->has('is_valid')) {
            $query->where('is_valid', $request->boolean('is_valid'));
        }

        if ($request->has('signer_role')) {
            $query->where('signer_role', $request->signer_role);
        }

        $signatures = $query->paginate($request->per_page ?? 50);

        return response()->json($signatures);
    }

    /**
     * Get document details
     */
    public function show(int $documentId): JsonResponse
    {
        $document = Document::with([
            'student',
            'academicYear',
            'semester',
            'programme',
            'template',
            'issuedBy',
            'verificationLogs' => function ($query) {
                $query->latest('verified_at')->limit(10);
            },
            'electronicSignatures',
            'archive',
        ])->findOrFail($documentId);

        return response()->json($document);
    }

    /**
     * Cancel document
     */
    public function cancelDocument(Request $request, int $documentId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $document = Document::findOrFail($documentId);

        $document->cancel($validated['reason']);

        return response()->json([
            'message' => 'Document cancelled',
            'document' => $document->fresh(),
        ]);
    }
}
