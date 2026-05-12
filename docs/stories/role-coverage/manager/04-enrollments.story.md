# Story: Manager — Inscriptions (Élèves CRUD limité) - Coverage

**Module** : Enrollment
**Rôle ciblé** : Manager
**Menu(s) concerné(s)** : `/admin/enrollment/students`
**Status** : Ready for Review

## User Story

En tant que **Manager**, je veux gérer les inscriptions des élèves (créer, modifier, lister, exporter) sans pouvoir supprimer définitivement, afin de tenir le registre des élèves au jour le jour.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Inscriptions / Élèves | ✅ | Lecture + écriture (sauf delete) |
| Import CSV élèves | ✅ | Volume rentrée |
| Status élève (changer) | ✅ | Workflow géré |
| Suppression définitive | ❌ | Admin |
| Documents (génération) | ✅ | Story 08 |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister élèves | DataGrid | `GET /admin/enrollment/students` | filters | 200 | `role:Administrator\|Manager,tenant` (à ajouter) |
| Voir détail élève | Click | `GET /admin/enrollment/students/{student}` | path | 200 + détail | idem |
| Créer élève | "Nouveau" | `POST /admin/enrollment/students` | payload | 201 | idem |
| Modifier élève | "Modifier" | `PUT /admin/enrollment/students/{student}` | path + payload | 200 | idem |
| Changer statut (actif → transféré, etc.) | Bouton | `POST /admin/enrollment/students/{student}/status` | path, `new_status`, `reason` | 200 | idem |
| Voir historique statuts | Onglet | `GET /admin/enrollment/students/{student}/status/history` | path | 200 | idem |
| Importer CSV (workflow preview→confirm) | Wizard | `POST .../students/import/preview` + `confirm` | files + uuid | 201 | idem |
| Exporter liste | "Exporter" | `GET .../students/export` | filters | 200 + xlsx | idem |
| Vérifier doublons | API | `POST .../students/check-duplicates` | name+birthdate | 200 + liste | idem |
| Audit log élève | Onglet | `GET .../students/{student}/audit-log` | path | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Suppression définitive d'un élève | `DELETE /admin/enrollment/students/{student}` → **403** (réservé Admin) |
| Gestion options/groupes/transferts/equivalences | Stories LMD-héritage non utilisées par Manager — **403** ou items cachés |
| Modifier statut sans motif | **422** validation `reason` |
| Cross-tenant access | `tenant.auth` garantit |

## Cas limites (edge cases)

- **Import CSV avec doublons** : preview signale + Manager choisit (skip / update / new).
- **Statut "Exclu"** : nécessite double validation Admin (workflow).
- **Élève sans parents liés** : alerte UI pour création compte parent (cf. story 7.6 du backlog Inscriptions).
- **Photo manquante** : OK ; relancer plus tard.

## Scenarios de test E2E

1. **Créer élève** : login Manager → "Nouveau" → payload → assert 201.
2. **Modifier** : `PUT` → assert 200.
3. **Import CSV** : preview 100 lignes (10 doublons) → résolution → confirm → assert 90 nouveaux + 10 mis à jour.
4. **Changer statut** : Transférer un élève → assert `student_status_history` entry.
5. **Action interdite — Delete** : `DELETE .../students/{id}` → **403**.
6. **Action interdite — Options LMD** : `POST .../options` → **403**.

## Dépendances backend

- ❌ Aucun middleware role sur `Modules/Enrollment/Routes/admin.php` (route 438 lignes)
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager,tenant')` sur students GET/POST/PUT, import, status
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur DELETE students
- ⚠️ **À implémenter** : politique double validation pour status "Exclu"
- ⚠️ Cleanup LMD-heritage (cf. Story 7.1) : options/groupes/transfers à désactiver pour Manager

## Definition of Done

- [ ] Les 6 scénarios E2E passent
- [ ] Middlewares appliqués
- [ ] Workflow status "Exclu" testé

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
