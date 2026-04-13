# UsersGuard Module

Module de gestion des utilisateurs multi-niveaux pour le CRM.

## Architecture

Ce module gère trois niveaux d'utilisateurs :

### 1. Super Admin (Base Centrale)
- Base de données : `mysql` (centrale)
- Table : `users` avec `application = 'superadmin'`
- Modèle : `Modules\UsersGuard\Entities\SuperAdmin`
- Guard : `superadmin`
- Routes : `/api/superadmin/*`

### 2. Admin Tenant (Base Tenant)
- Base de données : `tenant` (dynamique par tenant)
- Table : `users` avec `application = 'admin'`
- Modèle : `Modules\UsersGuard\Entities\TenantUser`
- Guard : `tenant`
- Routes : `/api/admin/*`

### 3. Frontend Tenant (Base Tenant)
- Base de données : `tenant` (dynamique par tenant)
- Table : `users` avec `application = 'frontend'`
- Modèle : `Modules\UsersGuard\Entities\TenantUser`
- Guard : `tenant`
- Routes : `/api/frontend/*` (à créer)

## Migrations

### Migrations Centrales
Localisation : `Modules/UsersGuard/Database/Migrations/`

Exécution :
```bash
php artisan module:migrate UsersGuard
```

Fichiers :
- `2024_01_01_000001_create_central_users_table.php` - Table users (super admin)
- `2024_01_01_000002_create_tenants_table.php` - Table tenants
- `2024_01_01_000003_create_domains_table.php` - Table domains

### Migrations Tenant
Localisation : `Modules/UsersGuard/Database/Migrations/tenant/`

Exécution (pour un tenant spécifique) :
```bash
php artisan tenants:migrate --tenants=<tenant_id>
```

Fichiers :
- `2024_01_01_100001_create_tenant_users_table.php` - Table users (admin & frontend)
- `2024_01_01_100002_create_permission_tables.php` - Tables permissions/roles (Spatie)

## Routes

### Super Admin Routes
```
POST   /api/superadmin/auth/login    - Connexion super admin
GET    /api/superadmin/auth/me       - Profil super admin
POST   /api/superadmin/auth/logout   - Déconnexion
POST   /api/superadmin/auth/refresh  - Rafraîchir le token
```

### Admin/Frontend Routes (Tenant)
```
POST   /api/admin/auth/login         - Connexion admin/frontend (avec X-Tenant-ID)
GET    /api/admin/auth/me            - Profil utilisateur
POST   /api/admin/auth/logout        - Déconnexion
POST   /api/admin/auth/refresh       - Rafraîchir le token
```

## Authentification

### Super Admin Login
```json
POST /api/superadmin/auth/login
{
  "username": "admin",
  "password": "password"
}
```

### Tenant Login
```json
POST /api/admin/auth/login
Headers: X-Tenant-ID: tenant1
{
  "username": "user@example.com",
  "password": "password",
  "application": "admin" // ou "frontend"
}
```

## Configuration

### Guards (config/auth.php)
- `superadmin` : Pour les super admins
- `tenant` : Pour les utilisateurs tenant (admin & frontend)

### Connections (config/database.php)
- `mysql` : Base centrale (super admin, tenants)
- `tenant` : Base tenant (dynamique)

## Permissions

Le module utilise **Spatie Laravel Permission** pour gérer les permissions des utilisateurs tenant.

### Utilisation
```php
// Assigner un rôle
$user->assignRole('Admin');

// Donner une permission
$user->givePermissionTo('edit articles');

// Vérifier une permission
if ($user->can('edit articles')) {
    //
}
```

## Commandes Utiles

### Migrations
```bash
# Migrer toutes les migrations du module
php artisan module:migrate UsersGuard

# Migrer les migrations tenant pour tous les tenants
php artisan tenants:migrate

# Migrer pour un tenant spécifique
php artisan tenants:migrate --tenants=tenant1
```

### Créer un nouveau tenant
```php
use Modules\UsersGuard\Entities\Tenant;

$tenant = Tenant::create([
    'site_id' => 'tenant1',
    'site_host' => 'tenant1.example.com',
    'site_db_name' => 'tenant_tenant1',
    'company_name' => 'Company Name',
    'is_active' => true,
]);

// Créer le domaine
$tenant->domains()->create([
    'domain' => 'tenant1.example.com',
    'is_primary' => true,
]);

// Exécuter les migrations pour ce tenant
artisan('tenants:migrate', ['--tenants' => [$tenant->site_id]]);
```

## Structure du Module

```
Modules/UsersGuard/
├── Config/
│   └── config.php
├── Database/
│   ├── Migrations/           # Migrations centrales
│   │   ├── 2024_01_01_000001_create_central_users_table.php
│   │   ├── 2024_01_01_000002_create_tenants_table.php
│   │   └── 2024_01_01_000003_create_domains_table.php
│   └── tenant/              # Migrations tenant
│       ├── 2024_01_01_100001_create_tenant_users_table.php
│       └── 2024_01_01_100002_create_permission_tables.php
├── Entities/
│   ├── SuperAdmin.php       # Modèle Super Admin
│   ├── Tenant.php           # Modèle Tenant
│   ├── Domain.php           # Modèle Domain
│   └── TenantUser.php       # Modèle utilisateur tenant
├── Http/
│   ├── Controllers/
│   │   ├── Superadmin/
│   │   │   └── AuthController.php
│   │   └── Admin/
│   │       └── AuthController.php
│   └── Requests/
│       ├── SuperAdminLoginRequest.php
│       └── TenantLoginRequest.php
├── Routes/
│   ├── superadmin.php       # Routes super admin
│   ├── admin.php            # Routes admin tenant
│   └── frontend.php         # Routes frontend (à créer)
└── Providers/
    ├── UsersGuardServiceProvider.php
    └── RouteServiceProvider.php
```

## Sécurité

- Les mots de passe supportent bcrypt (moderne) et MD5 (legacy)
- Tokens JWT via Laravel Sanctum
- Séparation stricte des bases de données
- Guards séparés pour chaque niveau
- Permissions granulaires avec Spatie Permission
