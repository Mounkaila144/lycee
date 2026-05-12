# Story: Agent Comptable — Home / Dashboard Facturation - Coverage

**Module** : Finance
**Rôle ciblé** : Agent Comptable
**Menu(s) concerné(s)** : `/admin/finance/invoices`
**Status** : Ready for Review

## User Story

En tant qu'**Agent Comptable**, je veux atterrir sur un dashboard "facturation" qui me montre le total facturé du mois, le total impayé, les factures en retard, les relances à envoyer, afin d'organiser mes priorités quotidiennes.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Dashboard facturation (home) | ✅ | home_route `/admin/finance/invoices` |
| Factures | ✅ | CRUD |
| Échéanciers | ✅ | Création |
| Pénalités de retard | ✅ | Application |
| Recouvrement | ✅ | Relances + blocages |
| Encaissement | ❌ | Caissier |
| Refunds / Rapprochement | ❌ | Comptable |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir dashboard facturation | Page principale | `GET /admin/finance/reports/dashboard` (vue Agent Comptable) | — | 200 + KPIs facturation (pas trésorerie globale) | `role:Administrator\|Agent Comptable\|Comptable,tenant` |
| Voir statements impayés | Widget | `GET /admin/finance/reports/unpaid-statements` | filters | 200 | idem |
| Voir aging balance | Widget | `GET /admin/finance/reports/aging-balance` | — | 200 | idem |
| Rechercher élève | Autocomplete | `GET /admin/students/?search=` | `q` | 200 | rôle Agent Comptable inclus |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Saisir un paiement | `POST /admin/finance/payments` → **403** (Caissier/Comptable) |
| Refund | **403** |
| Rapprochement bancaire | **403** |
| Atterrir sur `/admin/dashboard` | Redirect vers `/admin/finance/invoices` |

## Cas limites (edge cases)

- **Mois sans facturation** : KPIs à 0.
- **Aucun impayé** : "À jour ✅".
- **Trop d'impayés** : pagination + tri par retard.
- **Token expiré** : redirect login.

## Scenarios de test E2E

1. **Login → Home** : login Agent Comptable → redirect `/admin/finance/invoices`.
2. **Sidebar conforme** : assert items factures/échéanciers/recouvrement visibles ; encaissement absent.
3. **Dashboard** : assert KPIs facturation visibles, pas la trésorerie.
4. **Action interdite — Saisir paiement** : `POST .../payments` → **403**.
5. **Action interdite — Refund** : `POST .../refund` → **403**.

## Dépendances backend

- ⚠️ **À ajouter** : `middleware('role:Administrator|Agent Comptable|Comptable,tenant')` sur `reports/dashboard`, `unpaid-statements`, `aging-balance`
- ⚠️ **À implémenter** : `FinanceReportController@dashboard` retourne payload filtré pour Agent Comptable
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable|Caissier,tenant')` sur `payments` (exclure Agent Comptable)

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Dashboard filtré par rôle
- [ ] Middlewares appliqués

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
