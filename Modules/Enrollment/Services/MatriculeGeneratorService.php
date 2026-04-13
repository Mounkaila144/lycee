<?php

namespace Modules\Enrollment\Services;

use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Programme;

class MatriculeGeneratorService
{
    /**
     * Generate a unique matricule for a student
     *
     * Format: {YEAR}-{PROGRAM_CODE}-{SEQUENCE}
     * Example: 2025-INF-001, 2025-MATH-045
     *
     * Note: Uses withTrashed() to include soft-deleted records in sequence calculation
     */
    public function generate(Programme $programme, ?int $year = null): string
    {
        $year = $year ?? now()->year;
        $programCode = strtoupper($programme->code);

        $prefix = "{$year}-{$programCode}";

        // Find last matricule with this prefix (including soft-deleted records)
        $lastMatricule = Student::on('tenant')
            ->withTrashed()
            ->where('matricule', 'like', "{$prefix}-%")
            ->orderByRaw('CAST(SUBSTRING_INDEX(matricule, \'-\', -1) AS UNSIGNED) DESC')
            ->first();

        $sequence = 1;
        if ($lastMatricule) {
            // Extract sequence number from last matricule
            $parts = explode('-', $lastMatricule->matricule);
            $lastSequence = (int) end($parts);
            $sequence = $lastSequence + 1;
        }

        return sprintf('%s-%03d', $prefix, $sequence);
    }

    /**
     * Check if a matricule is unique (including soft-deleted records)
     */
    public function isUnique(string $matricule): bool
    {
        return ! Student::on('tenant')
            ->withTrashed()
            ->where('matricule', $matricule)
            ->exists();
    }

    /**
     * Parse matricule to extract components
     *
     * @return array{year: int, program_code: string, sequence: int}|null
     */
    public function parse(string $matricule): ?array
    {
        if (! preg_match('/^(\d{4})-([A-Z0-9]+)-(\d{3})$/', $matricule, $matches)) {
            return null;
        }

        return [
            'year' => (int) $matches[1],
            'program_code' => $matches[2],
            'sequence' => (int) $matches[3],
        ];
    }

    /**
     * Validate matricule format
     */
    public function isValid(string $matricule): bool
    {
        return $this->parse($matricule) !== null;
    }

    /**
     * Generate next available matricule for a program
     */
    public function generateNext(Programme $programme): string
    {
        $year = now()->year;

        // Keep trying until we get a unique matricule
        // (should only loop once in normal circumstances)
        do {
            $matricule = $this->generate($programme, $year);
        } while (! $this->isUnique($matricule));

        return $matricule;
    }

    /**
     * Generate a simple sequential matricule (for secondary school without programmes)
     *
     * Format: {YEAR}-{SEQUENCE}
     * Example: 2026-001, 2026-045
     */
    public function generateSimpleMatricule(?int $year = null): string
    {
        $year = $year ?? now()->year;
        $prefix = (string) $year;

        $lastMatricule = Student::on('tenant')
            ->withTrashed()
            ->where('matricule', 'like', "{$prefix}-%")
            ->orderByRaw('CAST(SUBSTRING_INDEX(matricule, \'-\', -1) AS UNSIGNED) DESC')
            ->first();

        $sequence = 1;

        if ($lastMatricule) {
            $parts = explode('-', $lastMatricule->matricule);
            $lastSequence = (int) end($parts);
            $sequence = $lastSequence + 1;
        }

        $matricule = sprintf('%s-%04d', $prefix, $sequence);

        if (! $this->isUnique($matricule)) {
            $sequence++;
            $matricule = sprintf('%s-%04d', $prefix, $sequence);
        }

        return $matricule;
    }
}
