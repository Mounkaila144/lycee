<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentModuleChoice extends Model
{
    protected $connection = 'tenant';

    protected $table = 'student_module_choices';

    protected $fillable = [
        'student_id',
        'module_id',
        'specialization_id',
        'choice_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'choice_date' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    // Note: La relation student sera ajoutée quand le module Enrollment sera disponible

    /**
     * Scopes
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'En attente');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'Confirmé');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'Refusé');
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForSpecialization(Builder $query, int $specializationId): Builder
    {
        return $query->where('specialization_id', $specializationId);
    }

    /**
     * Confirmer le choix
     */
    public function confirm(): bool
    {
        $this->status = 'Confirmé';

        return $this->save();
    }

    /**
     * Refuser le choix
     */
    public function reject(): bool
    {
        $this->status = 'Refusé';

        return $this->save();
    }
}
