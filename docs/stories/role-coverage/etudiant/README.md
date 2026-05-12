# Epic Étudiant — Couverture des menus

> **Status** : Draft
> **Role slug** : `Étudiant` ([`RolesAndPermissionsSeeder.php:121`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php))
> **home_route** : `/admin/student/home` ([`config/role-routes.php:31`](../../../config/role-routes.php))
> **Position hiérarchique** : 7/8

## 1. Permissions Spatie attribuées

Source : `RolesAndPermissionsSeeder.php:124-131`

```php
$studentRole->syncPermissions([
    'view dashboard',
    'view own grades',
    'view own timetable',
    'upload documents',
    'request attestations',
    'view own attendance',
]);
```

> Comme pour le Professeur, ces permissions ne sont **pas** utilisées dans les routes — aucun `permission('view own grades')` n'est appliqué. Le filtre « own » repose entièrement sur les controllers (`auth()->user()->student_id`). À documenter explicitement dans chaque story.

## 2. Profil utilisateur

- Élève du secondaire (11-19 ans), pour beaucoup mineur — implications RGPD/protection mineurs (cf. `docs/architecture/security.md#Protection des Données de Mineurs`).
- Plateforme : majoritairement smartphone bas/milieu de gamme, parfois tablette familiale partagée.
- L'élève **ne paie pas** ses factures — c'est le rôle Parent. L'élève voit seulement ses factures et leur statut.
- L'élève peut uploader des justificatifs d'absence.

## 3. Backend coverage (modules touchés)

| Module | Route file | État RBAC | Endpoints clés |
|---|---|---|---|
| **Enrollment** | [`frontend.php`](../../../Modules/Enrollment/Routes/frontend.php) (142 lignes) | ❌ Pas de `role:` | `my-groups`, `my-enrollment/{status,history,contract}`, `my-card`, `reenrollment/*`, `transfer/*`, `exemption/*` |
| **NotesEvaluations** | — | ❌ | Endpoint élève « mes notes » **à créer** (préfixe `/api/frontend/student/`) |
| **Attendance** | [`admin.php`](../../../Modules/Attendance/Routes/admin.php) | ❌ | Route `monitoring/students/{id}/history` à filtrer par owner |
| **Timetable** | [`frontend.php`](../../../Modules/Timetable/Routes/frontend.php) (11 L stub) | — | **À créer** : `/api/frontend/student/timetable` |
| **Documents** | [`admin.php`](../../../Modules/Documents/Routes/admin.php) | ⚠️ `auth:sanctum` seul | Lecture de SES documents (bulletins, attestations) — owner filter manquant |
| **Finance** | [`admin.php`](../../../Modules/Finance/Routes/admin.php) | ❌ | Routes « mes factures » **à créer** — préfixe `/api/frontend/student/invoices` |
| **UsersGuard** | [`admin.php:17`](../../../Modules/UsersGuard/Routes/admin.php) | ✅ `tenant.auth` | `GET /admin/auth/me` (compte propre) |

## 4. Périmètre fonctionnel

### Autorisé (cible)
- ✅ Dashboard étudiant
- ✅ Mes notes / bulletins semestriels (lecture seule)
- ✅ Mon emploi du temps (filtre owner)
- ✅ Mes présences / absences (lecture)
- ✅ Mes factures (lecture — pas de paiement, c'est le parent)
- ✅ Mes documents officiels (bulletins, attestations, carte étudiante) — téléchargement
- ✅ Ma carte étudiante (QR code, infos, statut)
- ✅ Réinscription (campagnes ouvertes) — endpoint `/frontend/enrollment/reenrollment/*`
- ✅ Demande de transfert / dispense — endpoints `/frontend/enrollment/transfer/*` + `/exemption/*`
- ✅ Upload de documents (carte d'identité, certificat médical…) via `upload documents` permission

### Interdit
- ❌ Voir/modifier les données d'un autre élève (ownership critique)
- ❌ Accéder à la liste globale des élèves (`GET /admin/students/`)
- ❌ Saisir/modifier ses propres notes (read-only — fait par le prof)
- ❌ Payer une facture directement (Caissier/Comptable ou Parent)
- ❌ Accéder à toute interface admin (`/admin/users`, `/admin/enrollment/students` en mode CRUD)
- ❌ Accéder aux endpoints Documents Admin (génération de bulletins de masse, etc.)

## 5. Stories planifiées (9 stories)

| # | Fichier | Menu cible | Endpoints principaux |
|---|---|---|---|
| 01 | `01-home-portail.story.md` | « Accueil » | `GET /admin/auth/me`, `GET /api/frontend/student/dashboard` (à créer) |
| 02 | `02-mes-notes-bulletins.story.md` | « Mes notes » | `GET /api/frontend/student/grades` (à créer), bulletins |
| 03 | `03-mon-emploi-du-temps.story.md` | « Emploi du temps » | `GET /api/frontend/student/timetable` (à créer) |
| 04 | `04-mes-presences.story.md` | « Présences » | `GET attendance/monitoring/students/{me}/history` |
| 05 | `05-mes-factures-paiements.story.md` | « Mes factures » | `GET /api/frontend/student/invoices` (à créer — lecture seule) |
| 06 | `06-mes-documents.story.md` | « Documents » | `GET documents/students/{me}` (à créer), download |
| 07 | `07-ma-carte-etudiante.story.md` | « Ma carte » | `GET /frontend/enrollment/my-card/{show,history,download,qr-code}` |
| 08 | `08-reinscription.story.md` | « Réinscription » | `GET reenrollment/campaigns`, `POST reenrollment/`, `POST submit` |
| 09 | `09-transferts-equivalences.story.md` | « Transferts / Dispenses » | `GET transfer/programs`, `POST transfer/`, `POST exemption/` |

## 6. Dépendances backend (à créer / à durcir)

Endpoints **à créer** (préfixe `/api/frontend/student/...`) :
- `GET /api/frontend/student/dashboard` — KPI synthèse (moyenne, % présence, solde factures, EDT du jour)
- `GET /api/frontend/student/grades` — notes par semestre, moyennes, classement
- `GET /api/frontend/student/grades/{semester}/bulletin` — bulletin PDF
- `GET /api/frontend/student/timetable` — EDT semaine + exceptions
- `GET /api/frontend/student/invoices` — factures + statuts (read-only)
- `GET /api/frontend/student/documents` — liste docs disponibles
- `GET /api/frontend/student/documents/{id}/download` — téléchargement avec ownership check

Middlewares à appliquer systématiquement :
```php
Route::middleware(['tenant', 'tenant.auth', 'role:Étudiant,tenant'])->group(function () { ... });
```

Filtres owner à ajouter dans **tous** les controllers `/api/frontend/student/...` :
```php
$student = auth()->user()->student ?? abort(404, 'Profil étudiant introuvable');
// puis $student->grades(), $student->timetable(), etc.
```

## 7. Risques spécifiques

| # | Risque | Mitigation |
|---|---|---|
| E1 | Routes `/frontend/enrollment/my-*` accessibles à tout authentifié (R5 du README global) | Ajouter `role:Étudiant,tenant` + relier `auth()->user()` → `student_id` en controller |
| E2 | IDOR (Insecure Direct Object Reference) sur `documents/{id}/download` | Vérifier `$document->student_id === auth()->user()->student_id` |
| E3 | Donnée sensible (santé, discipline) exposée par mégarde | Liste blanche de champs dans `StudentSelfResource` (jamais d'`->toArray()` brut) |
| E4 | Élève mineur consultant l'app depuis l'appareil d'un parent → confusion compte | Affichage prénom utilisateur connecté + bouton logout visible sur toutes les pages |
| E5 | Paiement initié depuis l'élève par erreur | Bouton « Payer » **caché** côté Étudiant — appartient au Parent |

## 8. Definition of Done

- [ ] 9 stories rédigées en Draft
- [ ] Backlog des endpoints `/api/frontend/student/...` à créer consolidé
- [ ] Backlog ownership filters consolidé
- [ ] Tests E2E couvrent au moins 1 cas d'IDOR par story

## 9. Change log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
