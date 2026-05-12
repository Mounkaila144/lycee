# Story: Administrator — Dashboard - Coverage

**Module** : Multi-modules (agrégateur)
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/dashboard`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux voir un dashboard complet et exhaustif (KPIs tous modules, alertes admin, vue trésorerie incluse), afin de piloter l'ensemble de l'établissement.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Dashboard | ✅ | home_route Admin |
| Tous les modules | ✅ | Accès complet |
| Toutes alertes système | ✅ | Vue complète |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir dashboard complet | Page | `GET /api/admin/dashboard` (à créer) | — | 200 + KPIs complets | `role:Administrator,tenant` |
| Voir tous les widgets | inclut KPIs de tous les modules | — | — | — | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Opérations cross-tenant | tenancy garantit (Admin tenant ≠ SuperAdmin) |
| Actions destructives sans confirmation | UI confirmation 2-étapes |

## Cas limites (edge cases)

- **Trop d'alertes** : pagination + filtres.
- **Performance** : caching Redis.

## Scenarios de test E2E

1. **Login → Home** : login Admin → redirect `/admin/dashboard`.
2. **Sidebar complète** : tous items visibles.
3. **Dashboard complet** : tous widgets chargés.
4. **Cross-tenant** : tenter `/admin/dashboard` avec token Admin tenant A vers tenant B → **403** (isolation).

## Dépendances backend

- ⚠️ **À créer** : agrégateur `/api/admin/dashboard`
- ⚠️ **À implémenter** : caching + invalidation
- ✅ Tenancy isolation déjà en place

## Definition of Done

- [ ] Les 4 scénarios E2E passent
- [ ] Cache opérationnel

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
