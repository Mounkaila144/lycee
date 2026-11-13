<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\NotesEvaluations\Database\Factories\ClassEvaluationFactory;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Notes lycée — évaluation (devoir, interrogation, composition) d'une matière d'une classe sur un semestre.
 */
class ClassEvaluation extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['devoir', 'interrogation', 'composition'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_PUBLISHED];

    protected $connection = 'tenant';

    protected $table = 'class_evaluations';

    protected $fillable = [
        'classe_id',
        'subject_id',
        'semester_id',
        'teacher_id',
        'title',
        'type',
        'evaluation_date',
        'max_score',
        'weight',
        'status',
        'submitted_at',
        'published_at',
        'published_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_date' => 'date',
            'max_score' => 'decimal:2',
            'weight' => 'decimal:2',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ClassEvaluationFactory
    {
        return ClassEvaluationFactory::new();
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'teacher_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'published_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(ClassEvaluationGrade::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
