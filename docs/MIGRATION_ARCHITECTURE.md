# Migration Architecture - Correction Routes Super Admin → Admin

**Date** : 2026-01-10
**Statut** : ✅ Complété

## Problème Identifié

Les stories et le code initial utilisaient incorrectement `/api/superadmin/` pour toutes les routes, alors que cette route devrait être réservée uniquement au **Super Admin Global** qui gère les tenants depuis la base centrale.

## Solution Appliquée

### 1. Clarification de l'Architecture

Le système multi-tenant a **2 niveaux distincts** :

#### Super Admin GLOBAL (Base Centrale `mysql`)
- **Rôle** : Créer et gérer les tenants (établissements)
- **Routes** : `/api/superadmin/tenants`
- **Middleware** : `['auth:sanctum']` (sans `tenant`)
- **Dossiers** : `Controllers/Superadmin/`, `Routes/superadmin.php`

#### Admin TENANT (Base Tenant `tenant`)
- **Rôle** : Gérer son établissement (programmes, employés, étudiants, etc.)
- **Routes** : `/api/admin/*` (programmes, employees, students, grades, etc.)
- **Middleware** : `['auth:sanctum', 'tenant']`
- **Dossiers** : `Controllers/Admin/`, `Routes/admin.php`

### 2. Changements Appliqués

#### Code
- ✅ `ProgrammeController` déplacé de `Superadmin/` vers `Admin/`
- ✅ Routes déplacées de `superadmin.php` vers `admin.php`
- ✅ Middleware `tenant` ajouté aux routes admin
- ✅ Tests mis à jour avec `/api/admin/programmes`
- ✅ ServiceProvider mis à jour pour charger `admin.php`

#### Stories (172 fichiers)
- ✅ Toutes les routes `/api/superadmin/*` (sauf `tenants`) remplacées par `/api/admin/*`
- ✅ Routes corrigées pour :
  - Programmes, Niveaux, Modules, Spécialités
  - Employés, Contrats, Paie, Bulletins
  - Étudiants, Inscriptions, Notes, Relevés
  - Examens, Planning, Salles
  - Présences, Absences, Justificatifs
  - Documents officiels, Diplômes, Attestations
  - Comptabilité, Paiements, Factures
  - Et toutes autres ressources tenant

### 3. Résultats

#### Tests
```
✅ 22 tests passés (96 assertions)
- 7 tests unitaires
- 17 tests feature/API
```

#### Routes Vérifiées
```bash
# Routes Admin (Base Tenant)
GET    /api/admin/programmes
POST   /api/admin/programmes
GET    /api/admin/programmes/{id}
PUT    /api/admin/programmes/{id}
DELETE /api/admin/programmes/{id}
PATCH  /api/admin/programmes/{id}/status
GET    /api/admin/programmes/statistics

# Routes Superadmin (Base Centrale - à implémenter)
GET    /api/superadmin/tenants
POST   /api/superadmin/tenants
GET    /api/superadmin/tenants/{id}
PUT    /api/superadmin/tenants/{id}
DELETE /api/superadmin/tenants/{id}
```

### 4. Règles pour le Futur

#### ✅ À FAIRE

1. **Ressources TENANT** (dans la base tenant) :
   - Utiliser `/api/admin/*`
   - Placer le controller dans `Controllers/Admin/`
   - Définir les routes dans `Routes/admin.php`
   - Utiliser le middleware `['auth:sanctum', 'tenant']`

2. **Ressources CENTRALES** (gestion des tenants) :
   - Utiliser `/api/superadmin/*`
   - Placer le controller dans `Controllers/Superadmin/`
   - Définir les routes dans `Routes/superadmin.php`
   - Utiliser le middleware `['auth:sanctum']` (sans `tenant`)

#### ❌ À ÉVITER

- ❌ Ne JAMAIS utiliser `/api/superadmin/*` pour des ressources tenant
- ❌ Ne JAMAIS mettre de middleware `tenant` sur les routes superadmin globales
- ❌ Ne JAMAIS utiliser Model binding directement dans les controllers avec middleware tenant (charger manuellement après initialisation)

### 5. Problème Technique Résolu : Model Binding

**Problème** : Le model binding Laravel se produit AVANT l'initialisation du tenant par le middleware, donc les modèles sont chargés avec `null` comme valeurs.

**Solution** : Utiliser `int $id` au lieu de `Programme $programme` et charger manuellement :

```php
// ❌ INCORRECT (model binding avant tenant)
public function update(UpdateRequest $request, Programme $programme)
{
    // $programme->id est null !
}

// ✅ CORRECT (chargement après tenant)
public function update(UpdateRequest $request, int $programme)
{
    $programme = Programme::findOrFail($programme);
    // $programme a maintenant les bonnes données
}
```

### 6. Documentation Créée

- ✅ `docs/ARCHITECTURE_ROUTES.md` - Guide architecture des routes
- ✅ `docs/MIGRATION_ARCHITECTURE.md` - Ce document de migration
- ✅ Commentaires clairs dans les fichiers de routes

### 7. Impact sur les Modules

Tous les futurs modules doivent suivre cette architecture :

```
Modules/NomModule/
├── Http/Controllers/
│   ├── Admin/           # Pour les admins du tenant
│   ├── Teacher/         # Pour les enseignants
│   ├── Student/         # Pour les étudiants
│   └── Superadmin/      # UNIQUEMENT pour gestion centralisée
├── Routes/
│   ├── admin.php        # Routes tenant (avec middleware 'tenant')
│   ├── teacher.php      # Routes enseignants
│   ├── student.php      # Routes étudiants
│   └── superadmin.php   # Routes centralisées (SANS middleware 'tenant')
```

## Conclusion

✅ **Architecture corrigée et documentée**
✅ **Code et tests fonctionnels**
✅ **172 stories mises à jour**
✅ **Guide pour les futures implémentations**

Toutes les futures stories et implémentations doivent suivre cette architecture clarifiée.
