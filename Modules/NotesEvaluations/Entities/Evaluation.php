<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\User;

/**
 * Secondary-school evaluation: a graded assessment for a (class + subject)
 * within a school period.
 */
class Evaluation extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'evaluations';

    protected $fillable = [
        'class_id',
        'subject_id',
        'semester_id',
        'type',
        'name',
        'evaluation_date',
        'max_score',
        'coefficient',
        'created_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_date' => 'date',
            'max_score' => 'decimal:2',
            'coefficient' => 'decimal:2',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'evaluation_id');
    }
}
