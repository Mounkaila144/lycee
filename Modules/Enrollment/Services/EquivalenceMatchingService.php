<?php

namespace Modules\Enrollment\Services;

use Modules\Enrollment\Entities\Equivalence;
use Modules\Enrollment\Entities\Transfer;
use Modules\StructureAcademique\Entities\Module;

class EquivalenceMatchingService
{
    /**
     * Minimum similarity threshold for auto-matching
     */
    private const MIN_SIMILARITY_THRESHOLD = 70;

    /**
     * Weights for similarity calculation
     */
    private const WEIGHT_NAME = 0.4;

    private const WEIGHT_HOURS = 0.3;

    private const WEIGHT_ECTS = 0.3;

    /**
     * Suggest equivalences automatically based on similarity
     */
    public function suggestEquivalences(Transfer $transfer, array $originModules): array
    {
        $targetModules = Module::query()
            ->whereHas('programmes', function ($q) use ($transfer) {
                $q->where('programmes.id', $transfer->target_program_id);
            })
            ->where('level', $transfer->target_level)
            ->get();

        $suggestions = [];

        foreach ($originModules as $originModule) {
            $bestMatch = null;
            $bestScore = 0;

            foreach ($targetModules as $targetModule) {
                $score = $this->calculateSimilarityScore($originModule, $targetModule);

                if ($score > $bestScore && $score >= self::MIN_SIMILARITY_THRESHOLD) {
                    $bestScore = $score;
                    $bestMatch = $targetModule;
                }
            }

            $suggestions[] = [
                'origin_module' => $originModule,
                'target_module' => $bestMatch,
                'similarity_score' => $bestScore,
                'equivalence_type' => $this->determineEquivalenceType($bestScore),
                'equivalence_percentage' => $this->determineEquivalencePercentage($bestScore),
            ];
        }

        return $suggestions;
    }

    /**
     * Create equivalence records from suggestions
     */
    public function createEquivalencesFromSuggestions(Transfer $transfer, array $suggestions): array
    {
        $equivalences = [];

        foreach ($suggestions as $suggestion) {
            $originModule = $suggestion['origin_module'];

            $equivalence = Equivalence::create([
                'transfer_id' => $transfer->id,
                'origin_module_code' => $originModule['code'] ?? '',
                'origin_module_name' => $originModule['name'],
                'origin_ects' => $originModule['ects'] ?? 0,
                'origin_hours' => $originModule['hours'] ?? 0,
                'origin_grade' => $originModule['grade'] ?? null,
                'target_module_id' => $suggestion['target_module']?->id,
                'equivalence_type' => $suggestion['equivalence_type'],
                'equivalence_percentage' => $suggestion['equivalence_percentage'],
                'granted_ects' => $this->calculateGrantedEcts(
                    $suggestion['target_module'],
                    $suggestion['equivalence_type'],
                    $suggestion['equivalence_percentage']
                ),
                'granted_grade' => $this->calculateGrantedGrade(
                    $originModule['grade'] ?? null,
                    $suggestion['equivalence_type']
                ),
                'similarity_score' => $suggestion['similarity_score'],
                'status' => Equivalence::STATUS_PROPOSED,
            ]);

            $equivalences[] = $equivalence;
        }

        // Update total ECTS claimed
        $totalClaimed = collect($suggestions)->sum(fn ($s) => $s['origin_module']['ects'] ?? 0);
        $transfer->update(['total_ects_claimed' => $totalClaimed]);

        return $equivalences;
    }

    /**
     * Create manual equivalence
     */
    public function createManualEquivalence(Transfer $transfer, array $data): Equivalence
    {
        $targetModule = $data['target_module_id']
            ? Module::find($data['target_module_id'])
            : null;

        return Equivalence::create([
            'transfer_id' => $transfer->id,
            'origin_module_code' => $data['origin_module_code'] ?? '',
            'origin_module_name' => $data['origin_module_name'],
            'origin_ects' => $data['origin_ects'] ?? 0,
            'origin_hours' => $data['origin_hours'] ?? 0,
            'origin_grade' => $data['origin_grade'] ?? null,
            'target_module_id' => $data['target_module_id'],
            'equivalence_type' => $data['equivalence_type'],
            'equivalence_percentage' => $data['equivalence_percentage'] ?? $this->getDefaultPercentage($data['equivalence_type']),
            'granted_ects' => $data['granted_ects'] ?? $this->calculateGrantedEcts(
                $targetModule,
                $data['equivalence_type'],
                $data['equivalence_percentage'] ?? 100
            ),
            'granted_grade' => $data['granted_grade'] ?? null,
            'notes' => $data['notes'] ?? null,
            'similarity_score' => 0, // Manual = no auto-matching
            'status' => Equivalence::STATUS_PROPOSED,
        ]);
    }

    /**
     * Update equivalence
     */
    public function updateEquivalence(Equivalence $equivalence, array $data): Equivalence
    {
        if ($equivalence->isValidated()) {
            throw new \Exception('Cannot update validated equivalence');
        }

        $updates = array_filter([
            'target_module_id' => $data['target_module_id'] ?? null,
            'equivalence_type' => $data['equivalence_type'] ?? null,
            'equivalence_percentage' => $data['equivalence_percentage'] ?? null,
            'granted_ects' => $data['granted_ects'] ?? null,
            'granted_grade' => $data['granted_grade'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null);

        $equivalence->update($updates);

        return $equivalence->fresh();
    }

    /**
     * Validate equivalence
     */
    public function validateEquivalence(Equivalence $equivalence): Equivalence
    {
        if (! $equivalence->canBeValidated()) {
            throw new \Exception('Equivalence cannot be validated');
        }

        $equivalence->update(['status' => Equivalence::STATUS_VALIDATED]);

        // Update transfer total granted ECTS
        $this->updateTransferGrantedEcts($equivalence->transfer);

        return $equivalence->fresh();
    }

    /**
     * Reject equivalence
     */
    public function rejectEquivalence(Equivalence $equivalence, ?string $notes = null): Equivalence
    {
        $equivalence->update([
            'status' => Equivalence::STATUS_REJECTED,
            'notes' => $notes ?? $equivalence->notes,
        ]);

        return $equivalence->fresh();
    }

    /**
     * Batch validate equivalences
     */
    public function batchValidateEquivalences(Transfer $transfer, array $equivalenceIds): array
    {
        $validated = [];
        $errors = [];

        foreach ($equivalenceIds as $id) {
            $equivalence = Equivalence::where('transfer_id', $transfer->id)
                ->where('id', $id)
                ->first();

            if (! $equivalence) {
                $errors[] = "Equivalence {$id} not found";

                continue;
            }

            try {
                $validated[] = $this->validateEquivalence($equivalence);
            } catch (\Exception $e) {
                $errors[] = "Equivalence {$id}: ".$e->getMessage();
            }
        }

        return [
            'validated' => $validated,
            'errors' => $errors,
        ];
    }

    /**
     * Calculate similarity score between origin module and target module
     */
    public function calculateSimilarityScore(array $originModule, Module $targetModule): int
    {
        $scores = [];

        // 1. Name similarity (40%)
        $nameScore = $this->levenshteinSimilarity(
            strtolower($originModule['name']),
            strtolower($targetModule->name)
        );
        $scores[] = $nameScore * self::WEIGHT_NAME;

        // 2. Hours similarity (30%)
        $originHours = $originModule['hours'] ?? 0;
        if ($originHours > 0) {
            $targetHours = $targetModule->total_hours;
            $hoursDiff = abs($originHours - $targetHours) / $originHours;
            $hoursScore = max(0, 100 - ($hoursDiff * 100));
            $scores[] = $hoursScore * self::WEIGHT_HOURS;
        } else {
            $scores[] = 50 * self::WEIGHT_HOURS; // Neutral if no data
        }

        // 3. ECTS similarity (30%)
        $originEcts = $originModule['ects'] ?? 0;
        if ($originEcts > 0) {
            $ectsDiff = abs($originEcts - $targetModule->credits_ects) / $originEcts;
            $ectsScore = max(0, 100 - ($ectsDiff * 100));
            $scores[] = $ectsScore * self::WEIGHT_ECTS;
        } else {
            $scores[] = 50 * self::WEIGHT_ECTS; // Neutral if no data
        }

        return (int) array_sum($scores);
    }

    /**
     * Calculate Levenshtein similarity percentage
     */
    private function levenshteinSimilarity(string $str1, string $str2): int
    {
        if (empty($str1) && empty($str2)) {
            return 100;
        }

        $levenshtein = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));

        if ($maxLength === 0) {
            return 100;
        }

        return (int) ((1 - ($levenshtein / $maxLength)) * 100);
    }

    /**
     * Determine equivalence type based on similarity score
     */
    private function determineEquivalenceType(int $score): string
    {
        return match (true) {
            $score >= 90 => Equivalence::TYPE_FULL,
            $score >= 70 => Equivalence::TYPE_PARTIAL,
            default => Equivalence::TYPE_NONE,
        };
    }

    /**
     * Determine equivalence percentage based on score
     */
    private function determineEquivalencePercentage(int $score): int
    {
        return match (true) {
            $score >= 90 => 100,
            $score >= 80 => 75,
            $score >= 70 => 50,
            default => 0,
        };
    }

    /**
     * Get default percentage for equivalence type
     */
    private function getDefaultPercentage(string $type): int
    {
        return match ($type) {
            Equivalence::TYPE_FULL => 100,
            Equivalence::TYPE_PARTIAL => 50,
            Equivalence::TYPE_EXEMPTION => 100,
            default => 0,
        };
    }

    /**
     * Calculate granted ECTS
     */
    private function calculateGrantedEcts(?Module $targetModule, string $type, int $percentage): int
    {
        if (! $targetModule || $type === Equivalence::TYPE_NONE) {
            return 0;
        }

        if ($type === Equivalence::TYPE_FULL || $type === Equivalence::TYPE_EXEMPTION) {
            return $targetModule->credits_ects;
        }

        return (int) round($targetModule->credits_ects * ($percentage / 100));
    }

    /**
     * Calculate granted grade
     */
    private function calculateGrantedGrade(?float $originGrade, string $type): ?float
    {
        if ($type === Equivalence::TYPE_NONE) {
            return null;
        }

        // If origin grade exists and type is Full, keep it (convert if needed)
        if ($originGrade !== null && $type === Equivalence::TYPE_FULL) {
            // Assuming origin is on /20 scale, cap at 20
            return min($originGrade, 20);
        }

        // Default forfait grade for equivalences
        if ($type === Equivalence::TYPE_FULL || $type === Equivalence::TYPE_EXEMPTION) {
            return 12.0;
        }

        return null;
    }

    /**
     * Update transfer total granted ECTS
     */
    private function updateTransferGrantedEcts(Transfer $transfer): void
    {
        $totalGranted = Equivalence::where('transfer_id', $transfer->id)
            ->where('status', Equivalence::STATUS_VALIDATED)
            ->sum('granted_ects');

        $transfer->update(['total_ects_granted' => $totalGranted]);
    }
}
