# Story: Administrator — Utilisateurs (CRUD complet) - Coverage

**Module** : UsersGuard
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/users`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux gérer tous les utilisateurs (créer, modifier, supprimer, restaurer, attribuer rôles/permissions), afin de maintenir le contrôle total des accès tenant.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Utilisateurs (CRUD) | ✅ | Permission complète |
| Soft delete + Restore | ✅ | Admin only |
| Force delete (destructif) | ✅ | Admin only avec confirmation |
| Attribution rôles/permissions | ✅ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister | DataGrid | `GET /admin/users/` | filters | 200 | `role:Administrator,tenant` ([`UsersGuard/admin.php:26-32`](../../../Modules/UsersGuard/Routes/admin.php)) |
| Voir détail | Click | `GET /admin/users/{user}` | path | 200 | idem |
| Créer | "Nouveau" | `POST /admin/users/` | payload | 201 | idem |
| Modifier | "Modifier" | `PUT /admin/users/{user}` | path + payload | 200 | idem |
| Soft delete | "Supprimer" + confirm | `DELETE /admin/users/{user}` | path | 200 | `role:Administrator,tenant` ([`UsersGuard/admin.php:33`](../../../Modules/UsersGuard/Routes/admin.php)) |
| Restaurer | "Restaurer" sur soft-deleted | `POST /admin/users/{user}/restore` | path | 200 | idem |
| Force delete (destructif) | "Suppression définitive" + 2-step confirm | `DELETE /admin/users/{user}/force` | path | 200 | idem |
| Ajouter permission | "Permissions" → "Ajouter" | `POST /admin/users/{user}/permissions/add` | `permissions: array` | 200 | idem |
| Retirer permission | "Permissions" → "Retirer" | `POST /admin/users/{user}/permissions/remove` | path | 200 | idem |
| Synchroniser permissions | bulk | `POST /admin/users/{user}/permissions/sync` | `permissions: array` | 200 | idem |
| Ajouter rôle | "Rôles" → "Ajouter" | `POST /admin/users/{user}/roles/add` | `roles: array` | 200 | idem |
| Retirer rôle | idem | `POST /admin/users/{user}/roles/remove` | path | 200 | idem |
| Synchroniser rôles | bulk | `POST /admin/users/{user}/roles/sync` | `roles: array` | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Opérations sur tenants (cross-tenancy) | SuperAdmin only |
| Modifier son propre rôle (auto-escalade impossible) | Backend valide `$user->id !== auth()->id() OR no role change` |
| Force delete d'un autre Admin sans seconde confirmation | UI 2-step + audit log |

## Cas limites (edge cases)

- **Dernier Admin** : impossible de se delete soi-même (au moins 1 Admin actif requis).
- **Reset password** : workflow email/SMS séparé (à confirmer endpoint existant).
- **Lockout** : compte verrouillé après échecs login (param tenant).
- **Audit log obligatoire** : toute action delete/restore/permissions/roles enregistrée.

## Scenarios de test E2E

1. **CRUD complet** : créer → modifier → soft delete → restaurer → assert tous OK.
2. **Force delete** : confirm 2-step → assert user purgé.
3. **Attribuer rôle Professeur** : `POST roles/add` → assert role assigné.
4. **Attribuer permission `manage grades`** : `POST permissions/add` → assert.
5. **Action interdite — Modifier mon rôle** : `PUT /admin/users/{me}` avec role différent → **422** ou backend ignore.
6. **Action interdite — Delete dernier Admin** : si je suis seul Admin → tenter `DELETE` → **422** "Au moins 1 Admin requis".
7. **Cross-tenant** : token Admin tenant A → `GET /admin/users/{user_of_tenant_B}` → **404** (isolation).

## Dépendances backend

- ✅ Toutes les routes `users` ont déjà les bons middlewares ([`UsersGuard/admin.php:26-49`](../../../Modules/UsersGuard/Routes/admin.php))
- ⚠️ **À implémenter** : protection auto-escalade
- ⚠️ **À implémenter** : "dernier Admin" protection
- ⚠️ **À implémenter** : audit log

## Definition of Done

- [ ] Les 7 scénarios E2E passent
- [ ] Protections auto-escalade + dernier Admin testées
- [ ] Audit log complet

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
