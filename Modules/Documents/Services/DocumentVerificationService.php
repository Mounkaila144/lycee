<?php

namespace Modules\Documents\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\DocumentArchive;
use Modules\Documents\Entities\ElectronicSignature;
use Modules\Documents\Entities\VerificationLog;

/**
 * Service for Epic 5: Vérification (Stories 21-24)
 *
 * Story 21: Document authenticity verification (QR codes)
 * Story 22: Document register
 * Story 23: Digital archiving
 * Story 24: Electronic signatures
 */
class DocumentVerificationService
{
    /**
     * Story 21: Verify document by QR code
     */
    public function verifyByQrCode(string $verificationCode, ?array $requestData = null): array
    {
        $document = Document::where('verification_code', $verificationCode)
            ->with(['student', 'academicYear', 'programme', 'semester'])
            ->first();

        $isValid = $document && $document->verify();

        // Log verification attempt
        $this->logVerification(
            $document,
            'qr_code',
            $isValid,
            $requestData
        );

        if (! $isValid) {
            return [
                'valid' => false,
                'message' => 'Document non trouvé ou invalide',
            ];
        }

        return [
            'valid' => true,
            'document' => [
                'document_number' => $document->document_number,
                'document_type' => $this->getDocumentTypeLabel($document->document_type),
                'student_name' => $document->student->full_name,
                'student_matricule' => $document->student->matricule,
                'issue_date' => $document->issue_date->format('d/m/Y'),
                'academic_year' => $document->academicYear?->name,
                'programme' => $document->programme?->name,
                'status' => $document->status,
                'metadata' => $document->metadata,
            ],
            'verification' => [
                'verified_at' => now()->format('Y-m-d H:i:s'),
                'verification_count' => $document->getVerificationCount() + 1,
            ],
        ];
    }

    /**
     * Story 21: Verify document by document number
     */
    public function verifyByDocumentNumber(string $documentNumber, ?array $requestData = null): array
    {
        $document = Document::where('document_number', $documentNumber)
            ->with(['student', 'academicYear', 'programme', 'semester'])
            ->first();

        $isValid = $document && $document->verify();

        // Log verification attempt
        $this->logVerification(
            $document,
            'document_number',
            $isValid,
            $requestData
        );

        if (! $isValid) {
            return [
                'valid' => false,
                'message' => 'Document non trouvé ou invalide',
            ];
        }

        return [
            'valid' => true,
            'document' => [
                'document_number' => $document->document_number,
                'document_type' => $this->getDocumentTypeLabel($document->document_type),
                'student_name' => $document->student->full_name,
                'student_matricule' => $document->student->matricule,
                'issue_date' => $document->issue_date->format('d/m/Y'),
                'academic_year' => $document->academicYear?->name,
                'programme' => $document->programme?->name,
                'status' => $document->status,
                'verification_code' => $document->verification_code,
            ],
            'verification' => [
                'verified_at' => now()->format('Y-m-d H:i:s'),
                'verification_count' => $document->getVerificationCount() + 1,
            ],
        ];
    }

    /**
     * Story 22: Get document register (list of all documents)
     */
    public function getDocumentRegister(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Document::with(['student', 'academicYear', 'programme', 'issuedBy'])
            ->orderBy('issue_date', 'desc');

        // Apply filters
        if (isset($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('issue_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('issue_date', '<=', $filters['date_to']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('matricule', 'like', "%{$search}%")
                            ->orWhere('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Story 23: Archive document
     */
    public function archiveDocument(Document $document, ?string $notes = null): DocumentArchive
    {
        // Get PDF file
        $pdfPath = Storage::disk('tenant')->path($document->pdf_path);

        if (! file_exists($pdfPath)) {
            throw new \Exception('PDF file not found');
        }

        // Calculate checksum
        $checksum = hash_file('sha256', $pdfPath);

        // Get file size
        $fileSize = filesize($pdfPath);

        // Determine storage location
        $archiveLocation = $this->getArchiveLocation($document);

        // Copy to archive location
        Storage::disk('tenant')->copy($document->pdf_path, $archiveLocation);

        // Create archive record
        $archive = DocumentArchive::create([
            'document_id' => $document->id,
            'archived_at' => now(),
            'archive_location' => $archiveLocation,
            'archive_format' => 'pdf',
            'checksum' => $checksum,
            'file_size' => $fileSize,
            'storage_tier' => 'hot',
            'archived_by' => auth()->id(),
            'archive_notes' => $notes,
            'is_encrypted' => false,
        ]);

        return $archive;
    }

    /**
     * Story 23: Verify archive integrity
     */
    public function verifyArchiveIntegrity(DocumentArchive $archive): bool
    {
        $archivePath = Storage::disk('tenant')->path($archive->archive_location);

        if (! file_exists($archivePath)) {
            return false;
        }

        return $archive->verifyIntegrity($archivePath);
    }

    /**
     * Story 23: Move to cold storage
     */
    public function moveToColdStorage(DocumentArchive $archive): bool
    {
        // This would typically involve moving to a different storage tier
        // (e.g., AWS S3 Glacier, Azure Archive Storage)
        return $archive->moveToColdStorage();
    }

    /**
     * Story 24: Add electronic signature to document
     */
    public function addElectronicSignature(
        Document $document,
        string $signerName,
        string $signerTitle,
        string $signerRole,
        ?string $signatureImagePath = null,
        ?string $certificatePath = null
    ): ElectronicSignature {
        // Get document content for hash
        $pdfPath = Storage::disk('tenant')->path($document->pdf_path);
        $documentContent = file_get_contents($pdfPath);

        // Generate signature hash
        $signatureHash = hash('sha256', $documentContent.now()->timestamp);

        // Create electronic signature
        $signature = ElectronicSignature::create([
            'document_id' => $document->id,
            'signer_name' => $signerName,
            'signer_title' => $signerTitle,
            'signer_role' => $signerRole,
            'signature_date' => now(),
            'signature_image_path' => $signatureImagePath,
            'certificate_path' => $certificatePath,
            'signature_hash' => $signatureHash,
            'is_valid' => true,
            'signed_by' => auth()->id(),
        ]);

        return $signature;
    }

    /**
     * Story 24: Verify electronic signature
     */
    public function verifyElectronicSignature(ElectronicSignature $signature): array
    {
        $isValid = $signature->isValid();
        $isExpired = $signature->isExpired();

        // Verify hash
        $pdfPath = Storage::disk('tenant')->path($signature->document->pdf_path);
        $documentContent = file_exists($pdfPath) ? file_get_contents($pdfPath) : null;

        $hashValid = false;
        if ($documentContent) {
            $hashValid = $signature->verifyHash($documentContent);
        }

        return [
            'is_valid' => $isValid && ! $isExpired && $hashValid,
            'is_expired' => $isExpired,
            'hash_valid' => $hashValid,
            'signature_date' => $signature->signature_date->format('Y-m-d H:i:s'),
            'signer_name' => $signature->signer_name,
            'signer_title' => $signature->signer_title,
            'expires_at' => $signature->expires_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Story 24: Invalidate electronic signature
     */
    public function invalidateElectronicSignature(ElectronicSignature $signature): bool
    {
        return $signature->invalidate();
    }

    /**
     * Get verification statistics
     */
    public function getVerificationStatistics(array $filters = []): array
    {
        $query = VerificationLog::query();

        // Apply date filters
        if (isset($filters['date_from'])) {
            $query->where('verified_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('verified_at', '<=', $filters['date_to']);
        }

        $totalVerifications = $query->count();
        $successfulVerifications = (clone $query)->successful()->count();
        $failedVerifications = (clone $query)->failed()->count();

        // Verifications by method
        $byMethod = (clone $query)
            ->selectRaw('verification_method, COUNT(*) as count')
            ->groupBy('verification_method')
            ->pluck('count', 'verification_method')
            ->toArray();

        // Verifications by day (last 30 days)
        $byDay = (clone $query)
            ->where('verified_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(verified_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Top verified documents
        $topDocuments = (clone $query)
            ->selectRaw('document_id, COUNT(*) as count')
            ->groupBy('document_id')
            ->orderByDesc('count')
            ->limit(10)
            ->with('document.student')
            ->get()
            ->map(function ($log) {
                return [
                    'document_number' => $log->document->document_number,
                    'student_name' => $log->document->student->full_name,
                    'verification_count' => $log->count,
                ];
            })
            ->toArray();

        return [
            'total_verifications' => $totalVerifications,
            'successful_verifications' => $successfulVerifications,
            'failed_verifications' => $failedVerifications,
            'success_rate' => $totalVerifications > 0 ? round(($successfulVerifications / $totalVerifications) * 100, 2) : 0,
            'by_method' => $byMethod,
            'by_day' => $byDay,
            'top_documents' => $topDocuments,
        ];
    }

    /**
     * Generate verification code
     */
    public function generateVerificationCode(): string
    {
        return strtoupper(Str::random(32));
    }

    /**
     * Log verification attempt
     */
    private function logVerification(
        ?Document $document,
        string $method,
        bool $successful,
        ?array $requestData = null
    ): void {
        if (! $document) {
            return;
        }

        $ipAddress = request()->ip();
        $userAgent = request()->userAgent();

        // Get location from IP (would use a geolocation service)
        $location = $this->getLocationFromIp($ipAddress);

        VerificationLog::create([
            'document_id' => $document->id,
            'verified_at' => now(),
            'verification_method' => $method,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'country' => $location['country'] ?? null,
            'city' => $location['city'] ?? null,
            'verified_by' => auth()->id(),
            'verification_successful' => $successful,
            'request_data' => $requestData,
        ]);
    }

    /**
     * Get location from IP address
     */
    private function getLocationFromIp(string $ipAddress): array
    {
        // This would use a geolocation service (e.g., GeoIP2, ipapi.co)
        // For now, returning mock data
        return [
            'country' => null,
            'city' => null,
        ];
    }

    /**
     * Get archive location
     */
    private function getArchiveLocation(Document $document): string
    {
        $year = $document->issue_date->format('Y');
        $month = $document->issue_date->format('m');
        $type = $document->document_type;

        return "archives/{$year}/{$month}/{$type}/{$document->document_number}.pdf";
    }

    /**
     * Get document type label
     */
    private function getDocumentTypeLabel(string $documentType): string
    {
        return match ($documentType) {
            'transcript_semester' => 'Relevé de notes semestriel',
            'transcript_global' => 'Relevé de notes global',
            'transcript_provisional' => 'Relevé de notes provisoire',
            'diploma' => 'Diplôme',
            'diploma_duplicate' => 'Duplicata de diplôme',
            'diploma_supplement' => 'Supplément au diplôme',
            'certificate_enrollment' => 'Attestation d\'inscription',
            'certificate_status' => 'Attestation de statut',
            'certificate_achievement' => 'Attestation de réussite',
            'certificate_attendance' => 'Attestation d\'assiduité',
            'certificate_schooling' => 'Certificat de scolarité',
            'certificate_transfer' => 'Certificat de transfert (Exeat)',
            'student_card' => 'Carte d\'étudiant',
            'access_badge' => 'Badge d\'accès',
            default => $documentType,
        };
    }
}
