<?php

namespace Modules\Enrollment\Http\Controllers\Frontend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Resources\StudentResource;
use Modules\Enrollment\Services\StudentPortalService;

/**
 * Portail Élève : accueil, notes, présences, factures, documents, emploi du temps, carte, réinscription.
 *
 * Ownership : l'identité de l'élève vient TOUJOURS de $request->user()->student.
 * Aucun query parameter `student_id` n'est accepté (cf. DEV-AGENT-PROMPT §3.3).
 */
class StudentDashboardController extends Controller
{
    public function __construct(private StudentPortalService $portal) {}

    /**
     * Profil Student du user connecté.
     */
    public function me(Request $request): JsonResponse|StudentResource
    {
        $student = $request->user()->student;

        if (! $student) {
            return response()->json([
                'message' => 'Aucun profil Étudiant associé à votre compte. Contactez l\'administration.',
                'code' => 'STUDENT_PROFILE_MISSING',
            ], 404);
        }

        return new StudentResource($student);
    }

    /**
     * Dashboard élève — KPIs synthétiques (Story 01).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $invoiceTotals = $this->portal->invoiceTotals($this->portal->invoices($student));
        $generalAverage = $this->portal->currentGeneralAverage($student);

        return response()->json([
            'data' => [
                'student' => new StudentResource($student),
                'classe' => $this->portal->currentClassSummary($student),
                'moyenne_actuelle' => $generalAverage,
                'average' => $generalAverage,
                'next_class' => $this->portal->nextClass($this->portal->timetable($student), now()),
                'recent_absences_count' => $this->portal->recentAbsencesCount($student),
                'pending_invoices_count' => $this->portal->pendingInvoicesCount($student),
                'balance_due' => $invoiceTotals['total_balance'],
                'currency' => $invoiceTotals['currency'],
                'available_documents_count' => $this->portal->availableDocumentsCount($student),
            ],
        ]);
    }

    /**
     * Story Élève 02 — Mes notes (modèle lycée, évaluations publiées uniquement).
     * Même forme que GET /api/admin/lycee-grades/results/students/{student}.
     */
    public function myGrades(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $semesterId = $request->filled('semester_id') ? $request->integer('semester_id') : null;
        $grades = $this->portal->lyceeGradesReport($student, $semesterId);

        return response()->json([
            'data' => $grades['report'],
            'meta' => [
                'student_id' => $student->id,
                'semester_id' => $grades['report']['semester']['id'] ?? null,
                'semesters' => $grades['semesters'],
            ],
        ]);
    }

    /**
     * Story Élève 04 — Mes présences/absences.
     */
    public function myAttendance(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $records = $this->portal->attendance($student);

        return response()->json([
            'data' => $records,
            'meta' => [
                'student_id' => $student->id,
                'summary' => $this->portal->attendanceSummary($records),
            ],
        ]);
    }

    /**
     * Story Élève 05 — Mes factures et paiements.
     */
    public function myInvoices(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $invoices = $this->portal->invoices($student);

        return response()->json([
            'data' => $invoices,
            'meta' => ['student_id' => $student->id] + $this->portal->invoiceTotals($invoices),
        ]);
    }

    /**
     * Story Élève 06 — Mes documents (documents officiels délivrés + pièces du dossier).
     */
    public function myDocuments(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => $this->portal->documents($student),
            'meta' => ['student_id' => $student->id],
        ]);
    }

    /**
     * Story Élève 03 — Emploi du temps de ma classe.
     */
    public function myTimetable(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => $this->portal->timetable($student),
            'meta' => [
                'student_id' => $student->id,
                'classe' => $this->portal->currentClassSummary($student),
            ],
        ]);
    }

    /**
     * Story Élève 07 — Ma carte scolaire.
     */
    public function myCard(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => $this->portal->card($student),
        ]);
    }

    /**
     * Story Élève 08 — Réinscription : campagnes ouvertes.
     */
    public function reenrollment(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => $this->portal->reenrollment($student),
        ]);
    }

    /**
     * Résout l'étudiant connecté ou retourne une 404.
     */
    private function requireStudent(Request $request): Student|JsonResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            return response()->json([
                'message' => 'Aucun profil Étudiant associé à votre compte.',
                'code' => 'STUDENT_PROFILE_MISSING',
            ], 404);
        }

        return $student;
    }
}
