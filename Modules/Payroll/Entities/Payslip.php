<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payslip extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'payslips';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'payroll_record_id',
        'employee_id',
        'payroll_period_id',
        'payslip_number',
        'issue_date',
        'pdf_path',
        'generated_at',
        'generated_by',
        'is_digitally_signed',
        'signature_hash',
        'signed_at',
        'distribution_method',
        'sent_at',
        'is_downloaded',
        'downloaded_at',
        'download_count',
        'is_acknowledged',
        'acknowledged_at',
        'status',
        'notes',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'generated_at' => 'datetime',
            'signed_at' => 'datetime',
            'sent_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'is_digitally_signed' => 'boolean',
            'is_downloaded' => 'boolean',
            'is_acknowledged' => 'boolean',
            'download_count' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function payrollRecord(): BelongsTo
    {
        return $this->belongsTo(PayrollRecord::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * Scopes
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeGenerated($query)
    {
        return $query->where('status', 'generated');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeDownloaded($query)
    {
        return $query->where('is_downloaded', true);
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('is_acknowledged', true);
    }

    public function scopeByDistributionMethod($query, string $method)
    {
        return $query->where('distribution_method', $method);
    }

    /**
     * Business Methods
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isGenerated(): bool
    {
        return in_array($this->status, ['generated', 'sent', 'delivered']);
    }

    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered']);
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function canBeGenerated(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeSent(): bool
    {
        return $this->status === 'generated';
    }

    public function recordDownload(): void
    {
        $this->is_downloaded = true;
        $this->downloaded_at = $this->downloaded_at ?? now();
        $this->download_count += 1;
        $this->save();
    }

    public function acknowledge(): void
    {
        $this->is_acknowledged = true;
        $this->acknowledged_at = now();
        $this->save();
    }

    public function sign(string $hash): void
    {
        $this->is_digitally_signed = true;
        $this->signature_hash = $hash;
        $this->signed_at = now();
        $this->save();
    }
}
