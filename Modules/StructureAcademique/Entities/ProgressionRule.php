<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\StructureAcademique\Database\Factories\ProgressionRuleFactory;

class ProgressionRule extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProgressionRuleFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'progression_rules';

    protected $fillable = [
        'programme_id',
        'from_level',
        'to_level',
        'min_credits_required',
        'max_debt_allowed',
        'allow_conditional_pass',
        'max_repeats_before_exclusion',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'min_credits_required' => 'integer',
            'max_debt_allowed' => 'integer',
            'allow_conditional_pass' => 'boolean',
            'max_repeats_before_exclusion' => 'integer',
        ];
    }

    /**
     * Relations
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    /**
     * Scopes
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('programme_id');
    }

    public function scopeForProgramme($query, int $programmeId)
    {
        return $query->where('programme_id', $programmeId);
    }

    public function scopeForTransition($query, string $fromLevel, string $toLevel)
    {
        return $query->where('from_level', $fromLevel)
            ->where('to_level', $toLevel);
    }

    /**
     * Get rule for specific programme and transition
     * Falls back to global rule if no programme-specific rule exists
     */
    public static function getRule(?int $programmeId, string $fromLevel, string $toLevel): ?self
    {
        // Try programme-specific rule first
        if ($programmeId) {
            $rule = self::where('programme_id', $programmeId)
                ->where('from_level', $fromLevel)
                ->where('to_level', $toLevel)
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        // Fall back to global rule
        return self::whereNull('programme_id')
            ->where('from_level', $fromLevel)
            ->where('to_level', $toLevel)
            ->first();
    }

    /**
     * Check if this is a global rule
     */
    public function isGlobal(): bool
    {
        return $this->programme_id === null;
    }

    /**
     * Get the next level after from_level
     */
    public static function getNextLevel(string $currentLevel): ?string
    {
        $levels = ['L1' => 'L2', 'L2' => 'L3', 'L3' => 'M1', 'M1' => 'M2', 'M2' => null];

        return $levels[$currentLevel] ?? null;
    }

    /**
     * Validate if transition is valid
     */
    public function isValidTransition(): bool
    {
        $validTransitions = [
            'L1' => ['L2'],
            'L2' => ['L3'],
            'L3' => ['M1'],
            'M1' => ['M2'],
        ];

        return in_array($this->to_level, $validTransitions[$this->from_level] ?? []);
    }
}
