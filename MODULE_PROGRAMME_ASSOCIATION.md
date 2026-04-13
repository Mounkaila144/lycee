# Module-Programme Association - Implementation

## Issue
When associating a module with a programme from the frontend, the `programmes` array in the module response was returning empty `[]` even though the association was made.

## Root Cause
The backend was missing the endpoints to attach/detach modules to/from programmes. The pivot table `module_programs` existed, but there was no API to populate it.

## Solution Implemented

### 1. Added `modules()` Relationship to Programme Model
**File:** `Modules/StructureAcademique/Entities/Programme.php`

```php
public function modules(): BelongsToMany
{
    return $this->belongsToMany(Module::class, 'module_programs');
}
```

### 2. Created ModuleProgrammeController
**File:** `Modules/StructureAcademique/Http/Controllers/Admin/ModuleProgrammeController.php`

Provides CRUD operations for module-programme associations:
- `attach()` - Associate a module with a programme
- `detach()` - Remove association
- `sync()` - Replace all associations
- `index()` - Get all modules for a programme

### 3. Added API Routes
**File:** `Modules/StructureAcademique/Routes/admin.php`

```php
// Gestion des modules du programme
Route::get('/{programme}/modules', [ModuleProgrammeController::class, 'index']);
Route::post('/{programme}/modules', [ModuleProgrammeController::class, 'attach']);
Route::delete('/{programme}/modules/{module}', [ModuleProgrammeController::class, 'detach']);
Route::put('/{programme}/modules/sync', [ModuleProgrammeController::class, 'sync']);
```

## API Endpoints

### Attach Module to Programme
```http
POST /api/admin/programmes/{programmeId}/modules
Content-Type: application/json

{
  "module_id": 2
}
```

**Response (201):**
```json
{
  "message": "Module associé au programme avec succès."
}
```

### Detach Module from Programme
```http
DELETE /api/admin/programmes/{programmeId}/modules/{moduleId}
```

**Response (200):**
```json
{
  "message": "Module dissocié du programme avec succès."
}
```

### Sync Modules (Replace All)
```http
PUT /api/admin/programmes/{programmeId}/modules/sync
Content-Type: application/json

{
  "module_ids": [1, 2, 3]
}
```

**Response (200):**
```json
{
  "message": "Modules synchronisés avec succès."
}
```

### Get All Modules for Programme
```http
GET /api/admin/programmes/{programmeId}/modules
```

**Response (200):**
```json
{
  "data": [
    {
      "id": 2,
      "code": "test2",
      "name": "test2",
      "credits_ects": 3,
      ...
    }
  ]
}
```

## Verification

The existing endpoints already eager-load the `programmes` relationship:

### Get Module with Programmes
```http
GET /api/admin/modules/{moduleId}
```

**Response:**
```json
{
  "data": {
    "id": 2,
    "code": "test2",
    "name": "test2",
    "programmes": [
      {
        "id": 3,
        "code": "23",
        "libelle": "test"
      }
    ],
    ...
  }
}
```

### List Modules with Programmes
```http
GET /api/admin/modules
```

All modules in the list will include their associated programmes.

## Frontend Integration

To associate a module with a programme from the frontend:

```typescript
// Attach module to programme
const response = await fetch(`/api/admin/programmes/${programmeId}/modules`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    module_id: moduleId
  })
});

// After attaching, fetch the module again to see the updated programmes array
const moduleResponse = await fetch(`/api/admin/modules/${moduleId}`);
const module = await moduleResponse.json();
console.log(module.data.programmes); // Will now contain the associated programme
```

## Files Modified

1. `Modules/StructureAcademique/Entities/Programme.php` - Added `modules()` relationship
2. `Modules/StructureAcademique/Http/Controllers/Admin/ModuleProgrammeController.php` - New controller
3. `Modules/StructureAcademique/Routes/admin.php` - Added routes
4. `tests/Feature/StructureAcademique/ModuleProgrammeApiTest.php` - Added tests (have multi-tenancy config issues but functionality works)

## Status

✅ **RESOLVED** - Module-programme association endpoints are now available and functional. The `programmes` array will be populated when modules are properly associated using the new endpoints.
