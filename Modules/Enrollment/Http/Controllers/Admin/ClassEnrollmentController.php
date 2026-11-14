<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Requests\CancelClassEnrollmentRequest;
use Modules\Enrollment\Http\Requests\ChangeStudentClassRequest;
use Modules\Enrollment\Http\Requests\StoreClassEnrollmentRequest;
use Modules\Enrollment\Services\ClassEnrollmentManagementService;
use Modules\StructureAcademique\Entities\Classe;

/**
 * Inscriptions des élèves dans les classes (Admin | Manager).
 */
class ClassEnrollmentController extends Controller
{
    public function __construct(private ClassEnrollmentManagementService $enrollmentService) {}

    /**
     * Historique des inscriptions en classe d'un élève (toutes années).
     */
    public function studentIndex(int $studentId): JsonResponse
    {
        $student = Student::query()->findOrFail($studentId);

        $enrollments = ClassEnrollment::query()
            ->with(['classe:id,name,max_capacity', 'academicYear:id,name,is_active,start_date'])
            ->where('student_id', $student->id)
            ->get()
            ->sortByDesc(fn (ClassEnrollment $enrollment): string => (string) $enrollment->academicYear?->start_date)
            ->values();

        return response()->json([
            'data' => $enrollments->map(fn (ClassEnrollment $enrollment): array => $this->present($enrollment))->all(),
        ]);
    }

    /**
     * Inscrit l'élève dans une classe (année = année de la classe).
     */
    public function store(StoreClassEnrollmentRequest $request, int $studentId): JsonResponse
    {
        $student = Student::query()->findOrFail($studentId);

        $enrollment = $this->enrollmentService->enroll(
            $student,
            $request->integer('classe_id'),
            $request->user(),
            $request->validated('assignment_type'),
            $request->validated('enrolled_at'),
            $request->validated('notes'),
        );

        return response()->json([
            'message' => "Élève inscrit en {$enrollment->classe?->name}.",
            'data' => $this->present($enrollment),
        ], 201);
    }

    /**
     * Change l'élève de classe dans la même année scolaire (transfert interne).
     */
    public function changeClass(ChangeStudentClassRequest $request, int $studentId): JsonResponse
    {
        $student = Student::query()->findOrFail($studentId);

        $enrollment = $this->enrollmentService->changeClass(
            $student,
            $request->integer('classe_id'),
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'message' => "Élève transféré en {$enrollment->classe?->name}.",
            'data' => $this->present($enrollment),
        ]);
    }

    /**
     * Annule une inscription active.
     */
    public function cancel(CancelClassEnrollmentRequest $request, int $enrollmentId): JsonResponse
    {
        $enrollment = ClassEnrollment::query()->findOrFail($enrollmentId);

        $enrollment = $this->enrollmentService->cancel($enrollment, $request->user(), $request->validated('reason'));

        return response()->json([
            'message' => 'Inscription annulée.',
            'data' => $this->present($enrollment->load(['classe:id,name,max_capacity', 'academicYear:id,name,is_active'])),
        ]);
    }

    /**
     * Inscriptions d'une classe (filtre facultatif `status`, par défaut toutes).
     */
    public function classIndex(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::query()->with('academicYear:id,name,is_active')->findOrFail($classeId);

        $enrollments = ClassEnrollment::query()
            ->with('student:id,matricule,firstname,lastname,sex,status')
            ->where('classe_id', $classe->id)
            ->when(
                in_array($request->query('status'), ClassEnrollment::STATUSES, true),
                fn ($query) => $query->where('status', $request->query('status'))
            )
            ->get()
            ->sortBy(fn (ClassEnrollment $enrollment): string => mb_strtolower(($enrollment->student?->lastname ?? '').' '.($enrollment->student?->firstname ?? '')))
            ->values();

        return response()->json([
            'data' => $enrollments->map(fn (ClassEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'assignment_type' => $enrollment->assignment_type,
                'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
                'final_decision' => $enrollment->final_decision,
                'notes' => $enrollment->notes,
                'student' => $enrollment->student ? [
                    'id' => $enrollment->student->id,
                    'matricule' => $enrollment->student->matricule,
                    'firstname' => $enrollment->student->firstname,
                    'lastname' => $enrollment->student->lastname,
                    'full_name' => trim($enrollment->student->firstname.' '.$enrollment->student->lastname),
                    'sex' => $enrollment->student->sex,
                    'status' => $enrollment->student->status,
                ] : null,
            ])->all(),
            'meta' => [
                'classe' => [
                    'id' => $classe->id,
                    'name' => $classe->name,
                    'max_capacity' => $classe->max_capacity,
                    'academic_year' => $classe->academicYear ? [
                        'id' => $classe->academicYear->id,
                        'name' => $classe->academicYear->name,
                        'is_active' => (bool) $classe->academicYear->is_active,
                    ] : null,
                ],
                'active_count' => $enrollments->where('status', 'Actif')->count(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ClassEnrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'classe_id' => $enrollment->classe_id,
            'classe' => $enrollment->classe ? [
                'id' => $enrollment->classe->id,
                'name' => $enrollment->classe->name,
                'max_capacity' => $enrollment->classe->max_capacity,
            ] : null,
            'academic_year_id' => $enrollment->academic_year_id,
            'academic_year' => $enrollment->academicYear ? [
                'id' => $enrollment->academicYear->id,
                'name' => $enrollment->academicYear->name,
                'is_active' => (bool) $enrollment->academicYear->is_active,
            ] : null,
            'assignment_type' => $enrollment->assignment_type,
            'status' => $enrollment->status,
            'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
            'final_decision' => $enrollment->final_decision,
            'notes' => $enrollment->notes,
        ];
    }
}
