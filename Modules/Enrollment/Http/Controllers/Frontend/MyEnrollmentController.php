<?php

namespace Modules\Enrollment\Http\Controllers\Frontend;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\PedagogicalEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Resources\PedagogicalEnrollmentResource;
use Modules\Enrollment\Services\EnrollmentValidationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyEnrollmentController extends Controller
{
    public function __construct(
        private EnrollmentValidationService $validationService
    ) {}

    /**
     * Get the authenticated student's enrollment status
     */
    public function status(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (! $student) {
            return response()->json([
                'message' => 'Profil étudiant non trouvé',
                'data' => null,
            ], 404);
        }

        $enrollment = PedagogicalEnrollment::on('tenant')
            ->with(['student', 'program', 'academicYear', 'semester', 'validator'])
            ->where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $enrollment) {
            return response()->json([
                'message' => 'Aucune inscription pédagogique trouvée',
                'data' => null,
            ], 404);
        }

        // Get checklist for pending/draft enrollments
        $checklist = null;
        if (in_array($enrollment->status, [
            PedagogicalEnrollment::STATUS_ACTIVE,
            PedagogicalEnrollment::STATUS_COMPLETED,
        ])) {
            $checks = $this->validationService->checkEnrollmentCompleteness($enrollment);
            $checklist = $this->formatChecklist($checks);
        }

        return response()->json([
            'data' => new PedagogicalEnrollmentResource($enrollment),
            'checklist' => $checklist,
            'can_start_courses' => $enrollment->status === PedagogicalEnrollment::STATUS_VALIDATED,
            'meta' => [
                'student' => [
                    'id' => $student->id,
                    'matricule' => $student->matricule,
                    'name' => $student->firstname.' '.$student->lastname,
                ],
            ],
        ]);
    }

    /**
     * Get all enrollments for the authenticated student
     */
    public function history(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (! $student) {
            return response()->json([
                'message' => 'Profil étudiant non trouvé',
                'data' => [],
            ], 404);
        }

        $enrollments = PedagogicalEnrollment::on('tenant')
            ->with(['program', 'academicYear', 'semester'])
            ->where('student_id', $student->id)
            ->orderBy('academic_year_id', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => PedagogicalEnrollmentResource::collection($enrollments),
            'meta' => [
                'total' => $enrollments->count(),
            ],
        ]);
    }

    /**
     * Download the pedagogical contract PDF
     */
    public function downloadContract(): StreamedResponse|JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (! $student) {
            return response()->json([
                'message' => 'Profil étudiant non trouvé',
            ], 404);
        }

        $enrollment = PedagogicalEnrollment::on('tenant')
            ->where('student_id', $student->id)
            ->where('status', PedagogicalEnrollment::STATUS_VALIDATED)
            ->whereNotNull('contract_pdf_path')
            ->orderBy('validated_at', 'desc')
            ->first();

        if (! $enrollment) {
            return response()->json([
                'message' => 'Aucun contrat pédagogique disponible. Votre inscription doit être validée.',
            ], 404);
        }

        $disk = Storage::disk('tenant');

        if (! $disk->exists($enrollment->contract_pdf_path)) {
            return response()->json([
                'message' => 'Fichier du contrat introuvable.',
            ], 404);
        }

        $fileName = "contrat_pedagogique_{$student->matricule}.pdf";

        return $disk->download($enrollment->contract_pdf_path, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Get the authenticated student from the user
     */
    private function getAuthenticatedStudent(): ?Student
    {
        $user = Auth::user();

        return Student::on('tenant')
            ->where('email', $user->email)
            ->first();
    }

    /**
     * Format checklist for frontend display
     */
    private function formatChecklist(array $checks): array
    {
        $items = [
            [
                'key' => 'administrative',
                'label' => 'Inscription administrative validée',
                'passed' => $checks['administrative'] ?? false,
                'icon' => ($checks['administrative'] ?? false) ? '✅' : '❌',
            ],
            [
                'key' => 'modules_check',
                'label' => 'Modules obligatoires inscrits',
                'passed' => $checks['modules_check'] ?? false,
                'icon' => ($checks['modules_check'] ?? false) ? '✅' : '❌',
            ],
            [
                'key' => 'ects_check',
                'label' => "Crédits ECTS suffisants ({$checks['total_ects']} ECTS)",
                'passed' => $checks['ects_check'] ?? false,
                'icon' => ($checks['ects_check'] ?? false) ? '✅' : '❌',
            ],
            [
                'key' => 'groups_check',
                'label' => 'Affectation aux groupes TD/TP',
                'passed' => $checks['groups_check'] ?? false,
                'icon' => ($checks['groups_check'] ?? false) ? '✅' : '❌',
            ],
            [
                'key' => 'options_check',
                'label' => 'Options/spécialités choisies',
                'passed' => $checks['options_check'] ?? false,
                'icon' => ($checks['options_check'] ?? false) ? '✅' : '❌',
            ],
            [
                'key' => 'prerequisites_check',
                'label' => 'Prérequis respectés',
                'passed' => $checks['prerequisites_check'] ?? false,
                'icon' => ($checks['prerequisites_check'] ?? false) ? '✅' : '❌',
            ],
        ];

        return [
            'checks' => $items,
            'is_complete' => $checks['is_complete'] ?? false,
            'missing_count' => collect($items)->where('passed', false)->count(),
        ];
    }
}
