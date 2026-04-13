<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\User;

class CoefficientHistory extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'coefficient_history';

    protected $fillable = [
        'evaluation_id',
        'old_coefficient',
        'new_coefficient',
        'changed_by',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_coefficient' => 'decimal:2',
            'new_coefficient' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    // Relations

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ModuleEvaluationConfig::class, 'evaluation_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // Computed

    public function getDifferenceAttribute(): float
    {
        return round($this->new_coefficient - $this->old_coefficient, 2);
    }

    public function getIsIncreaseAttribute(): bool
    {
        return $this->new_coefficient > $this->old_coefficient;
    }
}
