<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\StructureAcademique\Database\Factories\ProgramLevelFactory;

class ProgramLevel extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'program_levels';

    protected $fillable = [
        'program_id',
        'level',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProgramLevelFactory::new();
    }

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'program_id' => 'integer',
        ];
    }

    /**
     * Relations
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'program_id');
    }

    /**
     * Validation métier: vérifier cohérence type programme / niveau
     */
    public function validateLevelForProgramType(): bool
    {
        $programType = $this->program->type;
        $level = $this->level;

        // Niveaux Licence uniquement pour programmes Licence
        if (in_array($level, ['L1', 'L2', 'L3'])) {
            return $programType === 'Licence';
        }

        // Niveaux Master uniquement pour programmes Master
        if (in_array($level, ['M1', 'M2'])) {
            return $programType === 'Master';
        }

        return false;
    }

    /**
     * Scope: filtrer par niveau
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope: filtrer par programme
     */
    public function scopeByProgram($query, int $programId)
    {
        return $query->where('program_id', $programId);
    }
}
