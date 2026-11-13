<?php

namespace Modules\Documents\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Documents\Entities\DocumentRequest;
use Modules\Documents\Services\CertificateService;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;

/**
 * Controller for Epic 3: Attestations (Stories 11-16)
 */
class CertificateController extends Controller
{
    /**
     * Request types that can be generated without additional input on approval.
     */
    private const AUTO_GENERATED_REQUEST_TYPES = [
        'certificate_enrollment',
        'certificate_status',
        'certificate_attendance',
        'certificate_schooling',
    ];

    public function __construct(
        private CertificateService $certificateService
    ) {}

    /**
     * Story 11: Generate enrollment certificate
     */
    public function generateEnrollmentCertificate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'request_id' => 'nullable|exists:tenant.document_requests,id',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);
        $request = isset($validated['request_id']) ? DocumentRequest::find($validated['request_id']) : null;

        $document = $this->certificateService->generateEnrollmentCertificate($student, $academicYear, $request);

        return response()->json([
            'message' => 'Enrollment certificate generated successfully',
            'document' => $document->load(['student', 'academicYear']),
        ], 201);
    }

    /**
     * Story 12: Generate status certificate
     */
    public function generateStatusCertificate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'request_id' => 'nullable|exists:tenant.document_requests,id',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);
        $request = isset($validated['request_id']) ? DocumentRequest::find($validated['request_id']) : null;

        $document = $this->certificateService->generateStatusCertificate($student, $academicYear, $request);

        return response()->json([
            'message' => 'Status certificate generated successfully',
            'document' => $document->load(['student', 'academicYear']),
        ], 201);
    }

    /**
     * Story 13: Generate achievement certificate
     */
    public function generateAchievementCertificate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'achievement_title' => 'required|string',
            'achievement_description' => 'required|string',
            'achievement_date' => 'required|date',
            'request_id' => 'nullable|exists:tenant.document_requests,id',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);
        $request = isset($validated['request_id']) ? DocumentRequest::find($validated['request_id']) : null;

        $achievementData = [
            'title' => $validated['achievement_title'],
            'description' => $validated['achievement_description'],
            'date' => $validated['achievement_date'],
        ];

        $document = $this->certificateService->generateAchievementCertificate($student, $academicYear, $achievementData, $request);

        return response()->json([
            'message' => 'Achievement certificate generated successfully',
            'document' => $document->load(['student', 'academicYear']),
        ], 201);
    }

    /**
     * Story 14: Generate attendance certificate
     */
    public function generateAttendanceCertificate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'request_id' => 'nullable|exists:tenant.document_requests,id',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);
        $request = isset($validated['request_id']) ? DocumentRequest::find($validated['request_id']) : null;

        $document = $this->certificateService->generateAttendanceCertificate($student, $academicYear, $request);

        return response()->json([
            'message' => 'Attendance certificate generated successfully',
            'document' => $document->load(['student', 'academicYear']),
        ], 201);
    }

    /**
     * Story 17: Generate schooling certificate
     */
    public function generateSchoolingCertificate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'request_id' => 'nullable|exists:tenant.document_requests,id',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);
        $request = isset($validated['request_id']) ? DocumentRequest::find($validated['request_id']) : null;

        $document = $this->certificateService->generateSchoolingCertificate($student, $academicYear, $request);

        return response()->json([
            'message' => 'Schooling certificate generated successfully',
            'document' => $document->load(['student', 'academicYear']),
        ], 201);
    }

    /**
     * Story 18: Generate transfer certificate (exeat)
     */
    public function generateTransferCertificate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'transfer_destination' => 'required|string',
            'transfer_reason' => 'required|string',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);

        $document = $this->certificateService->generateTransferCertificate(
            $student,
            $validated['transfer_destination'],
            $validated['transfer_reason'],
            $academicYear
        );

        return response()->json([
            'message' => 'Transfer certificate generated successfully',
            'document' => $document->load(['student', 'academicYear']),
        ], 201);
    }

    /**
     * Story 15: Create certificate request
     */
    public function createRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'document_type' => 'required|string|in:certificate_enrollment,certificate_status,certificate_achievement,certificate_attendance,certificate_schooling,certificate_transfer',
            'reason' => 'required|string|min:10',
            'quantity' => 'integer|min:1|max:10',
            'urgent' => 'boolean',
        ]);

        $student = Student::findOrFail($validated['student_id']);

        $documentRequest = $this->certificateService->createCertificateRequest(
            $student,
            $validated['document_type'],
            $validated['reason'],
            $validated['quantity'] ?? 1,
            $validated['urgent'] ?? false
        );

        return response()->json([
            'message' => 'Certificate request created successfully',
            'request' => $documentRequest->load('student'),
        ], 201);
    }

    /**
     * Story 16: List certificate requests
     */
    public function listRequests(Request $request): JsonResponse
    {
        $query = DocumentRequest::with(['student', 'processedBy', 'generatedDocument'])
            ->orderBy('request_date', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->has('urgent')) {
            $query->where('urgency', $request->boolean('urgent') ? 'urgent' : 'normal');
        }

        $requests = $query->paginate($request->per_page ?? 50);

        return response()->json($requests);
    }

    /**
     * Story 16: Approve request and generate certificate
     */
    public function approveRequest(Request $request, int $requestId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $documentRequest = DocumentRequest::findOrFail($requestId);

        if (! $documentRequest->isPending()) {
            return response()->json([
                'message' => 'Cette demande a déjà été traitée.',
            ], 422);
        }

        if (! in_array($documentRequest->document_type, self::AUTO_GENERATED_REQUEST_TYPES, true)) {
            return response()->json([
                'message' => 'Ce type de document doit être généré manuellement (informations complémentaires requises).',
            ], 422);
        }

        $document = $this->certificateService->approveAndGenerateCertificate(
            $documentRequest,
            $request->user()->id,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Request approved and certificate generated',
            'request' => $documentRequest->fresh()->load('student', 'generatedDocument'),
            'document' => $document,
        ]);
    }

    /**
     * Story 16: Reject request
     */
    public function rejectRequest(Request $request, int $requestId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $documentRequest = DocumentRequest::findOrFail($requestId);

        $this->certificateService->rejectCertificateRequest(
            $documentRequest,
            $request->user()->id,
            $validated['reason']
        );

        return response()->json([
            'message' => 'Request rejected',
            'request' => $documentRequest->fresh()->load('student'),
        ]);
    }
}
