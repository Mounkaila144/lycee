<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\User;

class AbsenceJustification extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'absence_justifications';

    protected $fillable = [
        'student_id',
        'evaluation_id',
        'file_path',
        'original_filename',
        'submitted_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_comment',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ModuleEvaluationConfig::class, 'evaluation_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Business Logic

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function approve(User $reviewer, ?string $comment = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'admin_comment' => $comment,
        ]);
    }

    public function reject(User $reviewer, string $comment): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'admin_comment' => $comment,
        ]);
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('tenant')->url($this->file_path) : null;
    }
}
