<?php

namespace Modules\Exams\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Exams\Entities\ExamSession;
use Modules\Exams\Http\Requests\CreateEvaluationsFromSessionRequest;
use Modules\Exams\Http\Requests\StoreExamSessionRequest;
use Modules\Exams\Http\Requests\UpdateExamSessionRequest;
use Modules\Exams\Services\ExamGradeLinkService;
use Modules\Exams\Services\ExamPlanningService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\Timetable\Entities\Room;
use Modules\UsersGuard\Entities\TenantUser;

class ExamSessionController extends Controller
{
    public function __construct(
        private ExamPlanningService $planningService,
        private ExamGradeLinkService $gradeLinkService,
    ) {}

    /**
     * Relations chargées sur les listes et fiches de session.
     *
     * @return array<int, string>
     */
    private function listRelations(): array
    {
        $relations = ['module', 'evaluationPeriod', 'academicYear'];

        if (ExamSession::hasLyceeColumns()) {
            $relations = array_merge($relations, ['subject', 'classe', 'level', 'semester']);
        }

        return $relations;
    }

    public function index(Request $request): JsonResponse
    {
        $query = ExamSession::query()
            ->with($this->listRelations())
            ->withCount(['roomAssignments', 'supervisors', 'attendanceSheets', 'incidents']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        if (ExamSession::hasLyceeColumns()) {
            foreach (['subject_id', 'classe_id', 'level_id', 'semester_id'] as $filter) {
                if ($request->filled($filter)) {
                    $query->where($filter, $request->input($filter));
                }
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('exam_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('exam_date', '<=', $request->date_to);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $sessions = $query->orderByDesc('exam_date')->orderBy('start_time')->paginate($perPage);

        return response()->json($sessions);
    }

    /**
     * Référentiels du formulaire de session : années, classes, niveaux, matières,
     * semestres, salles et professeurs (surveillants).
     */
    public function options(Request $request): JsonResponse
    {
        $academicYears = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'is_active']);
        $academicYearId = $request->integer('academic_year_id')
            ?: $academicYears->firstWhere('is_active', true)?->id
            ?: $academicYears->first()?->id;

        return response()->json([
            'data' => [
                'lycee_ready' => ExamSession::hasLyceeColumns(),
                'grades_link_available' => $this->gradeLinkService->isAvailable(),
                'academic_year_id' => $academicYearId,
                'academic_years' => $academicYears,
                'classes' => Classe::query()
                    ->where('academic_year_id', $academicYearId)
                    ->orderBy('level_id')
                    ->orderBy('name')
                    ->get(['id', 'name', 'level_id', 'academic_year_id']),
                'levels' => Level::query()->orderBy('id')->get(['id', 'code', 'name']),
                'subjects' => Subject::query()->orderBy('name')->get(['id', 'code', 'name']),
                'semesters' => Semester::query()
                    ->where('academic_year_id', $academicYearId)
                    ->orderBy('start_date')
                    ->get(['id', 'name', 'academic_year_id', 'start_date', 'end_date', 'is_active']),
                'rooms' => Room::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'building', 'capacity']),
                'teachers' => TenantUser::query()
                    ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'Professeur'))
                    ->where('is_active', true)
                    ->orderBy('lastname')
                    ->orderBy('firstname')
                    ->get(['id', 'firstname', 'lastname']),
            ],
        ]);
    }

    public function store(StoreExamSessionRequest $request): JsonResponse
    {
        $session = $this->planningService->createExamSession($request->validated());

        return response()->json($session->load(array_merge($this->listRelations(), ['roomAssignments.room'])), 201);
    }

    public function show(int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);

        return response()->json($session->load(array_merge($this->listRelations(), [
            'roomAssignments.room',
            'supervisors.teacher:id,firstname,lastname',
            'supervisors.roomAssignment.room',
            'attendanceSheets.student:id,matricule,firstname,lastname',
            'attendanceSheets.roomAssignment.room',
        ])));
    }

    public function update(UpdateExamSessionRequest $request, int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);

        $session = $this->planningService->updateExamSession($session, $request->validated());

        return response()->json($session->load($this->listRelations()));
    }

    public function destroy(int $session): JsonResponse
    {
        ExamSession::query()->findOrFail($session)->delete();

        return response()->json(null, 204);
    }

    public function publish(int $session): JsonResponse
    {
        $session = $this->planningService->publishSession(ExamSession::query()->findOrFail($session));

        return response()->json($session);
    }

    public function cancel(Request $request, int $session): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = $this->planningService->cancelSession(ExamSession::query()->findOrFail($session), $validated['reason'] ?? null);

        return response()->json($session);
    }

    /**
     * Démarrer (ongoing) ou clôturer (completed) une session publiée.
     */
    public function changeStatus(Request $request, int $session): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['ongoing', 'completed'])],
        ], [
            'status.in' => 'Statut invalide : en cours ou terminée uniquement.',
        ]);

        $session = ExamSession::query()->findOrFail($session);

        if (in_array($session->status, ['draft', 'cancelled'], true)) {
            return response()->json([
                'message' => 'La session doit être publiée (et non annulée) avant de démarrer ou de se terminer.',
            ], 422);
        }

        return response()->json($this->planningService->changeStatus($session, $validated['status']));
    }

    /**
     * Crée les compositions (notes lycée) d'une session terminée : une évaluation par classe.
     */
    public function createEvaluations(CreateEvaluationsFromSessionRequest $request, int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);

        $evaluations = $this->gradeLinkService->createCompositions($session, $request->user(), $request->validated());

        return response()->json([
            'data' => $evaluations->map(fn ($evaluation): array => [
                'id' => $evaluation->id,
                'classe_id' => $evaluation->classe_id,
                'subject_id' => $evaluation->subject_id,
                'semester_id' => $evaluation->semester_id,
                'title' => $evaluation->title,
                'type' => $evaluation->type,
                'status' => $evaluation->status,
            ])->values(),
            'message' => $evaluations->count().' évaluation(s) de type composition créée(s) en brouillon.',
        ], 201);
    }

    public function duplicate(Request $request, int $session): JsonResponse
    {
        $newSession = $this->planningService->duplicateSession(ExamSession::query()->findOrFail($session), $request->all());

        return response()->json($newSession, 201);
    }

    public function validateSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'module_id' => 'nullable|exists:tenant.modules,id',
            'classe_id' => 'nullable|exists:tenant.classes,id',
            'level_id' => 'nullable|exists:tenant.levels,id',
            'academic_year_id' => 'nullable|exists:tenant.academic_years,id',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'exists:tenant.rooms,id',
            'exclude_session_id' => 'nullable|exists:tenant.exam_sessions,id',
        ]);

        $conflicts = $this->planningService->validateSchedule($validated, $validated['exclude_session_id'] ?? null);

        return response()->json([
            'valid' => empty($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    public function availableRooms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        $rooms = $this->planningService->getAvailableRooms(
            $validated['date'],
            $validated['start_time'],
            $validated['end_time']
        );

        return response()->json($rooms);
    }
}
