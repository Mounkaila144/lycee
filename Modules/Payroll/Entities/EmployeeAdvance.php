<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAdvance extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'employee_advances';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'employee_id',
        'advance_number',
        'advance_type',
        'amount',
        'reason',
        'request_date',
        'requested_by',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejection_reason',
        'disbursement_date',
        'disbursement_method',
        'disbursement_reference',
        'number_of_installments',
        'installment_amount',
        'first_deduction_date',
        'total_repaid',
        'balance',
        'has_interest',
        'interest_rate',
        'total_interest',
        'completion_date',
        'notes',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'total_repaid' => 'decimal:2',
            'balance' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'total_interest' => 'decimal:2',
            'number_of_installments' => 'integer',
            'request_date' => 'date',
            'approved_at' => 'datetime',
            'disbursement_date' => 'date',
            'first_deduction_date' => 'date',
            'completion_date' => 'date',
            'has_interest' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeDisbursed($query)
    {
        return $query->where('status', 'disbursed');
    }

    public function scopeRepaying($query)
    {
        return $query->where('status', 'repaying');
    }

    public function scopeRepaid($query)
    {
        return $query->where('status', 'repaid');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['disbursed', 'repaying']);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('advance_type', $type);
    }

    /**
     * Business Methods
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isDisbursed(): bool
    {
        return in_array($this->status, ['disbursed', 'repaying', 'repaid']);
    }

    public function isRepaying(): bool
    {
        return $this->status === 'repaying';
    }

    public function isFullyRepaid(): bool
    {
        return $this->status === 'repaid' || $this->balance <= 0;
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeRejected(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeDisbursed(): bool
    {
        return $this->status === 'approved';
    }

    public function approve(int $userId, ?string $notes = null): bool
    {
        if (! $this->canBeApproved()) {
            return false;
        }

        $this->status = 'approved';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->approval_notes = $notes;

        return $this->save();
    }

    public function reject(int $userId, string $reason): bool
    {
        if (! $this->canBeRejected()) {
            return false;
        }

        $this->status = 'rejected';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->rejection_reason = $reason;

        return $this->save();
    }

    public function disburse(string $method, ?string $reference = null): bool
    {
        if (! $this->canBeDisbursed()) {
            return false;
        }

        $this->status = 'disbursed';
        $this->disbursement_date = now();
        $this->disbursement_method = $method;
        $this->disbursement_reference = $reference;
        $this->balance = $this->amount;

        return $this->save();
    }

    /**
     * Record a repayment
     */
    public function recordRepayment(float $amount): bool
    {
        if (! $this->isDisbursed()) {
            return false;
        }

        $this->total_repaid += $amount;
        $this->balance = max(0, $this->balance - $amount);

        if ($this->balance <= 0) {
            $this->status = 'repaid';
            $this->completion_date = now();
        } elseif ($this->status === 'disbursed') {
            $this->status = 'repaying';
        }

        return $this->save();
    }

    /**
     * Get remaining installments
     */
    public function getRemainingInstallments(): int
    {
        if (! $this->installment_amount || $this->installment_amount <= 0) {
            return 0;
        }

        return (int) ceil($this->balance / $this->installment_amount);
    }

    /**
     * Get repayment percentage
     */
    public function getRepaymentPercentage(): float
    {
        if ($this->amount <= 0) {
            return 0;
        }

        return ($this->total_repaid / $this->amount) * 100;
    }
}
