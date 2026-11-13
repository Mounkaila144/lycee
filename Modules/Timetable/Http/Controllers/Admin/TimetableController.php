<?php

namespace Modules\Timetable\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Classe;
use Modules\Timetable\Entities\Room;
use Modules\Timetable\Entities\TimetableChange;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\Timetable\Http\Requests\CheckConflictsRequest;
use Modules\Timetable\Http\Requests\StoreTimetableSlotRequest;
use Modules\Timetable\Http\Requests\UpdateTimetableSlotRequest;
use Modules\Timetable\Http\Resources\TimetableChangeResource;
use Modules\Timetable\Http\Resources\TimetableSlotResource;
use Modules\Timetable\Services\ConflictDetectionService;
use Modules\Timetable\Services\TimetableViewService;

class TimetableController extends Controller
{
    private const SLOT_RELATIONS = ['module', 'subject', 'teacher', 'group', 'classe', 'room', 'semester'];

    public function __construct(
        private ConflictDetectionService $conflictService,
        private TimetableViewService $viewService
    ) {}

    /**
     * Liste des créneaux - filtrable par groupe, enseignant, salle
     */
    public function index(Request $request)
    {
        $teacherId = $this->isTeacherOnly($request) ? $request->user()->id : $request->teacher_id;

        $slots = TimetableSlot::query()
            ->with(self::SLOT_RELATIONS)
            ->when($request->semester_id, fn ($q, $id) => $q->bySemester($id))
            ->when($request->group_id, fn ($q, $id) => $q->byGroup($id))
            ->when($request->classe_id, fn ($q, $id) => $q->byClasse($id))
            ->when($request->subject_id, fn ($q, $id) => $q->where('subject_id', $id))
            ->when($teacherId, fn ($q, $id) => $q->byTeacher($id))
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
        $data['type'] ??= 'CM';

        // Formater les heures
        $data['start_time'] = $this->formatTime($data['start_time']);
        $data['end_time'] = $this->formatTime($data['end_time']);

        // Créer un slot temporaire pour vérification
        $tempSlot = $this->makeTemporarySlot($data);

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
        $userId = $request->user()?->id;

        $slot = DB::transaction(function () use ($data, $userId) {
            $slot = TimetableSlot::create($data);

            // Enregistrer l'historique
            TimetableChange::create([
                'timetable_slot_id' => $slot->id,
                'user_id' => $userId,
                'action' => 'Created',
                'new_values' => $data,
            ]);

            return $slot;
        });

        $slot->load(self::SLOT_RELATIONS);

        return response()->json([
            'message' => 'Créneau créé avec succès.',
            'warnings' => $conflictResult['warnings'],
            'data' => new TimetableSlotResource($slot),
        ], 201);
    }

    /**
     * Détails d'un créneau
     */
    public function show(Request $request, int $slot): TimetableSlotResource
    {
        $slot = TimetableSlot::findOrFail($slot);
        $this->ensureTeacherOwnsSlot($request, $slot);

        $slot->load([...self::SLOT_RELATIONS, 'changes.user']);

        return new TimetableSlotResource($slot);
    }

    /**
     * Modifier un créneau
     */
    public function update(UpdateTimetableSlotRequest $request, int $slot): JsonResponse
    {
        $slot = TimetableSlot::findOrFail($slot);
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
        $tempSlot = $this->makeTemporarySlot(array_merge($slot->only($slot->getFillable()), $data));

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
        $userId = $request->user()?->id;

        DB::transaction(function () use ($slot, $data, $oldValues, $userId) {
            $slot->update($data);

            // Enregistrer l'historique
            TimetableChange::create([
                'timetable_slot_id' => $slot->id,
                'user_id' => $userId,
                'action' => 'Updated',
                'old_values' => $oldValues,
                'new_values' => $data,
            ]);
        });

        $slot->load(self::SLOT_RELATIONS);

        return response()->json([
            'message' => 'Créneau modifié avec succès.',
            'warnings' => $conflictResult['warnings'],
            'data' => new TimetableSlotResource($slot),
        ]);
    }

    /**
     * Supprimer un créneau
     */
    public function destroy(Request $request, int $slot): JsonResponse
    {
        $slot = TimetableSlot::findOrFail($slot);
        $oldValues = $slot->toArray();

        DB::transaction(function () use ($slot, $oldValues, $request) {
            // Enregistrer l'historique avant suppression
            TimetableChange::create([
                'timetable_slot_id' => $slot->id,
                'user_id' => $request->user()?->id,
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

        $data['type'] ??= 'CM';
        $tempSlot = $this->makeTemporarySlot($data);

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
            ->with(['module', 'subject', 'teacher', 'classe', 'room'])
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
    public function byTeacher(Request $request, int $teacherId): JsonResponse
    {
        if ($this->isTeacherOnly($request) && $request->user()->id !== $teacherId) {
            abort(403, 'Vous ne pouvez consulter que votre propre emploi du temps.');
        }

        $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $slots = TimetableSlot::query()
            ->with(['module', 'subject', 'group', 'classe', 'room'])
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
            ->with(['module', 'subject', 'teacher', 'group', 'classe'])
            ->byRoom($roomId)
            ->bySemester($request->semester_id)
            ->orderByRaw("FIELD(day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi')")
            ->orderBy('start_time')
            ->get();

        return $this->formatAsGrid($slots);
    }

    /**
     * Emploi du temps d'un élève (via sa classe active)
     */
    public function byStudent(Request $request, int $studentId): JsonResponse
    {
        $request->validate([
            'semester_id' => 'nullable|integer|exists:tenant.semesters,id',
        ]);

        $student = Student::findOrFail($studentId);
        $timetable = $this->viewService->forStudent($student, $request->integer('semester_id') ?: null);

        return $this->formatAsGrid($timetable['slots'], [
            'classe' => $timetable['classe'],
            'semester_id' => $timetable['semester_id'],
        ]);
    }

    /**
     * Emploi du temps d'une classe (vue grille)
     */
    public function byClasse(Request $request, int $classeId): JsonResponse
    {
        $request->validate([
            'semester_id' => 'nullable|integer|exists:tenant.semesters,id',
        ]);

        $classe = Classe::findOrFail($classeId);
        $semesterId = $this->viewService->resolveSemesterId(
            $request->integer('semester_id') ?: null,
            $classe->academic_year_id
        );

        return $this->formatAsGrid($this->viewService->slotsForClasse($classe->id, $semesterId), [
            'classe' => ['id' => $classe->id, 'name' => $classe->name],
            'semester_id' => $semesterId,
        ]);
    }

    /**
     * Emploi du temps de l'enseignant connecté
     */
    public function myTimetable(Request $request): JsonResponse
    {
        $request->validate([
            'semester_id' => 'nullable|integer|exists:tenant.semesters,id',
        ]);

        $semesterId = $this->viewService->resolveSemesterId($request->integer('semester_id') ?: null);

        return $this->formatAsGrid($this->viewService->slotsForTeacher($request->user()->id, $semesterId), [
            'teacher' => [
                'id' => $request->user()->id,
                'name' => trim($request->user()->firstname.' '.$request->user()->lastname),
            ],
            'semester_id' => $semesterId,
        ]);
    }

    /**
     * Historique des modifications d'un créneau
     */
    public function history(Request $request, int $slot)
    {
        $slot = TimetableSlot::findOrFail($slot);
        $this->ensureTeacherOwnsSlot($request, $slot);

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
    /**
     * Professeur sans rôle de gestion : accès restreint à ses propres créneaux.
     */
    private function isTeacherOnly(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $user->hasRole('Professeur', 'tenant')
            && ! $user->hasAnyRole(['Administrator', 'Manager'], 'tenant');
    }

    private function ensureTeacherOwnsSlot(Request $request, TimetableSlot $slot): void
    {
        if ($this->isTeacherOnly($request) && (int) $slot->teacher_id !== $request->user()->id) {
            abort(403, 'Ce créneau ne vous est pas attribué.');
        }
    }

    /**
     * Crée un créneau non persisté (avec ses relations) pour la détection de conflits.
     *
     * @param  array<string, mixed>  $data
     */
    private function makeTemporarySlot(array $data): TimetableSlot
    {
        $tempSlot = new TimetableSlot($data);
        $tempSlot->setRelation('room', ! empty($data['room_id']) ? Room::find($data['room_id']) : null);
        $tempSlot->setRelation('group', ! empty($data['group_id']) ? Group::find($data['group_id']) : null);
        $tempSlot->setRelation('classe', ! empty($data['classe_id']) ? Classe::find($data['classe_id']) : null);

        return $tempSlot;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function formatAsGrid($slots, array $meta = []): JsonResponse
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
            ...$meta,
        ]);
    }
}
