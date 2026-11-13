<?php

namespace Modules\Attendance\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\UsersGuard\Entities\User;

class AttendanceSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'timetable_slot_id',
        'classe_id',
        'subject_id',
        'session_date',
        'start_time',
        'end_time',
        'status',
        'method',
        'created_by',
        'completed_by',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function timetableSlot()
    {
        return $this->belongsTo(TimetableSlot::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function records()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Séances dont la date tombe dans les bornes du semestre.
     */
    public function scopeWithinSemester(Builder $query, Semester $semester): Builder
    {
        return $query->whereBetween('session_date', [
            $semester->start_date->toDateString(),
            $semester->end_date->toDateString(),
        ]);
    }

    /**
     * Séances visibles par un enseignant : créées par lui, de ses classes
     * ou de ses créneaux d'emploi du temps.
     *
     * @param  list<int>  $classeIds
     */
    public function scopeVisibleToTeacher(Builder $query, int $teacherId, array $classeIds = []): Builder
    {
        return $query->where(function (Builder $q) use ($teacherId, $classeIds) {
            $q->where('created_by', $teacherId)
                ->when($classeIds !== [], fn (Builder $c) => $c->orWhereIn('classe_id', $classeIds))
                ->orWhereHas('timetableSlot', fn (Builder $t) => $t->where('teacher_id', $teacherId));
        });
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('session_date', $date);
    }
}
