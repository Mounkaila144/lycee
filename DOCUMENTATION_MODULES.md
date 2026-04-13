# Documentation Technique - Système de Modules Laravel

> **Audience**: Documentation destinée aux assistants IA pour la création de modules dans ce système.
>
> **Objectif**: Comprendre l'architecture modulaire, les conventions et créer des modules cohérents et fonctionnels.

---

## Table des matières

1. [Architecture du système](#1-architecture-du-système)
2. [Structure d'un module](#2-structure-dun-module)
3. [Guide de création étape par étape](#3-guide-de-création-étape-par-étape)
4. [Conventions et standards](#4-conventions-et-standards)
5. [Multi-tenancy](#5-multi-tenancy)
6. [Authentification et autorisations](#6-authentification-et-autorisations)
7. [Base de données](#7-base-de-données)
8. [API et Routes](#8-api-et-routes)
9. [Tests](#9-tests)
10. [Commandes utiles](#10-commandes-utiles)

---

## 1. Architecture du système

### 1.1 Stack technique

```yaml
PHP: 8.3.26
Laravel: 12.42.0
Database: MySQL
Packages principaux:
  - nwidart/laravel-modules: Gestion modulaire
  - stancl/tenancy: Multi-tenancy
  - spatie/laravel-permission: Gestion des permissions
  - laravel/sanctum: Authentification API
```

### 1.2 Organisation modulaire

Le projet utilise `nwidart/laravel-modules` pour organiser le code en modules autonomes.

**Emplacement**: `modules/`

**Structure de base**:
```
modules/
├── UsersGuard/          # Module existant (référence)
├── VotreModule/         # Nouveau module
└── ...
```

### 1.3 Architecture multi-tenant

Le système gère **deux types de bases de données**:

#### Base centrale (`mysql` connection)
- **Tables**: `users` (superadmin), `tenants`, `domains`
- **Usage**: Gestion des super administrateurs et des tenants
- **Migrations**: `modules/{Module}/Database/Migrations/`

#### Base tenant (`tenant` connection - dynamique)
- **Tables**: `users` (admin/frontend), `permissions`, `roles`, etc.
- **Usage**: Données isolées par tenant
- **Migrations**: `modules/{Module}/Database/Migrations/tenant/`

**Configuration**: `config/tenancy.php`
```php
'migration_parameters' => [
    '--path' => collect(glob(base_path('Modules/*/Database/Migrations/tenant')))
        ->filter(fn ($path) => is_dir($path))
        ->values()
        ->toArray(),
],
```

### 1.4 Trois niveaux d'utilisateurs

| Niveau | Base | Table | Application | Guard | Modèle | Routes |
|--------|------|-------|-------------|-------|--------|--------|
| Super Admin | `mysql` | `users` | `superadmin` | `superadmin` | `SuperAdmin` | `/api/superadmin/*` |
| Admin Tenant | `tenant` | `users` | `admin` | `tenant` | `TenantUser` | `/api/admin/*` |
| Frontend Tenant | `tenant` | `users` | `frontend` | `tenant` | `TenantUser` | `/api/frontend/*` |

---

## 2. Structure d'un module

### 2.1 Arborescence complète

```
modules/VotreModule/
├── module.json                    # ⚙️ Configuration du module
├── composer.json                  # 📦 Dépendances spécifiques (optionnel)
├── README.md                      # 📄 Documentation du module
│
├── Config/
│   └── config.php                 # ⚙️ Configuration
│
├── Database/
│   ├── Factories/                 # 🏭 Factories pour tests
│   │   └── ModelFactory.php
│   ├── Migrations/                # 🗄️ Migrations CENTRALES (mysql)
│   │   └── 2024_xx_xx_create_table.php
│   │   └── tenant/                # 🗄️ Migrations TENANT (tenant DB)
│   │       └── 2024_xx_xx_create_tenant_table.php
│   └── Seeders/                   # 🌱 Seeders
│       └── VotreModuleSeeder.php
│
├── Entities/                      # 🎯 Models Eloquent
│   └── VotreModel.php
│
├── Http/
│   ├── Controllers/               # 🎮 Controllers organisés par rôle
│   │   ├── Admin/
│   │   │   └── VotreController.php
│   │   ├── Frontend/
│   │   │   └── VotreController.php
│   │   └── Superadmin/
│   │       └── VotreController.php
│   ├── Requests/                  # ✅ Form Requests (validation)
│   │   ├── StoreVotreRequest.php
│   │   └── UpdateVotreRequest.php
│   └── Resources/                 # 📊 API Resources
│       └── VotreResource.php
│
├── Providers/
│   ├── VotreModuleServiceProvider.php  # 🔧 Service Provider principal
│   └── RouteServiceProvider.php        # 🛤️ Route Provider
│
├── Repositories/                  # 📚 Repositories (optionnel)
│   └── VotreRepository.php
│
├── Resources/
│   └── lang/                      # 🌍 Traductions
│       └── fr.json
│
└── Routes/
    ├── admin.php                  # 🛤️ Routes admin tenant
    ├── frontend.php               # 🛤️ Routes frontend tenant
    └── superadmin.php             # 🛤️ Routes super admin
```

### 2.2 Fichiers essentiels

#### `module.json`
```json
{
    "name": "VotreModule",
    "alias": "votremodule",
    "description": "Description claire du module",
    "keywords": [],
    "priority": 0,
    "providers": [
        "Modules\\VotreModule\\Providers\\VotreModuleServiceProvider"
    ],
    "files": []
}
```

**Règles**:
- `name`: PascalCase
- `alias`: lowercase
- `providers`: Tableau contenant le ServiceProvider principal

---

## 3. Guide de création étape par étape

### Étape 1: Créer le module

```bash
php artisan module:make VotreModule --no-interaction
```

**Résultat**: Structure de base créée dans `modules/VotreModule/`

---

### Étape 2: Configurer `module.json`

```json
{
    "name": "VotreModule",
    "alias": "votremodule",
    "description": "Gestion des [entités] pour le CRM",
    "keywords": [],
    "priority": 0,
    "providers": [
        "Modules\\VotreModule\\Providers\\VotreModuleServiceProvider"
    ],
    "files": []
}
```

---

### Étape 3: Créer le Service Provider

**Fichier**: `modules/VotreModule/Providers/VotreModuleServiceProvider.php`

```php
<?php

namespace Modules\VotreModule\Providers;

use Illuminate\Support\ServiceProvider;

class VotreModuleServiceProvider extends ServiceProvider
{
    protected $moduleName = 'VotreModule';
    protected $moduleNameLower = 'votremodule';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerConfig();

        // Charger les routes
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/admin.php'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/frontend.php'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/superadmin.php'));

        // Charger les migrations (optionnel, si pas géré par module:migrate)
        // $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
```

---

### Étape 4: Créer le RouteServiceProvider

**Fichier**: `modules/VotreModule/Providers/RouteServiceProvider.php`

```php
<?php

namespace Modules\VotreModule\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The module namespace to assume when generating URLs to actions.
     */
    protected $moduleNamespace = 'Modules\VotreModule\Http\Controllers';

    /**
     * Called before routes are registered.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
    }

    /**
     * Define the "api" routes for the application.
     */
    protected function mapApiRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('VotreModule', '/Routes/admin.php'));

        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('VotreModule', '/Routes/frontend.php'));

        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('VotreModule', '/Routes/superadmin.php'));
    }
}
```

---

### Étape 5: Créer les modèles (Entities)

```bash
php artisan module:make-model VotreModel VotreModule --no-interaction
```

**Fichier**: `modules/VotreModule/Entities/VotreModel.php`

#### Modèle pour base CENTRALE

```php
<?php

namespace Modules\VotreModule\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VotreModel extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Table dans la base CENTRALE
     */
    protected $table = 'votre_table';

    /**
     * Connexion CENTRALE (mysql)
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'nom',
        'description',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Scope to get only active records
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if record is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
```

#### Modèle pour base TENANT

```php
<?php

namespace Modules\VotreModule\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VotreModelTenant extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Table dans la base TENANT
     */
    protected $table = 'votre_table';

    /**
     * Connexion TENANT (dynamique)
     */
    protected $connection = 'tenant';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'nom',
        'description',
        'is_active',
        'tenant_id',  // Si nécessaire
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Scope to get only active records
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if record is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
```

**Règles importantes**:
- ✅ Toujours utiliser `protected function casts(): array` (Laravel 12)
- ✅ Utiliser `SoftDeletes` pour les suppressions logiques
- ✅ Déclarer explicitement `$connection` (`mysql` ou `tenant`)
- ✅ Utiliser `$fillable` (jamais `$guarded`)
- ✅ Type hints explicites sur toutes les méthodes

---

### Étape 6: Créer les migrations

#### Migration CENTRALE

```bash
php artisan module:make-migration create_votre_table VotreModule --no-interaction
```

**Fichier**: `modules/VotreModule/Database/Migrations/2024_xx_xx_create_votre_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * BASE CENTRALE (mysql)
     */
    public function up(): void
    {
        Schema::connection('mysql')->create('votre_table', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('nom');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('votre_table');
    }
};
```

#### Migration TENANT

```bash
php artisan module:make-migration create_votre_table_tenant VotreModule --no-interaction
```

**Ensuite**: Déplacer le fichier dans `modules/VotreModule/Database/Migrations/tenant/`

**Fichier**: `modules/VotreModule/Database/Migrations/tenant/2024_xx_xx_create_votre_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * BASE TENANT (tenant - dynamique)
     */
    public function up(): void
    {
        Schema::create('votre_table', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('nom');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votre_table');
    }
};
```

**Notes importantes**:
- ⚠️ Migrations centrales: utiliser `Schema::connection('mysql')`
- ⚠️ Migrations tenant: utiliser `Schema::create()` (sans connection explicite)
- ⚠️ **Toujours** créer les index pour les champs de recherche/filtrage
- ⚠️ Utiliser `softDeletes()` par défaut

---

### Étape 7: Créer les Form Requests

```bash
php artisan module:make-request StoreVotreModelRequest VotreModule --no-interaction
php artisan module:make-request UpdateVotreModelRequest VotreModule --no-interaction
```

#### StoreVotreModelRequest

**Fichier**: `modules/VotreModule/Http/Requests/StoreVotreModelRequest.php`

```php
<?php

namespace Modules\VotreModule\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVotreModelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;  // Ou vérifier les permissions
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
        ];
    }
}
```

#### UpdateVotreModelRequest

**Fichier**: `modules/VotreModule/Http/Requests/UpdateVotreModelRequest.php`

```php
<?php

namespace Modules\VotreModule\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVotreModelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nom' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
        ];
    }
}
```

**Règles**:
- ✅ Validation en **array syntax** (Laravel 12)
- ✅ Méthode `authorize()` retournant un booléen
- ✅ Messages en français
- ✅ `sometimes` pour les champs optionnels en UPDATE

---

### Étape 8: Créer les API Resources

```bash
php artisan module:make-resource VotreModelResource VotreModule --no-interaction
```

**Fichier**: `modules/VotreModule/Http/Resources/VotreModelResource.php`

```php
<?php

namespace Modules\VotreModule\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VotreModelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => $this->description,
            'is_active' => $this->is_active,

            // Relations (si présentes)
            // 'relation' => new RelationResource($this->whenLoaded('relation')),

            // Dates au format ISO 8601
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
```

**Règles**:
- ✅ Utiliser `toIso8601String()` pour les dates
- ✅ Utiliser `whenLoaded()` pour les relations
- ✅ Utiliser l'opérateur `?->` (null-safe)

---

### Étape 9: Créer les Controllers

```bash
php artisan module:make-controller Admin/VotreModelController VotreModule --no-interaction
php artisan module:make-controller Frontend/VotreModelController VotreModule --no-interaction
php artisan module:make-controller Superadmin/VotreModelController VotreModule --no-interaction
```

#### Controller Admin (exemple complet)

**Fichier**: `modules/VotreModule/Http/Controllers/Admin/VotreModelController.php`

```php
<?php

namespace Modules\VotreModule\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\VotreModule\Entities\VotreModelTenant;
use Modules\VotreModule\Http\Requests\StoreVotreModelRequest;
use Modules\VotreModule\Http\Requests\UpdateVotreModelRequest;
use Modules\VotreModule\Http\Resources\VotreModelResource;

class VotreModelController extends Controller
{
    /**
     * Display a listing of the resources.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $isActive = $request->input('is_active');

        $query = VotreModelTenant::query();

        // Recherche
        if ($search) {
            $query->where('nom', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        // Filtres
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        $models = $query->latest()->paginate($perPage);

        return VotreModelResource::collection($models);
    }

    /**
     * Store a newly created resource.
     */
    public function store(StoreVotreModelRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $model = VotreModelTenant::create($data);

        return response()->json([
            'message' => 'Créé avec succès.',
            'data' => new VotreModelResource($model),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $model = VotreModelTenant::findOrFail($id);

        return response()->json([
            'data' => new VotreModelResource($model),
        ]);
    }

    /**
     * Update the specified resource.
     */
    public function update(UpdateVotreModelRequest $request, string $id): JsonResponse
    {
        $model = VotreModelTenant::findOrFail($id);
        $model->update($request->validated());

        return response()->json([
            'message' => 'Modifié avec succès.',
            'data' => new VotreModelResource($model),
        ]);
    }

    /**
     * Remove the specified resource (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $model = VotreModelTenant::findOrFail($id);
        $model->delete();

        return response()->json([
            'message' => 'Supprimé avec succès.',
        ]);
    }

    /**
     * Restore a soft deleted resource.
     */
    public function restore(string $id): JsonResponse
    {
        $model = VotreModelTenant::onlyTrashed()->findOrFail($id);
        $model->restore();

        return response()->json([
            'message' => 'Restauré avec succès.',
            'data' => new VotreModelResource($model),
        ]);
    }

    /**
     * Permanently delete a resource.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $model = VotreModelTenant::withTrashed()->findOrFail($id);
        $model->forceDelete();

        return response()->json([
            'message' => 'Supprimé définitivement.',
        ]);
    }
}
```

**Règles**:
- ✅ Type hints explicites (`JsonResponse`, `AnonymousResourceCollection`)
- ✅ Utiliser `findOrFail()` (jamais `find()`)
- ✅ Retourner toujours des Resources, jamais des models bruts
- ✅ HTTP 201 pour `store()`
- ✅ Messages en français

---

### Étape 10: Définir les routes

#### Routes Admin

**Fichier**: `modules/VotreModule/Routes/admin.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\VotreModule\Http\Controllers\Admin\VotreModelController;

// Routes protégées (tenant + auth)
Route::prefix('admin')->middleware(['tenant', 'tenant.auth'])->group(function () {
    Route::prefix('votremodule')->group(function () {
        // CRUD standard
        Route::get('/', [VotreModelController::class, 'index']);
        Route::post('/', [VotreModelController::class, 'store']);
        Route::get('/{id}', [VotreModelController::class, 'show']);
        Route::put('/{id}', [VotreModelController::class, 'update']);
        Route::delete('/{id}', [VotreModelController::class, 'destroy']);

        // Restore & Force Delete
        Route::post('/{id}/restore', [VotreModelController::class, 'restore']);
        Route::delete('/{id}/force', [VotreModelController::class, 'forceDelete']);
    });
});
```

#### Routes Frontend

**Fichier**: `modules/VotreModule/Routes/frontend.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\VotreModule\Http\Controllers\Frontend\VotreModelController;

// Routes protégées (tenant + auth)
Route::prefix('frontend')->middleware(['tenant', 'tenant.auth'])->group(function () {
    Route::prefix('votremodule')->group(function () {
        // Généralement uniquement lecture pour le frontend
        Route::get('/', [VotreModelController::class, 'index']);
        Route::get('/{id}', [VotreModelController::class, 'show']);
    });
});
```

#### Routes Superadmin

**Fichier**: `modules/VotreModule/Routes/superadmin.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\VotreModule\Http\Controllers\Superadmin\VotreModelController;

// Routes publiques (si nécessaire)
Route::prefix('superadmin')->middleware([])->group(function () {
    // Route::post('/auth/login', [AuthController::class, 'login']);
});

// Routes protégées (superadmin auth)
Route::prefix('superadmin')->middleware(['superadmin.auth'])->group(function () {
    Route::prefix('votremodule')->group(function () {
        Route::get('/', [VotreModelController::class, 'index']);
        Route::post('/', [VotreModelController::class, 'store']);
        Route::get('/{id}', [VotreModelController::class, 'show']);
        Route::put('/{id}', [VotreModelController::class, 'update']);
        Route::delete('/{id}', [VotreModelController::class, 'destroy']);
    });
});
```

**Middleware disponibles**:
- `tenant`: Initialise le tenant
- `tenant.auth`: Authentification tenant (admin/frontend)
- `superadmin.auth`: Authentification super admin

---

### Étape 11: Créer les Seeders

```bash
php artisan module:make-seed VotreModuleSeeder VotreModule --no-interaction
```

**Fichier**: `modules/VotreModule/Database/Seeders/VotreModuleSeeder.php`

```php
<?php

namespace Modules\VotreModule\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\VotreModule\Entities\VotreModel;

class VotreModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'nom' => 'Exemple 1',
                'description' => 'Description de l\'exemple 1',
                'is_active' => true,
            ],
            [
                'nom' => 'Exemple 2',
                'description' => 'Description de l\'exemple 2',
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            VotreModel::updateOrCreate(
                ['nom' => $item['nom']],
                $item
            );
        }

        $this->command->info('VotreModule seeded successfully.');
    }
}
```

**Règles**:
- ✅ Utiliser `updateOrCreate()` pour éviter les doublons
- ✅ Afficher un message de confirmation

---

### Étape 12: Créer les Factories

```bash
php artisan module:make-factory VotreModelFactory VotreModule --no-interaction
```

**Fichier**: `modules/VotreModule/Database/Factories/VotreModelFactory.php`

```php
<?php

namespace Modules\VotreModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\VotreModule\Entities\VotreModel;

class VotreModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = VotreModel::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->word(),
            'description' => fake()->sentence(),
            'is_active' => fake()->boolean(80), // 80% true
        ];
    }

    /**
     * Indicate that the model is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the model is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
```

**Règles**:
- ✅ Utiliser `fake()` (pas `$this->faker`)
- ✅ Créer des states pour les variations courantes
- ✅ Type hint `static` pour les states

---

### Étape 13: Exécuter les migrations

#### Migrations centrales

```bash
php artisan module:migrate VotreModule --no-interaction
```

#### Migrations tenant

```bash
# Pour tous les tenants
php artisan tenants:migrate

# Pour un tenant spécifique
php artisan tenants:migrate --tenants=tenant1
```

---

### Étape 14: Exécuter les seeders

```bash
php artisan module:seed VotreModule --no-interaction
```

---

### Étape 15: Créer les tests (optionnel)

```bash
php artisan module:make-test VotreModuleTest VotreModule --phpunit --no-interaction
```

**Fichier**: `modules/VotreModule/Tests/Feature/VotreModuleTest.php`

```php
<?php

namespace Modules\VotreModule\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VotreModule\Entities\VotreModel;

class VotreModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testCanListModels(): void
    {
        VotreModel::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/votremodule');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function testCanCreateModel(): void
    {
        $data = [
            'nom' => 'Test Model',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/votremodule', $data);

        $response->assertStatus(201);
        $response->assertJsonPath('data.nom', 'Test Model');
        $this->assertDatabaseHas('votre_table', ['nom' => 'Test Model']);
    }

    public function testCanUpdateModel(): void
    {
        $model = VotreModel::factory()->create();

        $response = $this->putJson("/api/admin/votremodule/{$model->id}", [
            'nom' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('votre_table', [
            'id' => $model->id,
            'nom' => 'Updated Name',
        ]);
    }

    public function testCanDeleteModel(): void
    {
        $model = VotreModel::factory()->create();

        $response = $this->deleteJson("/api/admin/votremodule/{$model->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('votre_table', ['id' => $model->id]);
    }
}
```

---

## 4. Conventions et standards

### 4.1 Nommage

| Élément | Convention | Exemple |
|---------|-----------|---------|
| Module | PascalCase | `UsersGuard`, `ProductCatalog` |
| Alias module | lowercase | `usersguard`, `productcatalog` |
| Model | PascalCase singular | `User`, `Product`, `TenantUser` |
| Table | snake_case plural | `users`, `products`, `tenant_users` |
| Controller | PascalCase + Controller | `UserController` |
| Request | PascalCase + Request | `StoreUserRequest` |
| Resource | PascalCase + Resource | `UserResource` |
| Factory | PascalCase + Factory | `UserFactory` |
| Seeder | PascalCase + Seeder | `UserSeeder` |

### 4.2 Structure des Controllers

**Organisation par rôle**:
```
Http/Controllers/
├── Admin/              # Gestion tenant (backoffice)
├── Frontend/           # Application tenant (utilisateurs finaux)
└── Superadmin/         # Gestion centrale (super admin)
```

### 4.3 Réponses JSON standardisées

#### Succès (liste)
```json
{
    "data": [
        { "id": 1, "nom": "Item 1" },
        { "id": 2, "nom": "Item 2" }
    ],
    "links": { ... },
    "meta": { ... }
}
```

#### Succès (création/modification)
```json
{
    "message": "Créé avec succès.",
    "data": {
        "id": 1,
        "nom": "Item 1"
    }
}
```

#### Succès (suppression)
```json
{
    "message": "Supprimé avec succès."
}
```

#### Erreur de validation
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "nom": ["Le nom est obligatoire."]
    }
}
```

### 4.4 Codes HTTP

| Action | Code | Cas |
|--------|------|-----|
| GET (liste/détail) | 200 | Succès |
| POST | 201 | Ressource créée |
| PUT/PATCH | 200 | Ressource modifiée |
| DELETE | 200 | Ressource supprimée |
| Erreur validation | 422 | Données invalides |
| Non trouvé | 404 | Ressource inexistante |
| Non autorisé | 401 | Non authentifié |
| Interdit | 403 | Non autorisé |

---

## 5. Multi-tenancy

### 5.1 Principe

Le système utilise `stancl/tenancy` pour isoler les données par tenant.

**Bases de données**:
1. **Base centrale (`mysql`)**: Contient les tenants, domaines et super admins
2. **Bases tenant (`tenant_{id}`)**: Une base par tenant, isolée

### 5.2 Configuration

**Fichier**: `config/tenancy.php`

```php
'tenant_model' => \Modules\UsersGuard\Entities\Tenant::class,
'domain_model' => \Modules\UsersGuard\Entities\Domain::class,

'database' => [
    'prefix' => 'tenant_',  // tenant_tenant1, tenant_tenant2, etc.
],

'migration_parameters' => [
    '--path' => collect(glob(base_path('Modules/*/Database/Migrations/tenant')))
        ->filter(fn ($path) => is_dir($path))
        ->values()
        ->toArray(),
],
```

### 5.3 Modèles tenant

**Toujours déclarer `$connection = 'tenant'`**:

```php
class VotreModelTenant extends Model
{
    protected $connection = 'tenant';  // ⚠️ OBLIGATOIRE
    protected $table = 'votre_table';
}
```

### 5.4 Migrations tenant

**Placer dans**: `modules/{Module}/Database/Migrations/tenant/`

**Ne pas** spécifier de connexion dans la migration:
```php
Schema::create('votre_table', function (Blueprint $table) {
    // ...
});
```

### 5.5 Middleware tenant

**Routes admin/frontend**: Toujours utiliser `['tenant', 'tenant.auth']`

```php
Route::prefix('admin')->middleware(['tenant', 'tenant.auth'])->group(function () {
    // ...
});
```

**Headers requis**:
```
X-Tenant-ID: tenant1
Authorization: Bearer {token}
```

---

## 6. Authentification et autorisations

### 6.1 Guards

| Guard | Usage | Model | Routes |
|-------|-------|-------|--------|
| `superadmin` | Super administrateurs | `SuperAdmin` | `/api/superadmin/*` |
| `tenant` | Admins/Frontend tenant | `TenantUser` | `/api/admin/*`, `/api/frontend/*` |

### 6.2 Authentification

Le système utilise **Laravel Sanctum** pour l'authentification API.

#### Login Super Admin
```php
POST /api/superadmin/auth/login
{
    "username": "admin",
    "password": "password"
}

Response:
{
    "access_token": "...",
    "token_type": "Bearer",
    "user": { ... }
}
```

#### Login Tenant
```php
POST /api/admin/auth/login
Headers: X-Tenant-ID: tenant1
{
    "username": "user@example.com",
    "password": "password",
    "application": "admin"  // ou "frontend"
}

Response:
{
    "access_token": "...",
    "token_type": "Bearer",
    "user": { ... }
}
```

### 6.3 Permissions (Spatie)

Le système utilise `spatie/laravel-permission` pour les permissions tenant.

#### Utilisation

```php
use Spatie\Permission\Traits\HasRoles;

class TenantUser extends Authenticatable
{
    use HasRoles;

    protected $guard_name = 'tenant';  // ⚠️ IMPORTANT
}
```

#### Assignation

```php
$user->assignRole('Admin');
$user->givePermissionTo('edit articles');
$user->syncPermissions(['edit articles', 'delete articles']);
```

#### Vérification

```php
// Dans un controller
if ($user->can('edit articles')) {
    // ...
}

// Dans une route
Route::middleware(['permission:edit articles'])->group(function () {
    // ...
});
```

---

## 7. Base de données

### 7.1 Connexions

| Connexion | Usage | Modèles |
|-----------|-------|---------|
| `mysql` | Base centrale | `SuperAdmin`, `Tenant`, `Domain` |
| `tenant` | Base tenant (dynamique) | `TenantUser`, et tous les modèles métier |

### 7.2 Types de colonnes courants

```php
Schema::create('table', function (Blueprint $table) {
    $table->id();
    $table->string('nom');                          // VARCHAR(255)
    $table->string('nom', 100);                     // VARCHAR(100)
    $table->text('description')->nullable();        // TEXT
    $table->boolean('is_active')->default(true);    // BOOLEAN
    $table->integer('quantity')->default(0);        // INT
    $table->decimal('price', 10, 2)->default(0);    // DECIMAL(10,2)
    $table->json('meta')->nullable();               // JSON
    $table->enum('status', ['active', 'inactive']); // ENUM
    $table->timestamp('lastlogin')->nullable();     // TIMESTAMP
    $table->timestamps();                           // created_at, updated_at
    $table->softDeletes();                          // deleted_at
});
```

### 7.3 Relations

#### One to Many

```php
// Parent
public function children(): HasMany
{
    return $this->hasMany(Child::class);
}

// Child
public function parent(): BelongsTo
{
    return $this->belongsTo(Parent::class);
}
```

#### Many to Many

```php
// User
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class);
}

// Role
public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class);
}
```

### 7.4 Eager Loading

**Toujours** charger les relations pour éviter le N+1:

```php
// ❌ N+1 problem
$users = User::all();
foreach ($users as $user) {
    echo $user->roles;  // Query par utilisateur
}

// ✅ Eager loading
$users = User::with('roles')->get();
foreach ($users as $user) {
    echo $user->roles;  // Déjà chargé
}
```

---

## 8. API et Routes

### 8.1 Structure des URLs

```
/api/{niveau}/{module}/{action}
```

**Exemples**:
```
GET    /api/admin/users
POST   /api/admin/users
GET    /api/admin/users/{id}
PUT    /api/admin/users/{id}
DELETE /api/admin/users/{id}

GET    /api/frontend/products
GET    /api/frontend/products/{id}

GET    /api/superadmin/tenants
POST   /api/superadmin/tenants
```

### 8.2 Méthodes HTTP

| Méthode | Action | Route |
|---------|--------|-------|
| GET | Liste | `/api/admin/users` |
| GET | Détail | `/api/admin/users/{id}` |
| POST | Créer | `/api/admin/users` |
| PUT | Modifier | `/api/admin/users/{id}` |
| DELETE | Supprimer | `/api/admin/users/{id}` |
| POST | Restore | `/api/admin/users/{id}/restore` |
| DELETE | Force delete | `/api/admin/users/{id}/force` |

### 8.3 Paramètres de requête

#### Pagination

```
GET /api/admin/users?per_page=15&page=2
```

#### Recherche

```
GET /api/admin/users?search=john
```

#### Filtres

```
GET /api/admin/users?is_active=1&application=admin
```

#### Tri

```
GET /api/admin/users?sort_by=created_at&sort_order=desc
```

---

## 9. Tests

### 9.1 Structure

```
modules/VotreModule/Tests/
├── Feature/           # Tests d'intégration (API, etc.)
└── Unit/             # Tests unitaires (méthodes, logique)
```

### 9.2 Exemple de test

```php
namespace Modules\VotreModule\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VotreModule\Entities\VotreModel;

class VotreModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testCanCreateModel(): void
    {
        $data = [
            'nom' => 'Test',
            'description' => 'Description',
        ];

        $response = $this->postJson('/api/admin/votremodule', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('votre_table', ['nom' => 'Test']);
    }
}
```

### 9.3 Exécution

```bash
# Tous les tests
php artisan test

# Tests d'un module
php artisan test modules/VotreModule/Tests

# Test spécifique
php artisan test --filter=testCanCreateModel
```

---

## 10. Commandes utiles

### 10.1 Module

```bash
# Créer un module
php artisan module:make VotreModule --no-interaction

# Lister les modules
php artisan module:list

# Activer/désactiver
php artisan module:enable VotreModule
php artisan module:disable VotreModule

# Supprimer
php artisan module:delete VotreModule
```

### 10.2 Génération

```bash
# Model
php artisan module:make-model VotreModel VotreModule --no-interaction

# Controller
php artisan module:make-controller Admin/VotreController VotreModule --no-interaction

# Request
php artisan module:make-request StoreVotreRequest VotreModule --no-interaction

# Resource
php artisan module:make-resource VotreResource VotreModule --no-interaction

# Migration
php artisan module:make-migration create_votre_table VotreModule --no-interaction

# Seeder
php artisan module:make-seed VotreSeeder VotreModule --no-interaction

# Factory
php artisan module:make-factory VotreFactory VotreModule --no-interaction

# Test
php artisan module:make-test VotreTest VotreModule --phpunit --no-interaction
```

### 10.3 Migrations

```bash
# Migrer un module
php artisan module:migrate VotreModule --no-interaction

# Migrer tous les modules
php artisan module:migrate --no-interaction

# Rollback
php artisan module:migrate-rollback VotreModule

# Status
php artisan module:migrate-status

# Migrations tenant
php artisan tenants:migrate
php artisan tenants:migrate --tenants=tenant1
```

### 10.4 Seeders

```bash
# Seeder un module
php artisan module:seed VotreModule --no-interaction

# Seeder tous les modules
php artisan module:seed --no-interaction
```

### 10.5 Formatage

```bash
# Formatter le code avec Pint
vendor/bin/pint --dirty
```

---

## Checklist de création d'un module

- [ ] Créer le module avec `module:make`
- [ ] Configurer `module.json`
- [ ] Créer le `ServiceProvider`
- [ ] Créer le `RouteServiceProvider`
- [ ] Créer les modèles (Entities)
- [ ] Créer les migrations (centrale et/ou tenant)
- [ ] Créer les Form Requests
- [ ] Créer les API Resources
- [ ] Créer les Controllers (Admin/Frontend/Superadmin)
- [ ] Définir les routes (admin.php, frontend.php, superadmin.php)
- [ ] Créer les Seeders
- [ ] Créer les Factories
- [ ] Exécuter les migrations
- [ ] Exécuter les seeders
- [ ] Tester les endpoints
- [ ] Formatter le code avec Pint
- [ ] Créer les tests (optionnel)
- [ ] Documenter le module (README.md)

---

## Exemple de module complet

Voir le module `UsersGuard` comme référence complète d'un module bien structuré :
- Fichier: `modules/UsersGuard/`
- README: `modules/UsersGuard/README.md`

---

**Fin de la documentation** - Version 1.0 - 2024-12-25