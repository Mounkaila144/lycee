<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\Classe;

/**
 * A mandatory fee line attached to a class (e.g. "Scolarité + Inscription").
 * The sum of a class's lines is what each enrolled student must pay.
 */
class ClassFee extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'class_fees';

    protected $fillable = [
        'class_id',
        'fee_type_id',
        'label',
        'amount',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'class_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id');
    }
}
