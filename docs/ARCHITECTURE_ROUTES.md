# Architecture des Routes - Multi-Tenant

## Principe Fondamental

Le système utilise une architecture multi-tenant à **2 niveaux** :

### 1️⃣ Super Admin GLOBAL (Base Centrale)
- **Connexion BD** : `mysql` (base centrale)
- **Rôle** : Créer et gérer les **tenants** (établissements)
- **Dossiers** :
  - Controllers : `Modules/*/Http/Controllers/Superadmin/`
  - Routes : `Modules/*/Routes/superadmin.php`
- **Middleware** : `['auth:sanctum']` (PAS de middleware `tenant`)
- **Exemples de routes** :
  ```
  POST   /api/superadmin/tenants          # Créer un tenant
  GET    /api/superadmin/tenants          # Liste des tenants
  PUT    /api/superadmin/tenants/{id}     # Modifier un tenant
  DELETE /api/superadmin/tenants/{id}     # Supprimer un tenant
  ```

### 2️⃣ Admin TENANT (Base Tenant)
- **Connexion BD** : `tenant` (base spécifique au tenant)
- **Rôle** : Gérer **son établissement** (programmes, employés, étudiants, notes, etc.)
- **Dossiers** :
  - Controllers : `Modules/*/Http/Controllers/Admin/`
  - Routes : `Modules/*/Routes/admin.php`
- **Middleware** : `['auth:sanctum', 'tenant']`
- **Exemples de routes** :
  ```
  POST   /api/admin/programmes            # Créer un programme
  GET    /api/admin/employees             # Liste des employés
  POST   /api/admin/students              # Créer un étudiant
  GET    /api/admin/grades                # Consulter les notes
  ```

### 3️⃣ Enseignant (Base Tenant)
- **Connexion BD** : `tenant`
- **Rôle** : Gérer ses cours, saisir notes, consulter emploi du temps
- **Dossiers** :
  - Controllers : `Modules/*/Http/Controllers/Teacher/`
  - Routes : `Modules/*/Routes/teacher.php`
- **Middleware** : `['auth:sanctum', 'tenant']`
- **Exemples de routes** :
  ```
  GET    /api/teacher/courses             # Mes cours
  POST   /api/teacher/grades              # Saisir notes
  GET    /api/teacher/schedule            # Mon emploi du temps
  ```

### 4️⃣ Étudiant (Base Tenant)
- **Connexion BD** : `tenant`
- **Rôle** : Consulter ses notes, inscriptions, emploi du temps
- **Dossiers** :
  - Controllers : `Modules/*/Http/Controllers/Student/`
  - Routes : `Modules/*/Routes/student.php`
- **Middleware** : `['auth:sanctum', 'tenant']`
- **Exemples de routes** :
  ```
  GET    /api/student/grades              # Mes notes
  GET    /api/student/schedule            # Mon emploi du temps
  POST   /api/student/enrollments         # Mes inscriptions
  ```

## Table de Correspondance

| Ressource | Route CORRECTE | Route INCORRECTE ❌ |
|-----------|----------------|---------------------|
| **Tenants** (Super Admin Global) | `/api/superadmin/tenants` | - |
| **Programmes** (Admin Tenant) | `/api/admin/programmes` | ~~`/api/superadmin/programmes`~~ |
| **Employés** (Admin Tenant) | `/api/admin/employees` | ~~`/api/superadmin/employees`~~ |
| **Étudiants** (Admin Tenant) | `/api/admin/students` | ~~`/api/superadmin/students`~~ |
| **Notes** (Admin Tenant) | `/api/admin/grades` | ~~`/api/superadmin/grades`~~ |
| **Cours** (Admin Tenant) | `/api/admin/courses` | ~~`/api/superadmin/courses`~~ |

## Règle Simple

**Si la ressource existe dans la base `tenant` → utilisez `/api/admin/...`**

**Si la ressource existe dans la base `mysql` centrale → utilisez `/api/superadmin/...`**

## Middleware Configuration

### Routes Superadmin (Base Centrale)
```php
Route::prefix('api/superadmin')
    ->middleware(['auth:sanctum'])  // PAS de middleware 'tenant'
    ->group(function () {
        // Routes pour gérer les tenants
    });
```

### Routes Admin (Base Tenant)
```php
Route::prefix('api/admin')
    ->middleware(['auth:sanctum', 'tenant'])  // AVEC middleware 'tenant'
    ->group(function () {
        // Routes pour gérer le tenant
    });
```

## Migration des Stories

Toutes les stories utilisant `/api/superadmin/` pour des ressources tenant doivent être corrigées vers `/api/admin/`.

**Exception** : Seules les routes de gestion des tenants restent en `/api/superadmin/tenants`.