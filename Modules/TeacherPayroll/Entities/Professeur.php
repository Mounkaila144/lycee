<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UsersGuard\Entities\User;

class Professeur extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'professeurs';

    public const STATUTS = [
        'PERMANENT',
        'CONTRACTUEL',
        'VACATAIRE',
        'FONCTIONNAIRE_INTERVENANT',
        'INTERVENANT_EXTERNE',
        'REMPLACANT',
    ];

    protected $fillable = [
        'user_id',
        'matricule',
        'nom',
        'prenom',
        'telephone',
        'email',
        'adresse',
        'sexe',
        'date_naissance',
        'statut_professeur',
        'specialite',
        'niveau_enseignement',
        'diplome',
        'numero_piece_identite',
        'date_recrutement',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_recrutement' => 'date',
            'actif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(ContratProfesseur::class, 'professeur_id')->orderBy('date_debut', 'desc');
    }

    public function contratActif(): HasOne
    {
        return $this->hasOne(ContratProfesseur::class, 'professeur_id')
            ->where('statut', 'ACTIF')
            ->latest('date_debut');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(AffectationProfesseur::class, 'professeur_id');
    }

    public function heuresCours(): HasMany
    {
        return $this->hasMany(HeureCoursProfesseur::class, 'professeur_id');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(AbsenceProfesseur::class, 'professeur_id');
    }

    public function primes(): HasMany
    {
        return $this->hasMany(PrimeProfesseur::class, 'professeur_id');
    }

    public function retenues(): HasMany
    {
        return $this->hasMany(RetenueProfesseur::class, 'professeur_id');
    }

    public function avances(): HasMany
    {
        return $this->hasMany(AvanceProfesseur::class, 'professeur_id');
    }

    public function paies(): HasMany
    {
        return $this->hasMany(PaieProfesseur::class, 'professeur_id');
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }

    public function getContratActifPour(?int $anneeScolaireId = null): ?ContratProfesseur
    {
        $query = $this->contrats()->where('statut', 'ACTIF');

        if ($anneeScolaireId !== null) {
            $query->where('annee_scolaire_id', $anneeScolaireId);
        }

        return $query->latest('date_debut')->first();
    }
}
