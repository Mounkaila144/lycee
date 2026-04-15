<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelCreditConfiguration extends Model
{
    protected $connection = 'tenant';

    protected $table = 'level_credit_configurations';

    protected $fillable = [
        'program_id',
        'level',
        'semester_1_credits',
        'semester_2_credits',
    ];

    protected function casts(): array
    {
        return [
            'semester_1_credits' => 'integer',
            'semester_2_credits' => 'integer',
        ];
    }

    /**
     * Relation vers le programme (nullable pour config globale)
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * Computed attribute: total credits = S1 + S2
     */
    protected function totalCredits(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->semester_1_credits + $this->semester_2_credits,
        );
    }

    /**
     * Check if semester distribution is balanced
     * Returns true if difference > 10 credits (warning threshold)
     */
    public function hasImbalancedDistribution(): bool
    {
        return abs($this->semester_1_credits - $this->semester_2_credits) > 10;
    }

    /**
     * Scope: get global configurations (no program_id)
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('program_id');
    }

    /**
     * Scope: get program-specific configurations
     */
    public function scopeForProgram($query, ?int $programId)
    {
        if ($programId === null) {
            return $query->whereNull('program_id');
        }

        return $query->where('program_id', $programId);
    }

    /**
     * Scope: filter by level
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Get configuration for a specific level & program
     * Prioritizes program-specific over global
     */
    public static function getForProgramLevel(int $programId, string $level): ?self
    {
        // Try program-specific first
        $programSpecific = static::where('level', $level)
            ->where('program_id', $programId)
            ->first();

        if ($programSpecific) {
            return $programSpecific;
        }

        // Fallback to global configuration
        return static::where('level', $level)
            ->whereNull('program_id')
            ->first();
    }
}
