<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UsersGuard\Entities\TenantUser;

class CashierCloseRecord extends Model
{
    protected $connection = 'tenant';

    protected $table = 'cashier_close_records';

    protected $fillable = [
        'cashier_user_id',
        'close_date',
        'total_cash_declared',
        'total_cash_system',
        'total_cheque',
        'total_mobile_money',
        'total_card',
        'total_transfer',
        'variance',
        'status',
        'notes',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'close_date' => 'date',
            'closed_at' => 'datetime',
            'total_cash_declared' => 'decimal:2',
            'total_cash_system' => 'decimal:2',
            'total_cheque' => 'decimal:2',
            'total_mobile_money' => 'decimal:2',
            'total_card' => 'decimal:2',
            'total_transfer' => 'decimal:2',
            'variance' => 'decimal:2',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'cashier_user_id');
    }
}
