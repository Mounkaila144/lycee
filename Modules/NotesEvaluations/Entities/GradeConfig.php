<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeConfig extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'grade_configs';

    protected $fillable = [
        'min_module_average',
        'min_semester_average',
        'compensation_enabled',
        'min_compensable_grade',
        'max_compensated_modules',
        'allow_professional_module_compensation',
        'eliminatory_threshold',
        'allow_eliminatory_compensation',
        'year_progression_threshold',
    ];

    protected function casts(): array
    {
        return [
            'min_module_average' => 'decimal:2',
            'min_semester_average' => 'decimal:2',
            'min_compensable_grade' => 'decimal:2',
            'max_compensated_modules' => 'integer',
            'eliminatory_threshold' => 'decimal:2',
            'compensation_enabled' => 'boolean',
            'allow_eliminatory_compensation' => 'boolean',
            'allow_professional_module_compensation' => 'boolean',
            'year_progression_threshold' => 'integer',
        ];
    }

    /**
     * Get the configuration for the current tenant or create default
     */
    public static function getConfig(): self
    {
        return static::first() ?? static::create([
            'min_module_average' => 10.00,
            'min_semester_average' => 10.00,
            'compensation_enabled' => true,
            'min_compensable_grade' => 7.00,
            'max_compensated_modules' => null,
            'allow_professional_module_compensation' => true,
            'eliminatory_threshold' => 10.00,
            'allow_eliminatory_compensation' => false,
            'year_progression_threshold' => 80,
        ]);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\GradeConfigFactory::new();
    }
}
