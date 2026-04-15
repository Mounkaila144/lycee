<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSpecialization extends Model
{
    protected $connection = 'tenant';

    protected $table = 'student_specializations';

    protected $fillable = [
        'student_id',
        'specialization_id',
        'application_date',
        'status',
        'average_at_application',
        'preference_order',
        'assigned_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'datetime',
            'average_at_application' => 'decimal:2',
            'preference_order' => 'integer',
            'assigned_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    // Note: La relation student sera ajoutée quand le module Enrollment sera disponible
    // public function student(): BelongsTo
    // {
    //     return $this->belongsTo(Student::class);
    // }

    /**
     * Scopes
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'En attente');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'Accepté');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'Refusé');
    }

    public function scopeWaitlisted(Builder $query): Builder
    {
        return $query->where('status', 'Liste attente');
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeOrderedByPreference(Builder $query): Builder
    {
        return $query->orderBy('preference_order');
    }

    public function scopeOrderedByAverage(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('average_at_application', $direction);
    }

    /**
     * Accepter la candidature
     */
    public function accept(): bool
    {
        $this->status = 'Accepté';
        $this->assigned_at = now();

        return $this->save();
    }

    /**
     * Refuser la candidature
     */
    public function reject(string $reason = ''): bool
    {
        $this->status = 'Refusé';
        $this->rejection_reason = $reason;

        return $this->save();
    }

    /**
     * Mettre en liste d'attente
     */
    public function waitlist(): bool
    {
        $this->status = 'Liste attente';

        return $this->save();
    }

    /**
     * Vérifier si la candidature est en attente
     */
    public function isPending(): bool
    {
        return $this->status === 'En attente';
    }

    /**
     * Vérifier si la candidature est acceptée
     */
    public function isAccepted(): bool
    {
        return $this->status === 'Accepté';
    }
}
