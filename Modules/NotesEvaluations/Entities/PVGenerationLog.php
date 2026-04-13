<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class PVGenerationLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'pv_generation_logs';

    protected $fillable = [
        'deliberation_session_id',
        'semester_id',
        'file_path',
        'file_name',
        'type',
        'generated_by',
        'generated_at',
        'statistics',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'statistics' => 'array',
            'metadata' => 'array',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(DeliberationSession::class, 'deliberation_session_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    // Computed attributes

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'session1' => 'Session 1',
            'rattrapage' => 'Session Rattrapage',
            'final' => 'Final Année',
            default => 'Inconnu',
        };
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('api.admin.pv.download', $this->id);
    }
}
