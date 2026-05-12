# Story: Étudiant — Mes Factures & Paiements (lecture seule) - Coverage

**Module** : Finance
**Rôle ciblé** : Étudiant
**Menu(s) concerné(s)** : `/admin/student/invoices`
**Status** : Ready for Review

## User Story

En tant qu'**Étudiant**, je veux consulter mes factures et leur statut (payée, partielle, impayée, en retard), afin de savoir où j'en suis financièrement vis-à-vis de l'établissement. **L'étudiant ne paie PAS lui-même** (c'est le Parent, voir epic Parent story 06).

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Mes factures | ✅ | Lecture filtrée owner |
| Mes reçus de paiement | ✅ | Lecture |
| Payer en ligne | ❌ | Bouton "Payer" caché côté Étudiant (réservé Parent) |
| Édition / création facture | ❌ | Agent Comptable |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mes factures | Page principale | `GET /api/frontend/student/invoices` (**À CRÉER**) | `status?` | 200 + liste filtrée `student_id = me` | `role:Étudiant,tenant` |
| Voir détail facture | Click ligne | `GET /api/frontend/student/invoices/{invoice}` (**À CRÉER**) | invoice (path) | 200 + détail | idem (filter owner) |
| Voir échéancier | Onglet "Échéancier" | inclus dans détail | — | — | idem |
| Voir mes paiements/reçus | Onglet "Paiements" | `GET /api/frontend/student/payments` (**À CRÉER**) | — | 200 + liste | idem |
| Télécharger reçu PDF | Bouton "Télécharger reçu" | `GET /api/frontend/student/payments/{payment}/receipt` (**À CRÉER**) | payment (path) | 200 + PDF | idem (filter owner) |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir factures d'un autre élève | Endpoint ignore tout `student_id` query — toujours owner |
| Bouton "Payer" / endpoint pay | **Pas d'item** côté Étudiant. Endpoint `POST /api/admin/parent/.../pay` est rôle Parent uniquement |
| Créer / modifier / supprimer facture | Endpoints `/admin/finance/invoices` → **403** |
| Voir rapports financiers globaux | **403** |
| Annuler / rembourser paiement | **403** |

## Cas limites (edge cases)

- **Aucune facture** : "Aucune facture émise pour le moment".
- **Toutes payées** : badge ✅.
- **Facture en retard** : badge rouge + montant majoré (pénalité de retard).
- **Bourse / remise partielle** : ligne "Remise appliquée" visible.
- **Paiement en attente de validation** : statut `processing` (mobile money en cours).

## Scenarios de test E2E

1. **Voir mes factures** : login Étudiant → "Mes factures" → assert liste filtrée owner.
2. **Détail facture** : click → assert affichage des lignes + échéancier.
3. **Reçu PDF** : cliquer "Télécharger reçu" sur paiement → assert PDF.
4. **Action interdite — Autre élève** : `GET /api/frontend/student/invoices?student_id=99` → assert ignoré.
5. **Action interdite — Payer** : assert bouton "Payer" absent du DOM ; tenter `POST .../pay` → **403** (Parent only).
6. **Action interdite — Endpoint admin** : `POST /admin/finance/invoices` → **403**.

## Dépendances backend

- ⚠️ **À créer** : `GET /api/frontend/student/invoices` (filtrage owner)
- ⚠️ **À créer** : `GET /api/frontend/student/invoices/{invoice}`
- ⚠️ **À créer** : `GET /api/frontend/student/payments`
- ⚠️ **À créer** : `GET /api/frontend/student/payments/{payment}/receipt` (PDF)
- ⚠️ **À implémenter** : ownership strict (student_id = me)
- ⚠️ **À implémenter** : middleware role finance qui exclut Étudiant
- ⚠️ Dépend de Story Parent 06 (paiement en ligne) — l'étudiant a accès au flux UI mais "Payer" est masqué pour lui

## Definition of Done

- [ ] Endpoints créés
- [ ] Les 6 scénarios E2E passent
- [ ] Bouton "Payer" caché côté UI Étudiant
- [ ] PDF reçu généré

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
