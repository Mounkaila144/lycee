<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UsersGuard\Entities\User;

class ProgrammeHistory extends Model
{
    protected $connection = 'tenant';

    protected $table = 'programme_history';

    public $timestamps = false;

    protected $fillable = [
        'programme_id',
        'user_id',
        'action',
        'field_changed',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'json',
            'new_value' => 'json',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeByField($query, string $field)
    {
        return $query->where('field_changed', $field);
    }

    /**
     * Créer une entrée d'historique
     */
    public static function record(
        Programme $programme,
        string $action,
        ?string $field = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $reason = null
    ): self {
        return self::create([
            'programme_id' => $programme->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'field_changed' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Labels pour les champs
     */
    public static function getFieldLabel(string $field): string
    {
        return match ($field) {
            'libelle' => 'Libellé',
            'code' => 'Code',
            'type' => 'Type',
            'duree_annees' => 'Durée (années)',
            'description' => 'Description',
            'responsable_id' => 'Responsable',
            'statut' => 'Statut',
            default => $field,
        };
    }

    /**
     * Labels pour les actions
     */
    public static function getActionLabel(string $action): string
    {
        return match ($action) {
            'created' => 'Création',
            'updated' => 'Modification',
            'deleted' => 'Suppression',
            'restored' => 'Restauration',
            default => $action,
        };
    }
}
