<?php

namespace Modules\Attendance\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UsersGuard\Entities\User;

class AbsenceJustification extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'student_id',
        'absence_date_from',
        'absence_date_to',
        'type',
        'reason',
        'document_path',
        'status',
        'submitted_by',
        'validated_by',
        'validated_at',
        'validation_notes',
    ];

    protected function casts(): array
    {
        return [
            'absence_date_from' => 'date',
            'absence_date_to' => 'date',
            'validated_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

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

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'medical' => 'Médical',
            'family' => 'Familial',
            'administrative' => 'Administratif',
            default => 'Autre',
        };
    }
}
