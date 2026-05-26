<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\Evaluation;
use Modules\NotesEvaluations\Entities\Grade;

class EvaluationController extends Controller
{
    /**
     * List evaluations, filterable by class / subject / semester.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Evaluation::with(['classe', 'subject', 'creator'])
            ->withCount('grades')
            ->orderByDesc('evaluation_date');

        foreach (['class_id', 'subject_id', 'semester_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Create an evaluation for a class + subject.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|integer|exists:tenant.classes,id',
            'subject_id' => 'required|integer|exists:tenant.subjects,id',
            'semester_id' => 'nullable|integer|exists:tenant.semesters,id',
            'type' => ['nullable', 'string', Rule::in(['Devoir', 'Composition', 'Interrogation'])],
            'name' => 'required|string|max:255',
            'evaluation_date' => 'required|date',
            'max_score' => 'nullable|numeric|min:1|max:100',
            'coefficient' => 'nullable|numeric|min:0.5|max:20',
        ]);

        $evaluation = Evaluation::create([
            'class_id' => $validated['class_id'],
            'subject_id' => $validated['subject_id'],
            'semester_id' => $validated['semester_id'] ?? null,
            'type' => $validated['type'] ?? 'Devoir',
            'name' => $validated['name'],
            'evaluation_date' => $validated['evaluation_date'],
            'max_score' => $validated['max_score'] ?? 20,
            'coefficient' => $validated['coefficient'] ?? 1,
            'created_by' => auth('tenant')->id(),
            'status' => 'Draft',
        ]);

        return response()->json(['data' => $evaluation->load(['classe', 'subject'])], 201);
    }

    /**
     * Grade sheet: the evaluation, the students of its class, and current grades.
     */
    public function gradeSheet(int $id): JsonResponse
    {
        $evaluation = Evaluation::on('tenant')->with(['classe', 'subject'])->findOrFail($id);

        $students = Student::on('tenant')
            ->where('class_id', $evaluation->class_id)
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['id', 'matricule', 'firstname', 'lastname']);

        $grades = Grade::on('tenant')
            ->where('evaluation_id', $evaluation->id)
            ->get()
            ->keyBy('student_id');

        $rows = $students->map(function ($student) use ($grades) {
            $grade = $grades->get($student->id);

            return [
                'student_id' => $student->id,
                'matricule' => $student->matricule,
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'grade_id' => $grade?->id,
                'score' => $grade?->score !== null ? (float) $grade->score : null,
                'is_absent' => (bool) ($grade?->is_absent ?? false),
                'comment' => $grade?->comment,
            ];
        });

        $scored = $rows->whereNotNull('score')->where('is_absent', false)->pluck('score');

        return response()->json([
            'data' => [
                'evaluation' => $evaluation,
                'rows' => $rows->values(),
                'stats' => [
                    'count' => $rows->count(),
                    'graded' => $scored->count(),
                    'average' => $scored->count() ? round($scored->avg(), 2) : null,
                    'min' => $scored->count() ? (float) $scored->min() : null,
                    'max' => $scored->count() ? (float) $scored->max() : null,
                ],
            ],
        ]);
    }

    /**
     * Bulk upsert grades for an evaluation.
     */
    public function saveGrades(Request $request, int $id): JsonResponse
    {
        $evaluation = Evaluation::on('tenant')->findOrFail($id);

        $validated = $request->validate([
            'grades' => 'required|array',
            'grades.*.student_id' => 'required|integer|exists:tenant.students,id',
            'grades.*.score' => 'nullable|numeric|min:0|max:'.$evaluation->max_score,
            'grades.*.is_absent' => 'nullable|boolean',
            'grades.*.comment' => 'nullable|string|max:200',
        ]);

        $userId = auth('tenant')->id();

        foreach ($validated['grades'] as $row) {
            $isAbsent = (bool) ($row['is_absent'] ?? false);

            Grade::on('tenant')->updateOrCreate(
                ['student_id' => $row['student_id'], 'evaluation_id' => $evaluation->id],
                [
                    'score' => $isAbsent ? null : ($row['score'] ?? null),
                    'is_absent' => $isAbsent,
                    'comment' => $row['comment'] ?? null,
                    'entered_by' => $userId,
                    'entered_at' => Carbon::now(),
                ]
            );
        }

        return response()->json(['message' => 'Notes enregistrées avec succès.']);
    }

    /**
     * Delete an evaluation (and its grades via FK cascade).
     */
    public function destroy(int $id): JsonResponse
    {
        $evaluation = Evaluation::on('tenant')->findOrFail($id);
        $evaluation->delete();

        return response()->json(['message' => 'Évaluation supprimée.']);
    }
}
