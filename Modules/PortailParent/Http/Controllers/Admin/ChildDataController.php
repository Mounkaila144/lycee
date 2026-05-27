<?php

namespace Modules\PortailParent\Http\Controllers\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Services\StudentDataService;

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
    public function __construct(private readonly StudentDataService $studentData) {}

    /**
     * Story Parent 02 — Notes de l'enfant.
     */
    public function grades(Request $request, int $student): JsonResponse
    {
        $child = $this->resolveChild($request, 'viewGrades', $student);

        return response()->json(['data' => $this->studentData->grades($child)]);
    }

    /**
     * Story Parent 03 — Présences/absences de l'enfant.
     */
    public function attendance(Request $request, int $student): JsonResponse
    {
        $child = $this->resolveChild($request, 'viewAttendance', $student);

        return response()->json(['data' => $this->studentData->attendance($child)]);
    }

    /**
     * Story Parent 05 — Factures de l'enfant.
     */
    public function invoices(Request $request, int $student): JsonResponse
    {
        $child = $this->resolveChild($request, 'viewInvoices', $student);

        return response()->json(['data' => $this->studentData->invoices($child)]);
    }

    /**
     * Story Parent 04 — Emploi du temps de l'enfant.
     */
    public function timetable(Request $request, int $student): JsonResponse
    {
        $child = $this->resolveChild($request, 'viewTimetable', $student);

        return response()->json(['data' => $this->studentData->timetable($child)]);
    }

    /**
     * Story Parent 09 — Documents disponibles de l'enfant.
     */
    public function documents(Request $request, int $student): JsonResponse
    {
        $child = $this->resolveChild($request, 'viewDocuments', $student);

        return response()->json(['data' => $this->studentData->documents($child)]);
    }

    /**
     * Story Parent 08 — Annonces générales de l'école (non liées à un enfant spécifique).
     *
     * Permission requise : `view announcements` (sans ChildPolicy car non-ownership).
     */
    public function announcements(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view announcements')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return response()->json([
            'data' => [],
            'meta' => [
                'note' => 'Scaffold — annonces générales à câbler avec un module Communication (V2).',
            ],
        ]);
    }

    /**
     * Load the child on the tenant connection (route-model-binding runs before
     * tenancy init, so we accept an int and load manually) and verify ownership
     * via Gate::forUser() (TenantSanctumAuth ne pose pas Auth::user()).
     */
    private function resolveChild(Request $request, string $ability, int $studentId): Student
    {
        $student = Student::on('tenant')->findOrFail($studentId);

        if (! Gate::forUser($request->user())->allows($ability, $student)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return $student;
    }
}
