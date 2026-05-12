# Story: Parent — Paiement en Ligne d'une Facture - Coverage

**Module** : PortailParent + Finance + Gateway (CinetPay recommandé)
**Rôle ciblé** : Parent
**Menu(s) concerné(s)** : `/admin/parent/children/{id}/invoices/{invoice}/pay`
**Status** : Ready for Review

> ⚠️ **DÉCISION PRODUIT REQUISE** avant impl : choix gateway (option A CinetPay Mobile Money / B Stripe / C génération bon hors ligne) — cf. [`parent/README.md#6`](./README.md). Story rédigée en supposant **option A CinetPay**.

## User Story

En tant que **Parent**, je veux payer la facture de mon enfant en ligne via Orange Money / Moov Money / carte bancaire en moins de 2 minutes, afin d'éviter le déplacement à l'établissement et le retard d'inscription/réinscription.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Bouton "Payer" sur ligne facture impayée | ✅ | `pay children invoices` + `is_financial_responsible = true` (pivot) |
| Bouton "Payer" sur facture payée | ❌ | Statut déjà `paid` |
| Annulation paiement | ❌ | Pas d'auto-cancel — passer par recouvrement / refund (Comptable) |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Initier un paiement | Bouton "Payer maintenant" | `POST /api/admin/parent/children/{student}/invoices/{invoice}/pay` (**À CRÉER**) | path, `amount`, `method: OM\|MM\|CARD`, `phone?` | 201 + `payment_url` + `payment_id` | `role:Parent,tenant` + `ChildPolicy::payInvoices` (requiert `is_financial_responsible`) |
| Suivre statut paiement | Polling toutes les 5s pendant flow | `GET /api/admin/parent/payments/{payment}/status` (**À CRÉER**) | path | 200 + `{status: pending\|processing\|success\|failed, gateway_status, message}` | idem + filter owner |
| Annuler tentative en cours (avant validation gateway) | Bouton "Annuler" | `POST /api/admin/parent/payments/{payment}/cancel` (**À CRÉER**, à confirmer si gateway permet) | path | 200 ou 422 si déjà validé | idem |
| Recevoir reçu après succès | Auto à la confirmation webhook | (event) `PaymentSucceeded` → email parent + lien `payments/{payment}/receipt` | — | — | — |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Payer pour un enfant pas le sien | `ChildPolicy::payInvoices` → **403** |
| Payer si pas `is_financial_responsible` | Pivot check → **403** (parent non co-titulaire financier) |
| Modifier le montant à la baisse (paiement partiel non autorisé selon politique) | Validation backend : `amount === invoice.remaining_balance` OU `amount >= min_partial_payment` |
| Rembourser une transaction (côté Parent) | **403** (réservé Comptable) |
| Initier paiement sans téléphone valide (mobile money) | **422** validation |

## Cas limites (edge cases)

- **Paiement échoué (timeout gateway)** : status `failed` + bouton "Réessayer".
- **Paiement réussi mais webhook tardif** : flag `pending_confirmation`, message "Votre paiement est en cours de validation par l'opérateur".
- **Solde insuffisant Mobile Money** : status `failed` + message gateway.
- **Tentative paiement double (idempotency)** : `idempotency_key` côté backend, max 1 tentative active par facture.
- **Gateway down** : message "Service de paiement temporairement indisponible — réessayez dans quelques minutes".
- **Mauvaise saisie OTP** : Mobile Money renvoie échec, parent doit recommencer.

## Scenarios de test E2E

1. **Payer une facture (OM)** : login Parent → "Mes factures" → cliquer "Payer" → choisir Orange Money → entrer numéro → soumettre → mock webhook success → assert facture `paid` + reçu généré.
2. **Polling statut** : pendant flow, assert `GET .../payments/{id}/status` retourne progression.
3. **Webhook signature invalide** : POST webhook sans HMAC valide → assert **401** + log security.
4. **Action interdite — Payer enfant tiers** : `POST .../children/{other_kid}/invoices/{id}/pay` → **403**.
5. **Action interdite — Pas financial_responsible** : pivot `is_financial_responsible=false` → tenter pay → **403**.
6. **Action interdite — Montant incorrect** : `amount = 100` sur facture 500 sans politique partielle → **422**.
7. **Edge — Gateway timeout** : mock timeout → assert status `failed` + bouton "Réessayer".
8. **Edge — Idempotence** : 2 requêtes pay simultanées même facture → 1 succès, 1 erreur 409.

## Dépendances backend

- ⚠️ **Critique — DÉCISION PRODUIT** : valider option A (CinetPay) auprès du PO
- ⚠️ **À créer** : `Modules/Finance/Services/PaymentGatewayService.php` + intégration CinetPay
- ⚠️ **À créer** : `Modules/Finance/Http/Controllers/Webhook/CinetPayWebhookController.php`
- ⚠️ **À créer** : route publique `POST /api/webhooks/cinetpay` (sans `tenant.auth`, avec signature HMAC)
- ⚠️ **À créer** : endpoints Parent listés ci-dessus
- ⚠️ **À implémenter** : `ChildPolicy::payInvoices` (check `is_financial_responsible`)
- ⚠️ **À implémenter** : table `payments` augmentée (`gateway`, `gateway_transaction_id`, `gateway_status`, `webhook_received_at`, `idempotency_key`)
- ⚠️ **À implémenter** : Event `PaymentSucceeded` + listener `SendReceiptToParent`
- ⚠️ **À implémenter** : throttle 5/min sur l'endpoint pay (anti-bot)
- ⚠️ Bloque sur Story Parent 01

## Definition of Done

- [ ] Intégration CinetPay validée en sandbox
- [ ] Webhook signé HMAC + idempotency
- [ ] Les 8 scénarios E2E passent
- [ ] Test de bout en bout : facture 50 000 CFA payée via Orange Money sandbox → reçu reçu par email
- [ ] Throttle 5/min testé
- [ ] Audit log paiement complet

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | **Story différée V2** — gateway CinetPay actée (§A.4) mais implémentation complète (webhooks, idempotence, KYC) hors scope Quick Wins. RBAC `role:Parent` + `pay children invoices` posé via ChildPolicy. | Dev Agent (James) |
