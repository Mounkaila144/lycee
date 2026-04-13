# Agent Specifications - CRM API

## Project Information

- **Name**: CRM-API (Système de Gestion Académique)
- **Framework**: Laravel 12
- **PHP Version**: 8.3+
- **Architecture**: Multi-tenant avec modules (nwidart/laravel-modules)

---

## Build Commands

### Installation
```bash
composer install
npm install
```

### Code Formatting
```bash
vendor/bin/pint --dirty
```

### Cache Clear
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Migrations (Tenant)
```bash
# Les migrations tenant sont dans Modules/{Module}/Database/Migrations/tenant/
# Elles sont exécutées automatiquement lors du setup tenant
```

---

## Test Commands

### Run All Tests
```bash
php artisan test
```

### Run Specific Test File
```bash
php artisan test tests/Feature/NotesEvaluations/RetakeApiTest.php
```

### Run Tests with Filter
```bash
php artisan test --filter=RetakeTest
```

### Run Tests with Coverage (si configuré)
```bash
php artisan test --coverage
```

---

## Module Structure

```
Modules/{ModuleName}/
├── Config/
├── Console/
├── Database/
│   ├── Factories/
│   ├── Migrations/
│   │   └── tenant/          # Migrations multi-tenant
│   └── Seeders/
├── Entities/                 # Models Eloquent
├── Events/
├── Exports/                  # Exports Excel (Maatwebsite)
├── Http/
│   ├── Controllers/
│   │   ├── Admin/           # API Admin
│   │   └── Api/             # API Public/Student
│   ├── Middleware/
│   ├── Requests/            # Form Requests
│   └── Resources/           # API Resources
├── Jobs/
├── Notifications/
├── Providers/
├── Routes/
│   ├── admin.php            # Routes API admin
│   ├── api.php              # Routes API publiques
│   └── web.php
├── Services/                # Business Logic
└── Tests/
```

---

## Coding Standards

### Entity Template
```php
<?php

namespace Modules\{Module}\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class {EntityName} extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = '{table_name}';

    protected $fillable = [
        // ...
    ];

    protected function casts(): array
    {
        return [
            // ...
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    // Relations...
    // Scopes...
    // Accessors...
}
```

### Controller Template
```php
<?php

namespace Modules\{Module}\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class {ControllerName} extends Controller
{
    public function __construct(
        private {ServiceName} $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        // ...
    }
}
```

### Routes Template
```php
Route::middleware(['api', 'tenant.sanctum.auth'])
    ->prefix('api/admin')
    ->group(function () {
        Route::prefix('{resource}')->group(function () {
            Route::get('/', [Controller::class, 'index']);
            Route::post('/', [Controller::class, 'store']);
            Route::get('/{id}', [Controller::class, 'show']);
            Route::put('/{id}', [Controller::class, 'update']);
            Route::delete('/{id}', [Controller::class, 'destroy']);
        });
    });
```

---

## Validation Rules

### API Authentication (Tests)
```php
// IMPORTANT: Utiliser Bearer token, PAS Sanctum::actingAs()
$this->user = User::factory()->create();
$this->token = $this->user->createToken('test')->plainTextToken;

// Dans les tests
$this->withToken($this->token)->getJson('/api/admin/...');
```

### Database Connection
- Toutes les entities doivent avoir `protected $connection = 'tenant';`
- Les migrations tenant vont dans `Database/Migrations/tenant/`

### Response Format
```json
{
    "data": { ... },
    "message": "Success message",
    "meta": {
        "current_page": 1,
        "last_page": 10,
        "per_page": 15,
        "total": 150
    }
}
```

---

## Dependencies

### Required Packages
- `maatwebsite/excel` - Exports Excel
- `barryvdh/laravel-dompdf` - Generation PDF
- `stancl/tenancy` - Multi-tenancy

### Key Services
- `GradeConfig::getConfig()` - Configuration des notes (seuils, coefficients)
- `SemesterAverageService` - Calcul moyennes semestre
- `CompensationRulesService` - Règles de compensation LMD

---

## Environment

### Required ENV Variables
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=crm_api
TENANCY_DATABASE_PREFIX=tenant_
```

### Test Database
- Utilise SQLite en mémoire ou base de test séparée
- Le trait `InteractsWithTenancy` gère le setup tenant dans les tests
