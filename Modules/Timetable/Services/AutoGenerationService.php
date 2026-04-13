<?php

namespace Modules\Timetable\Services;

use Modules\StructureAcademique\Entities\Group;
use Modules\StructureAcademique\Entities\Semester;
use Modules\Timetable\DTOs\TimetableGenerationResult;
use Modules\Timetable\Entities\Room;
use Modules\Timetable\Entities\TeacherPreference;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\Timetable\Exceptions\GenerationException;

class AutoGenerationService
{
    public function __construct(
        private ConstraintSolver $solver,
        private ConflictDetectionService $conflictDetector
    ) {}

    /**
     * Générer un emploi du temps automatiquement
     */
    public function generate(
        int $semesterId,
        int $groupId,
        string $strategy = 'balanced'
    ): TimetableGenerationResult {
        // Phase 1: Analyse
        $constraints = $this->analyzeConstraints($semesterId, $groupId);

        if ($constraints['modules_count'] === 0) {
            throw new GenerationException('Aucun module à planifier pour ce groupe');
        }

        // Phase 2: Génération CSP
        $slots = match ($strategy) {
            'fast' => $this->solver->solveHeuristic($constraints),
            'balanced' => $this->solver->solveBacktracking($constraints),
            'optimal' => $this->solver->solveOptimal($constraints),
            default => $this->solver->solveBacktracking($constraints),
        };

        if (empty($slots)) {
            return new TimetableGenerationResult(
                success: false,
                message: 'Impossible de générer un emploi du temps. Contraintes incompatibles.',
                conflicts: $this->solver->getImpossibleConstraints(),
            );
        }

        // Phase 3: Optimisation
        $optimizedSlots = $this->optimizeSolution($slots, $constraints);

        // Phase 4: Vérification
        $conflicts = $this->verifyGeneration($optimizedSlots);

        $score = $this->calculateQualityScore($optimizedSlots, $constraints);

        return new TimetableGenerationResult(
            success: true,
            slots: $optimizedSlots,
            score: $score,
            conflicts: $conflicts,
            statistics: $this->generateStatistics($optimizedSlots, $constraints),
        );
    }

    /**
     * Analyser les contraintes pour la génération
     */
    private function analyzeConstraints(int $semesterId, int $groupId): array
    {
        $group = Group::with(['students'])->findOrFail($groupId);
        $semester = Semester::findOrFail($semesterId);

        // Récupérer modules avec leurs enseignants
        $modules = \DB::connection('tenant')
            ->table('module_semester_assignment')
            ->join('modules', 'modules.id', '=', 'module_semester_assignment.module_id')
            ->where('module_semester_assignment.semester_id', $semesterId)
            ->whereNotNull('module_semester_assignment.teacher_id')
            ->select([
                'modules.id',
                'modules.code',
                'modules.name',
                'module_semester_assignment.teacher_id',
                'modules.type',
            ])
            ->get()
            ->toArray();

        $modulesArray = array_map(function ($module) {
            return [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'teacher_id' => $module->teacher_id,
                'type' => $module->type ?? 'CM',
            ];
        }, $modules);

        $constraints = [
            'modules_count' => count($modulesArray),
            'modules' => $modulesArray,
            'group_id' => $groupId,
            'group_size' => $group->students->count(),
            'semester_id' => $semesterId,
            'available_rooms' => Room::where('is_active', true)->get(),
            'time_slots' => $this->getAvailableTimeSlots(),
            'hard_constraints' => [
                'no_conflicts' => true,
                'room_capacity' => true,
                'teacher_availability' => true,
            ],
            'soft_constraints' => [
                'max_hours_per_day_teacher' => config('timetable.max_hours_teacher', 6),
                'max_consecutive_hours_students' => config('timetable.max_consecutive_hours', 6),
                'respect_teacher_preferences' => true,
                'minimize_room_changes' => true,
            ],
        ];

        return $constraints;
    }

    /**
     * Obtenir créneaux horaires disponibles
     */
    private function getAvailableTimeSlots(): array
    {
        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
        $slots = [];

        foreach ($days as $day) {
            $slots[] = ['day' => $day, 'start' => '08:00:00', 'end' => '10:00:00'];
            $slots[] = ['day' => $day, 'start' => '10:00:00', 'end' => '12:00:00'];
            $slots[] = ['day' => $day, 'start' => '14:00:00', 'end' => '16:00:00'];
            $slots[] = ['day' => $day, 'start' => '16:00:00', 'end' => '18:00:00'];
        }

        return $slots;
    }

    /**
     * Optimiser la solution avec hill climbing
     */
    private function optimizeSolution(array $slots, array $constraints): array
    {
        // Pour MVP, retourne solution initiale
        // Dans version complète, appliquerait hill climbing pour améliorer score
        return $slots;
    }

    /**
     * Vérifier la génération et détecter conflits
     */
    private function verifyGeneration(array $slots): array
    {
        $conflicts = [];

        foreach ($slots as $index => $slotData) {
            // Créer un TimetableSlot temporaire pour vérification
            $slot = new TimetableSlot($slotData);
            $slot->id = $index; // ID temporaire

            $result = $this->conflictDetector->detectConflicts($slot, $index);

            if ($result['hasConflicts']) {
                $conflicts[] = [
                    'slot_index' => $index,
                    'conflicts' => $result['conflicts'],
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Calculer score de qualité de la solution
     */
    private function calculateQualityScore(array $slots, array $constraints): float
    {
        $score = 100.0;
        $penalties = [];

        foreach ($slots as $slotData) {
            // Vérifier charge enseignant
            $teacherHoursDay = $this->countTeacherHoursPerDay(
                $slotData['teacher_id'],
                $slotData['day_of_week'],
                $slots
            );

            if ($teacherHoursDay > $constraints['soft_constraints']['max_hours_per_day_teacher']) {
                $penalties[] = ['type' => 'teacher_overload', 'penalty' => 5];
                $score -= 5;
            }

            // Vérifier préférences enseignant
            $preference = TeacherPreference::where('teacher_id', $slotData['teacher_id'])
                ->where('day_of_week', $slotData['day_of_week'])
                ->first();

            if ($preference) {
                if (! $preference->is_preferred && $preference->overlaps($slotData['start_time'], $slotData['end_time'])) {
                    $penalties[] = ['type' => 'teacher_preference_violated', 'penalty' => 2];
                    $score -= 2;
                }
            }

            // Vérifier trous horaires étudiants
            $gap = $this->detectGapForGroup($slotData['group_id'], $slotData['day_of_week'], $slots);
            if ($gap > 2) { // Plus de 2h de trou
                $penalties[] = ['type' => 'student_gap', 'penalty' => 3];
                $score -= 3;
            }
        }

        return max(0, $score);
    }

    /**
     * Compter heures d'enseignement par jour pour un enseignant
     */
    private function countTeacherHoursPerDay(int $teacherId, string $day, array $slots): int
    {
        $hours = 0;

        foreach ($slots as $slotData) {
            if ($slotData['teacher_id'] === $teacherId && $slotData['day_of_week'] === $day) {
                $start = strtotime($slotData['start_time']);
                $end = strtotime($slotData['end_time']);
                $hours += ($end - $start) / 3600;
            }
        }

        return $hours;
    }

    /**
     * Détecter trous horaires pour un groupe
     */
    private function detectGapForGroup(int $groupId, string $day, array $slots): int
    {
        $groupSlots = array_filter($slots, function ($slotData) use ($groupId, $day) {
            return $slotData['group_id'] === $groupId && $slotData['day_of_week'] === $day;
        });

        if (count($groupSlots) < 2) {
            return 0;
        }

        // Trier par heure de début
        usort($groupSlots, function ($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        $maxGap = 0;
        for ($i = 0; $i < count($groupSlots) - 1; $i++) {
            $endCurrent = strtotime($groupSlots[$i]['end_time']);
            $startNext = strtotime($groupSlots[$i + 1]['start_time']);
            $gap = ($startNext - $endCurrent) / 3600; // En heures

            $maxGap = max($maxGap, $gap);
        }

        return $maxGap;
    }

    /**
     * Générer statistiques de la solution
     */
    private function generateStatistics(array $slots, array $constraints): array
    {
        $teacherHours = [];
        $roomUsage = [];

        foreach ($slots as $slotData) {
            // Statistiques enseignants
            if (! isset($teacherHours[$slotData['teacher_id']])) {
                $teacherHours[$slotData['teacher_id']] = 0;
            }

            $start = strtotime($slotData['start_time']);
            $end = strtotime($slotData['end_time']);
            $teacherHours[$slotData['teacher_id']] += ($end - $start) / 3600;

            // Statistiques salles
            if (! isset($roomUsage[$slotData['room_id']])) {
                $roomUsage[$slotData['room_id']] = 0;
            }

            $roomUsage[$slotData['room_id']]++;
        }

        return [
            'total_slots' => count($slots),
            'modules_planned' => $constraints['modules_count'],
            'coverage_percent' => count($slots) > 0 ? (count($slots) / $constraints['modules_count']) * 100 : 0,
            'teacher_hours' => $teacherHours,
            'room_usage' => $roomUsage,
        ];
    }
}
