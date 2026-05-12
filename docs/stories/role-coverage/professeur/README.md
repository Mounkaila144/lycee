# Epic Professeur — Couverture des menus

> **Status** : Draft
> **Role slug** : `Professeur` ([`RolesAndPermissionsSeeder.php:110`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php))
> **home_route** : `/admin/teacher/home` ([`config/role-routes.php:30`](../../../config/role-routes.php))
> **Position hiérarchique** : 6/8 ([`config/role-routes.php:19`](../../../config/role-routes.php))

## 1. Permissions Spatie attribuées

Source canonique : `RolesAndPermissionsSeeder.php:113-118`

```php
$teacherRole->syncPermissions([
    'view dashboard',
    'view students',
    'manage grades',
    'view timetable',
]);
```

> **Constat** : ces 4 permissions sont **déclarées** mais **non utilisées** sur les routes — aucun `middleware('permission:manage grades')` ni `permission('view students')` n'est appliqué côté backend. Aujourd'hui n'importe quel utilisateur authentifié peut atteindre les endpoints prof. Chaque story du Professeur devra spécifier les middlewares à AJOUTER.

## 2. Profil utilisateur

Enseignant de matière (TC ou suppléant) :
- Connecte plusieurs fois par semaine en classe ou depuis chez lui.
- Cas critique : saisie notes en fin de semestre (lot de 60 élèves × 5 classes) — performance importante.
- Doit voir uniquement **SES** modules, **SES** classes, **SES** créneaux EDT — ownership critique.

## 3. Backend coverage (modules touchés)

| Module | Route file | État RBAC | Endpoints clés |
|---|---|---|---|
| **NotesEvaluations** | [`teacher.php`](../../../Modules/NotesEvaluations/Routes/teacher.php) (95 lignes) | ❌ Pas de `role:` middleware | `my-modules`, `evaluations/*/students`, `grades/batch`, `grades/import`, `grades/submit`, `absences`, `retake-grades` |
| **UsersGuard** | [`admin.php:52`](../../../Modules/UsersGuard/Routes/admin.php) | ✅ `role:Administrator\|Manager\|Professeur` | `GET /admin/teachers/` (liste enseignants — lecture seule pour soi) |
| **UsersGuard** | [`admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php) | ✅ `role:Administrator\|Manager\|Caissier\|Comptable\|Agent Comptable\|Professeur` | `GET /admin/students/` (liste élèves) |
| **Attendance** | [`admin.php`](../../../Modules/Attendance/Routes/admin.php) | ❌ Aucun | `sessions/*/sheet`, `record`, `monitoring/students/*/history` |
| **Timetable** | [`admin.php`](../../../Modules/Timetable/Routes/admin.php) | ❌ Aucun | `views/teacher/{id}` — à filtrer par owner |
| **StructureAcademique** | [`admin.php`](../../../Modules/StructureAcademique/Routes/admin.php) | ❌ Aucun | Lecture seule sur classes/subjects (validation read-only) |
| **Exams** | [`admin.php`](../../../Modules/Exams/Routes/admin.php) | ⚠️ `auth:sanctum` (pas `tenant.auth`) | `supervision/teachers/{teacher}/schedule` — surveillance examens |

## 4. Périmètre fonctionnel

### Autorisé (cible cf. matrice README global §5.1)
- ✅ Dashboard enseignant (home `/admin/teacher/home`)
- ✅ Mes modules / mes classes (filtre owner sur `teacher_subject_assignments`)
- ✅ Saisie notes (`/api/frontend/teacher/grades/*`)
- ✅ Import notes batch (Excel/CSV)
- ✅ Marquer élèves absents à une évaluation + planifier rattrapage
- ✅ Présences en cours (sessions de pointage)
- ✅ Mon emploi du temps (lecture filtre owner)
- ✅ Surveillance examens — uniquement ceux où il est assigné
- ✅ Liste élèves de SES classes (read-only)
- ✅ Voir notes existantes d'un élève (read-only) pour préparer son cours

### Interdit
- ❌ Créer/modifier des utilisateurs (Admin/Manager only — `UsersGuard/admin.php:26`)
- ❌ Saisir des notes pour une matière/classe qui n'est PAS la sienne (filtre teacher_id requis dans Controller)
- ❌ Modifier une note déjà publiée (`is_published=true`) → 403 + workflow `request-correction`
- ❌ Voir/modifier la structure académique (années, semestres, séries, coefficients)
- ❌ Accéder à la Finance, Payroll (autres que sa propre fiche de paie — cf. story PortailParent ou settings)
- ❌ Accéder aux Documents officiels (transcripts, diplômes)
- ❌ Voir la liste des autres enseignants en mode édition

## 5. Stories planifiées (9 stories)

| # | Fichier | Menu cible | Endpoints principaux |
|---|---|---|---|
| 01 | `01-home-mes-classes.story.md` | Sidebar « Accueil » + « Mes classes » | `GET /api/frontend/teacher/my-modules` |
| 02 | `02-saisie-notes.story.md` | « Saisie des notes » | `GET evaluations/{id}/students`, `POST grades/batch`, `POST grades/auto-save` |
| 03 | `03-import-notes-batch.story.md` | « Import notes (Excel/CSV) » | `GET grades/import/template`, `POST grades/import/{validate,preview,execute}` |
| 04 | `04-absences-evaluations.story.md` | « Absents à l'évaluation » | `POST evaluations/{id}/absences/mark-absent`, `POST schedule-replacement` |
| 05 | `05-rattrapages.story.md` | « Rattrapages » | `GET retake-modules`, `POST retake-grades/batch` |
| 06 | `06-presences-cours.story.md` | « Présences en cours » | `GET attendance/sessions`, `POST attendance/record` (filtre owner) |
| 07 | `07-mon-emploi-du-temps.story.md` | « Mon emploi du temps » | `GET timetable/views/teacher/{me}` |
| 08 | `08-surveillance-examens.story.md` | « Mes surveillances » | `GET supervision/teachers/{me}/schedule`, `PUT supervisors/{id}/present` |
| 09 | `09-eleves-readonly.story.md` | « Élèves de mes classes » | `GET /admin/students/?class_id={mine}`, `GET enrollment/students/{id}` |

## 6. Dépendances backend (RBAC à durcir)

Aucune de ces fonctionnalités n'a aujourd'hui de garde `role:Professeur`. Les stories devront prescrire l'ajout systématique :

```php
Route::middleware(['tenant', 'tenant.auth', 'role:Professeur,tenant'])->group(function () {
    // routes prof
});
```

Et pour les filtres « ses » modules/classes : implémenter dans les controllers la clause `where('teacher_id', auth()->id())` — actuellement absente sur la plupart des endpoints `teacher.php`. Chaque story tracera la modification controller à faire.

**Endpoints supposés et à vérifier** (cités en §5) :
- `GET /api/frontend/teacher/my-modules` — ✅ existe (`teacher.php:7`)
- `POST /api/frontend/teacher/grades/batch` — ✅ existe (`teacher.php:32`)
- `POST /api/frontend/teacher/grades/import/execute` — ✅ existe (`teacher.php:41`)
- `GET /api/admin/teachers/` — ✅ existe (`UsersGuard/admin.php:53`)
- `GET /admin/attendance/sessions` filtré par owner — ⚠️ existe mais **pas de filtre owner**

## 7. Risques spécifiques

| # | Risque | Mitigation |
|---|---|---|
| P1 | Routes `teacher.php` accessibles à tout utilisateur authentifié | Ajouter `role:Professeur,tenant` ; tests E2E vérifient 403 pour Étudiant |
| P2 | Un prof peut saisir une note pour une matière d'un collègue (ownership manquant) | Filtre controller `where('teacher_id', auth()->id())` + tests croisés |
| P3 | Re-publication d'une note (`is_published`) sans audit | Story 02 ajoute un `GradeAuditLog` |
| P4 | Import 500 notes → timeout HTTP | Queue Laravel + endpoint `POST grades/import/status/{jobId}` (déjà prévu `teacher.php:43`) |

## 8. Definition of Done de l'epic Professeur

- [ ] 9 stories rédigées en Draft
- [ ] Pour chaque story : section « Dépendances backend » liste les `middleware('role:Professeur,tenant')` + filtres owner à ajouter
- [ ] Pour chaque story : section « Scenarios de test E2E » couvre au moins 1 happy path + 1 action interdite + 1 edge case
- [ ] Backlog des modifications RBAC consolidé en fin d'epic

## 9. Change log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
