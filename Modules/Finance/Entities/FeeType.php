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

    /**
     * @var list<string>
     */
    protected $appends = ['is_active'];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'applies_to' => 'array',
        ];
    }

    /**
     * Un type de frais désactivé est archivé (soft delete) : il n'est plus
     * proposé à la facturation mais reste lié aux factures existantes.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->deleted_at === null;
    }

    /**
     * Codes de niveau (levels.code) auxquels le frais s'applique ; vide = tous.
     *
     * @return list<string>
     */
    public function applicableLevelCodes(): array
    {
        return array_values(array_filter((array) ($this->applies_to['levels'] ?? [])));
    }

    /**
     * Montants spécifiques par code de niveau.
     *
     * @return array<string, float>
     */
    public function levelAmounts(): array
    {
        $amounts = (array) ($this->applies_to['level_amounts'] ?? []);

        return array_map(fn ($amount): float => (float) $amount, array_filter(
            $amounts,
            fn ($amount): bool => $amount !== null && $amount !== '' && is_numeric($amount)
        ));
    }

    public function appliesToLevel(?string $levelCode): bool
    {
        $levels = $this->applicableLevelCodes();

        return $levels === [] || ($levelCode !== null && in_array($levelCode, $levels, true));
    }

    public function appliesToAcademicYear(int $academicYearId): bool
    {
        return $this->academic_year_id === null || (int) $this->academic_year_id === $academicYearId;
    }

    /**
     * Montant applicable pour un niveau (montant du niveau, sinon montant par défaut).
     */
    public function amountForLevel(?string $levelCode): float
    {
        $levelAmounts = $this->levelAmounts();

        if ($levelCode !== null && array_key_exists($levelCode, $levelAmounts)) {
            return $levelAmounts[$levelCode];
        }

        return (float) $this->default_amount;
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
