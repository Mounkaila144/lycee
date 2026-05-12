# Story: Comptable — Rapprochement Bancaire - Coverage

**Module** : Finance
**Rôle ciblé** : Comptable
**Menu(s) concerné(s)** : `/admin/finance/payments/reconciliation`
**Status** : Approved

## User Story

En tant que **Comptable**, je veux rapprocher les paiements enregistrés en interne avec les opérations bancaires (relevés bancaires importés), afin de garantir la conformité comptable et détecter les écarts.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Rapprochement bancaire | ✅ | `manage bank reconciliation` |
| Import relevé | ✅ | Upload OFX/CSV bancaire |
| Match auto | ✅ | Suggestions |
| Match manuel | ✅ | Correction |
| Détacher un match | ✅ | Correction |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir données de rapprochement | Page principale | `GET /admin/finance/payments/reconciliation/data` | filters (period, account) | 200 + données | `role:Administrator\|Comptable,tenant` |
| Importer relevé bancaire | Bouton "Importer relevé" | `POST /admin/finance/payments/reconciliation/import` (**À CRÉER**) | `file` (OFX/CSV), `bank_account_id` | 201 + transactions importées | idem |
| Matcher manuellement paiement ↔ transaction bancaire | Drag-drop dans UI | `POST /admin/finance/payments/reconciliation/match` (**À CRÉER**) | `payment_id`, `bank_transaction_id` | 200 + match | idem |
| Détacher un match | Bouton "Détacher" | `DELETE /admin/finance/payments/reconciliation/match/{match}` (**À CRÉER**) | path | 200 | idem |
| Voir écarts non rapprochés | Onglet "Écarts" | inclus dans `/data` ou endpoint dédié | filters | 200 + liste | idem |
| Valider un rapprochement de période | Bouton "Clore période" | `POST /admin/finance/payments/reconciliation/close-period` (**À CRÉER**) | `period`, `validator_signature?` | 201 + période verrouillée | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Modifier un paiement déjà rapproché et clôturé | **422** verrou période |
| Importer relevé sans définir bank_account | **422** |
| Effacer une transaction bancaire importée | **403** (uniquement Admin avec audit) |
| Forcer un match sur deux montants différents | **422** + workflow ajustement |

## Cas limites (edge cases)

- **Frais bancaires** : import relevé doit catégoriser (charges vs paiements élèves).
- **Doublons bancaires** : détection auto (montant + date + référence).
- **Paiement annulé en banque** : match contradictoire → écart visible.
- **Devises mismatched** : V1 mono-devise XOF — sinon refus.
- **Période clôturée** : verrou modification + alerte.

## Scenarios de test E2E

1. **Importer relevé** : login Comptable → "Importer" CSV bancaire 50 lignes → assert 201 + 50 transactions disponibles à matcher.
2. **Match automatique** : assert N matches suggérés (par montant+date).
3. **Match manuel** : drag paiement vers transaction bancaire → assert 200 + match créé.
4. **Détacher match** : sur match erroné → "Détacher" → assert 200.
5. **Clore période** : tous matchés → "Clore période" → assert verrou actif.
6. **Action interdite — Modifier paiement clos** : `PUT /admin/finance/payments/{id}` sur période close → **422**.
7. **Edge — Doublon bancaire** : import même fichier 2× → assert détection doublons.

## Dépendances backend

- ✅ `GET /admin/finance/payments/reconciliation/data` existe ([`Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php))
- ⚠️ **À créer** : endpoints `import`, `match`, `match/{match}` DELETE, `close-period`
- ⚠️ **À créer** : tables `bank_accounts`, `bank_transactions`, `payment_bank_transaction_matches`, `reconciliation_periods`
- ⚠️ **À implémenter** : parseur OFX + CSV bancaire (formats locaux Niger : BICIA, BSIC, etc.)
- ⚠️ **À implémenter** : algo matching auto (montant + date ± 2 jours + reference fuzzy)
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable,tenant')`

## Definition of Done

- [ ] Endpoints + tables créés
- [ ] Les 7 scénarios E2E passent
- [ ] Matching auto précis ≥ 80%
- [ ] Période close = verrou modification garantie

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | **Story différée V2** — création des 4 tables (`bank_accounts`, `bank_transactions`, `payment_bank_transaction_matches`, `reconciliation_periods`) hors scope Quick Wins. RBAC posé : Comptable peut lire `/admin/finance/payments/reconciliation/data`. | Dev Agent (James) |
