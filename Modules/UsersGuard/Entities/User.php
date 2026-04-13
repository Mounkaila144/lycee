<?php

namespace Modules\UsersGuard\Entities;

use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Timetable\Traits\HasTimetableNotifications;
use Modules\UsersGuard\Database\Factories\UserFactory;

/**
 * Modèle User pour les TENANTS (base du site)
 * Différent de App\Models\User (superadmin)
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasPermissions, HasTimetableNotifications, Notifiable;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return UserFactory::new();
    }

    /**
     * Table dans la base TENANT
     */
    protected $table = 'users';

    /**
     * Connexion TENANT (dynamique)
     */
    protected $connection = 'tenant';

    /**
     * Pas de timestamps Laravel
     */
    public $timestamps = false;

    /**
     * Colonnes modifiables
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'firstname',
        'lastname',
        'is_active',
        'sex',
        'phone',
        'mobile',
    ];

    /**
     * Colonnes cachées
     */
    protected $hidden = [
        'password',
        'salt',
    ];

    /**
     * Cast des types
     */
    protected $casts = [
        'lastlogin' => 'datetime',
    ];

    /**
     * Accessors
     */

    /**
     * Get the user's full name.
     */
    public function getNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    /**
     * Relations
     */
}
