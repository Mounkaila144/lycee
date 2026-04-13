<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $connection = 'tenant';

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'fee_type_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    /**
     * Invoice relationship
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Fee type relationship
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    /**
     * Get net amount after discount
     */
    public function getNetAmountAttribute(): float
    {
        return (float) ($this->amount - $this->discount_amount);
    }
}
