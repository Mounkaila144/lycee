<?php

namespace Modules\Enrollment\Http\Controllers\Frontend;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Enrollment\Entities\GroupAssignment;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Resources\GroupAssignmentResource;

class StudentGroupController extends Controller
{
    /**
     * Get the authenticated student's group assignments
     */
    public function myGroups(): JsonResponse
    {
        $user = Auth::user();

        // Find the student associated with this user
        $student = Student::on('tenant')
            ->where('email', $user->email)
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'Profil étudiant non trouvé',
                'data' => [],
            ], 404);
        }

        $assignments = GroupAssignment::on('tenant')
            ->with([
                'group' => function ($query) {
                    $query->with(['module', 'programme', 'academicYear', 'semester', 'teacher']);
                },
                'module',
                'academicYear',
            ])
            ->where('student_id', $student->id)
            ->orderBy('assigned_at', 'desc')
            ->get();

        // Group by academic year and module for better organization
        $groupedAssignments = $assignments->groupBy(function ($assignment) {
            return $assignment->academicYear->name ?? 'Unknown Year';
        })->map(function ($yearGroup) {
            return $yearGroup->groupBy(function ($assignment) {
                return $assignment->module->name ?? 'Unknown Module';
            });
        });

        return response()->json([
            'data' => GroupAssignmentResource::collection($assignments),
            'grouped' => $groupedAssignments->map(function ($modules) {
                return $modules->map(function ($assignments) {
                    return GroupAssignmentResource::collection($assignments);
                });
            }),
            'meta' => [
                'total' => $assignments->count(),
                'student' => [
                    'id' => $student->id,
                    'matricule' => $student->matricule,
                    'name' => $student->firstname.' '.$student->lastname,
                ],
            ],
        ]);
    }

    /**
     * Get student's groups for a specific academic year
     */
    public function myGroupsByYear(int $academicYearId): JsonResponse
    {
        $user = Auth::user();

        $student = Student::on('tenant')
            ->where('email', $user->email)
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'Profil étudiant non trouvé',
                'data' => [],
            ], 404);
        }

        $assignments = GroupAssignment::on('tenant')
            ->with([
                'group' => function ($query) {
                    $query->with(['module', 'teacher']);
                },
                'module',
            ])
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYearId)
            ->orderBy('assigned_at', 'desc')
            ->get();

        return response()->json([
            'data' => GroupAssignmentResource::collection($assignments),
            'meta' => [
                'total' => $assignments->count(),
            ],
        ]);
    }
}
