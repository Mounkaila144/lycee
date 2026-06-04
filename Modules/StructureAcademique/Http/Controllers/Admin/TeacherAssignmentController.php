<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\StructureAcademique\Entities\TeacherAssignment;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Manages teaching assignments: which subjects a teacher teaches in which
 * classes. Used by admins/managers to scope what each teacher can grade.
 */
class TeacherAssignmentController extends Controller
{
    /**
     * List assignments, optionally filtered by teacher.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TeacherAssignment::on('tenant')
            ->with(['classe:id,name', 'subject:id,code,name', 'teacher:id,firstname,lastname,username'])
            ->orderByDesc('id');

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', (int) $request->input('teacher_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', (int) $request->input('class_id'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', (int) $request->input('subject_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Assign one or more subjects to a teacher within a single class.
     * Existing (teacher, class, subject) triples are silently kept.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'required|integer|exists:tenant.users,id',
            'class_id' => 'required|integer|exists:tenant.classes,id',
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'integer|exists:tenant.subjects,id',
        ]);

        $teacher = TenantUser::on('tenant')->findOrFail($validated['teacher_id']);

        if (! $teacher->hasRole('Professeur')) {
            throw ValidationException::withMessages([
                'teacher_id' => "L'utilisateur sélectionné n'a pas le rôle Professeur.",
            ]);
        }

        $created = [];

        foreach ($validated['subject_ids'] as $subjectId) {
            $assignment = TeacherAssignment::on('tenant')->firstOrCreate([
                'teacher_id' => $validated['teacher_id'],
                'class_id' => $validated['class_id'],
                'subject_id' => $subjectId,
            ]);

            $created[] = $assignment->load(['classe:id,name', 'subject:id,code,name']);
        }

        return response()->json([
            'message' => 'Affectations enregistrées avec succès.',
            'data' => $created,
        ], 201);
    }

    /**
     * Remove a single assignment.
     */
    public function destroy(int $assignment): JsonResponse
    {
        $model = TeacherAssignment::on('tenant')->findOrFail($assignment);
        $model->delete();

        return response()->json(['message' => 'Affectation supprimée.']);
    }

    /**
     * Teaching assignments of the currently authenticated user.
     *
     * For Admin / Manager (non-restricted) returns is_restricted = false so the
     * UI can keep showing every class and subject. For a pure Professeur it
     * returns is_restricted = true plus the exact (class, subject) pairs they
     * are allowed to grade.
     */
    public function mine(): JsonResponse
    {
        $user = auth('tenant')->user();

        $isRestricted = $user
            && $user->hasRole('Professeur')
            && ! $user->hasAnyRole(['Administrator', 'Manager']);

        if (! $isRestricted) {
            return response()->json(['data' => ['is_restricted' => false, 'assignments' => []]]);
        }

        $assignments = TeacherAssignment::on('tenant')
            ->with(['classe:id,name', 'subject:id,code,name'])
            ->where('teacher_id', $user->id)
            ->get();

        return response()->json([
            'data' => [
                'is_restricted' => true,
                'assignments' => $assignments,
            ],
        ]);
    }
}
