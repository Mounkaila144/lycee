<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\StructureAcademique\Entities\Module;

class ModuleAbsenceSettings extends Model
{
    protected $connection = 'tenant';

    protected $table = 'module_absence_settings';

    protected $fillable = [
        'module_id',
        'unjustified_grade_is_zero',
        'allow_replacement_evaluation',
        'justification_deadline_days',
        'auto_reminder_enabled',
    ];

    protected function casts(): array
    {
        return [
            'unjustified_grade_is_zero' => 'boolean',
            'allow_replacement_evaluation' => 'boolean',
            'justification_deadline_days' => 'integer',
            'auto_reminder_enabled' => 'boolean',
        ];
    }

    // Relations

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    // Static helpers

    public static function getForModule(int $moduleId): self
    {
        return static::firstOrCreate(
            ['module_id' => $moduleId],
            [
                'unjustified_grade_is_zero' => true,
                'allow_replacement_evaluation' => true,
                'justification_deadline_days' => 7,
                'auto_reminder_enabled' => true,
            ]
        );
    }
}
