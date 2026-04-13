<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractAmendment extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'contract_amendments';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'contract_id',
        'amendment_number',
        'amendment_type',
        'effective_date',
        'previous_values',
        'new_values',
        'description',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejection_reason',
        'amendment_document',
        'signature_date',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'signature_date' => 'date',
            'previous_values' => 'array',
            'new_values' => 'array',
            'approved_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmploymentContract::class, 'contract_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('amendment_type', $type);
    }

    public function scopeSalaryChanges($query)
    {
        return $query->where('amendment_type', 'salary_change');
    }

    /**
     * Business Methods
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canBeApproved(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval']);
    }

    public function canBeRejected(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval']);
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

    public function activate(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        if (now()->lt($this->effective_date)) {
            return false;
        }

        $this->status = 'active';

        return $this->save();
    }
}
