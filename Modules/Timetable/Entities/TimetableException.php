<?php

namespace Modules\Timetable\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UsersGuard\Entities\User;

class TimetableException extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'timetable_slot_id',
        'exception_date',
        'exception_type',
        'original_values',
        'new_values',
        'reason',
        'notify_students',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'exception_date' => 'date',
            'original_values' => 'array',
            'new_values' => 'array',
            'notify_students' => 'boolean',
        ];
    }

    public function timetableSlot()
    {
        return $this->belongsTo(TimetableSlot::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getExceptionTypeLabel(): string
    {
        return match ($this->exception_type) {
            'cancellation' => 'Annulation',
            'room_change' => 'Changement de salle',
            'teacher_replacement' => 'Remplacement enseignant',
            'time_change' => 'Déplacement horaire',
            'date_change' => 'Report de séance',
            'exceptional_session' => 'Séance exceptionnelle',
            default => 'Modification',
        };
    }

    public function getExceptionIcon(): string
    {
        return match ($this->exception_type) {
            'cancellation' => '🚫',
            'room_change' => '🔄',
            'teacher_replacement' => '👤',
            'time_change' => '⏰',
            'date_change' => '📅',
            'exceptional_session' => '➕',
            default => '⚠️',
        };
    }
}
