<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;

class ModuleResult extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'module_results';

    protected $fillable = [
        'module_id',
        'semester_id',
        'total_students',
        'class_average',
        'min_grade',
        'max_grade',
        'median',
        'standard_deviation',
        'pass_rate',
        'absence_rate',
        'distribution',
        'generated_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'total_students' => 'integer',
            'class_average' => 'decimal:2',
            'min_grade' => 'decimal:2',
            'max_grade' => 'decimal:2',
            'median' => 'decimal:2',
            'standard_deviation' => 'decimal:2',
            'pass_rate' => 'decimal:2',
            'absence_rate' => 'decimal:2',
            'distribution' => 'array',
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Resolve route binding for tenant connection
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    // Relations

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    // Scopes

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeForSemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    // Computed Attributes

    public function getIsPublishedAttribute(): bool
    {
        return $this->published_at !== null;
    }

    public function getPassCountAttribute(): int
    {
        if (! is_array($this->distribution)) {
            return 0;
        }

        return ($this->distribution['10-15'] ?? 0) + ($this->distribution['15-20'] ?? 0);
    }

    public function getFailCountAttribute(): int
    {
        if (! is_array($this->distribution)) {
            return 0;
        }

        return ($this->distribution['0-5'] ?? 0) + ($this->distribution['5-10'] ?? 0);
    }

    // Business Logic

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function publish(): void
    {
        $this->update([
            'published_at' => now(),
        ]);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\ModuleResultFactory::new();
    }
}
