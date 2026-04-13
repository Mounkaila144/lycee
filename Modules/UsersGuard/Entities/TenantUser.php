<?php

namespace Modules\UsersGuard\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Tenant User Model (Admin & Frontend Users)
 * Uses TENANT database (tenant connection)
 */
class TenantUser extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Table in TENANT database
     */
    protected $table = 'users';

    /**
     * TENANT database connection (dynamic)
     */
    protected $connection = 'tenant';

    /**
     * Guard name for permissions
     */
    protected $guard_name = 'tenant';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'firstname',
        'lastname',
        'application',
        'is_active',
        'sex',
        'phone',
        'mobile',
        'avatar',
        'address',
        'city',
        'country',
        'postal_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'lastlogin' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Scope to get only active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only admin users
     */
    public function scopeAdmin($query)
    {
        return $query->where('application', 'admin');
    }

    /**
     * Scope to get only frontend users
     */
    public function scopeFrontend($query)
    {
        return $query->where('application', 'frontend');
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}") ?: $this->username;
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->application === 'admin';
    }

    /**
     * Check if user is frontend user
     */
    public function isFrontend(): bool
    {
        return $this->application === 'frontend';
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update(['lastlogin' => now()]);
    }

    /**
     * Get avatar URL
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            return asset('storage/avatars/'.$this->avatar);
        }

        return null;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\UsersGuard\Database\Factories\TenantUserFactory::new();
    }
}
