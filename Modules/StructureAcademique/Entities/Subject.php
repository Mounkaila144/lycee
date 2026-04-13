<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Enums\SubjectCategory;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'subjects';

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'category',
        'description',
        'is_active',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'category' => SubjectCategory::class,
        ];
    }

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, SubjectCategory $category): Builder
    {
        return $query->where('category', $category->value);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\StructureAcademique\Database\Factories\SubjectFactory::new();
    }
}
