# Story: Comptable — Home / Rapports Financiers - Coverage

**Module** : Finance
**Rôle ciblé** : Comptable
**Menu(s) concerné(s)** : `/admin/finance/reports`
**Status** : Ready for Review

## User Story

En tant que **Comptable**, je veux atterrir sur un dashboard de **rapports financiers** (trésorerie, recouvrement, prévisions, exports comptables disponibles), afin d'avoir une vue 360° sur les finances de l'établissement.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Rapports (Home) | ✅ | home_route `/admin/finance/reports` |
| Vue d'ensemble | ✅ | Story 02 |
| Rapprochement bancaire | ✅ | Story 03 |
| Refunds | ✅ | Story 04 |
| Exports comptables | ✅ | Story 05 |
| Paie (lecture) | ✅ | Story 06 |
| Création factures | ❌ | Agent Comptable |
| Saisie notes / Présences | ❌ | Hors finance |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir dashboard complet | Page principale | `GET /admin/finance/reports/dashboard` | — | 200 + KPIs **complets** (y compris trésorerie) | `role:Administrator\|Comptable,tenant` |
| Voir résumé général | Widget | `GET /admin/finance/reports/summary` | — | 200 | idem |
| Voir prévisions cash flow | Widget | `GET /admin/finance/reports/cash-flow-forecast` | — | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Créer une facture | `POST /admin/finance/invoices` → **403** (Agent Comptable) |
| Saisir notes / présences | **403** |
| Gérer Users | **403** |
| Atterrir sur `/admin/dashboard` | Redirect vers `/admin/finance/reports` |

## Cas limites (edge cases)

- **Mois sans activité** : KPIs à 0.
- **Volume très important** : caching API + cache invalidation manuel via `POST .../reports/clear-cache`.

## Scenarios de test E2E

1. **Login → Home** : login Comptable → redirect `/admin/finance/reports`.
2. **Sidebar conforme** : assert items finance complets ; absent : création facture, saisie notes.
3. **Dashboard complet** : assert response contient `treasury_balance`, `cash_flow_forecast` (vs Caissier qui les a filtrés).
4. **Action interdite — Créer facture** : `POST .../invoices` → **403**.
5. **Action interdite — Saisie note** : **403**.

## Dépendances backend

- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable,tenant')` sur `reports/*` endpoints sensibles (cash_flow, accounting_export)
- ⚠️ **À implémenter** : `FinanceReportController@dashboard` détecte rôle et inclut/exclut champs selon Caissier/AgentComptable/Comptable
- ⚠️ **À ajouter** : `middleware('role:Administrator|Agent Comptable|Comptable,tenant')` sur création factures (exclure Comptable du write si politique le veut — à confirmer)

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Dashboard différencié par rôle
- [ ] Caching opérationnel

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
