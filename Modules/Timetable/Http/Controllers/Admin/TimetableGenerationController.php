<?php

namespace Modules\Timetable\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Modules\Timetable\Entities\TeacherPreference;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\Timetable\Jobs\GenerateTimetableJob;
use Modules\Timetable\Services\AutoGenerationService;

class TimetableGenerationController extends Controller
{
    /**
     * Lancer génération automatique emploi du temps
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
            'group_id' => 'required|integer|exists:tenant.groups,id',
            'strategy' => ['nullable', 'string', Rule::in(['fast', 'balanced', 'optimal'])],
            'async' => 'nullable|boolean',
        ]);

        $userId = auth()->id();
        $strategy = $validated['strategy'] ?? 'balanced';
        $async = $validated['async'] ?? true;

        if ($async) {
            // Lancer job asynchrone
            GenerateTimetableJob::dispatch(
                $validated['semester_id'],
                $validated['group_id'],
                $userId,
                $strategy
            );

            return response()->json([
                'message' => 'Génération lancée en arrière-plan',
                'status' => 'processing',
                'group_id' => $validated['group_id'],
            ], 202);
        }

        // Génération synchrone (pour tests ou petits emplois du temps)
        $service = app(AutoGenerationService::class);
        $result = $service->generate(
            $validated['semester_id'],
            $validated['group_id'],
            $strategy
        );

        return response()->json($result->toArray());
    }

    /**
     * Récupérer résultat de génération
     */
    public function getResult(Request $request, int $groupId): JsonResponse
    {
        $userId = auth()->id();
        $cacheKey = "timetable_generation:{$userId}:{$groupId}";

        $result = Cache::get($cacheKey);

        if (! $result) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Aucun résultat de génération trouvé',
            ], 404);
        }

        if (isset($result['error'])) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ], 500);
        }

        return response()->json([
            'status' => 'completed',
            'result' => $result,
        ]);
    }

    /**
     * Accepter et sauvegarder emploi du temps généré
     */
    public function acceptGenerated(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'required|integer|exists:tenant.groups,id',
        ]);

        $userId = auth()->id();
        $cacheKey = "timetable_generation:{$userId}:{$validated['group_id']}";

        $result = Cache::get($cacheKey);

        if (! $result || ! $result['success']) {
            return response()->json([
                'message' => 'Aucun résultat valide à sauvegarder',
            ], 400);
        }

        // Sauvegarder les créneaux
        $savedSlots = [];
        foreach ($result['slots'] as $slotData) {
            $slot = TimetableSlot::create($slotData);
            $savedSlots[] = $slot;
        }

        // Supprimer du cache
        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'Emploi du temps sauvegardé avec succès',
            'slots_count' => count($savedSlots),
        ], 201);
    }

    /**
     * Récupérer préférences enseignant
     */
    public function getTeacherPreferences(int $teacherId): JsonResponse
    {
        $preferences = TeacherPreference::where('teacher_id', $teacherId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json($preferences);
    }

    /**
     * Mettre à jour préférences enseignant
     */
    public function updateTeacherPreferences(Request $request, int $teacherId): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.day_of_week' => ['required', 'string', Rule::in(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'])],
            'preferences.*.start_time' => 'required|date_format:H:i',
            'preferences.*.end_time' => 'required|date_format:H:i|after:preferences.*.start_time',
            'preferences.*.is_preferred' => 'required|boolean',
            'preferences.*.priority' => 'nullable|integer|min:1|max:10',
            'preferences.*.notes' => 'nullable|string|max:500',
        ]);

        // Supprimer anciennes préférences
        TeacherPreference::where('teacher_id', $teacherId)->delete();

        // Créer nouvelles préférences
        $preferences = [];
        foreach ($validated['preferences'] as $prefData) {
            $preferences[] = TeacherPreference::create([
                'teacher_id' => $teacherId,
                'day_of_week' => $prefData['day_of_week'],
                'start_time' => $prefData['start_time'],
                'end_time' => $prefData['end_time'],
                'is_preferred' => $prefData['is_preferred'],
                'priority' => $prefData['priority'] ?? 5,
                'notes' => $prefData['notes'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Préférences mises à jour avec succès',
            'preferences' => $preferences,
        ]);
    }
}
