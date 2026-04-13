<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UsersGuard\Entities\User;

class Classe extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'classes';

    protected $fillable = [
        'academic_year_id',
        'level_id',
        'series_id',
        'section',
        'name',
        'max_capacity',
        'classroom',
        'head_teacher_id',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'max_capacity' => 'integer',
        ];
    }

    /**
     * Mapping codes niveaux vers noms affichés
     */
    private const LEVEL_DISPLAY_NAMES = [
        '6E' => '6ème',
        '5E' => '5ème',
        '4E' => '4ème',
        '3E' => '3ème',
        '2NDE' => '2nde',
        '1ERE' => '1ère',
        'TLE' => 'Tle',
    ];

    /**
     * Auto-generate name before save
     */
    protected static function booted(): void
    {
        static::creating(function (Classe $classe) {
            if (! $classe->name) {
                $classe->name = $classe->generateName();
            }
        });

        static::updating(function (Classe $classe) {
            if ($classe->isDirty(['level_id', 'series_id', 'section'])) {
                $classe->name = $classe->generateName();
            }
        });
    }

    /**
     * Génère le nom complet: "{Niveau} {Série}{Section}"
     */
    public function generateName(): string
    {
        $level = $this->level ?? Level::on('tenant')->find($this->level_id);
        if (! $level) {
            return '';
        }

        $displayName = self::LEVEL_DISPLAY_NAMES[$level->code] ?? $level->name;

        $series = null;
        if ($this->series_id) {
            $series = $this->series ?? Series::on('tenant')->find($this->series_id);
        }

        $parts = [$displayName];

        if ($series) {
            $parts[] = $series->code . ($this->section ?? '');
        } elseif ($this->section) {
            $parts[] = $this->section;
        }

        return implode(' ', $parts);
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
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function headTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_teacher_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\StructureAcademique\Database\Factories\ClasseFactory::new();
    }
}
