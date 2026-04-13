<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\StructureAcademique\Entities\Module;

class Equivalence extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'equivalences';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public const TYPE_FULL = 'Full';

    public const TYPE_PARTIAL = 'Partial';

    public const TYPE_NONE = 'None';

    public const TYPE_EXEMPTION = 'Exemption';

    public const TYPES = [
        self::TYPE_FULL,
        self::TYPE_PARTIAL,
        self::TYPE_NONE,
        self::TYPE_EXEMPTION,
    ];

    public const STATUS_PROPOSED = 'Proposed';

    public const STATUS_VALIDATED = 'Validated';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUSES = [
        self::STATUS_PROPOSED,
        self::STATUS_VALIDATED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'transfer_id',
        'origin_module_code',
        'origin_module_name',
        'origin_ects',
        'origin_hours',
        'origin_grade',
        'target_module_id',
        'equivalence_type',
        'equivalence_percentage',
        'granted_ects',
        'granted_grade',
        'notes',
        'similarity_score',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'origin_ects' => 'integer',
            'origin_hours' => 'integer',
            'origin_grade' => 'decimal:2',
            'equivalence_percentage' => 'integer',
            'granted_ects' => 'integer',
            'granted_grade' => 'decimal:2',
            'similarity_score' => 'integer',
        ];
    }

    /**
     * Relations
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function targetModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'target_module_id');
    }

    /**
     * Scopes
     */
    public function scopeProposed($query)
    {
        return $query->where('status', self::STATUS_PROPOSED);
    }

    public function scopeValidated($query)
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeFull($query)
    {
        return $query->where('equivalence_type', self::TYPE_FULL);
    }

    public function scopePartial($query)
    {
        return $query->where('equivalence_type', self::TYPE_PARTIAL);
    }

    public function scopeWithEcts($query)
    {
        return $query->where('granted_ects', '>', 0);
    }

    /**
     * Business Logic
     */
    public function isFull(): bool
    {
        return $this->equivalence_type === self::TYPE_FULL;
    }

    public function isPartial(): bool
    {
        return $this->equivalence_type === self::TYPE_PARTIAL;
    }

    public function isNone(): bool
    {
        return $this->equivalence_type === self::TYPE_NONE;
    }

    public function isExemption(): bool
    {
        return $this->equivalence_type === self::TYPE_EXEMPTION;
    }

    public function isProposed(): bool
    {
        return $this->status === self::STATUS_PROPOSED;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function canBeValidated(): bool
    {
        return $this->status === self::STATUS_PROPOSED;
    }

    /**
     * Get equivalence type label
     */
    public function getEquivalenceTypeLabel(): string
    {
        $labels = [
            self::TYPE_FULL => 'Équivalence totale',
            self::TYPE_PARTIAL => 'Équivalence partielle',
            self::TYPE_NONE => 'Pas d\'équivalence',
            self::TYPE_EXEMPTION => 'Dispense',
        ];

        return $labels[$this->equivalence_type] ?? $this->equivalence_type;
    }
}
