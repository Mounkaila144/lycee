# Story: Administrator — Rôles & Permissions - Coverage

**Module** : UsersGuard
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/roles`, `/admin/permissions`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux gérer les rôles (créer/modifier/supprimer rôles tenant) et les permissions Spatie, afin de configurer le RBAC selon les besoins de l'établissement.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Rôles (CRUD) | ✅ | Admin only ([`UsersGuard/admin.php:71`](../../../Modules/UsersGuard/Routes/admin.php)) |
| Permissions (CRUD) | ✅ | Admin only ([`UsersGuard/admin.php:62`](../../../Modules/UsersGuard/Routes/admin.php)) |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister rôles | Page | `GET /admin/roles/` | — | 200 | `role:Administrator,tenant` |
| Créer rôle | "Nouveau rôle" | `POST /admin/roles/` | `name`, `display_name`, `permissions: array` | 201 | idem |
| Modifier rôle | "Modifier" | `PUT /admin/roles/{role}` | path + payload | 200 | idem |
| Supprimer rôle | "Supprimer" | `DELETE /admin/roles/{role}` | path | 200 | idem |
| Lister permissions | Page | `GET /admin/permissions/` | — | 200 | idem |
| Créer permission | "Nouvelle permission" | `POST /admin/permissions/` | `name`, `display_name?` | 201 | idem |
| Modifier permission | "Modifier" | `PUT /admin/permissions/{permission}` | path + payload | 200 | idem |
| Supprimer permission | "Supprimer" | `DELETE /admin/permissions/{permission}` | path | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Supprimer un rôle système (`Administrator`, `Manager`, etc. — protégés) | **422** "Rôle système protégé" |
| Supprimer une permission utilisée par > 1 rôle | **422** ou cascade avec confirmation |
| Modifier `guard_name` d'un rôle existant | **422** ou audit |
| Opérations cross-tenant | tenancy garantit |

## Cas limites (edge cases)

- **Rôles seedés** : système ne permet pas suppression `Administrator`, `Manager`, `Professeur`, `Étudiant`, `Caissier`, `Agent Comptable`, `Comptable`, `Parent` (à venir), `User`.
- **Permission utilisée** : avertissement avant suppression.
- **Création nouveau rôle custom** : OK (ex: `Surveillant Général` à créer).

## Scenarios de test E2E

1. **CRUD rôle custom** : créer "Surveillant Général" avec permissions → assert 201.
2. **Modifier rôle** : ajouter permission → assert 200.
3. **Supprimer rôle custom** : sans utilisateur → assert 200.
4. **Action interdite — Supprimer Admin role** : `DELETE /admin/roles/{admin_role}` → **422**.
5. **Action interdite — Manager** : login Manager `GET /admin/roles` → **403** ([`UsersGuard/admin.php:71`](../../../Modules/UsersGuard/Routes/admin.php) `role:Administrator` only).
6. **CRUD permission** : créer "manage exams" → assigner à rôle → tester effectif.

## Dépendances backend

- ✅ Middlewares déjà en place
- ⚠️ **À implémenter** : protection rôles système non supprimables
- ⚠️ **À implémenter** : audit log permissions/rôles
- ⚠️ Pour nouvelle permission utilisée dans middleware : recharger cache Spatie (`PermissionRegistrar::forgetCachedPermissions`)

## Definition of Done

- [ ] Les 6 scénarios E2E passent
- [ ] Rôles système protégés
- [ ] Audit log
- [ ] Cache Spatie invalidé après modification

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
