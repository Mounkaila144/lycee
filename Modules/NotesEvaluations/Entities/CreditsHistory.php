<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\StructureAcademique\Entities\Module;
use Modules\UsersGuard\Entities\User;

class CreditsHistory extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'credits_history';

    protected $fillable = [
        'module_id',
        'old_credits',
        'new_credits',
        'changed_by',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_credits' => 'integer',
            'new_credits' => 'integer',
            'changed_at' => 'datetime',
        ];
    }

    // Relations

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // Computed

    public function getDifferenceAttribute(): int
    {
        return $this->new_credits - $this->old_credits;
    }

    public function getIsIncreaseAttribute(): bool
    {
        return $this->new_credits > $this->old_credits;
    }
}
