# Story: Parent — Factures de mon Enfant (consultation) - Coverage

**Module** : PortailParent + Finance
**Rôle ciblé** : Parent
**Menu(s) concerné(s)** : `/admin/parent/children/{id}/invoices`
**Status** : Ready for Review

## User Story

En tant que **Parent**, je veux consulter les factures de chaque enfant avec leur statut, leur échéancier et le solde, afin de gérer mes obligations financières. Le paiement en ligne est couvert dans la story 06.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Factures (enfant) | ✅ | `view children invoices` |
| Solde global (toutes factures) | ✅ | Widget header |
| Modification facture | ❌ | Agent Comptable |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir factures enfant | Page principale | `GET /api/admin/parent/children/{student}/invoices` (**À CRÉER**) | student, `status?` | 200 + liste | `role:Parent,tenant` + `ChildPolicy::viewInvoices` |
| Voir détail facture | Click ligne | `GET /api/admin/parent/children/{student}/invoices/{invoice}` (**À CRÉER**) | path | 200 + détail | idem |
| Voir échéancier | Onglet sur détail | inclus dans détail | — | — | idem |
| Voir reçus paiements passés | Onglet "Paiements" | `GET /api/admin/parent/children/{student}/payments` (**À CRÉER**) | student | 200 + liste | idem |
| Télécharger reçu PDF | Bouton "Télécharger reçu" | `GET /api/admin/parent/payments/{payment}/receipt` (**À CRÉER**) | payment (path) | 200 + PDF | `role:Parent,tenant` + filter owner (payment.student dans mes enfants) |
| Voir solde global tous enfants | Widget header | `GET /api/admin/parent/me/balance` (**À CRÉER**) | — | 200 + `{total_due, by_child: [...]}` | `role:Parent,tenant` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir factures d'un enfant non lié | `ChildPolicy::viewInvoices` → **403** |
| Créer / modifier / supprimer facture | Endpoints admin → **403** |
| Annuler / rembourser paiement | **403** |
| Voir rapports financiers globaux tenant | **403** |

## Cas limites (edge cases)

- **Plusieurs enfants, plusieurs factures** : sélecteur enfant + filtre statut.
- **Toutes payées** : badge ✅ + "À jour".
- **Facture en retard** : badge rouge + pénalité calculée.
- **Bourse / remise** : ligne visible.
- **Paiement en cours de validation** : status `processing` (Mobile Money pending).

## Scenarios de test E2E

1. **Voir factures enfant 1** : login Parent → sélectionner enfant → "Factures" → assert liste filtrée.
2. **Détail facture** : click → assert affichage lignes + échéancier.
3. **Voir solde global** : widget header → assert somme tous enfants.
4. **Reçu PDF** : "Télécharger reçu" → assert PDF.
5. **Action interdite — Enfant non lié** : `GET .../children/{other_kid}/invoices` → **403**.
6. **Action interdite — Créer facture** : `POST /admin/finance/invoices` → **403**.

## Dépendances backend

- ⚠️ **À créer** : tous les endpoints listés
- ⚠️ **À implémenter** : `ChildPolicy::viewInvoices`
- ⚠️ Bloque sur Story Parent 01
- ⚠️ Liaison : `payments.student_id` doit toujours être présent et indexé

## Definition of Done

- [ ] Endpoints créés
- [ ] Les 6 scénarios E2E passent
- [ ] Ownership testé
- [ ] Solde global calculé correctement (somme enfants)

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
