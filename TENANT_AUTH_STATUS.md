# État de l'authentification Multi-Tenant

## ✅ Ce qui a été complété

### 1. Structure de base
- ✅ IndexControllers créés pour Admin et Superadmin
- ✅ Routes configurées correctement (`/api/admin/auth/login`, `/api/superadmin/auth/login`)
- ✅ TenancyServiceProvider enregistré dans `bootstrap/providers.php`

### 2. Base de données

#### Base centrale (`crm_api` / `mysql`)
- ✅ Table `tenants` avec 3 tenants : `company1`, `company2`, `demo`
- ✅ Table `domains` configurée avec `tenant1.local` → `company1`
- ✅ Table `users` avec super admin

#### Bases tenant
- ✅ `tenant_company1` créée
- ✅ `tenant_company2` créée
- ✅ `tenant_demo` créée
- ✅ Tables migrées : `users`, `roles`, `permissions`, `model_has_roles`, etc.

### 3. Données tenant (tenant_company1)
- ✅ 4 utilisateurs créés :
  - `admin` (admin@company1.com) - application: admin
  - `manager` (manager@company1.com) - application: admin
  - `user1` (user1@company1.com) - application: frontend
  - `user2` (user2@company1.com) - application: frontend

- ✅ Rôles Spatie créés :
  - Administrator (toutes les permissions)
  - Manager (5 permissions)
  - User (1 permission)

- ✅ Rôles assignés aux utilisateurs via `model_has_roles`

### 4. Configuration
- ✅ `config/tenancy.php` : prefix = `tenant_`
- ✅ `config/database.php` : connexion `tenant` configurée
- ✅ `Modules/UsersGuard/Entities/Tenant.php` : `getDatabaseName()` retourne `tenant_company1`
- ✅ Middleware `tenant` configuré dans `bootstrap/app.php`

## ❌ Problème restant

### Le DatabaseTenancyBootstrapper ne configure PAS la connexion tenant

**Symptôme :**
```
SQLSTATE[3D000]: Invalid catalog name: 1046 No database selected (Connection: tenant)
```

**Cause identifiée :**
Même après `tenancy()->initialize($tenant)`, la config `database.connections.tenant.database` reste `null`.

**Test de vérification :**
```php
$tenant = \Modules\UsersGuard\Entities\Tenant::find('company1');
tenancy()->initialize($tenant);
config('database.connections.tenant.database'); // Retourne null au lieu de "tenant_company1"
```

**Ce qui devrait se passer :**
Le `DatabaseTenancyBootstrapper` (ligne 16 de `config/tenancy.php`) devrait :
1. Être exécuté lors de `tenancy()->initialize()`
2. Appeler `$tenant->database()->getName()` qui retourne `"tenant_company1"`
3. Configurer `config(['database.connections.tenant.database' => 'tenant_company1'])`
4. Reconnecter la connexion `tenant`

**Fichiers concernés :**
- `vendor/stancl/tenancy/src/Bootstrappers/DatabaseTenancyBootstrapper.php`
- `vendor/stancl/tenancy/src/DatabaseConfig.php` (ligne 66-69)
- `Modules/UsersGuard/Entities/Tenant.php`

## 🔍 Points à vérifier

1. **Le TenancyServiceProvider s'exécute-t-il ?**
   - Vérifier que `Stancl\Tenancy\TenancyServiceProvider` est bien chargé
   - Vérifier les événements `TenancyInitialized` et `TenancyEnded`

2. **Les bootstrappers sont-ils appelés ?**
   - Vérifier que `DatabaseTenancyBootstrapper::bootstrap()` est appelé
   - Ajouter des logs dans le bootstrapper pour déboguer

3. **La méthode `database()->getName()` fonctionne-t-elle ?**
   ```php
   $tenant = Tenant::find('company1');
   $tenant->database()->getName(); // Devrait retourner "tenant_company1"
   ```

4. **Alternative : Forcer la configuration manuellement**
   Si le bootstrapper ne fonctionne pas, créer un middleware personnalisé :
   ```php
   // app/Http/Middleware/SetTenantDatabase.php
   public function handle($request, Closure $next)
   {
       if (tenancy()->tenant) {
           $dbName = 'tenant_' . tenancy()->tenant->id;
           config(['database.connections.tenant.database' => $dbName]);
           DB::purge('tenant');
           DB::reconnect('tenant');
       }
       return $next($request);
   }
   ```

## 📝 Identifiants de test

### Super Admin (✅ Fonctionne)
- **URL**: POST http://localhost:8000/api/superadmin/auth/login
- **Body**:
  ```json
  {
    "username": "superadmin",
    "password": "password"
  }
  ```

### Admin Tenant (❌ À corriger)
- **URL**: POST http://tenant1.local/api/admin/auth/login
- **Headers**:
  - Content-Type: application/json
  - Accept: application/json
- **Body**:
  ```json
  {
    "username": "admin",
    "password": "password",
    "application": "admin"
  }
  ```

### Frontend Tenant (❌ À corriger)
- **URL**: POST http://tenant1.local/api/admin/auth/login
- **Body**:
  ```json
  {
    "username": "user1",
    "password": "password",
    "application": "frontend"
  }
  ```

## 🎯 Prochaines étapes

1. Déboguer pourquoi `DatabaseTenancyBootstrapper` ne s'exécute pas
2. Ou implémenter middleware personnalisé pour forcer la configuration
3. Tester l'authentification admin tenant
4. Tester l'authentification frontend tenant
5. Vérifier que les permissions Spatie fonctionnent correctement
