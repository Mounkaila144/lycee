# Story: Comptable — Remboursements (Refunds) - Coverage

**Module** : Finance
**Rôle ciblé** : Comptable
**Menu(s) concerné(s)** : `/admin/finance/payments/refunds`
**Status** : Ready for Review

## User Story

En tant que **Comptable**, je veux rembourser un paiement (totalement ou partiellement) avec un motif documenté et un audit complet, afin de gérer les cas spécifiques (départ d'élève, double paiement, erreur de saisie).

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Refunds | ✅ | `manage refunds` |
| Historique refunds | ✅ | Audit |
| Validation 2-étapes (>seuil) | ✅ | Double validation Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Initier refund | Bouton sur paiement → "Rembourser" | `POST /admin/finance/payments/{id}/refund` | path, `amount` (≤ paid), `reason`, `refund_method?` | 201 + refund draft (ou direct si < seuil) | `role:Administrator\|Comptable,tenant` |
| Approuver refund > seuil | Approbation Admin | `POST /admin/finance/payments/refunds/{refund}/approve` (**À CRÉER si pas existant**) | path | 200 + refund `approved` | `role:Administrator,tenant` |
| Voir historique refunds | Onglet | `GET /admin/finance/payments?type=refund` | filters | 200 + liste | `role:Administrator\|Comptable,tenant` |
| Voir détail refund | Click | (inclus dans `/payments/{id}`) | path | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Refund par Caissier ou Agent Comptable | **403** |
| Refund > montant initial du paiement | **422** validation |
| Refund sans motif (raison vide) | **422** |
| Refund sur paiement déjà entièrement remboursé | **422** |
| Refund sur paiement en cours de validation gateway (status processing) | **422** |
| Bypass validation 2-étapes pour montants importants | Workflow obligatoire |

## Cas limites (edge cases)

- **Refund Mobile Money** : initiation API gateway requise (différent d'un refund espèces).
- **Refund > 100 000 CFA** : workflow 2 étapes (Comptable initie → Admin valide).
- **Refund partiel** : suit le solde restant remboursable.
- **Refund vers banque** : `refund_method=bank_transfer` avec RIB requis.
- **Doublon refund (idempotence)** : `idempotency_key` requis.

## Scenarios de test E2E

1. **Refund total** : login Comptable → paiement 50 000 → "Rembourser" → motif "Erreur saisie" → assert 201 + facture re-due.
2. **Refund partiel** : 20 000 sur 50 000 → assert 201 + paiement marqué `partially_refunded`.
3. **Workflow 2 étapes** : refund 200 000 (>seuil 100 000) → assert status `pending_approval` → Admin valide → assert `approved`.
4. **Action interdite — Caissier refund** : login Caissier `POST .../refund` → **403**.
5. **Action interdite — Refund > montant** : tenter rembourser 60 000 sur paiement 50 000 → **422**.
6. **Action interdite — Sans motif** : reason vide → **422**.
7. **Edge — Mobile Money refund** : gateway API call → mock success/échec.

## Dépendances backend

- ✅ `POST /admin/finance/payments/{id}/refund` existe
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable,tenant')` sur refund
- ⚠️ **À créer** : workflow 2 étapes + endpoint approve
- ⚠️ **À implémenter** : seuil configurable tenant `refund_approval_threshold`
- ⚠️ **À implémenter** : intégration refund gateway (CinetPay/Stripe selon Story Parent 06)
- ⚠️ **À implémenter** : audit log obligatoire (qui, quand, motif)

## Definition of Done

- [ ] Les 7 scénarios E2E passent
- [ ] Workflow 2 étapes opérationnel
- [ ] Audit log complet
- [ ] Refund gateway testé en sandbox

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
