<?php

namespace Modules\UsersGuard\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * Domain Model
 * Uses CENTRAL database (mysql connection)
 */
class Domain extends BaseDomain
{
    /**
     * Table in CENTRAL database
     */
    protected $table = 'domains';

    /**
     * CENTRAL database connection
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Tenant relationship
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to get only primary domains
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
