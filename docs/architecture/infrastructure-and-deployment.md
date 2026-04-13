# Infrastructure et Déploiement

[← Retour à l'index](./index.md)

---

## Architecture Base de Données

### Base Centrale (`mysql`)

**Tables** :
- `users` : Superadmins uniquement
- `tenants` : Établissements (tenants)
- `domains` : Domaines par tenant
- Autres tables stancl/tenancy

**AUCUNE MODIFICATION** : Les nouveaux modules n'ajoutent aucune table centrale.

### Bases Tenant (`tenant_{id}`)

Chaque établissement (collège ou lycée) dispose de sa propre base de données avec :
- Tables UsersGuard (existantes) : `users`, `roles`, `permissions`, etc.
- **45+ nouvelles tables** des 12 modules scolaires

**Isolation complète** : Les données d'un établissement ne sont jamais accessibles depuis un autre.

---

## Configuration Multi-tenant

### Identification du Tenant

**Méthode** : Domain-based tenancy

Chaque tenant est identifié par :
- **Domaine** : `lycee-mariama.gestion-scolaire.ne`
- **Sous-domaine** : `*.gestion-scolaire.ne`
- **Header HTTP** : `X-Tenant-ID` (pour API)

### Middleware Tenant

Toutes les routes admin/frontend utilisent :
```php
Route::middleware(['tenant', 'tenant.auth'])->group(function () {
    // Routes tenant
});
```

### Connexion Automatique

```php
// Dans les Models
protected $connection = 'tenant';  // Connexion tenant
// OU
protected $connection = 'mysql';   // Connexion centrale (rare)
```

---

## Queues et Jobs

### Configuration

**Driver recommandé** : Redis

**.env** :
```env
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Jobs Principaux

| Job | Module | Usage | Priorité |
|-----|--------|-------|----------|
| `GenerateReportCardJob` | Documents | Génération bulletin semestriel | Normal |
| `GenerateBatchReportCardsJob` | Documents | Bulletins d'une classe entière | Normal |
| `GenerateAttestationJob` | Documents | Génération attestation/exeat | Normal |
| `GenerateReceiptJob` | Comptabilité | Génération reçu paiement | High |
| `GeneratePayslipJob` | Paie | Génération bulletin paie | Normal |
| `ImportStudentsJob` | Inscriptions | Import CSV élèves | Low |
| `NotifyParentAbsenceJob` | Présences | Notification absence aux parents | High |
| `NotifyParentDisciplineJob` | Discipline | Notification sanction aux parents | High |

### Lancement Worker

```bash
php artisan queue:work redis --queue=high,default,low
```

**Recommandation Production** : Supervisor pour gérer les workers.

---

## Cache et Performance

### Configuration Cache

**Driver recommandé** : Redis

**.env** :
```env
CACHE_STORE=redis
```

### Stratégie de Cache

**Éléments à cacher** :
- Structure académique (rarement modifiée) : cycles, niveaux, séries, classes
- Emplois du temps (par semestre)
- Coefficients matières par classe (par année scolaire)
- Barèmes de notation (mentions, seuils)

**Durée** :
- Structure académique : 24h
- Emplois du temps : 1h
- Coefficients : 24h (invalidé si modification)
- Barèmes : Permanent (invalidé manuellement)

**Exemple** :
```php
$classes = Cache::remember('classes_tenant_' . tenant('id'), 86400, function () {
    return ClassModel::with(['level', 'series'])->get();
});
```

### Optimisation Requêtes

**Eager Loading** :
```php
// ✅ BON
$students = Student::with(['classEnrollment.class', 'parent'])
    ->paginate(15);

// ❌ MAUVAIS (N+1 queries)
$students = Student::paginate(15);
foreach ($students as $student) {
    $student->classEnrollment; // N queries
}
```

**Pagination stricte** :
- Listes : 15-50 items max par page
- Jamais de `->get()` sans pagination sur tables volumineuses

**Indexes** :
- Sur colonnes fréquemment requêtées (voir data-models.md)
- Sur foreign keys
- Sur colonnes de tri/recherche

---

## Stockage Fichiers

### Structure

```
storage/app/tenants/
└── tenant_{id}/
    ├── documents/
    │   ├── bulletins/         # Bulletins semestriels PDF
    │   ├── attestations/      # Attestations, exeat
    │   ├── cartes-scolaires/  # Cartes d'identité scolaire
    │   ├── pv-conseil/        # PV conseil de classe
    │   ├── recus/             # Reçus de paiement
    │   └── bulletins-paie/    # Bulletins de paie
    ├── uploads/
    │   ├── photos/            # Photos élèves
    │   └── justificatifs/     # Justificatifs absence
    └── imports/
        └── csv/               # Fichiers CSV importés
```

### Configuration

**Local (Dev)** :
```env
FILESYSTEM_DISK=local
```

**Production (Recommandé)** :
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=gestion-scolaire-{env}
```

### Usage

```php
// Stockage tenant-aware
Storage::disk('tenant')->put('photos/eleve_123.jpg', $file);

// Récupération
$url = Storage::disk('tenant')->url('photos/eleve_123.jpg');
```

---

## Configuration Environnement

### Développement (.env.local)

```env
APP_NAME="Gestion Scolaire"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lycee
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=sync  # Pas de Redis en dev
CACHE_STORE=array      # Cache en mémoire

PDF_ENABLE_REMOTE=true
PDF_ENABLE_PHP=true
```

### Production (.env)

```env
APP_NAME="Gestion Scolaire"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gestion-scolaire.ne

DB_CONNECTION=mysql
DB_HOST=mysql.production.com
DB_PORT=3306
DB_DATABASE=lycee_prod
DB_USERNAME=lycee_user
DB_PASSWORD=STRONG_PASSWORD_HERE

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=redis.production.com
REDIS_PASSWORD=REDIS_PASSWORD_HERE

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
```

---

## Déploiement

### Commandes Déploiement Backend

```bash
# 1. Récupérer dernière version
git pull origin master

# 2. Installer dépendances
composer install --no-dev --optimize-autoloader

# 3. Migrations tenant
php artisan tenants:migrate --force

# 4. Cache config/routes
php artisan config:cache
php artisan route:cache

# 5. Redémarrer queues
php artisan queue:restart
```

### Commandes Déploiement Frontend

```bash
# 1. Récupérer dernière version
git pull origin main

# 2. Installer dépendances
npm ci

# 3. Build production
npm run build

# 4. Redémarrer serveur Next.js
pm2 restart lycee-frontend
```

### Rollback

**Backend** :
```bash
php artisan tenants:rollback --step=1
```

**Frontend** :
```bash
git checkout <previous-commit>
npm run build
pm2 restart lycee-frontend
```

---

## Monitoring et Logs

### Logs Laravel

**Emplacement** : `storage/logs/laravel.log`

**Configuration** :
```env
LOG_CHANNEL=stack
LOG_LEVEL=debug  # Dev
LOG_LEVEL=error  # Production
```

### Logs Tenant-Specific

Recommandation : Ajouter tenant_id dans tous les logs

```php
Log::info('Student enrolled', [
    'tenant_id' => tenant('id'),
    'student_id' => $student->id,
    'class_id' => $class->id,
]);
```

### Monitoring Performance

**Recommandations** :
- Laravel Telescope (dev)
- Laravel Horizon (queues Redis)
- New Relic ou Datadog (production)

---

[Suivant : Stratégie de Gestion des Erreurs →](./error-handling-strategy.md)
