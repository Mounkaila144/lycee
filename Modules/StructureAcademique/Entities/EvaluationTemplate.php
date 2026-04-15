<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvaluationTemplate extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'evaluation_templates';

    protected $fillable = [
        'name',
        'description',
        'config_json',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope to get only active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get evaluation structure from config
     */
    public function getEvaluationsAttribute(): array
    {
        return $this->config_json['evaluations'] ?? [];
    }
}
