# Story: Caissier — Mon Journal & Clôture Caisse - Coverage

**Module** : Finance
**Rôle ciblé** : Caissier
**Menu(s) concerné(s)** : `/admin/finance/payments/daily`
**Status** : Ready for Review

## User Story

En tant que **Caissier**, je veux consulter mon journal d'encaissements du jour (par moyen de paiement, total) et clôturer ma caisse en fin de journée, afin de valider mon activité et préparer les écritures comptables.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Mon journal | ✅ | Action quotidienne |
| Clôture caisse | ✅ | Bouton + workflow |
| Journal d'un autre caissier | ❌ | Filtre owner |
| Rapprochement bancaire | ❌ | Comptable |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mes encaissements jour | Page principale | `GET /admin/finance/payments/summary/daily?cashier_user_id={me}&date={today}` | filters | 200 + résumé par moyen paiement | `role:Caissier,tenant` + filter owner |
| Filtrer par date passée | Sélecteur date | idem avec `date` | date | 200 | idem |
| Voir détail par moyen de paiement | Onglet | inclus dans response | — | — | idem |
| Imprimer journal du jour | Bouton "Imprimer journal" | `GET /admin/finance/payments/summary/daily?print=pdf` (à créer si pas existant) ou via export | — | 200 + PDF | idem |
| Clôturer ma caisse | Bouton "Clôturer caisse" → modal de confirmation avec saisie comptage espèces réel | `POST /admin/finance/payments/close-cashier-day` (**À CRÉER**) | `date`, `cash_counted`, `notes?` | 201 + cloture créée + écart calculé | idem |
| Voir mes clôtures passées | Onglet "Historique" | `GET /admin/finance/payments/close-history` (**À CRÉER**) | `from?`, `to?` | 200 + liste | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir journal d'un autre caissier | Filter `cashier_user_id = me` (sauf Admin/Comptable) → **403** |
| Modifier une clôture déjà validée | Pas d'endpoint update — immutabilité |
| Voir caisse globale (tous caissiers) | `GET .../summary/daily` sans filter → **403** pour Caissier |
| Annuler une clôture | Si écart anormal, alerte → Admin doit intervenir, pas le caissier |
| Modifier un paiement après clôture | Verrou : paiements `closed=true` non modifiables |

## Cas limites (edge cases)

- **Écart espèces** : `cash_counted != cash_expected` → ligne d'alerte + commentaire obligatoire.
- **Aucun encaissement aujourd'hui** : "Journée vide — clôture optionnelle" + bouton "Clôturer vide".
- **Reprise journée passée** : impossible d'enregistrer un paiement à une date déjà clôturée → 422.
- **Mode hors ligne** : impossible de clôturer hors ligne (sécurité).
- **Caissier de remplacement** : si un autre caissier prend la suite, son journal démarre à 0.

## Scenarios de test E2E

1. **Voir mon journal** : login Caissier → "Mon journal" → assert seuls SES encaissements visibles.
2. **Imprimer journal** : "Imprimer" → assert PDF avec total + breakdown moyens paiement.
3. **Clôturer caisse — pas d'écart** : compter espèces = attendu → "Clôturer" → assert clôture créée écart = 0.
4. **Clôturer caisse — avec écart** : compter 50 000 vs attendu 52 000 → renseigner commentaire → assert clôture avec écart -2000 + alerte admin.
5. **Action interdite — Journal autre caissier** : `GET .../summary/daily?cashier_user_id={other}` → **403**.
6. **Action interdite — Modifier paiement après clôture** : `PUT /admin/finance/payments/{id}` sur paiement `closed=true` → **422**.
7. **Edge — Saisie paiement date passée clôturée** : `POST /admin/finance/payments` avec `created_at=hier (clôturé)` → **422**.

## Dépendances backend

- ✅ `GET /admin/finance/payments/summary/daily` existe ([`Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php))
- ⚠️ **À créer** : `POST /admin/finance/payments/close-cashier-day` + table `cashier_close_records`
- ⚠️ **À créer** : `GET /admin/finance/payments/close-history`
- ⚠️ **À ajouter** : `middleware('role:Caissier,tenant')` + filter owner
- ⚠️ **À implémenter** : flag `payment.closed=true` après clôture + verrou modification
- ⚠️ **À implémenter** : alerte admin sur écart > seuil (configurable)

## Definition of Done

- [ ] Endpoint clôture créé + table
- [ ] Les 7 scénarios E2E passent
- [ ] Écart détecté et notifié
- [ ] Verrou paiements après clôture testé

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
