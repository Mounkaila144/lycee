<?php

namespace Modules\Timetable\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Group;
use Modules\Timetable\Entities\Room;
use Modules\Timetable\Entities\TimetableChange;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\Timetable\Http\Requests\CheckConflictsRequest;
use Modules\Timetable\Http\Requests\StoreTimetableSlotRequest;
use Modules\Timetable\Http\Requests\UpdateTimetableSlotRequest;
use Modules\Timetable\Http\Resources\TimetableChangeResource;
use Modules\Timetable\Http\Resources\TimetableSlotResource;
use Modules\Timetable\Services\ConflictDetectionService;

class TimetableController extends Controller
{
    public function __construct(
        private ConflictDetectionService $conflictService
    ) {}

    /**
     * Liste des créneaux - filtrable par groupe, enseignant, salle
     */
    public function index(Request $request)
    {
        $slots = TimetableSlot::query()
            ->with(['module', 'teacher', 'group', 'room', 'semester'])
            ->when($request->semester_id, fn ($q, $id) => $q->bySemester($id))
            ->when($request->group_id, fn ($q, $id) => $q->byGroup($id))
            ->when($request->teacher_id, fn ($q, $id) => $q->byTeacher($id))
            ->when($request->room_id, fn ($q, $id) => $q->byRoom($id))
            ->when($request->day_of_week, fn ($q, $day) => $q->byDay($day))
            ->when($request->type, fn ($q, $type) => $q->byType($type))
            ->when($request->module_id, fn ($q, $id) => $q->where('module_id', $id))
            ->orderByRaw("FIELD(day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi')")
            ->orderBy('start_time')
            ->paginate($request->per_page ?? 50);

        return TimetableSlotResource::collection($slots);
    }

    /**
     * Créer un créneau
     */
    public function store(StoreTimetableSlotRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Formater les heures
        $data['start_time'] = $this->formatTime($data['start_time']);
        $data['end_time'] = $this->formatTime($data['end_time']);

        // Créer un slot temporaire pour vérification
        $tempSlot = new TimetableSlot($data);
        $tempSlot->setRelation('room', Room::find($data['room_id']));
        $tempSlot->setRelation('group', Group::find($data['group_id']));

        // Vérifier les conflits
        $conflictResult = $this->conflictService->detectConflicts($tempSlot);

        if ($conflictResult['hasConflicts']) {
            // Suggérer des alternatives
            $alternatives = $this->conflictService->suggestAlternatives($tempSlot, 5);

            return response()->json([
                'message' => 'Des conflits ont été détectés pour ce créneau.',
                'conflicts' => $conflictResult['conflicts'],
                'warnings' => $conflictResult['warnings'],
                'alternatives' => $alternatives,
            ], 422);
        }

        // Créer le créneau
        $slot = DB::transaction(function () use ($data) {
            $slot = TimetableSlot::create($data);

            // Enregistrer l'historique
            TimetableChange::create([
                'timetable_slot_id' => $slot->id,
                'user_id' => Auth::id(),
                'action' => 'Created',
                'new_values' => $data,
            ]);

            return $slot;
        });

        $slot->load(['module', 'teacher', 'group', 'room', 'semester']);

        return response()->json([
            'message' => 'Créneau créé avec succès.',
            'warnings' => $conflictResult['warnings'],
            'data' => new TimetableSlotResource($slot),
        ], 201);
    }

    /**
     * Détails d'un créneau
     */
    public function show(TimetableSlot $slot)
    {
        $slot->load(['module', 'teacher', 'group', 'room', 'semester', 'changes.user']);

        return new TimetableSlotResource($slot);
    }

    /**
     * Modifier un créneau
     */
    public function update(UpdateTimetableSlotRequest $request, TimetableSlot $slot): JsonResponse
    {
        $data = $request->validated();
        $oldValues = $slot->toArray();

        // Formater les heures si présentes
        if (isset($data['start_time'])) {
            $data['start_time'] = $this->formatTime($data['start_time']);
        }
        if (isset($data['end_time'])) {
            $data['end_time'] = $this->formatTime($data['end_time']);
        }

        // Créer un slot temporaire avec les nouvelles valeurs pour vérification
        $tempSlot = new TimetableSlot(array_merge($slot->toArray(), $data));
        $tempSlot->setRelation('room', Room::find($data['room_id'] ?? $slot->room_id));
        $tempSlot->setRelation('group', Group::find($data['group_id'] ?? $slot->group_id));

        // Vérifier les conflits (en excluant le créneau actuel)
        $conflictResult = $this->conflictService->detectConflicts($tempSlot, $slot->id);

        if ($conflictResult['hasConflicts']) {
            $alternatives = $this->conflictService->suggestAlternatives($tempSlot, 5);

            return response()->json([
                'message' => 'Des conflits ont été détectés pour cette modification.',
                'conflicts' => $conflictResult['conflicts'],
                'warnings' => $conflictResult['warnings'],
                'alternatives' => $alternatives,
            ], 422);
        }

        // Mettre à jour le créneau
        DB::transaction(function () use ($slot, $data, $oldValues) {
            $slot->update($data);

            // Enregistrer l'historique
            TimetableChange::create([
                'timetable_slot_id' => $slot->id,
                'user_id' => Auth::id(),
                'action' => 'Updated',
                'old_values' => $oldValues,
                'new_values' => $data,
            ]);
        });

        $slot->load(['module', 'teacher', 'group', 'room', 'semester']);

        return response()->json([
            'message' => 'Créneau modifié avec succès.',
            'warnings' => $conflictResult['warnings'],
            'data' => new TimetableSlotResource($slot),
        ]);
    }

    /**
     * Supprimer un créneau
     */
    public function destroy(Request $request, TimetableSlot $slot): JsonResponse
    {
        $oldValues = $slot->toArray();

        DB::transaction(function () use ($slot, $oldValues, $request) {
            // Enregistrer l'historique avant suppression
            TimetableChange::create([
                'timetable_slot_id' => $slot->id,
                'user_id' => Auth::id(),
                'action' => 'Deleted',
                'old_values' => $oldValues,
                'reason' => $request->reason,
            ]);

            $slot->delete();
        });

        return response()->json([
            'message' => 'Créneau supprimé avec succès.',
        ]);
    }

    /**
     * Vérifier les conflits sans créer
     */
    public function checkConflicts(CheckConflictsRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Formater les heures
        $data['start_time'] = $this->formatTime($data['start_time']);
        $data['end_time'] = $this->formatTime($data['end_time']);

        $tempSlot = new TimetableSlot($data);
        $tempSlot->setRelation('room', Room::find($data['room_id']));
        $tempSlot->setRelation('group', Group::find($data['group_id']));

        $result = $this->conflictService->detectConflicts($tempSlot, $request->exclude_slot_id);

        $response = [
            'has_conflicts' => $result['hasConflicts'],
            'conflicts' => $result['conflicts'],
            'warnings' => $result['warnings'],
        ];

        if ($result['hasConflicts']) {
            $response['alternatives'] = $this->conflictService->suggestAlternatives($tempSlot, 5);
        }

        return response()->json($response);
    }

    /**
     * Emploi du temps d'un groupe (vue grille)
     */
    public function byGroup(Request $request, int $groupId)
    {
        $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $slots = TimetableSlot::query()
            ->with(['module', 'teacher', 'room'])
            ->byGroup($groupId)
            ->bySemester($request->semester_id)
            ->orderByRaw("FIELD(day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi')")
            ->orderBy('start_time')
            ->get();

        return $this->formatAsGrid($slots);
    }

    /**
     * Emploi du temps d'un enseignant
     */
    public function byTeacher(Request $request, int $teacherId)
    {
        $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $slots = TimetableSlot::query()
            ->with(['module', 'group', 'room'])
            ->byTeacher($teacherId)
            ->bySemester($request->semester_id)
            ->orderByRaw("FIELD(day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi')")
            ->orderBy('start_time')
            ->get();

        return $this->formatAsGrid($slots);
    }

    /**
     * Emploi du temps d'une salle
     */
    public function byRoom(Request $request, int $roomId)
    {
        $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $slots = TimetableSlot::query()
            ->with(['module', 'teacher', 'group'])
            ->byRoom($roomId)
            ->bySemester($request->semester_id)
            ->orderByRaw("FIELD(day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi')")
            ->orderBy('start_time')
            ->get();

        return $this->formatAsGrid($slots);
    }

    /**
     * Emploi du temps d'un étudiant (via ses groupes)
     */
    public function byStudent(Request $request, int $studentId)
    {
        $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        // Récupérer groupes de l'étudiant pour ce semestre
        $groupIds = DB::connection('tenant')
            ->table('enrollments')
            ->join('group_student', 'enrollments.student_id', '=', 'group_student.student_id')
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.semester_id', $request->semester_id)
            ->pluck('group_student.group_id')
            ->unique();

        $slots = TimetableSlot::query()
            ->with(['module', 'teacher', 'room', 'group'])
            ->whereIn('group_id', $groupIds)
            ->where('semester_id', $request->semester_id)
            ->orderByRaw("FIELD(day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi')")
            ->orderBy('start_time')
            ->get();

        return $this->formatAsGrid($slots);
    }

    /**
     * Historique des modifications d'un créneau
     */
    public function history(TimetableSlot $slot)
    {
        $changes = $slot->changes()
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return TimetableChangeResource::collection($changes);
    }

    /**
     * Formate l'heure au format H:i:s
     */
    private function formatTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }

    /**
     * Formate les créneaux en grille hebdomadaire
     */
    private function formatAsGrid($slots): JsonResponse
    {
        $grid = [];
        $days = TimetableSlot::VALID_DAYS;

        foreach ($days as $day) {
            $grid[$day] = [];
        }

        foreach ($slots as $slot) {
            $grid[$slot->day_of_week][] = new TimetableSlotResource($slot);
        }

        return response()->json([
            'data' => $grid,
            'total_slots' => $slots->count(),
        ]);
    }
}
