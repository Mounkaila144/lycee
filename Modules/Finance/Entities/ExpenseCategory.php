<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $connection = 'tenant';

    protected $table = 'expense_categories';

    protected $fillable = [
        'code',
        'name',
        'icon',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
