<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectClassCoefficient extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'subject_class_coefficients';

    protected $fillable = [
        'subject_id',
        'level_id',
        'series_id',
        'coefficient',
        'hours_per_week',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:1',
            'hours_per_week' => 'integer',
        ];
    }

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Relations
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\StructureAcademique\Database\Factories\SubjectClassCoefficientFactory::new();
    }
}
