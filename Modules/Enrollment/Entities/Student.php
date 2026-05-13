<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Database\Factories\StudentFactory;
use Modules\PortailParent\Entities\ParentModel;
use Modules\UsersGuard\Entities\TenantUser;

class Student extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): StudentFactory
    {
        return StudentFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'students';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'user_id',
        'matricule',
        'firstname',
        'lastname',
        'birthdate',
        'birthplace',
        'sex',
        'nationality',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'country',
        'quarter',
        'blood_group',
        'health_notes',
        'photo',
        'status',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    /**
     * Relations
     */
    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(StudentAuditLog::class);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentModel::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot(['is_primary_contact', 'is_financial_responsible'])
            ->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Actif');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'Suspendu');
    }

    public function scopeExcluded($query)
    {
        return $query->where('status', 'Exclu');
    }

    public function scopeGraduated($query)
    {
        return $query->where('status', 'Diplômé');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('matricule', 'like', "%{$search}%")
                ->orWhere('firstname', 'like', "%{$search}%")
                ->orWhere('lastname', 'like', "%{$search}%");
        });
    }

    public function scopeDuplicateOf($query, string $firstname, string $lastname, string $birthdate)
    {
        return $query->where('firstname', $firstname)
            ->where('lastname', $lastname)
            ->whereDate('birthdate', $birthdate);
    }

    /**
     * Accessors
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function getAgeAttribute(): int
    {
        return $this->birthdate->age;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo) {
            return null;
        }

        return Storage::disk('tenant')->url($this->photo);
    }

    /**
     * Business Logic Methods
     */
    public function isActive(): bool
    {
        return $this->status === 'Actif';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'Suspendu';
    }

    public function isExcluded(): bool
    {
        return $this->status === 'Exclu';
    }

    public function isGraduated(): bool
    {
        return $this->status === 'Diplômé';
    }

    public function hasCompleteDocuments(): bool
    {
        $requiredTypes = [
            'certificat_naissance',
            'releve_baccalaureat',
            'photo_identite',
            'cni_passeport',
        ];

        $uploadedTypes = $this->documents()->pluck('type')->toArray();

        foreach ($requiredTypes as $type) {
            if (! in_array($type, $uploadedTypes)) {
                return false;
            }
        }

        return true;
    }

    public function getMissingDocuments(): array
    {
        $requiredTypes = [
            'certificat_naissance' => 'Certificat de naissance',
            'releve_baccalaureat' => 'Relevé de notes baccalauréat',
            'photo_identite' => 'Photo d\'identité',
            'cni_passeport' => 'CNI/Passeport',
        ];

        $uploadedTypes = $this->documents()->pluck('type')->toArray();

        return collect($requiredTypes)
            ->filter(fn ($label, $type) => ! in_array($type, $uploadedTypes))
            ->toArray();
    }

    public function getCompletenessPercentage(): int
    {
        $requiredTypes = [
            'certificat_naissance',
            'releve_baccalaureat',
            'photo_identite',
            'cni_passeport',
        ];

        $uploadedTypes = $this->documents()->pluck('type')->toArray();
        $uploadedCount = count(array_intersect($requiredTypes, $uploadedTypes));

        return (int) round(($uploadedCount / count($requiredTypes)) * 100);
    }

    /**
     * Check for potential duplicates
     */
    public static function findPotentialDuplicates(string $firstname, string $lastname, string $birthdate): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('firstname', $firstname)
            ->where('lastname', $lastname)
            ->where('birthdate', $birthdate)
            ->get();
    }
}
