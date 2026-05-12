# Story: Caissier — Saisie d'un Paiement - Coverage

**Module** : Finance
**Rôle ciblé** : Caissier
**Menu(s) concerné(s)** : `/admin/finance/payments/new`
**Status** : Ready for Review

## User Story

En tant que **Caissier**, je veux saisir rapidement un paiement (espèces, chèque, Mobile Money, virement) pour un élève en sélectionnant la facture impayée, afin de pouvoir traiter < 30 secondes par parent au guichet.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Nouveau paiement | ✅ | Action principale Caissier |
| Paiement partiel | ✅ | Endpoint `payments/partial` |
| Refund | ❌ | Comptable |
| Approuver remise | ❌ | Comptable |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Rechercher élève | Autocomplete | `GET /admin/enrollment/students/search/autocomplete?q=` | `q` | 200 + suggestions | `role:...|Caissier|...,tenant` |
| Voir factures impayées d'un élève | Section "Factures de l'élève" | `GET /admin/finance/invoices/?student_id=X&status=unpaid` | `student_id`, `status` | 200 + liste | `role:Administrator\|Comptable\|Agent Comptable\|Caissier,tenant` (à ajouter) |
| Voir détail facture | Click | `GET /admin/finance/invoices/{id}` | path | 200 + détail | idem |
| Voir échéancier facture | inclus | inclus | — | — | idem |
| Saisir paiement total | Formulaire + bouton "Encaisser" | `POST /admin/finance/payments` | `invoice_id`, `amount`, `payment_method: cash\|check\|mobile_money\|transfer`, `reference?`, `note?` | 201 + paiement créé + reçu auto-généré | `role:Administrator\|Comptable\|Caissier,tenant` |
| Saisir paiement partiel | Bouton "Partiel" | `POST /admin/finance/payments/partial` | `invoice_id`, `amount`, `payment_method`, `next_payment_date?` | 201 + facture statut `partially_paid` | idem |
| Imprimer reçu | Bouton "Imprimer" sur confirmation | `GET /admin/finance/payments/{id}/receipt` | path | 200 + PDF | idem |
| Annuler en cours de saisie (avant submit) | Bouton "Annuler" | (UI only) | — | — | — |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Annuler un paiement déjà enregistré | Pas de bouton "Annuler" sur paiement créé. `POST .../refund` réservé Comptable → **403** |
| Modifier le montant d'une facture | `PUT /admin/finance/invoices/{id}` → **403** (Agent Comptable) |
| Créer une facture sur place | `POST /admin/finance/invoices` → **403** |
| Appliquer une remise manuelle (discount) | `POST /admin/finance/discounts` → **403** ou validation après workflow |
| Saisir montant > `invoice.remaining_balance` | **422** sauf si paiement partiel justifié |
| Saisir paiement avec date dans le futur | **422** |
| Saisir paiement avec moyen non autorisé par config tenant | **422** |

## Cas limites (edge cases)

- **Élève sans facture** : message "Aucune facture impayée pour cet élève".
- **Élève en doublon (homonymes)** : afficher photo + classe + matricule pour disambiguation.
- **Chèque sans référence** : 422 "Référence chèque obligatoire" si moyen = check.
- **Mobile Money — numéro téléphone payeur** : champ optionnel mais recommandé pour audit.
- **Coupure courant pendant impression** : reçu re-imprimable depuis la liste des paiements.
- **Saisie en zone hors ligne** : queue locale + sync (cf. story 06 Prof).

## Scenarios de test E2E

1. **Encaisser total** : login Caissier → "Nouveau paiement" → chercher élève → choisir facture → entrer montant total + moyen "cash" → "Encaisser" → assert 201 + reçu disponible + facture `paid`.
2. **Encaisser partiel** : facture 50 000 → entrer 20 000 → "Partiel" → assert 201 + facture `partially_paid` (remaining 30 000).
3. **Reçu imprimé** : sur confirmation → cliquer "Imprimer reçu" → assert PDF reçu avec numéro reçu unique.
4. **Action interdite — Refund** : `POST .../payments/{id}/refund` → **403**.
5. **Action interdite — Créer facture** : `POST .../invoices` → **403**.
6. **Action interdite — Montant > restant** : `amount = 100 000` sur restant 30 000 → **422**.
7. **Edge — Chèque sans réf** : moyen check, reference vide → **422**.

## Dépendances backend

- ✅ Endpoints `payments/*` existent ([`Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable|Caissier,tenant')` sur `payments`, `payments/partial`, `payments/{id}/receipt`
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable,tenant')` sur `payments/{id}/refund` (exclure Caissier)
- ⚠️ **À implémenter** : `PaymentController@store` valide `cashier_user_id = auth()->id()` (traçabilité)
- ⚠️ **À implémenter** : génération numéro reçu unique (séquentiel par tenant)
- ⚠️ **À implémenter** : audit log obligatoire chaque création paiement

## Definition of Done

- [ ] Les 7 scénarios E2E passent
- [ ] Middlewares appliqués
- [ ] Audit log paiement (`cashier_user_id`, `gateway_status`, etc.)
- [ ] Numéro reçu unique par tenant testé
- [ ] Performance saisie < 30 secondes mesurée

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
