<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModulePrerequisite extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'module_prerequisites';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'module_id',
        'prerequisite_module_id',
        'type',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relation: Module cible (celui qui a le prérequis)
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    /**
     * Relation: Module prérequis (celui qui doit être validé)
     */
    public function prerequisiteModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'prerequisite_module_id');
    }
}
