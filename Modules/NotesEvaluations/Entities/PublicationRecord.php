<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class PublicationRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'publication_records';

    protected $fillable = [
        'semester_id',
        'programme_id',
        'publication_type',
        'scope',
        'level',
        'published_at',
        'published_by',
        'students_count',
        'success_count',
        'success_rate',
        'notifications_sent',
        'notifications_count',
        'statistics',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'students_count' => 'integer',
            'success_count' => 'integer',
            'success_rate' => 'decimal:2',
            'notifications_sent' => 'boolean',
            'notifications_count' => 'integer',
            'statistics' => 'array',
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

    public function publishedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    // Scopes

    public function scopeForSemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeForProgramme($query, int $programmeId)
    {
        return $query->where('programme_id', $programmeId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('publication_type', $type);
    }

    public function scopeFinal($query)
    {
        return $query->where('publication_type', 'final');
    }

    public function scopeProvisional($query)
    {
        return $query->where('publication_type', 'provisional');
    }

    public function scopeWithNotifications($query)
    {
        return $query->where('notifications_sent', true);
    }

    // Computed Attributes

    public function getPublicationTypeLabelAttribute(): string
    {
        return match ($this->publication_type) {
            'provisional' => 'Provisoire',
            'final' => 'Définitif',
            'deliberation' => 'Après délibération',
            default => 'Non défini',
        };
    }

    public function getScopeLabelAttribute(): string
    {
        return match ($this->scope) {
            'semester' => 'Semestre complet',
            'programme' => 'Par programme',
            'level' => 'Par niveau',
            default => 'Non défini',
        };
    }

    public function getFailureCountAttribute(): int
    {
        return $this->students_count - $this->success_count;
    }

    public function getFailureRateAttribute(): float
    {
        return $this->students_count > 0
            ? round((($this->students_count - $this->success_count) / $this->students_count) * 100, 2)
            : 0;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\PublicationRecordFactory::new();
    }
}
