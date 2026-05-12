# Story: Manager — Dashboard - Coverage

**Module** : Multi-modules (agrégateur)
**Rôle ciblé** : Manager
**Menu(s) concerné(s)** : `/admin/dashboard`
**Status** : Ready for Review

## User Story

En tant que **Manager**, je veux voir un dashboard avec les KPIs opérationnels de l'établissement (effectifs, taux de présence, factures à jour, alertes notes), afin de piloter quotidiennement sans entrer dans chaque module.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Dashboard | ✅ | home_route Manager |
| Tous les modules en lecture | ✅ | Voir transverse |
| Utilisateurs (sans delete) | ✅ | Story 02 |
| Rôles & Permissions | ❌ | Admin uniquement |
| Réglages tenant | ❌ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir dashboard agrégé | Page principale | `GET /api/admin/dashboard` (**À CRÉER** ou agrégateur existant) | — | 200 + KPIs multi-modules | `role:Administrator\|Manager,tenant` |
| Voir effectifs | Widget | `GET /admin/enrollment/students/statistics/summary` | — | 200 | idem |
| Voir KPIs notes | Widget | `GET /api/admin/grades/statistics` (à vérifier endpoint exact) | — | 200 | idem |
| Voir KPIs présence | Widget | `GET /admin/attendance/reports/statistics` | — | 200 | idem |
| Voir KPIs finance (lecture) | Widget | `GET /admin/finance/reports/summary` | — | 200 (sans champs sensibles) | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir Rôles/Permissions | Pas d'item menu + `/admin/roles`, `/admin/permissions` → **403** ([`UsersGuard/admin.php:62-77`](../../../Modules/UsersGuard/Routes/admin.php) `role:Administrator` seul) |
| Modifier paramètres tenant | **403** |
| Atterrir sur `/admin/teacher/home` | Redirect basé sur `home_routes` Manager = `/admin/dashboard` |

## Cas limites (edge cases)

- **Tenant fraîchement créé (aucune donnée)** : widgets "Aucune donnée" + CTA "Inviter les premiers utilisateurs".
- **Année non active** : bandeau "Aucune année scolaire active — créer (Admin)".
- **Performance** : KPIs cachés 5 minutes via Redis.

## Scenarios de test E2E

1. **Login → Home** : login Manager → redirect `/admin/dashboard`.
2. **Sidebar conforme** : items présents conformes au README §3 ; absent : Rôles/Permissions, Settings.
3. **Dashboard KPIs** : assert 4 widgets chargés.
4. **Action interdite — Rôles** : `GET /admin/roles` → **403**.
5. **Action interdite — Permissions** : `GET /admin/permissions` → **403**.

## Dépendances backend

- ⚠️ **À créer** : agrégateur `/api/admin/dashboard` (ou utiliser widgets séparés)
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager,tenant')` sur endpoints stats lecture
- ✅ Routes `roles/permissions` déjà gardées `Administrator` only ([`UsersGuard/admin.php:62,71`](../../../Modules/UsersGuard/Routes/admin.php))

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Sidebar Manager filtrée
- [ ] Cache 5 minutes opérationnel

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
