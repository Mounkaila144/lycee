<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UsersGuard\Entities\User;

class GradeHistory extends Model
{
    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'grade_history';

    protected $fillable = [
        'grade_id',
        'old_value',
        'new_value',
        'old_is_absent',
        'new_is_absent',
        'changed_by',
        'changed_at',
        'reason',
        'change_type',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'decimal:2',
            'new_value' => 'decimal:2',
            'old_is_absent' => 'boolean',
            'new_is_absent' => 'boolean',
            'changed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    // Relations

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // Scopes

    public function scopeForGrade($query, int $gradeId)
    {
        return $query->where('grade_id', $gradeId);
    }

    public function scopeCreations($query)
    {
        return $query->where('change_type', 'creation');
    }

    public function scopeModifications($query)
    {
        return $query->where('change_type', 'modification');
    }

    public function scopeCorrections($query)
    {
        return $query->where('change_type', 'correction');
    }

    // Helpers

    public function getFormattedChange(): string
    {
        $oldDisplay = $this->old_is_absent ? 'ABS' : ($this->old_value ?? '-');
        $newDisplay = $this->new_is_absent ? 'ABS' : ($this->new_value ?? '-');

        return "{$oldDisplay} → {$newDisplay}";
    }
}
