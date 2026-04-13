<?php

namespace Modules\Timetable\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\Timetable\Services\TimetableDuplicationService;

class TimetableDuplicationController extends Controller
{
    public function __construct(
        private TimetableDuplicationService $duplicationService
    ) {}

    /**
     * Aperçu avant duplication
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_semester_id' => 'required|integer|exists:tenant.semesters,id',
            'source_group_id' => 'required|integer|exists:tenant.groups,id',
        ]);

        $preview = $this->duplicationService->getPreview(
            $validated['source_semester_id'],
            $validated['source_group_id']
        );

        return response()->json($preview);
    }

    /**
     * Dupliquer emploi du temps
     */
    public function duplicate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_semester_id' => 'required|integer|exists:tenant.semesters,id',
            'source_group_id' => 'required|integer|exists:tenant.groups,id',
            'target_semester_id' => 'required|integer|exists:tenant.semesters,id',
            'target_group_id' => 'required|integer|exists:tenant.groups,id',
            'mode' => ['nullable', 'string', Rule::in(['full', 'structure'])],
            'duplicate_rooms' => 'nullable|boolean',
            'selected_modules' => 'nullable|array',
            'selected_modules.*' => 'integer|exists:tenant.modules,id',
            'force' => 'nullable|boolean',
        ]);

        $options = [
            'mode' => $validated['mode'] ?? 'full',
            'duplicate_rooms' => $validated['duplicate_rooms'] ?? true,
            'selected_modules' => $validated['selected_modules'] ?? [],
            'force' => $validated['force'] ?? false,
        ];

        $result = $this->duplicationService->duplicate(
            $validated['source_semester_id'],
            $validated['source_group_id'],
            $validated['target_semester_id'],
            $validated['target_group_id'],
            $options
        );

        return response()->json($result->toArray(), 201);
    }

    /**
     * Obtenir suggestions pour une séance
     */
    public function getSuggestions(TimetableSlot $slot): JsonResponse
    {
        $suggestions = $this->duplicationService->getSuggestions($slot);

        return response()->json($suggestions);
    }

    /**
     * Affectation rapide (enseignant ou salle)
     */
    public function quickAssign(Request $request, TimetableSlot $slot): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'nullable|integer|exists:tenant.users,id',
            'room_id' => 'nullable|integer|exists:tenant.rooms,id',
        ]);

        if (isset($validated['teacher_id'])) {
            $slot->teacher_id = $validated['teacher_id'];
        }

        if (isset($validated['room_id'])) {
            $slot->room_id = $validated['room_id'];
        }

        $slot->save();

        return response()->json([
            'message' => 'Affectation mise à jour avec succès',
            'slot' => $slot->load(['module', 'teacher', 'room']),
        ]);
    }
}
