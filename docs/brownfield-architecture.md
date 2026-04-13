# Documentation Architecture Brownfield - Système CRM Modulaire Multi-Tenant

> Documentation de l'état actuel du système au 2026-01-07
>
> Architecture existante à suivre pour tout nouveau développement

---

## Table des Matières

1. [Vue d'ensemble du système](#1-vue-densemble-du-système)
2. [Stack technique](#2-stack-technique)
3. [Architecture Polyrepo](#3-architecture-polyrepo)
4. [Module UsersGuard (référence)](#4-module-usersguard-référence)
5. [Architecture Multi-Tenant](#5-architecture-multi-tenant)
6. [Authentification et Autorisations](#6-authentification-et-autorisations)
7. [Base de données](#7-base-de-données)
8. [API REST et Routes](#8-api-rest-et-routes)
9. [Conventions de code](#9-conventions-de-code)
10. [Patterns établis](#10-patterns-établis)
11. [Infrastructure technique](#11-infrastructure-technique)

---

## 1. Vue d'ensemble du système

### 1.1 Description

Système de gestion pour l'enseignement supérieur avec architecture **modulaire** et **multi-tenant**. Chaque tenant dispose de sa propre base de données isolée, avec trois niveaux d'accès : SuperAdmin (central), Admin (tenant) et Frontend (tenant).

### 1.2 Architecture globale

```
┌─────────────────────────────────────────────────────────────┐
│                    APPLICATION GLOBALE                       │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌───────────────────────┐      ┌───────────────────────┐   │
│  │   BACKEND (Laravel)   │◄────►│  FRONTEND (Next.js)   │   │
│  │  C:\laragon\www\      │      │  C:\Users\Mounkaila\  │   │
│  │  crm-api              │      │  PhpstormProjects\    │   │
│  │                       │      │  icall26-front        │   │
│  └───────────────────────┘      └───────────────────────┘   │
│           │                              │                   │
│           │                              │                   │
│  ┌────────▼──────────┐          ┌───────▼────────┐          │
│  │  Base Centrale    │          │  Client HTTP   │          │
│  │  (mysql)          │          │  (Axios)       │          │
│  │  - users          │          │                │          │
│  │  - tenants        │          │  Header:       │          │
│  │  - domains        │          │  X-Tenant-ID   │          │
│  └───────────────────┘          └────────────────┘          │
│           │                                                  │
│  ┌────────▼──────────┐                                      │
│  │  Bases Tenant     │                                      │
│  │  (tenant_{id})    │                                      │
│  │  - users          │                                      │
│  │  - permissions    │                                      │
│  │  - roles          │                                      │
│  │  - ...            │                                      │
│  └───────────────────┘                                      │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### 1.3 État actuel

- **Base fonctionnelle** : Module UsersGuard opérationnel
- **Modules actifs** : 1 seul (UsersGuard)
- **Prêt pour extension** : Architecture modulaire prête pour nouveaux modules métier
- **État** : Production-ready pour le module existant

---

## 2. Stack technique

### 2.1 Backend (Laravel)

**Chemin** : `C:\laragon\www\crm-api`

```json
{
  "php": "^8.2",
  "laravel/framework": "^12.0",
  "laravel/sanctum": "^4.0",
  "nwidart/laravel-modules": "^12.0",
  "spatie/laravel-permission": "^6.24",
  "stancl/tenancy": "^3.9"
}
```

**Packages clés** :
- `nwidart/laravel-modules` : Gestion modulaire du backend
- `stancl/tenancy` : Multi-tenancy avec isolation de bases de données
- `spatie/laravel-permission` : Gestion des permissions tenant
- `laravel/sanctum` : Authentification API via tokens

### 2.2 Frontend (Next.js)

**Chemin** : `C:\Users\Mounkaila\PhpstormProjects\icall26-front`

```json
{
  "next": "15.1.2",
  "react": "18.3.1",
  "typescript": "5.5.4",
  "@mui/material": "6.2.1",
  "axios": "^1.13.2"
}
```

**Caractéristiques** :
- Next.js 15 avec App Router
- TypeScript strict
- Material-UI (MUI) pour l'interface
- Architecture modulaire miroir du backend

### 2.3 Base de données

- **MySQL** : Base centrale + bases tenant dynamiques
- **Structure** :
  - Base centrale : `mysql` (users superadmin, tenants, domains)
  - Bases tenant : `tenant_{id}` (users, roles, permissions, données métier)

---

## 3. Architecture Polyrepo

### 3.1 Séparation Backend / Frontend

Le projet utilise une architecture **Polyrepo** avec deux dépôts séparés :

```
┌──────────────────────────────┐
│ BACKEND (Laravel)            │
│ C:\laragon\www\crm-api       │
│                              │
│ - API REST                   │
│ - Gestion base de données    │
│ - Logique métier             │
│ - Authentification Sanctum   │
└──────────────────────────────┘

┌──────────────────────────────┐
│ FRONTEND (Next.js)           │
│ C:\Users\Mounkaila\          │
│ PhpstormProjects\icall26-front│
│                              │
│ - Interface utilisateur      │
│ - Client API (Axios)         │
│ - État de l'application      │
│ - Composants React           │
└──────────────────────────────┘
```

### 3.2 Structure modulaire Backend

**Emplacement** : `C:\laragon\www\crm-api\Modules\`

```
Modules/
└── UsersGuard/                            # Seul module actuel
    ├── module.json                        # Configuration du module
    ├── Providers/
    │   ├── UsersGuardServiceProvider.php
    │   └── RouteServiceProvider.php
    ├── Entities/                          # Models Eloquent
    │   ├── SuperAdmin.php                 # Model central
    │   ├── TenantUser.php                 # Model tenant
    │   ├── Tenant.php                     # Model tenant
    │   ├── Domain.php
    │   └── Permission.php
    ├── Database/
    │   ├── Migrations/                    # Migrations centrales
    │   │   ├── 2024_01_01_000001_create_central_users_table.php
    │   │   ├── 2024_01_01_000002_create_tenants_table.php
    │   │   └── 2024_01_01_000003_create_domains_table.php
    │   └── Migrations/tenant/             # Migrations tenant
    │       ├── 2024_01_01_100001_create_tenant_users_table.php
    │       ├── 2024_01_01_100002_create_permission_tables.php
    │       └── 2024_01_01_100003_create_personal_access_tokens_table.php
    ├── Http/
    │   ├── Controllers/
    │   │   ├── Admin/                     # Controllers tenant admin
    │   │   │   ├── AuthController.php
    │   │   │   └── UserController.php
    │   │   ├── Frontend/                  # Controllers tenant frontend
    │   │   │   └── IndexController.php
    │   │   └── Superadmin/                # Controllers central
    │   │       ├── AuthController.php
    │   │       ├── TenantController.php
    │   │       └── UserController.php
    │   ├── Requests/                      # Form Requests
    │   │   ├── TenantLoginRequest.php
    │   │   └── SuperAdminLoginRequest.php
    │   └── Resources/                     # API Resources
    │       ├── GroupResource.php
    │       └── PermissionResource.php
    └── Routes/                            # Routes par niveau
        ├── admin.php                      # Routes tenant admin
        ├── frontend.php                   # Routes tenant frontend
        └── superadmin.php                 # Routes central
```

### 3.3 Structure modulaire Frontend

**Emplacement** : `C:\Users\Mounkaila\PhpstormProjects\icall26-front\src\modules\`

```
modules/
└── UsersGuard/                            # Miroir du module backend
    ├── index.ts                           # Barrel export (API publique)
    ├── admin/                             # Couche admin tenant
    │   ├── components/
    │   │   ├── LoginForm.tsx
    │   │   ├── UsersList.tsx
    │   │   ├── UserListTable.tsx
    │   │   ├── UserAddModal.tsx
    │   │   └── UserEditModal.tsx
    │   ├── hooks/
    │   │   ├── useAuth.ts
    │   │   └── useUsers.ts
    │   └── services/
    │       ├── authService.ts
    │       └── userService.ts
    ├── superadmin/                        # Couche superadmin central
    │   ├── components/
    │   │   └── LoginForm.tsx
    │   ├── hooks/
    │   │   └── useAuth.ts
    │   └── services/
    │       └── authService.ts
    ├── frontend/                          # Couche frontend public
    │   └── (vide pour l'instant)
    ├── types/                             # Types TypeScript partagés
    │   ├── auth.types.ts
    │   └── user.types.ts
    └── translations/                      # i18n
        ├── fr.json
        └── ar.json
```

---

## 4. Module UsersGuard (référence)

Le module **UsersGuard** est le seul module opérationnel et sert de **référence architecturale** pour tous les futurs modules.

### 4.1 Fonctionnalités

#### 4.1.1 Authentification

**SuperAdmin (central)** :
- Login via username/password
- Base centrale `mysql`
- Routes `/api/superadmin/auth/*`
- Guard `superadmin`

**Admin & Frontend (tenant)** :
- Login via username/password + application
- Base tenant dynamique
- Routes `/api/admin/auth/*` ou `/api/frontend/auth/*`
- Guard `tenant`
- Header requis : `X-Tenant-ID`

#### 4.1.2 Multi-tenancy

- Isolation complète des données par tenant
- Base de données dédiée par tenant : `tenant_{id}`
- Détection du tenant via :
  - Header `X-Tenant-ID` (API)
  - Domaine (future implémentation)

#### 4.1.3 Permissions (Spatie)

- Gestion des rôles et permissions
- Scope tenant uniquement
- Tables : `roles`, `permissions`, `model_has_permissions`, `model_has_roles`

### 4.2 Models clés

#### 4.2.1 SuperAdmin (base centrale)

**Fichier** : `C:\laragon\www\crm-api\Modules\UsersGuard\Entities\SuperAdmin.php`

```php
class SuperAdmin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';
    protected $connection = 'mysql';  // Base CENTRALE
    protected $guard = 'superadmin';

    protected $fillable = [
        'username', 'email', 'password',
        'firstname', 'lastname', 'application',
        'is_active', 'sex', 'phone', 'mobile',
    ];

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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSuperadmin($query)
    {
        return $query->where('application', 'superadmin');
    }
}
```

#### 4.2.2 TenantUser (base tenant)

**Fichier** : `C:\laragon\www\crm-api\Modules\UsersGuard\Entities\TenantUser.php`

```php
class TenantUser extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $table = 'users';
    protected $connection = 'tenant';  // Base TENANT (dynamique)
    protected $guard_name = 'tenant';  // Pour Spatie Permissions

    protected $fillable = [
        'username', 'email', 'password',
        'firstname', 'lastname', 'application',
        'is_active', 'sex', 'phone', 'mobile',
        'avatar', 'address', 'city', 'country', 'postal_code',
    ];

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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAdmin($query)
    {
        return $query->where('application', 'admin');
    }

    public function scopeFrontend($query)
    {
        return $query->where('application', 'frontend');
    }
}
```

#### 4.2.3 Tenant (base centrale)

**Fichier** : `C:\laragon\www\crm-api\Modules\UsersGuard\Entities\Tenant.php`

```php
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    protected $table = 'tenants';
    protected $connection = 'mysql';  // Base CENTRALE

    protected $fillable = ['id', 'data'];

    public static function getCustomColumns(): array
    {
        return [
            'id', 'site_id', 'site_host', 'site_db_name',
            'company_name', 'company_email', 'company_phone',
            'is_active', 'settings', 'trial_ends_at',
        ];
    }

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function getDatabaseName(): string
    {
        return 'tenant_' . $this->id;
    }
}
```

### 4.3 Schéma de base de données

#### 4.3.1 Base centrale (mysql)

**Tables** :

```sql
-- users (SuperAdmin)
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(255) NULL,
    lastname VARCHAR(255) NULL,
    application ENUM('superadmin') DEFAULT 'superadmin',
    is_active BOOLEAN DEFAULT 1,
    phone VARCHAR(255) NULL,
    mobile VARCHAR(255) NULL,
    sex ENUM('M', 'F', 'O') NULL,
    lastlogin TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_application_active (application, is_active)
);

-- tenants
CREATE TABLE tenants (
    id VARCHAR(255) PRIMARY KEY,
    data JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- domains
CREATE TABLE domains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) UNIQUE NOT NULL,
    tenant_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

#### 4.3.2 Base tenant (tenant_{id})

**Tables** :

```sql
-- users (Admin & Frontend)
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(255) NULL,
    lastname VARCHAR(255) NULL,
    application ENUM('admin', 'frontend') DEFAULT 'frontend',
    is_active BOOLEAN DEFAULT 1,
    phone VARCHAR(255) NULL,
    mobile VARCHAR(255) NULL,
    sex ENUM('M', 'F', 'O') NULL,
    avatar VARCHAR(255) NULL,
    address TEXT NULL,
    city VARCHAR(255) NULL,
    country VARCHAR(255) NULL,
    postal_code VARCHAR(255) NULL,
    lastlogin TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_application_active (application, is_active)
);

-- roles (Spatie)
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY roles_name_guard_name_unique (name, guard_name)
);

-- permissions (Spatie)
CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)
);

-- model_has_permissions
-- model_has_roles
-- role_has_permissions
-- (tables Spatie standard)
```

### 4.4 Controllers clés

#### 4.4.1 Admin AuthController

**Fichier** : `C:\laragon\www\crm-api\Modules\UsersGuard\Http\Controllers\Admin\AuthController.php`

**Endpoints** :
- `POST /api/admin/auth/login` : Login tenant (admin/frontend)
- `GET /api/admin/auth/me` : Utilisateur connecté
- `POST /api/admin/auth/logout` : Déconnexion
- `POST /api/admin/auth/refresh` : Rafraîchir le token

**Exemple de code** :

```php
public function login(TenantLoginRequest $request): JsonResponse
{
    $validated = $request->validated();

    $user = TenantUser::where('username', $validated['username'])
        ->where('application', $validated['application'])
        ->active()
        ->first();

    if (!$user || !$this->checkPassword($validated['password'], $user->password)) {
        throw ValidationException::withMessages([
            'username' => ['Invalid credentials'],
        ]);
    }

    $token = $user->createToken('tenant-token', [
        'role:' . $validated['application'],
        'tenant:' . tenancy()->tenant->getTenantKey(),
    ])->plainTextToken;

    $user->load(['roles.permissions', 'permissions']);
    $user->updateLastLogin();

    return response()->json([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'user' => [...],
            'token' => $token,
            'token_type' => 'Bearer',
            'tenant' => [...],
        ],
    ]);
}
```

### 4.5 Routes

#### 4.5.1 Routes Admin

**Fichier** : `C:\laragon\www\crm-api\Modules\UsersGuard\Routes\admin.php`

```php
// Routes publiques (tenant context requis)
Route::prefix('admin')->middleware(['tenant'])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Routes protégées (tenant + auth)
Route::prefix('admin')->middleware(['tenant', 'tenant.auth'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
    });
});
```

#### 4.5.2 Routes Superadmin

**Fichier** : `C:\laragon\www\crm-api\Modules\UsersGuard\Routes\superadmin.php`

```php
// Routes publiques
Route::prefix('superadmin')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
});

// Routes protégées
Route::prefix('superadmin')->middleware(['auth:sanctum'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::prefix('tenants')->group(function () {
        Route::get('/', [TenantController::class, 'index']);
        Route::post('/', [TenantController::class, 'store']);
        Route::get('/{tenant}', [TenantController::class, 'show']);
        Route::put('/{tenant}', [TenantController::class, 'update']);
        Route::delete('/{tenant}', [TenantController::class, 'destroy']);
    });
});
```

### 4.6 Frontend (Next.js)

#### 4.6.1 Service d'authentification

**Fichier** : `C:\Users\Mounkaila\PhpstormProjects\icall26-front\src\modules\UsersGuard\admin\services\authService.ts`

```typescript
class AdminAuthService {
    async login(credentials: LoginCredentials): Promise<LoginResponse> {
        const client = createApiClient();

        const response = await client.post<LoginResponse>(
            '/admin/auth/login',
            {
                username: credentials.username,
                password: credentials.password,
                application: credentials.application,
            }
        );

        if (response.data.success && response.data.data.token) {
            localStorage.setItem('auth_token', response.data.data.token);
            localStorage.setItem('user', JSON.stringify(response.data.data.user));
            localStorage.setItem('tenant', JSON.stringify(response.data.data.tenant));
        }

        return response.data;
    }

    async logout(): Promise<void> {
        const client = createApiClient();
        try {
            await client.post('/admin/auth/logout');
        } finally {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            localStorage.removeItem('tenant');
        }
    }
}
```

#### 4.6.2 Hook d'authentification

**Fichier** : `C:\Users\Mounkaila\PhpstormProjects\icall26-front\src\modules\UsersGuard\admin\hooks\useAuth.ts`

```typescript
export const useAuth = (): UseAuthReturn => {
    const router = useRouter();
    const [state, setState] = useState<AuthState>({
        user: null,
        token: null,
        tenant: null,
        isAuthenticated: false,
        isLoading: true,
    });

    const login = useCallback(async (credentials: LoginCredentials) => {
        const response = await adminAuthService.login(credentials);

        if (response.success) {
            setState({
                user: response.data.user,
                token: response.data.token,
                tenant: response.data.tenant,
                isAuthenticated: true,
                isLoading: false,
            });

            router.push('/admin/users');
        }
    }, [router]);

    const logout = useCallback(async () => {
        await adminAuthService.logout();
        setState({
            user: null,
            token: null,
            tenant: null,
            isAuthenticated: false,
            isLoading: false,
        });
        router.push('/login');
    }, [router]);

    return { ...state, login, logout };
};
```

#### 4.6.3 Types TypeScript

**Fichier** : `C:\Users\Mounkaila\PhpstormProjects\icall26-front\src\modules\UsersGuard\types\auth.types.ts`

```typescript
export interface LoginCredentials {
  username: string;
  password: string;
  application: 'admin' | 'frontend';
}

export interface LoginResponse {
  success: boolean;
  message: string;
  data: {
    user: User;
    token: string;
    token_type: string;
    tenant: Tenant;
  };
}

export interface User {
  id: number;
  username: string;
  email: string;
  firstname?: string;
  lastname?: string;
  application: 'admin' | 'frontend' | 'superadmin';
  groups?: Group[];
  permissions?: Permission[];
}
```

---

## 5. Architecture Multi-Tenant

### 5.1 Principe (stancl/tenancy)

Le système utilise **stancl/tenancy** pour gérer le multi-tenancy avec **isolation complète des bases de données**.

**Caractéristiques** :
- Une base de données centrale (`mysql`)
- Une base de données par tenant (`tenant_{id}`)
- Isolation totale des données
- Connexion dynamique selon le contexte

### 5.2 Configuration

**Fichier** : `C:\laragon\www\crm-api\config\tenancy.php`

```php
return [
    'tenant_model' => \Modules\UsersGuard\Entities\Tenant::class,
    'domain_model' => \Modules\UsersGuard\Entities\Domain::class,

    'database' => [
        'central_connection' => 'mysql',
        'prefix' => 'tenant_',  // tenant_tenant1, tenant_tenant2...
        'suffix' => '',
    ],

    'migration_parameters' => [
        '--force' => true,
        '--path' => collect(glob(base_path('Modules/*/Database/Migrations/tenant')))
            ->filter(fn ($path) => is_dir($path))
            ->values()
            ->toArray(),
        '--realpath' => true,
    ],
];
```

### 5.3 Middleware

**Fichier** : `C:\laragon\www\crm-api\bootstrap\app.php`

```php
$middleware->alias([
    'tenant' => \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
    'tenant.header' => \Stancl\Tenancy\Middleware\InitializeTenancyByRequestData::class,
    'tenant.auth' => \App\Http\Middleware\TenantSanctumAuth::class,
]);
```

**Middleware personnalisé** : `C:\laragon\www\crm-api\app\Http\Middleware\TenantSanctumAuth.php`

```php
class TenantSanctumAuth
{
    public function handle(Request $request, Closure $next, string $guard = 'tenant'): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        [$id, $plainTextToken] = explode('|', $token, 2);

        // Chercher le token dans la base TENANT
        $accessToken = PersonalAccessToken::on('tenant')
            ->where('id', $id)
            ->first();

        if (!$accessToken || !hash_equals($accessToken->token, hash('sha256', $plainTextToken))) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $accessToken->tokenable;
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
```

### 5.4 Flux de requête tenant

```
1. Client HTTP
   ↓
   Header: X-Tenant-ID: tenant1
   Header: Authorization: Bearer {token}
   ↓
2. Middleware 'tenant'
   ↓
   Initialise le tenant (tenant1)
   Connexion à la base tenant_tenant1
   ↓
3. Middleware 'tenant.auth'
   ↓
   Vérifie le token dans tenant_tenant1
   Authentifie l'utilisateur
   ↓
4. Controller
   ↓
   Accède aux données de tenant_tenant1
   ↓
5. Response JSON
```

---

## 6. Authentification et Autorisations

### 6.1 Guards configurés

**Fichier** : `C:\laragon\www\crm-api\config\auth.php`

```php
'guards' => [
    // Super Admin Guard (CENTRAL database)
    'superadmin' => [
        'driver' => 'sanctum',
        'provider' => 'superadmins',
    ],

    // Tenant Admin & Frontend Guard (TENANT database)
    'tenant' => [
        'driver' => 'sanctum',
        'provider' => 'tenants',
    ],
],

'providers' => [
    // Super Admin Provider (CENTRAL database)
    'superadmins' => [
        'driver' => 'eloquent',
        'model' => Modules\UsersGuard\Entities\SuperAdmin::class,
    ],

    // Tenant Users Provider (TENANT database)
    'tenants' => [
        'driver' => 'eloquent',
        'model' => Modules\UsersGuard\Entities\TenantUser::class,
    ],
],
```

### 6.2 Trois niveaux d'utilisateurs

| Niveau | Base | Guard | Routes | Middleware |
|--------|------|-------|--------|------------|
| **SuperAdmin** | mysql (centrale) | superadmin | /api/superadmin/* | auth:sanctum |
| **Admin Tenant** | tenant_{id} | tenant | /api/admin/* | tenant + tenant.auth |
| **Frontend Tenant** | tenant_{id} | tenant | /api/frontend/* | tenant + tenant.auth |

### 6.3 Authentification Sanctum

**Flux de login** :

```php
// 1. Requête de login
POST /api/admin/auth/login
Body: { username, password, application }
Header: X-Tenant-ID: tenant1

// 2. Vérification credentials
$user = TenantUser::where('username', $username)
    ->where('application', $application)
    ->first();

// 3. Création du token
$token = $user->createToken('tenant-token')->plainTextToken;

// 4. Réponse
{
    "success": true,
    "data": {
        "user": {...},
        "token": "1|xyz...",
        "tenant": {...}
    }
}
```

**Requêtes authentifiées** :

```
GET /api/admin/users
Header: Authorization: Bearer 1|xyz...
Header: X-Tenant-ID: tenant1
```

### 6.4 Permissions (Spatie)

**Configuration** : Scope tenant uniquement

```php
class TenantUser extends Authenticatable
{
    use HasRoles;

    protected $guard_name = 'tenant';  // IMPORTANT pour Spatie
}
```

**Utilisation** :

```php
// Assignation
$user->assignRole('Admin');
$user->givePermissionTo('edit users');

// Vérification
if ($user->can('edit users')) {
    // ...
}

// Dans les routes
Route::middleware(['permission:edit users'])->group(function () {
    // ...
});
```

---

## 7. Base de données

### 7.1 Connexions

**Fichier** : `C:\laragon\www\crm-api\config\database.php`

```php
'connections' => [
    // Base CENTRALE
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'database' => env('DB_DATABASE', 'crm_central'),
        // ...
    ],

    // Base TENANT (dynamique)
    'tenant' => [
        'driver' => 'mysql',
        // Database dynamique configurée par stancl/tenancy
    ],
],
```

### 7.2 Migrations

#### 7.2.1 Migrations centrales

**Emplacement** : `Modules/*/Database/Migrations/`

**Exécution** :
```bash
php artisan module:migrate UsersGuard
```

**Exemple** :

```php
// CENTRALE - Schema::connection('mysql')
public function up(): void
{
    Schema::connection('mysql')->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('username')->unique();
        // ...
    });
}
```

#### 7.2.2 Migrations tenant

**Emplacement** : `Modules/*/Database/Migrations/tenant/`

**Exécution** :
```bash
php artisan tenants:migrate
php artisan tenants:migrate --tenants=tenant1
```

**Exemple** :

```php
// TENANT - Schema::create() (sans connection)
public function up(): void
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('username')->unique();
        // ...
    });
}
```

### 7.3 Relations Eloquent

**Exemple avec eager loading** :

```php
// ❌ N+1 problème
$users = TenantUser::all();
foreach ($users as $user) {
    echo $user->roles;  // Query par utilisateur
}

// ✅ Eager loading
$users = TenantUser::with('roles.permissions')->get();
foreach ($users as $user) {
    echo $user->roles;  // Déjà chargé
}
```

---

## 8. API REST et Routes

### 8.1 Structure des URLs

```
/api/{niveau}/{module}/{ressource}
```

**Exemples** :

```
GET    /api/admin/users              # Liste utilisateurs tenant
POST   /api/admin/users              # Créer utilisateur
GET    /api/admin/users/1            # Détail utilisateur
PUT    /api/admin/users/1            # Modifier utilisateur
DELETE /api/admin/users/1            # Supprimer utilisateur

GET    /api/superadmin/tenants       # Liste tenants
POST   /api/superadmin/tenants       # Créer tenant
```

### 8.2 Paramètres de requête standards

**Pagination** :
```
GET /api/admin/users?per_page=15&page=2
```

**Recherche** :
```
GET /api/admin/users?search=john
```

**Filtres** :
```
GET /api/admin/users?is_active=1&application=admin
```

**Tri** :
```
GET /api/admin/users?sort_by=created_at&sort_order=desc
```

### 8.3 Réponses JSON standardisées

**Liste (pagination)** :

```json
{
    "data": [
        { "id": 1, "username": "user1" },
        { "id": 2, "username": "user2" }
    ],
    "links": {
        "first": "http://api.com/users?page=1",
        "last": "http://api.com/users?page=5",
        "prev": null,
        "next": "http://api.com/users?page=2"
    },
    "meta": {
        "current_page": 1,
        "total": 50,
        "per_page": 15
    }
}
```

**Création/Modification** :

```json
{
    "message": "Créé avec succès.",
    "data": {
        "id": 1,
        "username": "newuser"
    }
}
```

**Erreur de validation** :

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "username": ["Le username est obligatoire."],
        "email": ["L'email doit être valide."]
    }
}
```

### 8.4 Codes HTTP utilisés

| Action | Code | Usage |
|--------|------|-------|
| GET | 200 | Succès |
| POST | 201 | Ressource créée |
| PUT/PATCH | 200 | Ressource modifiée |
| DELETE | 200 | Ressource supprimée |
| Validation | 422 | Données invalides |
| Non trouvé | 404 | Ressource inexistante |
| Non authentifié | 401 | Token manquant/invalide |
| Interdit | 403 | Pas les droits |

---

## 9. Conventions de code

### 9.1 Backend (Laravel)

#### 9.1.1 Nommage

| Élément | Convention | Exemple |
|---------|-----------|---------|
| Module | PascalCase | UsersGuard |
| Alias module | lowercase | usersguard |
| Model | PascalCase singular | TenantUser, SuperAdmin |
| Table | snake_case plural | users, tenants |
| Controller | PascalCase + Controller | AuthController |
| Request | PascalCase + Request | TenantLoginRequest |
| Resource | PascalCase + Resource | UserResource |
| Migration | snake_case | create_users_table |

#### 9.1.2 Structure des Controllers

```php
class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = TenantUser::query()
            ->when($request->search, fn($q) => $q->where('username', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = TenantUser::create($request->validated());

        return response()->json([
            'message' => 'Créé avec succès.',
            'data' => new UserResource($user),
        ], 201);
    }
}
```

**Règles** :
- Type hints explicites sur toutes les méthodes
- Retourner des Resources, jamais des models bruts
- Utiliser `findOrFail()` au lieu de `find()`
- HTTP 201 pour `store()`

#### 9.1.3 Form Requests

```php
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Le username est obligatoire.',
            'email.email' => 'L\'email doit être valide.',
        ];
    }
}
```

**Règles** :
- Array syntax pour les règles de validation
- Messages en français
- `sometimes` pour UPDATE

#### 9.1.4 API Resources

```php
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'full_name' => $this->full_name,

            // Relations
            'roles' => RoleResource::collection($this->whenLoaded('roles')),

            // Dates ISO 8601
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

**Règles** :
- Utiliser `whenLoaded()` pour les relations
- Format ISO 8601 pour les dates
- Opérateur null-safe `?->`

#### 9.1.5 Models

```php
class TenantUser extends Authenticatable
{
    protected $connection = 'tenant';  // TOUJOURS déclarer
    protected $table = 'users';

    protected $fillable = ['username', 'email', 'password'];

    // Laravel 12 : casts() method
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relations avec type hints
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
```

**Règles** :
- Toujours déclarer `$connection` (mysql ou tenant)
- Utiliser `casts()` method (Laravel 12)
- Type hints sur les relations
- Utiliser `SoftDeletes` par défaut

### 9.2 Frontend (Next.js)

#### 9.2.1 Structure des modules

```
ModuleName/
├── index.ts                    # Barrel export
├── admin/
│   ├── components/
│   ├── hooks/
│   └── services/
├── superadmin/
│   ├── components/
│   ├── hooks/
│   └── services/
├── frontend/
│   ├── components/
│   ├── hooks/
│   └── services/
└── types/
    └── *.types.ts
```

#### 9.2.2 Services

```typescript
class UserService {
    async getUsers(tenantId?: string) {
        const client = createApiClient(tenantId);
        const response = await client.get('/admin/users');
        return response.data;
    }

    async createUser(data: CreateUserDto, tenantId?: string) {
        const client = createApiClient(tenantId);
        const response = await client.post('/admin/users', data);
        return response.data;
    }
}

export const userService = new UserService();
```

**Règles** :
- Classes singleton exportées
- Passer `tenantId` optionnel
- Utiliser `createApiClient()`

#### 9.2.3 Hooks

```typescript
'use client';

export const useUsers = () => {
    const { tenantId } = useTenant();
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchUsers = async () => {
            try {
                const data = await userService.getUsers(tenantId || undefined);
                setUsers(data);
            } catch (error) {
                console.error(error);
            } finally {
                setLoading(false);
            }
        };

        fetchUsers();
    }, [tenantId]);

    return { users, loading };
};
```

**Règles** :
- Directive `'use client'` pour les hooks
- Utiliser `useTenant()` pour le context multi-tenant
- Gérer les états loading/error

#### 9.2.4 Types TypeScript

```typescript
export interface User {
  id: number;
  username: string;
  email: string;
  firstname?: string;
  lastname?: string;
}

export interface LoginCredentials {
  username: string;
  password: string;
  application: 'admin' | 'frontend';
}
```

**Règles** :
- Miroir des types backend
- Utiliser les unions pour les enums
- Optionalité avec `?`

---

## 10. Patterns établis

### 10.1 API REST avec Form Requests

**Toujours** créer des Form Requests pour la validation :

```php
// Controller
public function store(StoreUserRequest $request): JsonResponse
{
    $user = TenantUser::create($request->validated());
    return response()->json([...], 201);
}

// Request
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
        ];
    }
}
```

### 10.2 API Resources pour réponses JSON

**Jamais** retourner de models bruts :

```php
// ❌ Non
return response()->json(['data' => $user]);

// ✅ Oui
return response()->json(['data' => new UserResource($user)]);

// ✅ Collections
return UserResource::collection($users);
```

### 10.3 Multi-tenancy via header

**Frontend** : Ajouter automatiquement le header

```typescript
const client = createApiClient(tenantId);  // Ajoute X-Tenant-ID
```

**Backend** : Vérifier le middleware

```php
Route::prefix('admin')->middleware(['tenant', 'tenant.auth'])->group(...);
```

### 10.4 Gestion des permissions

```php
// Modèle
use HasRoles;
protected $guard_name = 'tenant';

// Assignation
$user->assignRole('Admin');
$user->givePermissionTo('edit users');

// Vérification
if ($user->can('edit users')) { ... }
```

### 10.5 Soft Deletes

**Toujours** utiliser `SoftDeletes` :

```php
use SoftDeletes;

// Migration
$table->softDeletes();

// Routes
Route::delete('/{id}', 'destroy');           // Soft delete
Route::post('/{id}/restore', 'restore');      // Restore
Route::delete('/{id}/force', 'forceDelete');  // Hard delete
```

---

## 11. Infrastructure technique

### 11.1 Configuration environnement

**Backend** : `.env`

```env
APP_NAME=CRM-API
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm_central
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

**Frontend** : `.env.local`

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

### 11.2 Structure de routes

**Backend** : Les routes sont chargées via les ServiceProviders des modules

```php
// Module ServiceProvider
public function boot(): void
{
    $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/admin.php'));
    $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/frontend.php'));
    $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/superadmin.php'));
}
```

### 11.3 Commandes artisan utiles

```bash
# Modules
php artisan module:list
php artisan module:make ModuleName
php artisan module:migrate ModuleName

# Tenants
php artisan tenants:migrate
php artisan tenants:migrate --tenants=tenant1

# Code quality
vendor/bin/pint --dirty
```

### 11.4 Tests

**Emplacement** : `Modules/{Module}/Tests/`

```php
class UserTest extends TestCase
{
    use RefreshDatabase;

    public function testCanCreateUser(): void
    {
        $response = $this->postJson('/api/admin/users', [
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['username' => 'testuser']);
    }
}
```

**Exécution** :

```bash
php artisan test --filter=testCanCreateUser
```

---

## 12. Points d'attention pour nouveaux modules

### 12.1 Checklist de création

Lors de la création d'un nouveau module, s'inspirer de **UsersGuard** :

- [ ] Créer la structure complète du module
- [ ] Définir les models avec `$connection` appropriée
- [ ] Créer les migrations (centrale ET/OU tenant)
- [ ] Créer les Form Requests pour validation
- [ ] Créer les API Resources
- [ ] Créer les Controllers (Admin/Frontend/Superadmin)
- [ ] Définir les routes avec les bons middleware
- [ ] Implémenter les services frontend
- [ ] Créer les hooks React
- [ ] Définir les types TypeScript
- [ ] Tester les endpoints

### 12.2 Patterns à respecter

**Backend** :
- Connexion explicite sur les models (`mysql` ou `tenant`)
- Form Requests pour toute validation
- API Resources pour toutes les réponses
- Type hints sur toutes les méthodes
- Middleware `['tenant', 'tenant.auth']` pour routes admin/frontend

**Frontend** :
- Structure en 3 couches (admin/superadmin/frontend)
- Services avec `createApiClient(tenantId)`
- Hooks avec `useTenant()` pour le contexte
- Types miroir du backend
- Barrel exports dans `index.ts`

### 12.3 Documentation de référence

**Référence principale** : Module UsersGuard
- Backend : `C:\laragon\www\crm-api\Modules\UsersGuard\`
- Frontend : `C:\Users\Mounkaila\PhpstormProjects\icall26-front\src\modules\UsersGuard\`

**Documentation complète** : `C:\laragon\www\crm-api\DOCUMENTATION_MODULES.md`

---

## Conclusion

Cette documentation décrit l'**état actuel** de l'architecture du système CRM modulaire multi-tenant. Le module **UsersGuard** est opérationnel et sert de **référence architecturale** pour tout développement futur.

**Principes clés à retenir** :
1. Architecture **Polyrepo** (backend Laravel + frontend Next.js séparés)
2. Structure **modulaire** avec nwidart/laravel-modules
3. **Multi-tenancy** avec isolation complète des bases de données
4. **Trois niveaux** d'utilisateurs (SuperAdmin, Admin, Frontend)
5. **Authentification Sanctum** via tokens Bearer
6. **Permissions Spatie** pour les tenants
7. **Conventions strictes** à suivre pour cohérence

**Pour aller plus loin** :
- Étudier le code source de UsersGuard (backend + frontend)
- Consulter `DOCUMENTATION_MODULES.md` pour guide de création
- Respecter les patterns établis dans UsersGuard

---

**Version** : 1.0
**Date** : 2026-01-07
**Auteur** : Documentation brownfield générée
**Statut** : Production-ready pour UsersGuard
