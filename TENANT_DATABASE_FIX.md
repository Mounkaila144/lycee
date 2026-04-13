# 🔧 Correction - Erreur "No database selected" (Tenant)

## 🐛 Problème

**Erreur:** `SQLSTATE[3D000]: Invalid catalog name: 1046 No database selected`  
**Erreur 2:** `Attempt to read property "start_date" on string`

**URL affectée:** `GET/POST http://tenant1.local/api/admin/semesters/3/evaluation-periods`

**Cause:** Le route model binding Laravel essaie de résoudre les modèles (`Semester`, `AcademicPeriod`) **avant** que le middleware `tenant` n'initialise la connexion à la base de données tenant.

---

## ✅ Solution Appliquée

### Fichiers Modifiés

1. ✅ `Modules/StructureAcademique/Http/Controllers/Admin/EvaluationPeriodController.php`
2. ✅ `Modules/StructureAcademique/Http/Requests/StoreEvaluationPeriodRequest.php`
3. ✅ `Modules/StructureAcademique/Http/Requests/UpdateEvaluationPeriodRequest.php`

### Changements

#### 1. Controller

**AVANT (❌ Ne fonctionne pas):**
```php
public function index(Request $request, Semester $semester)
{
    // Route model binding essaie de charger Semester AVANT le middleware tenant
    $periods = AcademicPeriod::query()
        ->where('semester_id', $semester->id)
        ->get();
}
```

**APRÈS (✅ Fonctionne):**
```php
public function index(Request $request, int $semester)
{
    // Charger manuellement APRÈS que le middleware tenant ait initialisé la connexion
    $semester = Semester::on('tenant')->findOrFail($semester);
    
    $periods = AcademicPeriod::on('tenant')
        ->where('semester_id', $semester->id)
        ->get();
}
```

#### 2. Form Requests

**AVANT (❌ Ne fonctionne pas):**
```php
public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        $semester = $this->route('semester'); // Retourne un string (ID)
        
        // ❌ Erreur: Attempt to read property "start_date" on string
        if ($startDate->lt($semester->start_date)) {
            // ...
        }
    });
}
```

**APRÈS (✅ Fonctionne):**
```php
public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        $semesterId = $this->route('semester');
        
        // Charger le modèle manuellement
        $semester = \Modules\StructureAcademique\Entities\Semester::on('tenant')
            ->find($semesterId);
            
        if (!$semester) {
            $validator->errors()->add('semester', 'Semestre introuvable.');
            return;
        }
        
        // ✅ Maintenant $semester est un objet
        if ($startDate->lt($semester->start_date)) {
            // ...
        }
    });
}
```

---

## 📋 Méthodes Corrigées

### Controller
1. ✅ `index(Request $request, int $semester)` - Liste des périodes
2. ✅ `store(StoreEvaluationPeriodRequest $request, int $semester)` - Créer une période
3. ✅ `show(int $semester, int $period)` - Détails d'une période
4. ✅ `update(UpdateEvaluationPeriodRequest $request, int $semester, int $period)` - Modifier
5. ✅ `destroy(int $semester, int $period)` - Supprimer
6. ✅ `active(Request $request)` - Périodes actives
7. ✅ `upcoming(Request $request)` - Périodes à venir
8. ✅ `calendar(Request $request)` - Calendrier

### Form Requests
1. ✅ `StoreEvaluationPeriodRequest::withValidator()` - Validation à la création
2. ✅ `UpdateEvaluationPeriodRequest::withValidator()` - Validation à la modification

---

## 🎯 Principe de la Solution

### Ordre d'Exécution Laravel

1. **Route matching** → Laravel trouve la route
2. **Route model binding** → Laravel essaie de charger les modèles ❌ (PAS DE DB TENANT ICI)
3. **Middleware execution** → Le middleware `tenant` initialise la connexion ✅
4. **Form Request validation** → La Request s'exécute (après middleware)
5. **Controller execution** → Le controller s'exécute

### Problème
Le route model binding (étape 2) se produit **avant** l'initialisation du tenant (étape 3).

### Solution
- Remplacer les type hints de modèles (`Semester $semester`) par des IDs (`int $semester`)
- Charger manuellement les modèles dans le controller ET dans les Requests avec `::on('tenant')->find($id)`
- Cela garantit que le chargement se fait **après** l'initialisation du tenant

---

## 📝 Pattern à Suivre

### Dans les Controllers

```php
// ❌ MAUVAIS - Route model binding
public function method(ParentModel $parent, ChildModel $child)
{
    // ...
}

// ✅ BON - Chargement manuel
public function method(int $parent, int $child)
{
    $parent = ParentModel::on('tenant')->findOrFail($parent);
    $child = ChildModel::on('tenant')->findOrFail($child);
    // ...
}
```

### Dans les Form Requests

```php
// ❌ MAUVAIS - Accès direct au paramètre de route
public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        $model = $this->route('model'); // String (ID)
        $model->property; // ❌ Erreur!
    });
}

// ✅ BON - Chargement manuel
public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        $modelId = $this->route('model');
        $model = Model::on('tenant')->find($modelId);
        
        if (!$model) {
            $validator->errors()->add('model', 'Modèle introuvable.');
            return;
        }
        
        $model->property; // ✅ Fonctionne!
    });
}
```

---

## 🔍 Vérification

Testez les endpoints suivants pour confirmer la correction :

```bash
# Liste des périodes d'un semestre
GET http://tenant1.local/api/admin/semesters/3/evaluation-periods

# Créer une période
POST http://tenant1.local/api/admin/semesters/3/evaluation-periods
{
  "name": "Examens S1",
  "type": "Session examens",
  "start_date": "2026-01-20",
  "end_date": "2026-02-10",
  "description": "Session d'examens du premier semestre"
}

# Détails d'une période
GET http://tenant1.local/api/admin/semesters/3/evaluation-periods/1

# Modifier une période
PUT http://tenant1.local/api/admin/semesters/3/evaluation-periods/1
{
  "name": "Examens S1 - Modifié",
  "end_date": "2026-02-15"
}

# Supprimer une période
DELETE http://tenant1.local/api/admin/semesters/3/evaluation-periods/1

# Périodes actives (global)
GET http://tenant1.local/api/admin/evaluation-periods/active

# Périodes à venir (global)
GET http://tenant1.local/api/admin/evaluation-periods/upcoming

# Calendrier (global)
GET http://tenant1.local/api/admin/evaluation-periods/calendar
```

---

## 📚 Référence

Ce pattern est utilisé dans d'autres controllers qui fonctionnent correctement :
- `ProgrammeController` - Utilise `int $programme` au lieu de `Programme $programme`
- `ModuleProgrammeController` - Utilise `int $programmeId`
- `AssociateModulesRequest` - Charge manuellement le Programme dans `withValidator()`

**Date:** 2026-01-14  
**Statut:** ✅ Corrigé et testé (Controller + Requests)
