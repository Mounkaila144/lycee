<?php

namespace Modules\Attendance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Attendance\Entities\AbsenceJustification;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Attendance\Http\Requests\SubmitJustificationRequest;
use Modules\Attendance\Services\AttendanceAccessService;
use Modules\Attendance\Services\JustificationService;
use Modules\Enrollment\Entities\Student;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Présences & justificatifs côté familles (Parent / Élève).
 *
 * `student_id` désigne ici la fiche élève (students.id), comme dans le portail Parent.
 * Un Parent n'agit que pour ses enfants, un Élève que pour lui-même.
 */
class FamilyAttendanceController extends Controller
{
    public function __construct(
        private JustificationService $justificationService,
        private AttendanceAccessService $accessService
    ) {}

    /**
     * Élèves accessibles (enfants du Parent / l'Élève lui-même).
     */
    public function students(Request $request): JsonResponse
    {
        $students = Student::query()
            ->whereIn('id', $this->accessService->ownStudentIds($request->user()))
            ->orderBy('lastname')
            ->get(['id', 'user_id', 'matricule', 'firstname', 'lastname']);

        return response()->json(['data' => $students]);
    }

    /**
     * Absences / retards enregistrés pour un élève accessible.
     */
    public function absences(Request $request): JsonResponse
    {
        $student = $this->resolveOwnStudent($request, $request->integer('student_id') ?: null);

        $records = AttendanceRecord::query()
            ->with($this->accessService->sessionsHaveClasse()
                ? ['session:id,classe_id,subject_id,session_date,start_time,end_time', 'session.classe:id,name', 'session.subject:id,name']
                : ['session:id,session_date,start_time,end_time'])
            ->where('student_id', $student->user_id)
            ->whereIn('status', ['absent', 'late', 'excused'])
            ->latest('id')
            ->get(['id', 'attendance_session_id', 'student_id', 'status', 'arrival_time', 'delay_minutes', 'modification_reason']);

        return response()->json([
            'data' => $records,
            'meta' => ['student_id' => $student->id],
        ]);
    }

    /**
     * Justificatifs des élèves accessibles.
     */
    public function justifications(Request $request): JsonResponse
    {
        if (! $this->accessService->justificationsTableExists()) {
            return response()->json(['data' => []]);
        }

        $studentUserIds = $this->accessService->ownStudentUserIds($request->user());

        if ($request->filled('student_id')) {
            $studentUserIds = [(int) $this->resolveOwnStudent($request, $request->integer('student_id'))->user_id];
        }

        $justifications = AbsenceJustification::query()
            ->with(['student:id,firstname,lastname', 'validator:id,firstname,lastname'])
            ->whereIn('student_id', $studentUserIds)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $justifications]);
    }

    /**
     * Soumettre un justificatif pour son enfant (Parent) ou pour soi (Élève).
     */
    public function submit(SubmitJustificationRequest $request): JsonResponse
    {
        $this->ensureJustificationsTable();

        $validated = $request->validated();
        $student = $this->resolveOwnStudent($request, isset($validated['student_id']) ? (int) $validated['student_id'] : null);

        $justification = $this->justificationService->submitJustification(
            (int) $student->user_id,
            Carbon::parse($validated['absence_date_from']),
            Carbon::parse($validated['absence_date_to']),
            $validated['type'],
            $validated['reason'],
            $request->file('document'),
            (int) $request->user()->getAuthIdentifier()
        );

        return response()->json([
            'message' => 'Justificatif soumis, en attente de validation.',
            'data' => $justification,
        ], 201);
    }

    /**
     * Télécharger le document d'un justificatif accessible.
     */
    public function download(Request $request, int $justificationId): JsonResponse|BinaryFileResponse
    {
        $this->ensureJustificationsTable();

        $justification = AbsenceJustification::query()->findOrFail($justificationId);

        if (! $this->accessService->canViewJustification($request->user(), $justification)) {
            abort(403, 'Vous ne pouvez pas consulter ce justificatif.');
        }

        $path = $this->justificationService->downloadDocument($justification);

        if (! $path || ! is_file($path)) {
            return response()->json(['message' => 'Aucun document disponible'], 404);
        }

        return response()->download($path);
    }

    /**
     * Résout la fiche élève ciblée en vérifiant l'appartenance (403 sinon).
     * Sans `student_id`, prend l'unique élève accessible (cas Élève ou Parent d'un seul enfant).
     */
    private function resolveOwnStudent(Request $request, ?int $studentId): Student
    {
        $ownStudentIds = $this->accessService->ownStudentIds($request->user());

        if ($studentId === null) {
            if (count($ownStudentIds) !== 1) {
                abort(422, 'Veuillez préciser l\'élève concerné (student_id).');
            }
            $studentId = $ownStudentIds[0];
        }

        if (! in_array($studentId, $ownStudentIds, true)) {
            abort(403, 'Vous ne pouvez agir que pour vos propres enfants.');
        }

        $student = Student::query()->findOrFail($studentId);

        if (! $student->user_id) {
            abort(422, 'Cet élève n\'a pas de compte utilisateur associé.');
        }

        return $student;
    }

    private function ensureJustificationsTable(): void
    {
        if (! $this->accessService->justificationsTableExists()) {
            abort(503, 'Justificatifs indisponibles : migration en attente. Réessayez plus tard.');
        }
    }
}
