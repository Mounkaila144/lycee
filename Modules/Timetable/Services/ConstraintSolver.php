<?php

namespace Modules\Timetable\Services;

class ConstraintSolver
{
    private array $impossibleConstraints = [];

    /**
     * Résoudre avec backtracking (stratégie balanced)
     */
    public function solveBacktracking(array $constraints): array
    {
        $domains = $this->initializeDomains($constraints);
        $assignment = [];

        $result = $this->backtrack($assignment, $domains, $constraints);

        return $result ?: [];
    }

    /**
     * Résoudre avec heuristique rapide (stratégie fast)
     */
    public function solveHeuristic(array $constraints): array
    {
        $slots = [];
        $modules = $constraints['modules'];
        $timeSlots = $constraints['time_slots'];

        foreach ($modules as $index => $module) {
            // Trouver premier créneau disponible
            foreach ($timeSlots as $timeSlot) {
                foreach ($constraints['available_rooms'] as $room) {
                    $candidate = [
                        'module_id' => $module['id'],
                        'teacher_id' => $module['teacher_id'],
                        'group_id' => $constraints['group_id'],
                        'room_id' => $room->id,
                        'day_of_week' => $timeSlot['day'],
                        'start_time' => $timeSlot['start'],
                        'end_time' => $timeSlot['end'],
                        'type' => $module['type'],
                    ];

                    if ($this->isConsistent($index, $candidate, $slots, $constraints)) {
                        $slots[$index] = $candidate;
                        break 2;
                    }
                }
            }

            if (! isset($slots[$index])) {
                $this->impossibleConstraints[] = [
                    'module' => $module['name'],
                    'reason' => 'Aucun créneau disponible',
                ];
            }
        }

        return $slots;
    }

    /**
     * Résoudre avec optimisation complète (stratégie optimal)
     */
    public function solveOptimal(array $constraints): array
    {
        // Pour MVP, utilise backtracking
        // Dans version complète, utiliserait algorithmes plus avancés (simulated annealing, genetic algorithm)
        return $this->solveBacktracking($constraints);
    }

    /**
     * Backtracking avec forward checking
     */
    private function backtrack(array &$assignment, array $domains, array $constraints): ?array
    {
        if (count($assignment) === count($constraints['modules'])) {
            return $assignment; // Solution trouvée
        }

        $variable = $this->selectUnassignedVariable($assignment, $domains, $constraints);

        if ($variable === null || ! isset($domains[$variable])) {
            return null;
        }

        foreach ($domains[$variable] as $value) {
            if ($this->isConsistent($variable, $value, $assignment, $constraints)) {
                $assignment[$variable] = $value;

                // Forward checking
                $newDomains = $this->forwardCheck($assignment, $domains, $constraints);

                if ($newDomains !== null) {
                    $result = $this->backtrack($assignment, $newDomains, $constraints);
                    if ($result !== null) {
                        return $result;
                    }
                }

                unset($assignment[$variable]);
            }
        }

        return null; // Aucune solution
    }

    /**
     * Sélectionner variable non assignée avec heuristique MRV
     */
    private function selectUnassignedVariable(array $assignment, array $domains, array $constraints): mixed
    {
        $unassigned = array_diff_key($domains, $assignment);

        if (empty($unassigned)) {
            return null;
        }

        return array_reduce(array_keys($unassigned), function ($carry, $key) use ($domains) {
            if ($carry === null || count($domains[$key]) < count($domains[$carry])) {
                return $key;
            }

            return $carry;
        });
    }

    /**
     * Vérifier cohérence d'une assignation
     */
    private function isConsistent($variable, $value, array $assignment, array $constraints): bool
    {
        // Vérifier contraintes dures

        // Conflit enseignant
        foreach ($assignment as $assignedVar => $assignedValue) {
            if ($assignedValue['teacher_id'] === $value['teacher_id'] &&
                $assignedValue['day_of_week'] === $value['day_of_week'] &&
                $this->timeSlotsOverlap($assignedValue, $value)) {
                return false;
            }

            // Conflit salle
            if ($assignedValue['room_id'] === $value['room_id'] &&
                $assignedValue['day_of_week'] === $value['day_of_week'] &&
                $this->timeSlotsOverlap($assignedValue, $value)) {
                return false;
            }

            // Conflit groupe
            if ($assignedValue['group_id'] === $value['group_id'] &&
                $assignedValue['day_of_week'] === $value['day_of_week'] &&
                $this->timeSlotsOverlap($assignedValue, $value)) {
                return false;
            }
        }

        // Capacité salle
        $room = $constraints['available_rooms']->firstWhere('id', $value['room_id']);
        if ($room && $room->capacity < $constraints['group_size']) {
            return false;
        }

        return true;
    }

    /**
     * Forward checking pour réduire domaines
     */
    private function forwardCheck(array $assignment, array $domains, array $constraints): ?array
    {
        $newDomains = $domains;

        foreach ($newDomains as $var => $domain) {
            if (isset($assignment[$var])) {
                continue;
            }

            $newDomains[$var] = array_filter($domain, function ($value) use ($var, $assignment, $constraints) {
                return $this->isConsistent($var, $value, $assignment, $constraints);
            });

            if (empty($newDomains[$var])) {
                return null; // Domaine vide = échec
            }
        }

        return $newDomains;
    }

    /**
     * Initialiser domaines (valeurs possibles pour chaque variable)
     */
    private function initializeDomains(array $constraints): array
    {
        $domains = [];

        foreach ($constraints['modules'] as $index => $module) {
            $domains[$index] = [];

            foreach ($constraints['time_slots'] as $timeSlot) {
                foreach ($constraints['available_rooms'] as $room) {
                    $domains[$index][] = [
                        'module_id' => $module['id'],
                        'teacher_id' => $module['teacher_id'],
                        'group_id' => $constraints['group_id'],
                        'room_id' => $room->id,
                        'day_of_week' => $timeSlot['day'],
                        'start_time' => $timeSlot['start'],
                        'end_time' => $timeSlot['end'],
                        'type' => $module['type'],
                    ];
                }
            }
        }

        return $domains;
    }

    /**
     * Vérifier si deux créneaux se chevauchent
     */
    private function timeSlotsOverlap(array $slot1, array $slot2): bool
    {
        return ! ($slot1['end_time'] <= $slot2['start_time'] || $slot1['start_time'] >= $slot2['end_time']);
    }

    public function getImpossibleConstraints(): array
    {
        return $this->impossibleConstraints;
    }
}
