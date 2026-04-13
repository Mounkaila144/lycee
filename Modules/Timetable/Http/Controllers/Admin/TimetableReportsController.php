<?php

namespace Modules\Timetable\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Timetable\Entities\TimetableSlot;

class TimetableReportsController extends Controller
{
    /**
     * Export PDF emploi du temps (Story 14)
     */
    public function exportPdf(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:group,teacher,room,student',
            'entity_id' => 'required|integer',
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        // Récupérer données selon type
        $slots = match ($validated['type']) {
            'group' => TimetableSlot::byGroup($validated['entity_id'])
                ->bySemester($validated['semester_id'])
                ->with(['module', 'teacher', 'room'])
                ->orderByRaw("FIELD(day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi')")
                ->orderBy('start_time')
                ->get(),
            'teacher' => TimetableSlot::byTeacher($validated['entity_id'])
                ->bySemester($validated['semester_id'])
                ->with(['module', 'group', 'room'])
                ->get(),
            'room' => TimetableSlot::byRoom($validated['entity_id'])
                ->bySemester($validated['semester_id'])
                ->with(['module', 'teacher', 'group'])
                ->get(),
            default => collect([]),
        };

        // Pour MVP, retourner data JSON (le frontend générera PDF)
        return response()->json([
            'type' => $validated['type'],
            'entity_id' => $validated['entity_id'],
            'semester_id' => $validated['semester_id'],
            'slots' => $slots,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Statistiques occupation salles (Story 15)
     */
    public function occupationStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $stats = DB::connection('tenant')
            ->table('timetable_slots')
            ->join('rooms', 'rooms.id', '=', 'timetable_slots.room_id')
            ->where('timetable_slots.semester_id', $validated['semester_id'])
            ->select([
                'rooms.id',
                'rooms.name',
                'rooms.type',
                'rooms.capacity',
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('SUM(TIMESTAMPDIFF(HOUR, start_time, end_time)) as total_hours'),
            ])
            ->groupBy('rooms.id', 'rooms.name', 'rooms.type', 'rooms.capacity')
            ->get();

        // Calculer taux occupation (basé sur 40h/semaine possible)
        $statsWithRate = $stats->map(function ($stat) {
            $maxHoursPerWeek = 40; // Configurable
            $occupationRate = min(100, ($stat->total_hours / $maxHoursPerWeek) * 100);

            return [
                'room_id' => $stat->id,
                'room_name' => $stat->name,
                'room_type' => $stat->type,
                'capacity' => $stat->capacity,
                'total_sessions' => $stat->total_sessions,
                'total_hours' => $stat->total_hours,
                'occupation_rate' => round($occupationRate, 2),
            ];
        });

        return response()->json([
            'semester_id' => $validated['semester_id'],
            'stats' => $statsWithRate,
            'total_rooms' => $statsWithRate->count(),
            'average_occupation' => round($statsWithRate->avg('occupation_rate'), 2),
        ]);
    }

    /**
     * Charges enseignants (Story 16)
     */
    public function teacherWorkload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $workload = DB::connection('tenant')
            ->table('timetable_slots')
            ->join('users', 'users.id', '=', 'timetable_slots.teacher_id')
            ->where('timetable_slots.semester_id', $validated['semester_id'])
            ->select([
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('SUM(TIMESTAMPDIFF(HOUR, start_time, end_time)) as total_hours'),
                DB::raw('COUNT(DISTINCT day_of_week) as days_teaching'),
            ])
            ->groupBy('users.id', 'users.name', 'users.email')
            ->get();

        return response()->json([
            'semester_id' => $validated['semester_id'],
            'workload' => $workload,
            'total_teachers' => $workload->count(),
            'total_hours' => $workload->sum('total_hours'),
            'average_hours_per_teacher' => round($workload->avg('total_hours'), 2),
        ]);
    }

    /**
     * Taux utilisation salles détaillé (Story 17)
     */
    public function roomUtilization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
            'room_id' => 'nullable|integer|exists:tenant.rooms,id',
        ]);

        $query = DB::connection('tenant')
            ->table('timetable_slots')
            ->join('rooms', 'rooms.id', '=', 'timetable_slots.room_id')
            ->where('timetable_slots.semester_id', $validated['semester_id']);

        if (isset($validated['room_id'])) {
            $query->where('rooms.id', $validated['room_id']);
        }

        $utilization = $query
            ->select([
                'rooms.id',
                'rooms.name',
                'rooms.building',
                'rooms.type',
                'timetable_slots.day_of_week',
                DB::raw('COUNT(*) as sessions_count'),
                DB::raw('SUM(TIMESTAMPDIFF(HOUR, start_time, end_time)) as hours_used'),
            ])
            ->groupBy('rooms.id', 'rooms.name', 'rooms.building', 'rooms.type', 'timetable_slots.day_of_week')
            ->get();

        // Regrouper par salle
        $byRoom = $utilization->groupBy('id')->map(function ($roomSlots) {
            $firstSlot = $roomSlots->first();

            return [
                'room_id' => $firstSlot->id,
                'room_name' => $firstSlot->name,
                'building' => $firstSlot->building,
                'type' => $firstSlot->type,
                'days' => $roomSlots->map(function ($day) {
                    return [
                        'day' => $day->day_of_week,
                        'sessions' => $day->sessions_count,
                        'hours' => $day->hours_used,
                    ];
                })->values(),
                'total_sessions' => $roomSlots->sum('sessions_count'),
                'total_hours' => $roomSlots->sum('hours_used'),
            ];
        })->values();

        return response()->json([
            'semester_id' => $validated['semester_id'],
            'utilization' => $byRoom,
        ]);
    }
}
