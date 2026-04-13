<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Database\Factories\StudentAuditLogFactory;
use Modules\UsersGuard\Entities\User;

class StudentAuditLog extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): StudentAuditLogFactory
    {
        return StudentAuditLogFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'student_audit_logs';

    public $timestamps = true;

    protected $fillable = [
        'student_id',
        'user_id',
        'event',
        'field_name',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeByField($query, string $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Helper methods
     */
    public function isUpdateEvent(): bool
    {
        return $this->event === 'updated';
    }

    public function isCreateEvent(): bool
    {
        return $this->event === 'created';
    }

    public function isDeleteEvent(): bool
    {
        return $this->event === 'deleted';
    }

    public function getChangedValueAttribute(): string
    {
        if ($this->isCreateEvent()) {
            return 'Créé';
        }

        if ($this->isDeleteEvent()) {
            return 'Supprimé';
        }

        return "{$this->old_value} → {$this->new_value}";
    }
}
