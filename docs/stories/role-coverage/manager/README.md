# Epic Manager — Couverture des menus

> **Status** : Draft
> **Role slug** : `Manager` ([`RolesAndPermissionsSeeder.php:90`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php))
> **home_route** : `/admin/dashboard` ([`config/role-routes.php:26`](../../../config/role-routes.php))
> **Position hiérarchique** : 2/8

## 1. Permissions Spatie attribuées

Source : `RolesAndPermissionsSeeder.php:93-99`

```php
$managerRole->syncPermissions([
    'view users',
    'create users',
    'edit users',
    'view reports',
    'view dashboard',
]);
```

> Notable : **pas** de `delete users`, **pas** de `manage roles/permissions`, **pas** de `manage settings`. Manager = Admin allégé sans pouvoir destructif/structurel.

## 2. Profil utilisateur

Directeur adjoint / responsable pédagogique :
- Pilote opérationnel quotidien — RH (création/édition utilisateurs), suivi pédagogique, rapports.
- N'a pas la main sur la configuration tenant ni sur la sécurité (rôles/permissions).
- Lecture transverse sur toute la donnée pédagogique (élèves, classes, notes, présences, EDT) mais sans saisie directe (sauf comptes utilisateurs).

## 3. Backend coverage

| Module | Route file | État RBAC | Endpoints clés |
|---|---|---|---|
| **UsersGuard** | [`admin.php:26`](../../../Modules/UsersGuard/Routes/admin.php) | ✅ `role:Administrator\|Manager,tenant` | CRUD users sauf DELETE/restore/force/permissions/roles |
| **UsersGuard** | [`admin.php:52`](../../../Modules/UsersGuard/Routes/admin.php) | ✅ inclut Manager | `GET /admin/teachers/` |
| **UsersGuard** | [`admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php) | ✅ inclut Manager | `GET /admin/students/` |
| **StructureAcademique** | [`admin.php`](../../../Modules/StructureAcademique/Routes/admin.php) | ❌ Aucun | Lecture seule cible : années, classes, matières, coefficients |
| **Enrollment** | [`admin.php`](../../../Modules/Enrollment/Routes/admin.php) | ❌ | CRUD élèves (création, modification — pas suppression hard) ; lecture statistiques |
| **NotesEvaluations** | [`admin.php`](../../../Modules/NotesEvaluations/Routes/admin.php) | ❌ | Lecture seule sur les notes (suivi pédagogique) ; publications, deliberations |
| **Attendance** | [`admin.php`](../../../Modules/Attendance/Routes/admin.php) | ❌ | Lecture (`reports/*`, `monitoring/*`) |
| **Timetable** | [`admin.php`](../../../Modules/Timetable/Routes/admin.php) | ❌ | Lecture (`views/*`) |
| **Documents** | [`admin.php`](../../../Modules/Documents/Routes/admin.php) | ⚠️ `auth:sanctum` | Génération bulletins/attestations OK |
| **Finance** | [`admin.php`](../../../Modules/Finance/Routes/admin.php) | ❌ | Lecture rapports uniquement (`reports/*` filtré) |
| **Exams** | [`admin.php`](../../../Modules/Exams/Routes/admin.php) | ⚠️ `auth:sanctum` | Lecture sessions + reports |

## 4. Périmètre fonctionnel

### Autorisé
- ✅ Dashboard
- ✅ Utilisateurs : créer / modifier / consulter (PAS supprimer ni gérer rôles/permissions — `UsersGuard/admin.php:32`)
- ✅ Lecture transverse : élèves, notes, présences, EDT, structure académique
- ✅ Inscriptions : CRUD élèves (création, mise à jour) + statistiques
- ✅ Génération de documents officiels (bulletins, attestations, cartes)
- ✅ Suivi pédagogique : voir les notes, les conseils de classe, les délibérations
- ✅ Rapports (lecture)

### Interdit
- ❌ Supprimer un utilisateur (`UsersGuard/admin.php:33` réservé Admin)
- ❌ Restaurer / hard delete (`UsersGuard/admin.php:36-37`)
- ❌ Modifier les permissions ou rôles d'un utilisateur (`UsersGuard/admin.php:40-47`)
- ❌ CRUD permissions / rôles (`UsersGuard/admin.php:62, 71`)
- ❌ Modifier la structure académique (années, semestres, classes, coefficients) — lecture seule
- ❌ Saisir des notes (Professeur)
- ❌ Encaisser, créer factures, exports financiers (rôles finance)
- ❌ Toucher à la paie

## 5. Stories planifiées (9 stories)

| # | Fichier | Menu cible | Endpoints principaux |
|---|---|---|---|
| 01 | `01-dashboard.story.md` | Dashboard | `GET /admin/dashboard`, `GET /admin/auth/me` |
| 02 | `02-users-management.story.md` | « Utilisateurs » (sans delete) | `GET/POST/PUT /admin/users/*` ; **403** sur DELETE/permissions/roles |
| 03 | `03-academic-structure-readonly.story.md` | « Structure » (lecture) | `GET /admin/{academic-years,classes,subjects,coefficients,…}` ; **403** sur POST/PUT/DELETE |
| 04 | `04-enrollments.story.md` | « Inscriptions » | CRUD élèves : `GET/POST/PUT /admin/enrollment/students` ; statistiques |
| 05 | `05-grades-readonly.story.md` | « Notes » (lecture) | `GET /api/admin/grades`, `GET /api/admin/results/*` ; **pas** d'écriture |
| 06 | `06-attendance-readonly.story.md` | « Présences » (lecture) | `GET /admin/attendance/reports/*` |
| 07 | `07-timetable-readonly.story.md` | « Emplois du temps » (lecture) | `GET /admin/timetable/views/*` |
| 08 | `08-documents.story.md` | « Documents » | `POST /admin/documents/transcripts/semester`, `certificates/*`, `cards/*` |
| 09 | `09-finance-readonly.story.md` | « Finance » (lecture rapports) | `GET /admin/finance/reports/*` ; **403** sur factures/payments POST |

## 6. Dépendances backend (à durcir)

Le contrôle RBAC existant sur `UsersGuard/admin.php` est satisfaisant pour les users. Pour les autres modules :

```php
// Lecture seule pour Manager
Route::middleware('role:Administrator|Manager,tenant')->group(function () {
    // Structure académique - GET only
    Route::get('academic-years', ...);
    Route::get('classes', ...);
    // …
});

// Manager peut écrire sur Inscriptions
Route::middleware('role:Administrator|Manager,tenant')->group(function () {
    Route::resource('enrollment/students', ...)->except(['destroy']);
});

// Manager NE PEUT PAS supprimer un élève (Admin only)
Route::middleware('role:Administrator,tenant')->group(function () {
    Route::delete('enrollment/students/{student}', ...);
});
```

## 7. Risques spécifiques

| # | Risque | Mitigation |
|---|---|---|
| M1 | Manager élève sa propre permission via `PUT /admin/users/{me}` | Validation backend : un utilisateur ne peut pas modifier ses propres rôles (interdit côté `UserController@update`) |
| M2 | Création abusive de comptes Professeur/Manager | Audit log + alerte si > N comptes créés/jour |
| M3 | Suppression de facture via UI Manager malgré 403 backend | Bouton DELETE caché côté frontend (vérifier `menu.config.ts`) |
| M4 | Manager voit des données sensibles élèves (santé, discipline) | Filtrer `student_resource` selon le rôle (`when($user->isAdmin(), ...)`) |

## 8. Definition of Done

- [ ] 9 stories rédigées en Draft
- [ ] Tests E2E confirment 403 sur les actions Admin-only
- [ ] Backlog `role:Administrator|Manager` middleware sur lecture transverse consolidé

## 9. Change log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
