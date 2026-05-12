# Epic Administrator — Couverture des menus

> **Status** : Draft
> **Role slug** : `Administrator` ([`RolesAndPermissionsSeeder.php:84`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php))
> **home_route** : `/admin/dashboard` ([`config/role-routes.php:25`](../../../config/role-routes.php))
> **Position hiérarchique** : 1/8 (le plus élevé)

## 1. Permissions Spatie attribuées

Source : `RolesAndPermissionsSeeder.php:87` — **TOUTES** les permissions du tenant.

```php
$adminRole->syncPermissions(Permission::all());
```

Cela inclut, à date du seeder :
- User Management : `view/create/edit/delete users`
- Role Management : `view/create/edit/delete roles`
- Settings : `view/edit settings`
- Reports : `view/export reports`
- Dashboard : `view dashboard`
- Academic : `view students`, `manage grades`, `view timetable`
- Student perms : `view own grades`, `view own timetable`, `upload documents`, `request attestations`, `view own attendance`
- Financial : 14 permissions finance (invoices, payments, refunds, reconciliation, reports, exports…)

> Note : aucune permission « system » spéciale Admin — c'est l'attribution `Permission::all()` qui fait le rôle « god mode » sur le tenant. **L'Administrator agit dans son tenant uniquement** ; les opérations cross-tenant relèvent du `SuperAdmin` (guard central, hors scope de cet epic).

## 2. Profil utilisateur

Directeur(trice) de l'établissement :
- Configure le tenant (paramètres, années scolaires, classes).
- Crée les comptes des autres rôles (enseignants, comptables, parents).
- Approuve les opérations sensibles (refunds majeurs, write-offs, exclusions élèves).
- Vue 360° sur l'établissement.

## 3. Backend coverage

L'Administrator a accès à **TOUTES** les routes admin de tous les modules. Cet epic sert d'**étalon** : si un endpoint apparaît dans une story d'un autre rôle comme « interdit pour ce rôle », il doit apparaître ici comme « autorisé pour Admin ».

| Module | Route file | État RBAC | Couverture Admin |
|---|---|---|---|
| **UsersGuard** | [`admin.php`](../../../Modules/UsersGuard/Routes/admin.php) | ✅ Admin-only sur permissions/roles/delete | ✅ Tout |
| **StructureAcademique** | [`admin.php`](../../../Modules/StructureAcademique/Routes/admin.php) | ❌ | ✅ CRUD |
| **Enrollment** | [`admin.php`](../../../Modules/Enrollment/Routes/admin.php) (438 L) | ❌ | ✅ Tout (élèves, enrollments, options, groups, transferts, équivalences, exemptions, cartes, campagnes, stats, exports) |
| **NotesEvaluations** | [`admin.php`](../../../Modules/NotesEvaluations/Routes/admin.php) (336 L) | ❌ | ✅ Tout (corrections, deliberations, publications, retakes, analytics) |
| **Attendance** | [`admin.php`](../../../Modules/Attendance/Routes/admin.php) | ❌ | ✅ Tout |
| **Timetable** | [`admin.php`](../../../Modules/Timetable/Routes/admin.php) | ❌ | ✅ Tout (rooms, slots, generation, exceptions, teacher preferences, reports) |
| **Exams** | [`admin.php`](../../../Modules/Exams/Routes/admin.php) | ⚠️ `auth:sanctum` | ✅ Tout (sessions, management, supervision, incidents, reports) |
| **Documents** | [`admin.php`](../../../Modules/Documents/Routes/admin.php) | ⚠️ `auth:sanctum` | ✅ Tout (transcripts, diplomas, certificates, cards, verification) |
| **Finance** | [`admin.php`](../../../Modules/Finance/Routes/admin.php) | ❌ | ✅ Tout (fee-types, invoices, payments, discounts, collection, reports) |
| **Payroll** | [`admin.php`](../../../Modules/Payroll/Routes/admin.php) | ⚠️ `auth:sanctum` | ✅ Tout (employees, contracts, components, periods, payslips, declarations, reports) |

## 4. Périmètre fonctionnel

### Autorisé : TOUT ce qui est listé dans les autres READMEs comme « autorisé »

Y compris les actions exclusives Admin :
- ✅ Supprimer / restaurer / force-delete utilisateurs (`UsersGuard/admin.php:33-37`)
- ✅ Gérer les rôles et permissions Spatie (`UsersGuard/admin.php:62, 71`)
- ✅ Modifier les paramètres tenant
- ✅ Approuver les write-off (`collection/write-off/{id}`)
- ✅ Configurer la structure académique (créer années, semestres, niveaux, séries, classes, matières, coefficients)
- ✅ Génération de masse (bulletins toute une classe, factures toute un cycle)

### Interdit
- ❌ Opérations cross-tenant (réservées au `SuperAdmin` central — hors scope tenant)
- ❌ Voir les données d'un autre tenant (isolation BD garantie par stancl/tenancy)
- ❌ Modifier le code applicatif ou les migrations (rôle DevOps)

## 5. Stories planifiées (13 stories)

| # | Fichier | Menu cible | Modules backend |
|---|---|---|---|
| 01 | `01-dashboard.story.md` | « Tableau de bord » | KPI tous modules |
| 02 | `02-users-management.story.md` | « Utilisateurs » | UsersGuard (CRUD complet + soft delete) |
| 03 | `03-roles-permissions.story.md` | « Rôles & Permissions » | UsersGuard (RoleController, PermissionController) |
| 04 | `04-academic-structure.story.md` | « Structure académique » | StructureAcademique (années, classes, matières, coefficients) |
| 05 | `05-enrollments.story.md` | « Inscriptions » | Enrollment (CRUD complet, import, options, groupes, cartes) |
| 06 | `06-grades-evaluations.story.md` | « Notes & Évaluations » | NotesEvaluations admin (corrections, validations, deliberations, publications) |
| 07 | `07-attendance.story.md` | « Présences » | Attendance (sessions, justifications, monitoring, reports) |
| 08 | `08-timetable.story.md` | « Emplois du temps » | Timetable (rooms, slots, generation) |
| 09 | `09-exams.story.md` | « Examens » | Exams (sessions, supervision, incidents, reports) |
| 10 | `10-documents.story.md` | « Documents » | Documents (transcripts, diplomas, certificates, cards, verification) |
| 11 | `11-finance.story.md` | « Finance » | Finance (factures, encaissements, remises, recouvrement, rapports) |
| 12 | `12-payroll.story.md` | « Paie » | Payroll (employees, periods, declarations, reports) |
| 13 | `13-settings.story.md` | « Réglages » | Settings tenant (config, branding, intégrations) — endpoints à mapper |

## 6. Dépendances backend

L'Administrator n'introduit pas de nouvelle dépendance ; en revanche, **toutes les stories Admin doivent valider** que les sécurisations RBAC ajoutées pour les autres rôles **n'empêchent pas** Admin de tout faire :

```php
// Pattern recommandé
Route::middleware('role:Administrator|<other>,tenant')->group(...);
// Plutôt que :
Route::middleware('role:<other>,tenant')->group(...);
```

L'Administrator est **toujours** inclus dans la liste des rôles autorisés (sauf cas où volontairement il devrait passer par un rôle dédié — ex : encaissement caissier — mais même là on l'inclut par souplesse).

## 7. Risques spécifiques

| # | Risque | Mitigation |
|---|---|---|
| A1 | Admin verrouille tous les autres utilisateurs (bug ou malveillance) | Audit log sur toute modification de rôle/permission ; alerte SuperAdmin si Admin se déconnecte tous les Managers en une session |
| A2 | Suppression définitive de données critiques (`force-delete` user) | Confirmation 2-étapes + délai de 24h ; backup automatique |
| A3 | Modification de coefficients en cours de semestre | Verrou : impossible de modifier la structure d'une année avec notes saisies (lecture seule) |
| A4 | Admin « god mode » obscurcit qui a fait quoi | Audit log obligatoire sur **toutes** les actions Admin (table `admin_action_logs` à vérifier) |
| A5 | Création de fake Admin pour fraude | Limite 2 Admins max par tenant ; alerte SuperAdmin lors de toute promotion vers Admin |

## 8. Definition of Done

- [ ] 13 stories rédigées en Draft
- [ ] Pour chaque story : confirmation que l'Admin peut faire **toutes** les actions interdites aux autres rôles
- [ ] Audit log Admin vérifié sur chaque endpoint sensible

## 9. Change log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
