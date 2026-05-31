<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'expenses';

    protected $fillable = [
        'expense_category_id',
        'label',
        'amount',
        'expense_date',
        'paid_date',
        'status',
        'payment_method',
        'supplier',
        'reference',
        'notes',
        'is_recurring',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'paid_date' => 'date',
            'is_recurring' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    /**
     * Dépenses décaissées dans une période (base trésorerie).
     */
    public function scopePaidBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->where('status', 'paid')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(paid_date, expense_date)'), [$from, $to]);
    }
}
