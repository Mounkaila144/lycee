# Story: Comptable — Vue d'Ensemble Finance - Coverage

**Module** : Finance
**Rôle ciblé** : Comptable
**Menu(s) concerné(s)** : `/admin/finance/reports/overview`
**Status** : Ready for Review

## User Story

En tant que **Comptable**, je veux explorer en détail les rapports financiers (journal de paiements, aging balance, factures impayées, prévisions, stats recouvrement), afin d'analyser la santé financière de l'établissement.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Journal paiements | ✅ | Lecture |
| Aging balance | ✅ | Lecture |
| Factures impayées (statements) | ✅ | Lecture |
| Prévisions cash flow | ✅ | Lecture |
| Stats recouvrement | ✅ | Lecture |
| Création/modif facture | ❌ | Agent Comptable |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir journal de paiements | Page | `GET /admin/finance/reports/payment-journal` | filters | 200 | `role:Administrator\|Comptable,tenant` |
| Voir aging balance | Page | `GET /admin/finance/reports/aging-balance` | filters | 200 | idem |
| Voir statements impayés | Page | `GET /admin/finance/reports/unpaid-statements` | filters | 200 | idem |
| Voir prévisions cash flow | Page | `GET /admin/finance/reports/cash-flow-forecast` | — | 200 | idem |
| Voir stats recouvrement | Page | `GET /admin/finance/reports/collection-statistics` | — | 200 | idem |
| Filtrer/exporter rapports | Bouton "Exporter" | `GET /admin/finance/reports/export/excel?report_type=X` ou `export/pdf` | filters | 200 + fichier | idem |
| Vider cache | Bouton "Rafraîchir données" | `POST /admin/finance/reports/clear-cache` | — | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Créer facture | **403** |
| Annuler paiement (refund) — via cette page | Doit passer par Story 04 (page dédiée Refunds) |
| Modifier les chiffres | Rapports lecture seule |

## Cas limites (edge cases)

- **Filtres très larges** : pagination + perf via caching.
- **Export volumineux** : queue Laravel + email notification quand prêt.
- **Date hors année active** : autorisé pour historique.

## Scenarios de test E2E

1. **Voir journal paiements** : assert tableau filtrable par date/moyen.
2. **Voir aging balance** : assert breakdown 0-30, 31-60, 61-90, 90+ jours.
3. **Exporter rapport Excel** : assert fichier reçu avec données filtrées.
4. **Vider cache** : assert cache flush + chiffres rafraîchis.
5. **Action interdite — Créer facture** : `POST .../invoices` → **403**.

## Dépendances backend

- ✅ Tous les endpoints rapports existent ([`Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable,tenant')` sur exports + cache + cashflow
- ⚠️ **À implémenter** : caching Redis sur rapports lourds
- ⚠️ **À implémenter** : queue export volumineux → notification email

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Caching opérationnel
- [ ] Exports queue testés (volume 10 000 lignes)

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
