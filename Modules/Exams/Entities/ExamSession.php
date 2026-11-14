<?php

namespace Modules\Exams\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Modules\StructureAcademique\Entities\AcademicPeriod;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Module as AcademicModule;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\User;

class ExamSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected static ?bool $hasLyceeColumnsCache = null;

    protected $fillable = [
        'module_id',
        'subject_id',
        'classe_id',
        'level_id',
        'semester_id',
        'evaluation_period_id',
        'academic_year_id',
        'title',
        'description',
        'type',
        'exam_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'total_capacity',
        'status',
        'cancellation_reason',
        'class_evaluation_ids',
        'instructions',
        'allowed_materials',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'allowed_materials' => 'array',
        'class_evaluation_ids' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Les colonnes lycée (subject_id, classe_id, level_id, semester_id, cancellation_reason…)
     * n'existent qu'après la migration 2026_11_14_100000_adapt_exam_sessions_for_lycee.
     */
    public static function hasLyceeColumns(): bool
    {
        return static::$hasLyceeColumnsCache ??= Schema::connection('tenant')->hasColumn('exam_sessions', 'subject_id');
    }

    public static function flushLyceeColumnsCache(): void
    {
        static::$hasLyceeColumnsCache = null;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Classes concernées : la classe de la session, ou toutes les classes du niveau
     * pour l'année scolaire de la session (examen blanc commun).
     *
     * @return array<int, int>
     */
    public function targetClasseIds(): array
    {
        if (! static::hasLyceeColumns()) {
            return [];
        }

        if ($this->classe_id) {
            return [(int) $this->classe_id];
        }

        if ($this->level_id) {
            return Classe::query()
                ->where('level_id', $this->level_id)
                ->where('academic_year_id', $this->academic_year_id)
                ->orderBy('name')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return [];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AcademicModule::class, 'module_id');
    }

    /**
     * La colonne evaluation_period_id référence la table academic_periods.
     */
    public function evaluationPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'evaluation_period_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(ExamRoomAssignment::class);
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(ExamSupervisor::class);
    }

    public function attendanceSheets(): HasMany
    {
        return $this->hasMany(ExamAttendanceSheet::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(ExamIncident::class);
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('exam_date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('exam_date')
            ->orderBy('start_time');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('exam_date', $date);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
