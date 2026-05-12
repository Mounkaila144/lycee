<?php

namespace Modules\PortailParent\Http\Controllers\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Enrollment\Entities\Student;

/**
 * Données rattachées à un enfant via le portail Parent.
 *
 *  - Story Parent 02 : GET /api/admin/parent/children/{student}/grades
 *  - Story Parent 03 : GET /api/admin/parent/children/{student}/attendance
 *  - Story Parent 05 : GET /api/admin/parent/children/{student}/invoices
 *
 * Chaque endpoint vérifie l'ownership via ChildPolicy (registered in
 * PortailParentServiceProvider). Les agrégations concrètes (notes, présences,
 * factures) restent à câbler avec les modules Notes/Attendance/Finance.
 */
class ChildDataController extends Controller
{
    /**
     * Story Parent 02 — Notes de l'enfant (uniquement notes publiées).
     */
    public function grades(Request $request, Student $student): JsonResponse
    {
        $this->ensure($request, 'viewGrades', $student);

        return response()->json([
            'data' => [],
            'meta' => [
                'student_id' => $student->id,
                'note' => 'Scaffold — agrégation grades à câbler avec NotesEvaluations (filtre is_published=true).',
            ],
        ]);
    }

    /**
     * Story Parent 03 — Présences/absences de l'enfant.
     */
    public function attendance(Request $request, Student $student): JsonResponse
    {
        $this->ensure($request, 'viewAttendance', $student);

        return response()->json([
            'data' => [],
            'meta' => [
                'student_id' => $student->id,
                'note' => 'Scaffold — agrégation attendance à câbler avec Attendance.',
            ],
        ]);
    }

    /**
     * Story Parent 05 — Factures de l'enfant.
     */
    public function invoices(Request $request, Student $student): JsonResponse
    {
        $this->ensure($request, 'viewInvoices', $student);

        return response()->json([
            'data' => [],
            'meta' => [
                'student_id' => $student->id,
                'note' => 'Scaffold — agrégation invoices à câbler avec Finance.',
            ],
        ]);
    }

    /**
     * Vérifie l'ownership via Gate::forUser() (TenantSanctumAuth ne pose pas Auth::user()).
     */
    private function ensure(Request $request, string $ability, Student $student): void
    {
        if (! Gate::forUser($request->user())->allows($ability, $student)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }
}
