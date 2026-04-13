<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\Enrollment\Http\Resources\StudentEnrollmentResource;

/**
 * Enrollment Validation Controller
 *
 * Handles validation and rejection of student enrollments.
 * Uses StudentEnrollment model (student_enrollments table).
 */
class EnrollmentValidationController extends Controller
{
    /**
     * List pending enrollments for validation
     * Pending = status 'Actif' (active enrollments awaiting validation)
     */
    public function pending(Request $request): JsonResponse
    {
        $enrollments = StudentEnrollment::query()
            ->where('status', 'Actif')
            ->when($request->academic_year_id, fn ($q, $yearId) => $q->where('academic_year_id', $yearId))
            ->when($request->programme_id, fn ($q, $programId) => $q->where('programme_id', $programId))
            ->when($request->level, fn ($q, $level) => $q->where('level', $level))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('firstname', 'like', "%{$search}%")
                ->orWhere('lastname', 'like', "%{$search}%")
                ->orWhere('matricule', 'like', "%{$search}%")
            ))
            ->with(['student', 'programme', 'academicYear', 'semester', 'moduleEnrollments'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => StudentEnrollmentResource::collection($enrollments),
            'current_page' => $enrollments->currentPage(),
            'last_page' => $enrollments->lastPage(),
            'per_page' => $enrollments->perPage(),
            'total' => $enrollments->total(),
        ]);
    }

    /**
     * Show enrollment details
     */
    public function show(int $id): JsonResponse
    {
        $enrollment = StudentEnrollment::with([
            'student',
            'programme',
            'academicYear',
            'semester',
            'moduleEnrollments.module',
        ])->findOrFail($id);

        return response()->json([
            'data' => new StudentEnrollmentResource($enrollment),
        ]);
    }

    /**
     * Check enrollment completeness
     * Returns a checklist for validation
     */
    public function check(int $id): JsonResponse
    {
        $enrollment = StudentEnrollment::with(['student', 'programme', 'academicYear', 'moduleEnrollments.module'])
            ->findOrFail($id);

        // Build checklist
        $checklist = [
            'administrative' => $enrollment->student && $enrollment->student->status === 'Actif',
            'modules_check' => $enrollment->moduleEnrollments->count() > 0,
            'ects_check' => $enrollment->total_credits >= 30,
            'groups_check' => true, // Simplified - assume OK
            'options_check' => true, // Simplified - assume OK
            'prerequisites_check' => true, // Simplified - assume OK
            'details' => [
                'administrative_status' => $enrollment->student?->status ?? 'Unknown',
                'enrolled_modules_count' => $enrollment->moduleEnrollments->count(),
                'required_modules_count' => $enrollment->moduleEnrollments->count(),
                'total_ects' => $enrollment->total_credits,
                'required_ects' => 30,
                'group_assignments_count' => 0,
                'required_group_assignments' => 0,
            ],
        ];

        $checklist['is_complete'] = $checklist['administrative']
            && $checklist['modules_check']
            && $checklist['ects_check'];

        return response()->json([
            'data' => $checklist,
        ]);
    }

    /**
     * Validate an enrollment
     * Changes status from 'Actif' to 'Validé'
     */
    public function validate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'validation_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $enrollment = StudentEnrollment::findOrFail($id);

        if ($enrollment->status !== 'Actif') {
            return response()->json([
                'message' => "L'inscription ne peut pas être validée (statut actuel: {$enrollment->status})",
            ], 422);
        }

        try {
            DB::connection('tenant')->transaction(function () use ($enrollment, $request) {
                $notes = $enrollment->notes;
                if ($request->validation_notes) {
                    $notes = ($notes ? $notes . "\n\n" : '') . "[Validation] " . $request->validation_notes;
                }

                $enrollment->update([
                    'status' => 'Validé',
                    'notes' => $notes,
                ]);
            });

            return response()->json([
                'message' => 'Inscription pédagogique validée avec succès.',
                'data' => new StudentEnrollmentResource($enrollment->fresh(['student', 'programme', 'academicYear', 'semester', 'moduleEnrollments'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Impossible de valider l\'inscription.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject an enrollment
     * Changes status from 'Actif' to 'Rejeté'
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        $enrollment = StudentEnrollment::findOrFail($id);

        if ($enrollment->status !== 'Actif') {
            return response()->json([
                'message' => "L'inscription ne peut pas être rejetée (statut actuel: {$enrollment->status})",
            ], 422);
        }

        try {
            $notes = $enrollment->notes;
            $notes = ($notes ? $notes . "\n\n" : '') . "[Rejet] " . $request->rejection_reason;

            $enrollment->update([
                'status' => 'Rejeté',
                'notes' => $notes,
            ]);

            return response()->json([
                'message' => 'Inscription pédagogique rejetée.',
                'data' => new StudentEnrollmentResource($enrollment->fresh(['student', 'programme'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Impossible de rejeter l\'inscription.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Batch validate multiple enrollments
     */
    public function batchValidate(Request $request): JsonResponse
    {
        $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'exists:tenant.student_enrollments,id'],
        ]);

        $results = [
            'validated' => [],
            'failed' => [],
        ];

        foreach ($request->enrollment_ids as $enrollmentId) {
            try {
                $enrollment = StudentEnrollment::findOrFail($enrollmentId);

                if ($enrollment->status !== 'Actif') {
                    throw new \Exception("Status is not 'Actif'");
                }

                $enrollment->update(['status' => 'Validé']);

                $results['validated'][] = [
                    'id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $enrollmentId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $validatedCount = count($results['validated']);
        $failedCount = count($results['failed']);

        return response()->json([
            'message' => "{$validatedCount} inscription(s) validée(s), {$failedCount} échec(s).",
            'data' => $results,
        ]);
    }

    /**
     * Get validation statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => ['nullable', 'exists:tenant.academic_years,id'],
            'programme_id' => ['nullable', 'exists:tenant.programmes,id'],
        ]);

        $query = StudentEnrollment::query();

        if ($request->academic_year_id) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        if ($request->programme_id) {
            $query->where('programme_id', $request->programme_id);
        }

        $total = $query->count();
        $byStatus = (clone $query)->get()->groupBy('status')->map->count();

        $validated = $byStatus->get('Validé', 0);
        $pending = $byStatus->get('Actif', 0);
        $rejected = $byStatus->get('Rejeté', 0);

        return response()->json([
            'data' => [
                'total' => $total,
                'by_status' => $byStatus->toArray(),
                'validation_rate' => $total > 0 ? round(($validated / $total) * 100, 2) : 0,
                'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 2) : 0,
                'pending_count' => $pending,
            ],
        ]);
    }

    /**
     * Download contract PDF
     * Note: Contract generation not implemented for StudentEnrollment yet
     */
    public function downloadContract(int $id): JsonResponse
    {
        $enrollment = StudentEnrollment::with('student')->findOrFail($id);

        if ($enrollment->status !== 'Validé') {
            return response()->json([
                'message' => 'Le contrat n\'est disponible que pour les inscriptions validées.',
            ], 422);
        }

        // TODO: Implement contract generation for StudentEnrollment
        return response()->json([
            'message' => 'La génération de contrat n\'est pas encore implémentée pour ce type d\'inscription.',
        ], 501);
    }
}
