<?php

namespace Modules\Timetable\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Timetable\Entities\Room;
use Modules\Timetable\Http\Requests\StoreRoomRequest;
use Modules\Timetable\Http\Requests\UpdateRoomRequest;
use Modules\Timetable\Http\Resources\RoomResource;
use Modules\Timetable\Services\RoomAvailabilityService;

class RoomController extends Controller
{
    public function __construct(
        private RoomAvailabilityService $availabilityService
    ) {}

    /**
     * Liste des salles
     */
    public function index(Request $request)
    {
        $rooms = Room::query()
            ->when($request->search, fn ($q, $search) => $q->where(function ($q2) use ($search) {
                $q2->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('building', 'like', "%{$search}%");
            }))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->building, fn ($q, $building) => $q->where('building', $building))
            ->when($request->min_capacity, fn ($q, $min) => $q->where('capacity', '>=', $min))
            ->when($request->boolean('active_only', false), fn ($q) => $q->active())
            ->orderBy($request->sort_by ?? 'name', $request->sort_direction ?? 'asc')
            ->paginate($request->per_page ?? 15);

        return RoomResource::collection($rooms);
    }

    /**
     * Créer une salle
     */
    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        return response()->json([
            'message' => 'Salle créée avec succès.',
            'data' => new RoomResource($room),
        ], 201);
    }

    /**
     * Détails d'une salle
     */
    public function show(Room $room)
    {
        return new RoomResource($room);
    }

    /**
     * Modifier une salle
     */
    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $room->update($request->validated());

        return response()->json([
            'message' => 'Salle modifiée avec succès.',
            'data' => new RoomResource($room),
        ]);
    }

    /**
     * Supprimer une salle
     */
    public function destroy(Room $room): JsonResponse
    {
        // Vérifier si la salle a des créneaux associés
        if ($room->timetableSlots()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer cette salle car elle est utilisée dans des emplois du temps.',
            ], 422);
        }

        $room->delete();

        return response()->json([
            'message' => 'Salle supprimée avec succès.',
        ]);
    }

    /**
     * Liste des salles disponibles pour un créneau donné
     */
    public function available(Request $request)
    {
        $request->validate([
            'day_of_week' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
            'type' => 'nullable|string|in:CM,TD,TP',
            'min_capacity' => 'nullable|integer|min:1',
            'exclude_slot_id' => 'nullable|integer',
        ]);

        $rooms = Room::query()
            ->active()
            ->availableForSlot(
                $request->day_of_week,
                $request->start_time.':00',
                $request->end_time.':00',
                $request->semester_id,
                $request->exclude_slot_id
            )
            ->when($request->type, fn ($q, $type) => $q->whereIn('type', $this->getSuitableRoomTypes($type)))
            ->when($request->min_capacity, fn ($q, $min) => $q->where('capacity', '>=', $min))
            ->orderBy('capacity')
            ->get();

        return RoomResource::collection($rooms);
    }

    /**
     * Statistiques d'occupation des salles
     */
    public function occupation(Request $request, Room $room)
    {
        $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $occupation = $room->getOccupationForSemester($request->semester_id);

        return response()->json([
            'data' => array_merge($occupation, [
                'room' => new RoomResource($room),
            ]),
        ]);
    }

    /**
     * Liste des bâtiments distincts
     */
    public function buildings()
    {
        $buildings = Room::query()
            ->whereNotNull('building')
            ->distinct()
            ->pluck('building')
            ->sort()
            ->values();

        return response()->json(['data' => $buildings]);
    }

    /**
     * Retourne les types de salles appropriés pour un type de séance
     */
    private function getSuitableRoomTypes(string $sessionType): array
    {
        return match ($sessionType) {
            'CM' => ['Amphi', 'Salle'],
            'TD' => ['Salle', 'Amphi'],
            'TP' => ['Labo', 'Salle_Info'],
            default => Room::VALID_TYPES,
        };
    }

    /**
     * Bloquer une salle pour maintenance
     */
    public function block(Request $request, Room $room): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'from' => 'required|date',
            'to' => 'required|date|after:from',
        ]);

        $result = $this->availabilityService->blockRoom(
            $room,
            $request->reason,
            Carbon::parse($request->from),
            Carbon::parse($request->to)
        );

        return response()->json([
            'message' => 'Salle bloquée avec succès.',
            'data' => $result,
        ]);
    }

    /**
     * Débloquer une salle
     */
    public function unblock(Room $room): JsonResponse
    {
        $room = $this->availabilityService->unblockRoom($room);

        return response()->json([
            'message' => 'Salle débloquée avec succès.',
            'data' => new RoomResource($room),
        ]);
    }

    /**
     * Rapport d'occupation détaillé
     */
    public function occupationReport(Request $request, Room $room): JsonResponse
    {
        $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $report = $this->availabilityService->getOccupationReport($room, $request->semester_id);

        return response()->json(['data' => $report]);
    }

    /**
     * Statistiques globales d'occupation des salles
     */
    public function globalStats(Request $request): JsonResponse
    {
        $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $stats = $this->availabilityService->getGlobalOccupationStats($request->semester_id);

        return response()->json(['data' => $stats]);
    }

    /**
     * Salles suggérées pour une séance
     */
    public function suggested(Request $request): JsonResponse
    {
        $request->validate([
            'session_type' => 'required|string|in:CM,TD,TP',
            'group_size' => 'required|integer|min:1',
            'day_of_week' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
            'exclude_slot_id' => 'nullable|integer',
        ]);

        $suggestions = $this->availabilityService->getSuggestedRooms(
            $request->session_type,
            $request->group_size,
            $request->day_of_week,
            $request->start_time.':00',
            $request->end_time.':00',
            $request->semester_id,
            $request->exclude_slot_id
        );

        return response()->json([
            'data' => $suggestions->map(fn ($s) => [
                'room' => new RoomResource($s['room']),
                'capacity_match' => $s['capacity_match'],
                'capacity_warning' => $s['capacity_warning'],
                'excess_capacity' => $s['excess_capacity'],
            ]),
        ]);
    }
}
