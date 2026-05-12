# Story: Caissier — Reçus (impression & ré-impression) - Coverage

**Module** : Finance
**Rôle ciblé** : Caissier
**Menu(s) concerné(s)** : `/admin/finance/receipts`
**Status** : Ready for Review

## User Story

En tant que **Caissier**, je veux ré-imprimer un reçu déjà émis si le parent l'a perdu, en marquant clairement les copies pour éviter la fraude, afin de répondre à une demande sans avoir à recréer le paiement.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Reçus | ✅ | Vue centralisée des reçus émis |
| Recherche reçu | ✅ | Par numéro ou nom élève |
| Annulation reçu | ❌ | Comptable (lié à refund) |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister reçus de mes encaissements | Page principale | `GET /admin/finance/payments/?cashier_user_id={me}&with_receipt=true` | filtres | 200 + liste | `role:Caissier,tenant` + filtre owner |
| Lister tous les reçus (admin/comptable) | filtres avancés | `GET /admin/finance/payments/?with_receipt=true` (sans filter cashier) | filtres | 200 | `role:Administrator\|Comptable,tenant` |
| Rechercher reçu par numéro | Barre recherche | `GET /admin/finance/payments/?receipt_number=X` | `receipt_number` | 200 + résultat | `role:Caissier,tenant` |
| Télécharger reçu PDF | Bouton | `GET /admin/finance/payments/{id}/receipt` | path | 200 + PDF avec watermark "COPIE" si `print_count > 1` | `role:Caissier,tenant` + filter owner pour Caissier |
| Voir historique impression | Onglet "Historique" sur reçu | (inclus dans détail payment) | — | 200 + count + dates | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Modifier un reçu (montant, date) | Pas d'endpoint, immutabilité forcée |
| Supprimer un reçu | **403** |
| Annuler un paiement | **403** (refund Comptable) |
| Émettre un reçu sans paiement préalable | Endpoint `receipt` lié à payment uniquement |
| Re-imprimer un reçu de paiement effectué par un autre caissier (sauf admin) | **403** |

## Cas limites (edge cases)

- **Reçu perdu / pas reçu** : ré-imprimer avec watermark "COPIE - [N]".
- **Reçu d'un paiement en `processing`** : pas de PDF généré tant que paiement non `succeeded`.
- **Compteur impression** : `print_count` en DB pour audit.
- **Reçu en double pour fraude** : audit log alerte si `print_count > 3` en moins de 24h.

## Scenarios de test E2E

1. **Lister mes reçus** : login Caissier → "Reçus" → assert liste reçus de SES encaissements uniquement.
2. **Ré-imprimer** : sélectionner reçu → "Télécharger PDF" → assert watermark "COPIE" présent + `print_count` incrémenté.
3. **Recherche par numéro** : taper N° reçu → assert résultat unique.
4. **Action interdite — Reçu autre caissier** : `GET /admin/finance/payments/{other_cashier_payment}/receipt` → **403** (sauf Admin/Comptable).
5. **Action interdite — Supprimer** : `DELETE /admin/finance/payments/{id}` → **403** ou endpoint inexistant.

## Dépendances backend

- ✅ `GET /admin/finance/payments/{id}/receipt` existe ([`Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable|Caissier,tenant')` + filter owner pour Caissier
- ⚠️ **À implémenter** : compteur `print_count` + watermark "COPIE" si `print_count > 1`
- ⚠️ **À implémenter** : audit log + alerte sur impression anormale (>3/24h)
- ⚠️ **À créer** template PDF reçu avec en-tête établissement

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Watermark "COPIE" testé
- [ ] Audit log impression
- [ ] Filter owner pour Caissier

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
