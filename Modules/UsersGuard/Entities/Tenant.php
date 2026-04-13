<?php

namespace Modules\UsersGuard\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Tenant Model
 * Uses CENTRAL database (mysql connection)
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    /**
     * Table in CENTRAL database
     */
    protected $table = 'tenants';

    /**
     * CENTRAL database connection
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'data',
    ];

    /**
     * Custom attributes stored in data JSON
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'site_id',
            'site_host',
            'site_db_name',
            'site_db_host',
            'site_db_port',
            'site_db_username',
            'site_db_password',
            'company_name',
            'company_email',
            'company_phone',
            'company_address',
            'company_logo',
            'is_active',
            'settings',
            'trial_ends_at',
            'subscription_ends_at',
        ];
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    /**
     * Scope to get only active tenants
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if tenant is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if tenant trial has expired
     */
    public function isTrialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Check if tenant subscription has expired
     */
    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_ends_at && $this->subscription_ends_at->isPast();
    }

    /**
     * Get the database name for this tenant
     */
    public function getDatabaseName(): string
    {
        return 'tenant_'.$this->id;
    }

    /**
     * Domains relationship
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Get a setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a setting value
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\UsersGuard\Database\Factories\TenantFactory::new();
    }
}
