<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\AcademicYear;

class FeeType extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'fee_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'default_amount',
        'category',
        'is_mandatory',
        'applies_to',
        'academic_year_id',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'applies_to' => 'array',
        ];
    }

    /**
     * Academic year relationship
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Invoice items using this fee type
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Discounts for this fee type
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    /**
     * Scope for mandatory fees
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Scope for optional fees
     */
    public function scopeOptional($query)
    {
        return $query->where('is_mandatory', false);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for active fees (current academic year or no year specified)
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('academic_year_id')
                ->orWhereHas('academicYear', function ($ay) {
                    $ay->where('is_active', true);
                });
        });
    }
}
