<?php

namespace Modules\PortailParent\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\PortailParent\Database\Factories\ParentModelFactory;
use Modules\UsersGuard\Entities\TenantUser;

class ParentModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'parents';

    protected $fillable = [
        'user_id',
        'firstname',
        'lastname',
        'relationship',
        'phone',
        'phone_secondary',
        'email',
        'profession',
        'address',
    ];

    protected static function newFactory(): ParentModelFactory
    {
        return ParentModelFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
            ->withPivot(['is_primary_contact', 'is_financial_responsible'])
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }
}
