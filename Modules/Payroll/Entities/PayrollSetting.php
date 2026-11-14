<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Version des paramètres de paie de l'école (cotisations, barème d'impôt, charges patronales).
 *
 * Chaque enregistrement est une version immuable avec une date d'effet ;
 * l'historique est conservé.
 *
 * @property int $id
 * @property int $version
 * @property \Illuminate\Support\Carbon $effective_date
 * @property array<string, mixed> $settings
 * @property string|null $notes
 * @property int|null $created_by
 */
class PayrollSetting extends Model
{
    protected $connection = 'tenant';

    protected $table = 'payroll_settings';

    protected $fillable = [
        'version',
        'effective_date',
        'settings',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_date' => 'date:Y-m-d',
            'settings' => 'array',
            'created_by' => 'integer',
        ];
    }

    /**
     * Versions en vigueur à une date donnée, la plus récente d'abord.
     */
    public function scopeEffectiveAt(Builder $query, string $date): Builder
    {
        return $query->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->orderByDesc('version');
    }
}
