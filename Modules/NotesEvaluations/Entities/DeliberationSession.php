<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class DeliberationSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'deliberation_sessions';

    protected $fillable = [
        'semester_id',
        'programme_id',
        'session_type',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'location',
        'agenda',
        'jury_members',
        'president_id',
        'minutes',
        'summary',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'jury_members' => 'array',
            'summary' => 'array',
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

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function president(): BelongsTo
    {
        return $this->belongsTo(User::class, 'president_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(JuryDecision::class, 'deliberation_session_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForSemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeForProgramme($query, int $programmeId)
    {
        return $query->where('programme_id', $programmeId);
    }

    // Computed Attributes

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'En attente',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            default => 'Inconnu',
        };
    }

    public function getSessionTypeLabelAttribute(): string
    {
        return match ($this->session_type) {
            'regular' => 'Session ordinaire',
            'retake' => 'Session de rattrapage',
            'exceptional' => 'Session exceptionnelle',
            default => 'Non défini',
        };
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['pending', 'in_progress']);
    }

    public function getDurationAttribute(): ?int
    {
        if (! $this->started_at || ! $this->ended_at) {
            return null;
        }

        return $this->ended_at->diffInMinutes($this->started_at);
    }

    public function getDecisionsCountAttribute(): int
    {
        return $this->decisions()->count();
    }

    // Business Logic

    public function start(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function complete(array $summary = []): void
    {
        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'summary' => $summary,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function canStart(): bool
    {
        return $this->status === 'pending' && $this->scheduled_at <= now();
    }

    public function canAddDecisions(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\DeliberationSessionFactory::new();
    }
}
