# Story: Manager — Finance (Lecture rapports) - Coverage

**Module** : Finance
**Rôle ciblé** : Manager
**Menu(s) concerné(s)** : `/admin/finance/reports`
**Status** : Ready for Review

## User Story

En tant que **Manager**, je veux consulter les rapports finance synthétiques (sans détail trésorerie), afin de comprendre la santé financière au quotidien sans aller jusqu'au détail Comptable.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Finance — Rapports (lecture) | ✅ | Lecture seule |
| Création/édition facture | ❌ | Agent Comptable |
| Encaissement | ❌ | Caissier |
| Refunds / Rapprochement | ❌ | Comptable |
| Exports comptables | ❌ | Comptable |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir dashboard finance (synthétique) | Page | `GET /admin/finance/reports/dashboard` (filtré Manager) | — | 200 + KPIs limités | `role:Administrator\|Manager,tenant` (à ajouter) |
| Voir résumé général | Widget | `GET /admin/finance/reports/summary` | — | 200 | idem |
| Voir aging balance (synthèse) | Widget | `GET /admin/finance/reports/aging-balance` | — | 200 (sans détail élève) | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Créer/modifier facture | **403** |
| Saisir paiement | **403** |
| Refund | **403** |
| Rapprochement bancaire | **403** |
| Exports comptables / cashflow | **403** |
| Voir trésorerie globale détaillée | **403** ou filtre |

## Cas limites (edge cases)

- **Données absentes** : KPIs à 0.
- **Manager curieux du détail** : redirection "Demandez au Comptable".

## Scenarios de test E2E

1. **Voir dashboard finance** : login Manager → "Finance" → assert KPIs synthétiques visibles.
2. **Action interdite — Créer facture** : `POST /admin/finance/invoices` → **403**.
3. **Action interdite — Refund** : `POST .../refund` → **403**.
4. **Action interdite — Rapprochement** : `GET .../reconciliation/data` → **403**.
5. **Action interdite — Cash flow** : `GET .../cash-flow-forecast` → **403** (ou filtré).

## Dépendances backend

- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager|Comptable,tenant')` sur dashboard/summary/aging (lecture)
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable,tenant')` sur cashflow, accounting-export, reconciliation, refund (exclure Manager)
- ⚠️ **À implémenter** : payload `dashboard` filtré par rôle (Manager = synthèse, Comptable = complet)

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Payload dashboard différencié par rôle
- [ ] Manager bloqué sur actions finance

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
