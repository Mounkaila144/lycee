<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Database\Factories\SpecializationFactory;
use Modules\UsersGuard\Entities\User;

class Specialization extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'specializations';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected static function newFactory()
    {
        return SpecializationFactory::new();
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'programme_id',
        'available_from_level',
        'capacity',
        'responsable_id',
        'min_average_required',
        'application_start_date',
        'application_end_date',
        'type',
        'selection_mode',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'min_average_required' => 'decimal:2',
            'application_start_date' => 'date',
            'application_end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relations
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function studentSpecializations(): HasMany
    {
        return $this->hasMany(StudentSpecialization::class);
    }

    public function specializationModules(): HasMany
    {
        return $this->hasMany(SpecializationModule::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'specialization_modules')
            ->withPivot('type', 'capacity')
            ->withTimestamps();
    }

    public function mandatoryModules(): BelongsToMany
    {
        return $this->modules()->wherePivot('type', 'Obligatoire');
    }

    public function optionalModules(): BelongsToMany
    {
        return $this->modules()->wherePivot('type', 'Optionnel');
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProgramme(Builder $query, int $programmeId): Builder
    {
        return $query->where('programme_id', $programmeId);
    }

    public function scopeForLevel(Builder $query, string $level): Builder
    {
        return $query->where('available_from_level', $level);
    }

    public function scopeApplicationOpen(Builder $query): Builder
    {
        $now = now();

        return $query->where('application_start_date', '<=', $now)
            ->where('application_end_date', '>=', $now);
    }

    /**
     * Vérifier si les candidatures sont ouvertes
     */
    public function isApplicationOpen(): bool
    {
        if (! $this->application_start_date || ! $this->application_end_date) {
            return false;
        }

        $now = now();

        return $now->between($this->application_start_date, $this->application_end_date);
    }

    /**
     * Obtenir le nombre de places restantes
     */
    public function getRemainingCapacityAttribute(): ?int
    {
        if ($this->capacity === null) {
            return null; // Capacité illimitée
        }

        $acceptedCount = $this->studentSpecializations()
            ->where('status', 'Accepté')
            ->count();

        return max(0, $this->capacity - $acceptedCount);
    }

    /**
     * Vérifier si la spécialité est pleine
     */
    public function isFull(): bool
    {
        if ($this->capacity === null) {
            return false;
        }

        return $this->remaining_capacity <= 0;
    }

    /**
     * Vérifier si un étudiant peut candidater
     */
    public function canStudentApply(float $studentAverage): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->isApplicationOpen()) {
            return false;
        }

        if ($this->min_average_required && $studentAverage < $this->min_average_required) {
            return false;
        }

        return true;
    }
}
