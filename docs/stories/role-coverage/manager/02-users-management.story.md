# Story: Manager — Utilisateurs (sans Delete) - Coverage

**Module** : UsersGuard
**Rôle ciblé** : Manager
**Menu(s) concerné(s)** : `/admin/users`
**Status** : Ready for Review

## User Story

En tant que **Manager**, je veux créer, modifier, lister les comptes utilisateurs (Enseignants, Caissiers, Élèves, Parents) sans pouvoir les supprimer définitivement, afin de gérer les arrivées/départs RH au quotidien tout en laissant la décision destructive à l'Admin.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Utilisateurs | ✅ | `view/create/edit users` |
| Détail utilisateur | ✅ | Lecture |
| Suppression utilisateur | ❌ | Admin only |
| Restauration / Force delete | ❌ | Admin |
| Gestion rôles/permissions utilisateur | ❌ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister utilisateurs | DataGrid | `GET /admin/users/` | filters | 200 | `role:Administrator\|Manager,tenant` ([`UsersGuard/admin.php:26`](../../../Modules/UsersGuard/Routes/admin.php)) |
| Voir détail utilisateur | Click ligne | `GET /admin/users/{user}` | path | 200 | idem |
| Créer utilisateur | Bouton "Nouveau" → formulaire | `POST /admin/users/` | `username`, `email`, `password`, `firstname`, `lastname`, `role` | 201 | idem |
| Modifier utilisateur | Bouton "Modifier" | `PUT /admin/users/{user}` | path + payload | 200 | idem |
| Désactiver compte (soft) | Bouton "Désactiver" (`is_active=false`) | `PUT /admin/users/{user}` avec payload `is_active:false` | path | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Supprimer un utilisateur | `DELETE /admin/users/{user}` → **403** ([`UsersGuard/admin.php:33`](../../../Modules/UsersGuard/Routes/admin.php) `role:Administrator` seul) |
| Restore après delete | `POST /admin/users/{user}/restore` → **403** |
| Force delete | `DELETE /admin/users/{user}/force` → **403** |
| Ajouter/retirer permissions à un user | `POST /admin/users/{user}/permissions/*` → **403** |
| Ajouter/retirer rôles à un user | `POST /admin/users/{user}/roles/*` → **403** |
| Modifier son propre rôle (escalade) | Backend valide `$user->id !== auth()->id() OR no role change` |

## Cas limites (edge cases)

- **Création doublon username** : 422.
- **Email déjà utilisé** : 422.
- **Manager créant un Administrator** : validation backend → 422 (Manager ne peut pas créer plus haut hiérarchique) — à confirmer politique.
- **Mot de passe faible** : 422 + politique tenant.
- **Force lockout après 5 échecs login** : (auth, hors story).

## Scenarios de test E2E

1. **Liste users** : login Manager → "Utilisateurs" → assert DataGrid.
2. **Créer Enseignant** : "Nouveau" → role=Professeur → assert 201.
3. **Modifier** : `PUT /admin/users/{user}` → assert 200.
4. **Désactiver compte** : assert `is_active=false`.
5. **Action interdite — Delete** : `DELETE /admin/users/{user}` → **403**.
6. **Action interdite — Permissions** : `POST .../permissions/add` → **403**.
7. **Action interdite — Roles** : `POST .../roles/add` → **403**.
8. **Action interdite — Modifier soi-même role** : `PUT /admin/users/{me}` avec role changé → **422** ou **403**.

## Dépendances backend

- ✅ Middleware `role:Administrator|Manager` actif sur `/users` CRUD ([`UsersGuard/admin.php:26`](../../../Modules/UsersGuard/Routes/admin.php))
- ✅ Middleware `role:Administrator` seul sur delete/restore/force/permissions/roles ([`UsersGuard/admin.php:32-47`](../../../Modules/UsersGuard/Routes/admin.php))
- ⚠️ **À implémenter** : validation `Manager ne peut pas créer un Administrator` (politique)
- ⚠️ **À implémenter** : interdiction de se modifier soi-même son rôle

## Definition of Done

- [ ] Les 8 scénarios E2E passent
- [ ] Auto-escalade impossible (test)
- [ ] Politique création hiérarchique appliquée

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
