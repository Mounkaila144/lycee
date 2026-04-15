<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Database\Factories\ModuleFactory;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'modules';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ModuleFactory::new();
    }

    protected $fillable = [
        'code',
        'name',
        'credits_ects',
        'coefficient',
        'type',
        'semester',
        'level',
        'description',
        'hours_cm',
        'hours_td',
        'hours_tp',
        'is_eliminatory',
        'eliminatory_threshold',
        'module_group_id',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'credits_ects' => 'integer',
            'coefficient' => 'decimal:1',
            'hours_cm' => 'integer',
            'hours_td' => 'integer',
            'hours_tp' => 'integer',
            'is_eliminatory' => 'boolean',
            'eliminatory_threshold' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function moduleGroup(): BelongsTo
    {
        return $this->belongsTo(ModuleGroup::class);
    }

    public function programmes(): BelongsToMany
    {
        return $this->belongsToMany(Programme::class, 'module_programs');
    }

    /**
     * Modules prérequis pour ce module
     * (modules qui doivent être validés avant celui-ci)
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Module::class,
            'module_prerequisites',
            'module_id',
            'prerequisite_module_id'
        )->withPivot('type')->withTimestamps();
    }

    /**
     * Modules qui ont ce module comme prérequis
     * (modules qui nécessitent la validation de celui-ci)
     */
    public function dependentModules(): BelongsToMany
    {
        return $this->belongsToMany(
            Module::class,
            'module_prerequisites',
            'prerequisite_module_id',
            'module_id'
        )->withPivot('type')->withTimestamps();
    }

    /**
     * Rattachements du module aux semestres
     */
    public function semesterAssignments()
    {
        return $this->hasMany(ModuleSemesterAssignment::class);
    }

    /**
     * Semestres auxquels ce module est rattaché
     */
    public function semesters(): BelongsToMany
    {
        return $this->belongsToMany(Semester::class, 'module_semester_assignments')
            ->withPivot('programme_id', 'is_active')
            ->withTimestamps();
    }

    /**
     * Configurations d'évaluation pour ce module
     */
    public function evaluationConfigs()
    {
        return $this->hasMany(ModuleEvaluationConfig::class);
    }

    /**
     * Configurations d'évaluation pour un semestre spécifique
     */
    public function evaluationConfigsForSemester(int $semesterId)
    {
        return $this->evaluationConfigs()->where('semester_id', $semesterId);
    }

    /**
     * Affectations des enseignants à ce module
     */
    public function teacherAssignments()
    {
        return $this->hasMany(TeacherModuleAssignment::class);
    }

    /**
     * Accessors
     */
    public function getTotalHoursAttribute(): int
    {
        return ($this->hours_cm ?? 0) + ($this->hours_td ?? 0) + ($this->hours_tp ?? 0);
    }

    /**
     * Scopes
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeBySemester($query, string $semester)
    {
        return $query->where('semester', $semester);
    }

    public function scopeObligatoire($query)
    {
        return $query->where('type', 'Obligatoire');
    }

    public function scopeOptional($query)
    {
        return $query->where('type', 'Optionnel');
    }

    public function scopeEliminatory($query)
    {
        return $query->where('is_eliminatory', true);
    }

    /**
     * Méthodes métier
     */
    public function canBeModified(): bool
    {
        // TODO: Vérifier s'il y a des notes saisies pour ce module
        // Pour l'instant, on retourne true
        return true;
    }

    public function canBeDeleted(): bool
    {
        // TODO: Vérifier s'il y a des inscriptions actives ou des notes
        // Pour l'instant, on retourne true
        return true;
    }

    /**
     * Vérifier la cohérence semestre/niveau
     */
    public function isSemesterLevelConsistent(): bool
    {
        $semesterNumber = (int) str_replace('S', '', $this->semester);

        return match ($this->level) {
            'L1' => in_array($semesterNumber, [1, 2]),
            'L2' => in_array($semesterNumber, [3, 4]),
            'L3' => in_array($semesterNumber, [5, 6]),
            'M1' => in_array($semesterNumber, [7, 8]),
            'M2' => in_array($semesterNumber, [9, 10]),
            default => false,
        };
    }

    /**
     * Vérifier le volume horaire minimum
     */
    public function hasMinimumHours(): bool
    {
        return $this->total_hours >= 15;
    }
}
