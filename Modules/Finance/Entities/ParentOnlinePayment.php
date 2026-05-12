<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\TenantUser;

class ParentOnlinePayment extends Model
{
    protected $connection = 'tenant';

    protected $table = 'parent_online_payments';

    protected $fillable = [
        'transaction_id', 'parent_user_id', 'student_id', 'invoice_id',
        'amount', 'currency', 'method', 'status',
        'cinetpay_token', 'cinetpay_transaction_id', 'payment_url',
        'init_payload', 'webhook_payload', 'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'init_payload' => 'array',
            'webhook_payload' => 'array',
            'notified_at' => 'datetime',
        ];
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'parent_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function isFinal(): Attribute
    {
        return Attribute::get(fn (): bool => in_array($this->status, ['success', 'failed', 'cancelled', 'refused'], true));
    }
}
