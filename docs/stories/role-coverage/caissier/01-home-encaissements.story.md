# Story: Caissier — Home / Encaissements - Coverage

**Module** : Finance + Enrollment
**Rôle ciblé** : Caissier
**Menu(s) concerné(s)** : `/admin/finance/payments`
**Status** : Ready for Review

## User Story

En tant que **Caissier**, je veux atterrir sur un dashboard qui me montre mes encaissements du jour (total, par moyen de paiement) et un accès direct à la saisie d'un nouveau paiement, afin de démarrer ma journée à la caisse efficacement.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Encaissements (Home) | ✅ | home_route Caissier `/admin/finance/payments` |
| Nouveau paiement | ✅ | Action principale |
| Reçus | ✅ | Re-impression |
| Factures (lecture) | ✅ | Voir avant encaisser |
| Mon journal | ✅ | Fermeture caisse |
| Rapports globaux finance | 👁️ Limité | Lecture restreinte (pas la trésorerie globale) |
| Création facture | ❌ | Agent Comptable |
| Refund / Rapprochement | ❌ | Comptable |
| Utilisateurs / Admin | ❌ | Hors rôle |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mon dashboard caisse | Page principale | `GET /admin/finance/reports/dashboard` (filtré rôle Caissier) | — | 200 + KPIs filtrés (mes encaissements, pas trésorerie globale) | `role:Administrator\|Comptable\|Caissier,tenant` |
| Voir mon résumé journalier | Widget "Aujourd'hui" | `GET /admin/finance/payments/summary/daily?cashier_id={me}` | optionnel `date` | 200 + total/breakdown | idem + filter `cashier_user_id` |
| Rechercher un élève | Autocomplete | `GET /admin/students/?search=` ou `GET /admin/enrollment/students/search/autocomplete?q=` | `q` | 200 + suggestions | `role:...|Caissier|...` ([`UsersGuard/admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php)) |
| Logout | Header | `POST /admin/auth/logout` | — | 200 | `tenant.auth` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir le solde global de trésorerie du tenant | Dashboard filtre selon rôle Caissier → champs sensibles absents |
| Voir les KPIs de Recouvrement / Refunds | **403** ou filtre côté backend |
| Atterrir sur `/admin/dashboard` | Redirect login → `/admin/finance/payments` |

## Cas limites (edge cases)

- **Aucun paiement aujourd'hui** : "Aucun encaissement aujourd'hui — démarrez avec 'Nouveau paiement'".
- **Plus de 100 paiements/jour** : pagination + virtual scrolling.
- **Caisse non clôturée la veille** : bandeau "Clôture caisse en attente" + CTA vers Story 05.
- **Mauvaise mise à jour token** : redirect login.

## Scenarios de test E2E

1. **Login → Home** : login Caissier `caissier1` ⇒ redirect `/admin/finance/payments` (config).
2. **Sidebar conforme** : assert `[Encaissements, Nouveau paiement, Reçus, Factures, Mon journal]` ; NE PAS contenir `[Utilisateurs, Refunds, Création facture, Rapprochement]`.
3. **Dashboard limité** : assert response `reports/dashboard` ne contient pas `cash_flow_forecast` ni `treasury_balance`.
4. **Action interdite — Liste users** : `GET /admin/users/` → **403**.
5. **Action interdite — Créer facture** : `POST /admin/finance/invoices` → **403**.

## Dépendances backend

- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable|Caissier,tenant')` sur `reports/dashboard`, `payments/summary/daily`
- ⚠️ **À implémenter** : `FinanceReportController@dashboard` filtre payload selon rôle (Caissier ne voit que ses KPIs)
- ⚠️ **À implémenter** : `summary/daily` filtre par `cashier_user_id` quand rôle Caissier
- ✅ Recherche élève : route existe avec Caissier inclus dans middleware
- ⚠️ **À créer** côté FE : `src/modules/Finance/cashier/menu.config.ts` avec `requiredRoles: ['Caissier']`

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Middleware role appliqué sur Finance reports/payments
- [ ] Filtre par rôle dans `FinanceReportController@dashboard`
- [ ] Sidebar restreinte au rôle Caissier

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
