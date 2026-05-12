# Story: Agent Comptable — Pénalités de Retard - Coverage

**Module** : Finance
**Rôle ciblé** : Agent Comptable
**Menu(s) concerné(s)** : `/admin/finance/invoices/late-fees`
**Status** : Ready for Review

## User Story

En tant qu'**Agent Comptable**, je veux voir et appliquer des pénalités de retard sur les factures impayées au-delà de leur échéance, afin d'inciter au paiement et de couvrir les coûts administratifs.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Pénalités de retard | ✅ | Permission `manage late fees` |
| Configuration tarif pénalité | ✅ | Via fee-types / settings |
| Suppression pénalité | ⚠️ Limité | Avec audit |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir pénalités calculées sur facture | Onglet "Pénalités" | `GET /admin/finance/invoices/{id}/late-fees` | path | 200 + pénalités appliquées | `role:Administrator\|Agent Comptable\|Comptable,tenant` |
| Appliquer pénalité manuelle | Bouton "Ajouter pénalité" | `POST /admin/finance/invoices/{id}/late-fees` (**À CRÉER si pas existant**) | path, `amount`, `reason` | 201 + pénalité créée | idem |
| Voir liste factures en retard | Page liste | `GET /admin/finance/reports/aging-balance` ou `unpaid-statements` | filters | 200 | idem |
| Configurer politique pénalité | (Settings) | `GET/PUT /admin/finance/settings/late-fee-policy` (**À CRÉER**) | — | 200 | `role:Administrator\|Comptable,tenant` (Admin/Comptable plutôt qu'Agent Comptable pour politique) |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Supprimer pénalité auto-calculée | **403** + audit (impose passage Admin) |
| Désactiver pénalités pour un élève spécifique (sans bourse formelle) | **403** workflow remise |
| Modifier taux pénalité tenant | **403** réservé Admin/Comptable |
| Saisir paiement | **403** |

## Cas limites (edge cases)

- **Élève en bourse** : pénalité auto-désactivée (config).
- **Pénalité > montant facture** : plafonner à `max_late_fee_ratio` (config tenant, ex: 20%).
- **Échéancier en cours** : pénalité s'applique sur versements en retard uniquement.
- **Paiement reçu mais non rapproché** : pénalité gelée jusqu'au rapprochement.

## Scenarios de test E2E

1. **Voir pénalités calculées** : facture en retard 30 jours → assert pénalité visible et montant correct.
2. **Appliquer manuelle** : "Ajouter pénalité" 5 000 motif "Retard exceptionnel" → assert 201.
3. **Action interdite — Modifier politique** : `PUT .../settings/late-fee-policy` Agent Comptable → **403**.
4. **Edge — Plafond dépassé** : configurer ratio 20%, facture 10 000, pénalité auto = 3 000 → assert plafond respecté.
5. **Edge — Bourse** : élève en bourse → pénalité ignorée.

## Dépendances backend

- ✅ `GET /admin/finance/invoices/{id}/late-fees` existe
- ⚠️ **À créer** : `POST /admin/finance/invoices/{id}/late-fees` (manuelle)
- ⚠️ **À créer** : settings tenant `late_fee_policy` (rate, grace_period_days, max_ratio, exclude_scholarships)
- ⚠️ **À implémenter** : job programmé `CalculateLateFeesJob` (quotidien)
- ⚠️ **À ajouter** : middlewares

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Politique configurable et appliquée
- [ ] Job programmé opérationnel
- [ ] Audit log

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
