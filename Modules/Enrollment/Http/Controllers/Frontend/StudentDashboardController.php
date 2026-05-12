<?php

namespace Modules\Enrollment\Http\Controllers\Frontend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Http\Resources\StudentResource;

/**
 * Stories Étudiant 01 (Home Portail) + données rattachées (profil, KPIs basiques).
 *
 * Ownership : l'identité de l'élève vient TOUJOURS de $request->user()->student.
 * Aucun query parameter `student_id` n'est accepté (cf. DEV-AGENT-PROMPT §3.3).
 */
class StudentDashboardController extends Controller
{
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
     * Dashboard étudiant — KPIs synthétiques (Story 01).
     *
     * Note : agrégations Notes/Attendance/Finance non implémentées (stubs).
     * À enrichir story par story (02 notes, 04 présences, 05 factures).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            return response()->json([
                'message' => 'Aucun profil Étudiant associé à votre compte.',
                'code' => 'STUDENT_PROFILE_MISSING',
            ], 404);
        }

        return response()->json([
            'data' => [
                'student' => new StudentResource($student),
                'moyenne_actuelle' => null,
                'next_class' => null,
                'recent_absences_count' => 0,
                'pending_invoices_count' => 0,
                'available_documents_count' => 0,
                'message' => 'Agrégations Notes/Attendance/Finance à implémenter (Stories Étudiant 02, 04, 05, 06).',
            ],
        ]);
    }

    /**
     * Story Étudiant 02 — Mes notes (lecture seule, filtré sur student_id du connecté).
     *
     * Pattern : query Grade::where('student_id', $student->id). L'intégration concrète
     * avec Modules\NotesEvaluations\Entities\Grade est à câbler quand le schéma sera stable.
     */
    public function myGrades(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => [],
            'meta' => [
                'student_id' => $student->id,
                'note' => 'Endpoint scaffold — agrégation des notes à câbler avec NotesEvaluations.',
            ],
        ]);
    }

    /**
     * Story Étudiant 04 — Mes présences/absences.
     */
    public function myAttendance(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => [],
            'meta' => [
                'student_id' => $student->id,
                'note' => 'Endpoint scaffold — agrégation présences à câbler avec Attendance.',
            ],
        ]);
    }

    /**
     * Story Étudiant 05 — Mes factures / paiements (lecture seule, owner filter).
     */
    public function myInvoices(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => [],
            'meta' => [
                'student_id' => $student->id,
                'note' => 'Endpoint scaffold — agrégation factures à câbler avec Finance.',
            ],
        ]);
    }

    /**
     * Story Étudiant 06 — Mes documents (lecture seule, owner filter).
     */
    public function myDocuments(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => [],
            'meta' => [
                'student_id' => $student->id,
                'note' => 'Endpoint scaffold — agrégation documents à câbler avec Documents.',
            ],
        ]);
    }

    /**
     * Story Étudiant 03 — Mon emploi du temps (lecture seule, owner filter).
     */
    public function myTimetable(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => [],
            'meta' => [
                'student_id' => $student->id,
                'note' => 'Endpoint scaffold — agrégation timetable à câbler avec Timetable.',
            ],
        ]);
    }

    /**
     * Story Étudiant 07 — Ma carte étudiante (état + téléchargement PDF).
     */
    public function myCard(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => [
                'student_id' => $student->id,
                'matricule' => $student->matricule,
                'card_status' => 'not_issued',
                'pdf_url' => null,
            ],
            'meta' => [
                'note' => 'Endpoint scaffold — génération PDF à câbler avec Documents/CardController.',
            ],
        ]);
    }

    /**
     * Story Étudiant 08 — Réinscription : campagnes ouvertes pour l'élève.
     */
    public function reenrollment(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return response()->json([
            'data' => [
                'student_id' => $student->id,
                'open_campaigns' => [],
                'eligible' => true,
            ],
            'meta' => [
                'note' => 'Endpoint scaffold — workflow de réinscription à câbler avec Enrollment.',
            ],
        ]);
    }

    /**
     * Résout l'étudiant connecté ou retourne une 404.
     */
    private function requireStudent(Request $request): \Modules\Enrollment\Entities\Student|JsonResponse
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
