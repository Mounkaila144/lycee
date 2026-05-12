# Story: Agent Comptable — Échéanciers de Paiement - Coverage

**Module** : Finance
**Rôle ciblé** : Agent Comptable
**Menu(s) concerné(s)** : `/admin/finance/invoices/{id}/payment-schedule`
**Status** : Ready for Review

## User Story

En tant qu'**Agent Comptable**, je veux créer un échéancier de paiement sur une facture (ex: 3 versements sur 3 mois), afin d'aider les familles à étaler leurs paiements et réduire les impayés.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Échéanciers (depuis facture) | ✅ | Endpoint payment-schedule |
| Plans de paiement (recouvrement) | ✅ | Endpoint plus généraliste |
| Modification d'un plan en cours | ⚠️ Limité | Selon politique |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Créer échéancier sur facture | Onglet facture → "Créer échéancier" | `POST /admin/finance/invoices/{id}/payment-schedule` | path, `schedule: [{amount, due_date}]` | 201 + planning créé | `role:Administrator\|Agent Comptable\|Comptable,tenant` |
| Voir échéancier d'une facture | inclus dans détail facture | (lecture) | — | 200 | idem |
| Créer plan paiement (cas recouvrement) | Page "Recouvrement" → bouton "Plan de paiement" | `POST /admin/finance/collection/payment-plans` | `invoice_id`, `installments` | 201 | idem |
| Modifier dates d'un échéancier non démarré | Édition | (à confirmer endpoint — actuellement `payment-schedule` POST only) | — | — | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Créer échéancier sur facture déjà partiellement payée sans recalcul | **422** "Préciser politique recalcul" |
| Total échéancier différent du montant facture | **422** validation |
| Échéances dans le passé | **422** |
| Modifier un plan déjà actif après premier paiement | **403** ou nécessite workflow Admin |
| Saisir paiement | **403** (Caissier) |

## Cas limites (edge cases)

- **Élève en bourse partielle** : montant total = facture - remise.
- **Politique tenant — nombre max d'échéances** (ex: 6) : 422 si dépassement.
- **Premier versement immédiat** : flag `first_installment_now=true` → générer paiement attendu = aujourd'hui.
- **Lissage automatique** : option "Diviser en N parts égales".

## Scenarios de test E2E

1. **Créer échéancier 3 mois** : login Agent Comptable → facture 60 000 → "Créer échéancier" → 3 versements 20 000 → assert 201 + 3 lignes en DB.
2. **Plan paiement recouvrement** : facture en retard → "Plan paiement" → 5 versements → assert 201.
3. **Action interdite — Total ≠ facture** : tenter 3 × 18 000 sur facture 60 000 → **422**.
4. **Action interdite — Échéance passée** : due_date hier → **422**.
5. **Action interdite — Modifier plan actif** : tenter `PUT` sur plan avec 1er paiement reçu → **403** ou 422.

## Dépendances backend

- ✅ `POST /admin/finance/invoices/{id}/payment-schedule` existe
- ✅ `POST /admin/finance/collection/payment-plans` existe
- ⚠️ **À ajouter** : `middleware('role:Administrator|Agent Comptable|Comptable,tenant')`
- ⚠️ **À implémenter** : validation total = facture
- ⚠️ **À implémenter** : verrou modification après 1er paiement (selon politique)
- ⚠️ **À implémenter** : tenant settings `max_installments` configurable

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Validations testées
- [ ] Politique max_installments respectée

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
