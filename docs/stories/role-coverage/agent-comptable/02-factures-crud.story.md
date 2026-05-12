# Story: Agent Comptable — Factures CRUD - Coverage

**Module** : Finance
**Rôle ciblé** : Agent Comptable
**Menu(s) concerné(s)** : `/admin/finance/invoices`
**Status** : Ready for Review

## User Story

En tant qu'**Agent Comptable**, je veux créer, modifier et générer des factures (individuelles ou par lot), gérer les types de frais (fee-types), afin de facturer correctement les familles selon les barèmes officiels.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Factures (CRUD) | ✅ | Action principale |
| Types de frais (`fee-types`) | ✅ | Configuration barèmes |
| Génération automatique | ✅ | Endpoint dédié |
| Encaissement / Refund | ❌ | Hors rôle |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister factures | DataGrid | `GET /admin/finance/invoices/` | filters | 200 + liste | `role:Administrator\|Agent Comptable\|Comptable,tenant` (à ajouter) |
| Créer facture | Bouton "Nouvelle facture" | `POST /admin/finance/invoices/` | `student_id`, `fee_type_id`, `amount`, `due_date`, `description?` | 201 + facture | idem |
| Modifier facture | Bouton "Modifier" | `PUT /admin/finance/invoices/{id}` | path + payload | 200 | idem (avec restrictions selon paiements) |
| Supprimer facture (si non payée) | Bouton "Supprimer" | `DELETE /admin/finance/invoices/{id}` | path | 200 ou 422 | idem (Admin OR Agent OR Comptable) |
| Générer factures auto par lot | Bouton "Générer en lot" → wizard | `POST /admin/finance/invoices/generate-automated` | `class_id?`, `cycle_id?`, `fee_type_ids: array`, `due_date` | 201 + count créées | idem |
| Lister fee-types | Page "Types de frais" | `GET /admin/finance/fee-types/` | — | 200 | idem |
| Créer fee-type | Bouton "Nouveau type" | `POST /admin/finance/fee-types/` | `name`, `default_amount`, `applies_to: cycle\|level\|class\|all` | 201 | idem |
| Modifier fee-type | Bouton "Modifier" | `PUT /admin/finance/fee-types/{id}` | path + payload | 200 | idem |
| Voir détail facture | Click ligne | `GET /admin/finance/invoices/{id}` | path | 200 | idem |
| Voir pénalités calculées | Onglet | `GET /admin/finance/invoices/{id}/late-fees` | path | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Supprimer une facture déjà payée (même partiellement) | **422** si `payments_sum > 0` |
| Modifier le `student_id` d'une facture (changement bénéficiaire) | **422** "Création d'une nouvelle facture requise" |
| Saisir paiement | `POST .../payments` → **403** |
| Approuver remise / appliquer discount manuel | **403** (Comptable) |
| Refund | **403** |
| Rapprochement bancaire | **403** |

## Cas limites (edge cases)

- **Génération automatique avec montant nul** : 422.
- **Génération sur classe vide** : "Aucun élève à facturer dans cette classe".
- **Doublons** : 1 facture par fee_type × période × élève — UNIQUE constraint backend.
- **Modification montant après paiement partiel** : 422 ou avertissement fort.
- **fee_type utilisé sur facture existante** : ne peut pas être supprimé, soft delete uniquement.
- **Devise** : tout en CFA (XOF) ; pas multi-devise V1.

## Scenarios de test E2E

1. **Créer facture individuelle** : login Agent Comptable → "Nouvelle facture" → élève + fee-type Scolarité + montant → assert 201.
2. **Génération automatique** : choisir classe 3A → fee-type Scolarité → due_date → "Générer" → assert 60 factures créées.
3. **Modifier facture non payée** : `PUT .../invoices/{id}` montant → assert 200.
4. **Supprimer facture non payée** : `DELETE .../invoices/{id}` → assert 200.
5. **CRUD fee-type** : créer + modifier → assert OK.
6. **Action interdite — Supprimer facture payée** : facture avec paiement → `DELETE` → **422**.
7. **Action interdite — Modifier student_id** : `PUT .../invoices/{id}` change `student_id` → **422**.
8. **Action interdite — Saisir paiement** : `POST .../payments` → **403**.
9. **Edge — Génération masse classe vide** : assert message + count=0.

## Dépendances backend

- ✅ Endpoints `invoices`, `fee-types`, `generate-automated` existent ([`Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Administrator|Agent Comptable|Comptable,tenant')` sur invoices/fee-types CRUD
- ⚠️ **À implémenter** : validation "facture non payable supprimable" dans `InvoiceController@destroy`
- ⚠️ **À implémenter** : UNIQUE constraint `(student_id, fee_type_id, period)` selon politique
- ⚠️ **À implémenter** : audit log création/modification factures

## Definition of Done

- [ ] Les 9 scénarios E2E passent
- [ ] Middlewares appliqués
- [ ] Audit log opérationnel
- [ ] Validations métier testées

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
