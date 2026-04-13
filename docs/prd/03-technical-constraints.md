# ⚙️ Technical Constraints - Contraintes Techniques

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Version** : v5
> **Date** : 2026-03-16
> **Type** : Documentation Transverse

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 2.0 | Adaptation terminologie et exemples pour enseignement secondaire | John (PM) |
| 2026-01-07 | 1.0 | Création initiale - Contraintes techniques et architecture brownfield | John (PM) |

---

## 1. Vue d'Ensemble

Ce document définit les **contraintes techniques** du projet, issues de l'approche **brownfield** (construction sur un système existant) et du contexte technologique.

---

## 2. Approche Brownfield

### 2.1 Définition

Le projet est développé en mode **brownfield**, c'est-à-dire sur une **base existante opérationnelle** :

**Système Existant** :
- Module **UsersGuard** (authentification et gestion utilisateurs) déjà opérationnel
- Architecture **multi-tenant** fonctionnelle (stancl/tenancy)
- Stack **Laravel 12 + Next.js 15** déjà en place
- Patterns architecturaux établis

**Conséquence** :
- ✅ **Avantages** : Fondations solides, authentification prête, patterns éprouvés, risque réduit
- ⚠️ **Contraintes** : Respect des conventions existantes, impossibilité de refonte complète, adaptation aux choix techniques passés

### 2.2 Principes Brownfield

1. **Respecter l'Existant**
   - TOUJOURS suivre les patterns du module UsersGuard
   - NE PAS modifier l'architecture multi-tenant existante
   - NE PAS introduire de nouvelles technologies sans justification majeure

2. **Construction Incrémentale**
   - Ajout de modules un par un
   - Tests systématiques pour éviter les régressions
   - Déploiement progressif (phase par phase)

3. **Documentation Continue**
   - Documenter toute divergence par rapport aux patterns existants
   - Maintenir la cohérence architecturale globale

---

## 3. Stack Technique Imposée

### 3.1 Backend - Laravel 12

| Composant | Version | Contrainte |
|-----------|---------|------------|
| **PHP** | 8.3.26 | 🔴 NON MODIFIABLE |
| **Laravel** | 12.x | 🔴 NON MODIFIABLE |
| **Laravel Sanctum** | 4.x | 🔴 NON MODIFIABLE |
| **Laravel Modules** (nwidart) | 12.x | 🔴 NON MODIFIABLE |
| **Stancl Tenancy** | 3.9+ | 🔴 NON MODIFIABLE |
| **Spatie Permissions** | 6.24+ | 🔴 NON MODIFIABLE |
| **MySQL** | 8.0+ | 🔴 NON MODIFIABLE |

### 3.2 Frontend - Next.js 15

| Composant | Version | Contrainte |
|-----------|---------|------------|
| **Next.js** | 15.x | 🔴 NON MODIFIABLE |
| **React** | 18.x | 🔴 NON MODIFIABLE |
| **TypeScript** | 5.x | 🔴 NON MODIFIABLE |
| **Tailwind CSS** | 4.x | 🔴 NON MODIFIABLE |

### 3.3 Base de Données

**Architecture Multi-Tenant** :

```
┌─────────────────────────────────────────────┐
│          BASE CENTRALE (mysql)              │
├─────────────────────────────────────────────┤
│  Tables :                                   │
│  - users (superadmin central)               │
│  - tenants (établissements)                 │
│  - domains (mapping domaines ↔ tenants)     │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│       BASES TENANT (tenant_1, tenant_2...)  │
├─────────────────────────────────────────────┤
│  Tables :                                   │
│  - users (admin, enseignants, élèves,       │
│           parents, comptable, surv. gén.)   │
│  - permissions, roles (Spatie)              │
│  - academic_* (structure, classes, matières)│
│  - grades_*, attendance_*, discipline_*     │
│  - finance_*, payroll_*, documents_*        │
└─────────────────────────────────────────────┘
```

**Contraintes** :
- 🔴 **Isolation complète** : Chaque tenant a sa propre base de données
- 🔴 **Migrations tenant** : Toutes les tables métier sont des migrations tenant (pas central)
- 🔴 **Models avec $connection** : Tous les models doivent spécifier explicitement `$connection = 'mysql'` ou `'tenant'`

### 3.4 Architecture Polyrepo

- Backend et Frontend sont des **repositories séparés**
- Communication via **API REST** uniquement
- Header `X-Tenant-ID` obligatoire pour toutes les requêtes tenant

---

## 4. Patterns Architecturaux Établis (À RESPECTER)

### 4.1 Structure Backend - Laravel Modules

**Structure Imposée** (basée sur UsersGuard) :

```
Modules/
└── {ModuleName}/
    ├── Config/
    │   └── config.php
    ├── Database/
    │   ├── Migrations/
    │   │   ├── tenant/      # 🔴 OBLIGATOIRE pour tables métier
    │   │   └── central/     # Rare, seulement si justifié
    │   ├── Seeders/
    │   └── Factories/
    ├── Entities/            # Models Eloquent
    ├── Http/
    │   ├── Controllers/
    │   │   ├── Superadmin/  # API Centrale
    │   │   ├── Admin/       # API Tenant Admin
    │   │   └── Frontend/    # API Tenant Frontend (élèves, parents)
    │   ├── Requests/        # Form Requests pour validation
    │   └── Resources/       # API Resources pour transformation JSON
    ├── Providers/
    ├── Routes/
    │   ├── api.php
    │   ├── superadmin.php
    │   ├── admin.php
    │   └── frontend.php
    └── module.json
```

**Règles Strictes** :
- 🔴 **Respecter cette structure** : Pas de dossiers custom au même niveau
- 🔴 **Migrations tenant** : Toutes les tables métier dans `Database/Migrations/tenant/`
- 🔴 **Form Requests obligatoires** : JAMAIS de validation inline dans les controllers
- 🔴 **API Resources obligatoires** : JAMAIS de retour direct de models

### 4.2 Controllers Pattern

```php
// ✅ CORRECT - Pattern à suivre
class ClasseController extends Controller
{
    public function index()
    {
        $classes = Classe::query()
            ->with('niveau', 'serie', 'professeurPrincipal')
            ->paginate(15);

        return ClasseResource::collection($classes);
    }

    public function store(StoreClasseRequest $request)
    {
        $classe = Classe::create($request->validated());
        return new ClasseResource($classe);
    }
}
```

### 4.3 Models Pattern

```php
// ✅ CORRECT - Model Tenant
namespace Modules\StructureAcademique\Entities;

class Classe extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant'; // 🔴 OBLIGATOIRE

    protected $fillable = [
        'name',
        'niveau_id',
        'serie_id',
        'annee_scolaire_id',
    ];

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function eleves(): HasMany
    {
        return $this->hasMany(Eleve::class);
    }
}
```

### 4.4 Routes Pattern

```php
// Routes/admin.php (API Tenant Admin)
Route::middleware(['auth:sanctum', 'tenant'])->prefix('admin')->group(function () {
    Route::apiResource('classes', ClasseController::class);
    Route::apiResource('matieres', MatiereController::class);
});

// Routes/frontend.php (API Tenant Frontend - élèves, parents)
Route::middleware(['auth:sanctum', 'tenant'])->prefix('frontend')->group(function () {
    Route::get('classes', [ClasseController::class, 'index']);
    Route::get('classes/{classe}', [ClasseController::class, 'show']);
});
```

---

## 5. Contraintes Multi-Tenant

### 5.1 Règles Strictes

| Règle | Conséquence si non respectée |
|-------|------------------------------|
| **Header X-Tenant-ID** obligatoire | Erreur 400 Bad Request |
| **Isolation BD complète** | Fuite de données si $connection mal définie |
| **Pas de foreign keys inter-tenant** | Intégrité référentielle impossible |
| **Migrations tenant séparées** | Tables créées dans mauvaise base |

### 5.2 Permissions Tenant

- Permissions et rôles sont **isolés par tenant**
- Rôles spécifiques au secondaire : admin, censeur, surveillant_general, enseignant, eleve, parent, comptable
- SuperAdmin (central) a des permissions différentes des Admin (tenant)

---

## 6. Contraintes de Performance

### 6.1 Objectifs de Performance

| Métrique | Objectif |
|----------|----------|
| **Response Time API** | <200ms (p95) |
| **Page Load Time** | <3s (p95) |
| **Database Query Time** | <50ms |
| **PDF Generation (1 bulletin)** | <3s |
| **PDF Generation (classe 60 élèves)** | <5 min |

### 6.2 Stratégies d'Optimisation Imposées

- **Eager Loading Obligatoire** : Éviter N+1 queries
- **Pagination Obligatoire** : Pour toutes les listes
- **Index Database Obligatoires** : Sur foreign keys et colonnes de recherche
- **Optimisation bande passante** : Compression, lazy loading (contexte Niger)

### 6.3 Limites Techniques

| Limite | Valeur |
|--------|--------|
| **Max élèves par tenant** | 5,000 |
| **Max fichiers uploadés** | 10MB/fichier |
| **Max résultats API sans pagination** | 100 |
| **Timeout requête API** | 30s |

---

## 7. Contraintes de Sécurité

### 7.1 Protection Données de Mineurs

- 🔴 **Protection renforcée** : Les élèves sont des mineurs (11-20 ans)
- 🔴 **Accès parents uniquement** : Un parent ne peut voir que les données de SES enfants
- 🔴 **Logs d'audit** : Toute action sensible est tracée
- 🔴 **Archivage minimum** : 10 ans pour documents officiels

### 7.2 Authentification et Validation

- Tokens Bearer obligatoires (Laravel Sanctum)
- Form Requests obligatoires (jamais validation inline)
- Protection CSRF, XSS, SQL Injection (Laravel native)
- Rate limiting sur API

---

## 8. Contraintes de Déploiement

### 8.1 Environnements

| Environnement | Usage |
|---------------|-------|
| **Local** | Développement |
| **Staging** | Tests pré-prod |
| **Production** | Production |

### 8.2 CI/CD

1. Tests PHPUnit (backend) + Jest (frontend)
2. Laravel Pint (formatting)
3. Déploiement automatique si tests passent

---

## 9. Checklist Conformité Technique

**Avant de commencer un nouveau module, vérifier** :

- [ ] Structure Laravel Modules respectée
- [ ] Migrations dans `Database/Migrations/tenant/`
- [ ] Models avec `$connection = 'tenant'` explicite
- [ ] Form Requests pour toute validation
- [ ] API Resources pour toute réponse JSON
- [ ] Routes avec middleware `auth:sanctum` et `tenant`
- [ ] Tests PHPUnit écrits
- [ ] Protection données de mineurs vérifiée

---

## 10. Évolution Future Possible (Hors Scope MVP)

- **Mode offline partiel** : PWA avec Service Workers
- **Redis Caching** : Cache distribué pour performance
- **WebSockets** : Notifications temps réel
- **Mobile Apps** : React Native (iOS/Android)
- **SMS Notifications** : Via API SMS locale

---

## 11. Documents Connexes

- **[Overview](./00-overview.md)** : Vision globale du système
- **[Integration Architecture](./05-integration-architecture.md)** : Architecture détaillée inter-modules
- **[Architecture Brownfield](../brownfield-architecture.md)** : Documentation technique existante complète

---

**Maintenu par** : John (PM Agent) & Winston (Architect Agent)
**Dernière mise à jour** : 2026-03-16
