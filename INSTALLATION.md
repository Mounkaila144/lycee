# Installation et Configuration du CRM Multi-Tenant

## Prérequis

- PHP 8.3+
- MySQL/MariaDB
- Composer
- Node.js & NPM

## Installation

### 1. Cloner et Installer les Dépendances

```bash
composer install
npm install
```

### 2. Configuration de l'Environnement

Copier `.env.example` vers `.env` et configurer :

```env
APP_NAME="CRM Multi-Tenant"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

# Base de données CENTRALE (pour super admin et tenants)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm_api
DB_USERNAME=root
DB_PASSWORD=

# Tenancy
TENANCY_DATABASE_PREFIX=tenant_
```

Générer la clé d'application :
```bash
php artisan key:generate
```

### 3. Créer la Base de Données Centrale

```sql
CREATE DATABASE crm_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Note:** Les bases de données des tenants seront créées automatiquement par le système lors de la création des tenants via les seeders.

## Installation Complète (Migrations + Seeders)

### Option 1 : Installation Automatique Complète (Recommandée)

Cette méthode exécute automatiquement :
- Les migrations de la base centrale
- Les seeders de la base centrale (Super Admins)
- La création des tenants
- Les migrations des tenants
- Les seeders des tenants (utilisateurs, rôles, permissions)

```bash
# 1. Exécuter les migrations des modules pour la base centrale
php artisan module:migrate UsersGuard --force

# 2. Exécuter les seeders (crée la base centrale ET les tenants automatiquement)
php artisan module:seed UsersGuard --force
```

**Résultat :** Vous aurez :
- Base centrale avec 2 super admins
- 3 tenants créés : `company1`, `company2`, `demo`
- Chaque tenant avec 4 utilisateurs (admin, manager, user1, user2)
- Rôles et permissions configurés pour chaque tenant

### Option 2 : Installation Manuelle Étape par Étape

#### Étape 1 : Migrations de la Base Centrale

```bash
# Migrer uniquement le module UsersGuard (base centrale)
php artisan module:migrate UsersGuard --force
```

**Tables créées dans la base centrale :**
- `cache`, `cache_locks` - Cache système
- `users` - Super admins centraux
- `tenants` - Liste des tenants
- `domains` - Domaines des tenants
- `migrations` - Suivi des migrations

#### Étape 2 : Seeders de la Base Centrale

```bash
# Exécuter les seeders du module
php artisan module:seed UsersGuard --force
```

**Ce seeder va automatiquement :**
1. Créer les super admins dans la base centrale
2. Créer les 3 tenants (company1, company2, demo)
3. Créer les bases de données pour chaque tenant
4. Exécuter les migrations des tenants
5. Créer les utilisateurs, rôles et permissions dans chaque tenant

## Réinstallation / Réinitialisation Complète

### Méthode 1 : Vider et Réinstaller (Sans Supprimer les Bases)

```bash
# 1. Vider toutes les tables de la base centrale
php artisan db:wipe --force

# 2. Réexécuter les migrations de la base centrale
php artisan module:migrate UsersGuard --force

# 3. Réexécuter les seeders (recrée tout)
php artisan module:seed UsersGuard --force
```

### Méthode 2 : Suppression Complète et Réinstallation

**Étape 1 : Supprimer toutes les bases de données**

```sql
-- Supprimer la base centrale
DROP DATABASE IF EXISTS crm_api;

-- Supprimer les bases des tenants
DROP DATABASE IF EXISTS tenant_company1;
DROP DATABASE IF EXISTS tenant_company2;
DROP DATABASE IF EXISTS tenant_demo;

-- Recréer la base centrale
CREATE DATABASE crm_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Étape 2 : Réinstaller**

```bash
# 1. Migrations de la base centrale
php artisan module:migrate UsersGuard --force

# 2. Seeders (recrée tout automatiquement)
php artisan module:seed UsersGuard --force
```

## Credentials par Défaut

Après l'installation, vous pouvez vous connecter avec :

### Super Admins (Base Centrale)
```
Username: superadmin
Password: password
Email: superadmin@crm.com

Username: admin
Password: password
Email: admin@crm.com
```

### Tenant Admin (Pour company1, company2, demo)
```
Username: admin
Password: password
Application: admin
```

### Tenant Manager (Pour company1, company2, demo)
```
Username: manager
Password: password
Application: admin
```

### Tenant Users (Pour company1, company2, demo)
```
Username: user1 ou user2
Password: password
Application: frontend
```

## Structure des Bases de Données

### Base Centrale (`crm_api`)
- `cache`, `cache_locks` - Cache système
- `users` - Super admins
- `tenants` - Liste des tenants
- `domains` - Domaines des tenants
- `migrations` - Suivi des migrations

### Base Tenant (`tenant_company1`, `tenant_company2`, `tenant_demo`)
- `users` - Utilisateurs admin et frontend du tenant
- `permissions` - Permissions (Spatie Permission)
- `roles` - Rôles (Spatie Permission)
- `model_has_roles` - Pivot user-roles
- `model_has_permissions` - Pivot user-permissions
- `role_has_permissions` - Pivot role-permissions
- `migrations` - Suivi des migrations tenant

## Configuration des Hosts (Développement Local)

Ajouter dans `/etc/hosts` (Linux/Mac) ou `C:\Windows\System32\drivers\etc\hosts` (Windows) :

```
127.0.0.1   tenant1.local
127.0.0.1   tenant2.local
127.0.0.1   demo.localhost
```

## Utilisation

### Démarrer le Serveur

```bash
php artisan serve
```

Ou avec Laravel Sail :
```bash
./vendor/bin/sail up
```

### Tester l'API

#### Super Admin Login
```bash
curl -X POST http://localhost:8000/api/superadmin/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"superadmin","password":"password"}'
```

#### Tenant Admin Login (par domaine)
```bash
curl -X POST http://tenant1.local:8000/api/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password","application":"admin"}'
```

#### Tenant Admin Login (par header)
```bash
curl -X POST http://localhost:8000/api/admin/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: company1" \
  -d '{"username":"admin","password":"password","application":"admin"}'
```

## Commandes Artisan Utiles

### Modules

```bash
# Lister tous les modules
php artisan module:list

# Migrer un module spécifique (base centrale uniquement)
php artisan module:migrate UsersGuard --force

# Migrer tous les modules
php artisan module:migrate --all --force

# Seed un module spécifique
php artisan module:seed UsersGuard --force

# Rollback d'un module
php artisan module:migrate-rollback UsersGuard
```

### Tenants

```bash
# Lister tous les tenants
php artisan tenants:list

# Migrer tous les tenants
php artisan tenants:migrate --force

# Migrer un tenant spécifique
php artisan tenants:migrate --tenants=company1 --force

# Fresh migration pour tous les tenants
php artisan tenants:migrate-fresh --force

# Seed tous les tenants
php artisan tenants:seed --force

# Seed un tenant spécifique
php artisan tenants:seed --tenants=company1 --force

# Rollback migrations des tenants
php artisan tenants:rollback
```

### Base de Données

```bash
# Vider toutes les tables (base centrale)
php artisan db:wipe --force

# Vider et réexécuter toutes les migrations (base centrale)
php artisan migrate:fresh --force
```

## Créer un Nouveau Tenant Manuellement

Si vous voulez créer un tenant après l'installation initiale :

```bash
php artisan tinker
```

```php
use Modules\UsersGuard\Entities\Tenant;

// Créer le tenant
$tenant = Tenant::create(['id' => 'company3']);

// Ajouter le domaine
$tenant->domains()->create(['domain' => 'tenant3.local']);

// Les migrations du tenant seront exécutées automatiquement
```

## Architecture Modulaire

Ce projet utilise **nwidart/laravel-modules** pour une architecture modulaire.

### Emplacement des Migrations

- **Migrations Centrales** : `Modules/{ModuleName}/Database/Migrations/*.php`
- **Migrations Tenants** : `Modules/{ModuleName}/Database/Migrations/tenant/*.php`

La configuration dans `config/tenancy.php` collecte automatiquement les migrations tenant de tous les modules :

```php
'migration_parameters' => [
    '--force' => true,
    '--path' => collect(glob(base_path('Modules/*/Database/Migrations/tenant')))
        ->filter(fn ($path) => is_dir($path))
        ->values()
        ->toArray(),
    '--realpath' => true,
],
```

### Créer un Nouveau Module

```bash
# Créer le module
php artisan module:make NouveauModule

# Créer une migration pour la base centrale
php artisan make:migration create_table_name --path=Modules/NouveauModule/Database/Migrations

# Créer une migration pour les tenants
php artisan make:migration create_tenant_table --path=Modules/NouveauModule/Database/Migrations/tenant
```

## Troubleshooting

### Erreur de connexion base de données tenant

Vérifier que :
1. La base tenant existe (`SHOW DATABASES LIKE 'tenant_%';`)
2. Le tenant est actif dans la table `tenants`
3. Le domaine existe dans la table `domains`

### Token invalide

Vérifier que :
1. `APP_KEY` est défini dans `.env`
2. Le guard utilisé correspond au type d'utilisateur

### Permissions ne fonctionnent pas

Vérifier que :
1. Les migrations Spatie ont été exécutées dans la base tenant
2. Le trait `HasRoles` est utilisé dans le modèle `TenantUser`
3. Le cache est vidé : `php artisan cache:clear`

### Table 'cache' n'existe pas

Si vous rencontrez cette erreur, c'est que la migration `create_cache_table` n'a pas été exécutée. Vérifiez que le module UsersGuard contient cette migration dans `Modules/UsersGuard/Database/Migrations/2024_01_01_000000_create_cache_table.php`.

### Les seeders ne créent pas les tenants

Assurez-vous que le `TenantSeeder` appelle bien `tenants:migrate` pour chaque tenant créé. Vérifiez dans `Modules/UsersGuard/Database/Seeders/TenantSeeder.php`.

## Développement

### Workflow de Développement

1. Créer les migrations dans le module approprié
2. Exécuter `php artisan module:migrate ModuleName`
3. Créer les seeders si nécessaire
4. Tester avec `php artisan module:seed ModuleName`

### Bonnes Pratiques

- ✅ Toujours utiliser `--force` en production pour éviter les prompts
- ✅ Utiliser les migrations modulaires (pas `database/migrations`)
- ✅ Créer des seeders pour les données de test
- ✅ Utiliser `module:seed` pour seed automatique des tenants
- ✅ Vérifier la structure avec `tenants:list` après installation