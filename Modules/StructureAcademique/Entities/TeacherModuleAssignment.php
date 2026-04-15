<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherModuleAssignment extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';
    protected $table = 'teacher_module_assignments';

    protected $fillable = [
        'teacher_id',
        'module_id',
        'programme_id',
        'semester_id',
        'group_id',
        'level',
        'type',
        'hours_allocated',
        'status',
        'replaced_by',
        'replacement_reason',
    ];

    protected $casts = [
        'hours_allocated' => 'integer',
    ];

    public function teacher()
    {
        return $this->belongsTo(\Modules\UsersGuard\Entities\TenantUser::class, 'teacher_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class ?? Model::class, 'module_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function scopeByTeacher($query, int $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
