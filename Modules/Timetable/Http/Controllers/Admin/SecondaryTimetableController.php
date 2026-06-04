<?php

namespace Modules\Timetable\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\StructureAcademique\Entities\TeacherAssignment;
use Modules\Timetable\Entities\TimetableSlot;

/**
 * Secondary-school timetable: weekly slots per (class + subject + teacher).
 */
class SecondaryTimetableController extends Controller
{
    private const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

    public function index(Request $request): JsonResponse
    {
        $query = TimetableSlot::on('tenant')
            ->with(['classe', 'subject', 'teacher'])
            ->whereNotNull('class_id')
            ->orderByRaw("FIELD(day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi')")
            ->orderBy('start_time');

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->input('class_id'));
        }
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->input('teacher_id'));
        }

        $slots = $query->get()->map(fn (TimetableSlot $s) => $this->transform($s));

        return response()->json(['data' => $slots]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|integer|exists:tenant.classes,id',
            'subject_id' => 'required|integer|exists:tenant.subjects,id',
            'teacher_id' => 'nullable|integer|exists:tenant.users,id',
            'day_of_week' => ['required', Rule::in(self::DAYS)],
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room_name' => 'nullable|string|max:100',
        ]);

        // A teacher may only be scheduled for a subject they are assigned to teach
        // in that class (cf. teacher_assignments). Prevents the admin from booking a
        // teacher on a subject/class they were never assigned.
        if (! empty($validated['teacher_id'])) {
            $isAssigned = TeacherAssignment::on('tenant')
                ->where('teacher_id', $validated['teacher_id'])
                ->where('class_id', $validated['class_id'])
                ->where('subject_id', $validated['subject_id'])
                ->exists();

            if (! $isAssigned) {
                return response()->json([
                    'message' => "Cet enseignant n'est pas affecté à cette matière dans cette classe. Affectez-la d'abord depuis la fiche de l'enseignant.",
                ], 422);
            }
        }

        // Overlap check: same class, same day, overlapping time range.
        $overlap = TimetableSlot::on('tenant')
            ->where('class_id', $validated['class_id'])
            ->where('day_of_week', $validated['day_of_week'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Conflit : un autre cours est déjà programmé pour cette classe sur ce créneau.',
            ], 422);
        }

        $slot = TimetableSlot::create([
            'class_id' => $validated['class_id'],
            'subject_id' => $validated['subject_id'],
            'teacher_id' => $validated['teacher_id'] ?? null,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'room_name' => $validated['room_name'] ?? null,
            'is_recurring' => true,
        ]);

        $slot->load(['classe', 'subject', 'teacher']);

        return response()->json(['data' => $this->transform($slot)], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $slot = TimetableSlot::on('tenant')->findOrFail($id);
        $slot->delete();

        return response()->json(['message' => 'Créneau supprimé.']);
    }

    private function transform(TimetableSlot $s): array
    {
        return [
            'id' => $s->id,
            'class_id' => $s->class_id,
            'subject_id' => $s->subject_id,
            'teacher_id' => $s->teacher_id,
            'day_of_week' => $s->day_of_week,
            'start_time' => substr((string) $s->start_time, 0, 5),
            'end_time' => substr((string) $s->end_time, 0, 5),
            'room_name' => $s->room_name,
            'classe' => $s->classe ? ['id' => $s->classe->id, 'name' => $s->classe->name] : null,
            'subject' => $s->subject ? ['id' => $s->subject->id, 'name' => $s->subject->name, 'code' => $s->subject->code] : null,
            'teacher' => $s->teacher ? ['id' => $s->teacher->id, 'name' => trim(($s->teacher->firstname ?? '').' '.($s->teacher->lastname ?? ''))] : null,
        ];
    }
}
