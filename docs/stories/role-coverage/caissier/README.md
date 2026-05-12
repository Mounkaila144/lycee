# Epic Caissier — Couverture des menus

> **Status** : Draft
> **Role slug** : `Caissier` ([`RolesAndPermissionsSeeder.php:169`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php))
> **home_route** : `/admin/finance/payments` ([`config/role-routes.php:29`](../../../config/role-routes.php))
> **Position hiérarchique** : 5/8

## 1. Permissions Spatie attribuées

Source : `RolesAndPermissionsSeeder.php:172-179`

```php
$cashierRole->syncPermissions([
    'view dashboard',
    'view students',
    'view invoices',
    'create payments',
    'generate receipts',
    'view financial reports',
]);
```

## 2. Profil utilisateur

Caissier(ère) au guichet — saisit les paiements espèces / chèques / mobile money apportés en personne par les parents/élèves.
- Plateforme : poste fixe (desktop) avec imprimante reçu (thermique 80mm).
- Charge : pics massifs en début de trimestre (rentrée, T1, T2, T3).
- Critère succès : encaissement < 30 secondes (incluant impression reçu).

## 3. Backend coverage

| Module | Route file | État RBAC | Endpoints clés |
|---|---|---|---|
| **Finance** | [`admin.php`](../../../Modules/Finance/Routes/admin.php) | ❌ Pas de `role:` | `payments/*` (POST, partial, refund interdit), `payments/{id}/receipt`, `payments/summary/daily`, `invoices` (lecture seule) |
| **UsersGuard** | [`admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php) | ✅ `role:...|Caissier|...,tenant` | `GET /admin/students/` (recherche élève au guichet) |
| **Enrollment** | [`admin.php`](../../../Modules/Enrollment/Routes/admin.php) | ❌ | `GET /admin/enrollment/students/search/autocomplete` (recherche rapide) |

## 4. Périmètre fonctionnel

### Autorisé (cible)
- ✅ Dashboard caissier (home `/admin/finance/payments`)
- ✅ Recherche d'un élève par nom/matricule (autocomplete)
- ✅ Voir les factures impayées d'un élève (lecture)
- ✅ Saisir un paiement (espèces, chèque, mobile money, virement reçu)
- ✅ Saisir un paiement partiel
- ✅ Imprimer / re-générer un reçu (PDF + format thermique)
- ✅ Voir le journal de SES encaissements du jour (résumé journalier)
- ✅ Voir les rapports financiers (lecture seule, pas d'export)

### Interdit
- ❌ Créer/modifier une facture (Agent Comptable)
- ❌ Annuler ou rembourser un paiement (`refund`) — réservé au Comptable
- ❌ Approuver une remise / bourse — Comptable uniquement
- ❌ Rapprochement bancaire (Comptable)
- ❌ Recouvrement / écritures de pertes (`write-off`)
- ❌ Exports financiers / accounting export (Comptable)
- ❌ Toute action hors module Finance (pas d'accès Users, Notes, Présences…)
- ❌ Voir les fiches de paie personnel

## 5. Stories planifiées (5 stories)

| # | Fichier | Menu cible | Endpoints principaux |
|---|---|---|---|
| 01 | `01-home-encaissements.story.md` | Dashboard | `GET /admin/finance/reports/dashboard` (filtre lecture caissier), `GET payments/summary/daily` |
| 02 | `02-saisie-paiement.story.md` | « Nouveau paiement » | `GET /admin/students/?search=`, `GET /admin/finance/invoices/{id}`, `POST /admin/finance/payments`, `POST payments/partial` |
| 03 | `03-recus.story.md` | « Reçus » | `GET payments/{id}/receipt`, ré-impression |
| 04 | `04-factures-lecture.story.md` | « Factures » (lecture) | `GET /admin/finance/invoices/`, `GET /{id}` |
| 05 | `05-rapports-journaliers.story.md` | « Mon journal » | `GET payments/summary/daily`, fermeture caisse |

## 6. Dépendances backend (à durcir)

Toutes les routes Finance n'ont **pas** de `middleware('role:...')`. À ajouter :

```php
Route::middleware(['tenant', 'tenant.auth', 'role:Administrator|Comptable|Caissier,tenant'])
    ->group(function () {
        Route::post('payments', [PaymentController::class, 'store']);
        Route::post('payments/partial', [PaymentController::class, 'recordPartial']);
        Route::get('payments/{id}/receipt', [PaymentController::class, 'getReceipt']);
        Route::get('payments/summary/daily', [PaymentController::class, 'dailySummary']);
    });

Route::middleware(['tenant', 'tenant.auth', 'role:Administrator|Comptable,tenant'])
    ->group(function () {
        Route::post('payments/{id}/refund', [PaymentController::class, 'refund']);  // ⚠️ pas Caissier
    });
```

Filtre owner « mon journal » : `WHERE cashier_user_id = auth()->id()` à ajouter dans `PaymentController@dailySummary`.

## 7. Risques spécifiques

| # | Risque | Mitigation |
|---|---|---|
| C1 | Caissier annule un paiement réel pour détournement | Endpoint refund **interdit** au Caissier (uniquement Comptable) + audit log obligatoire |
| C2 | Double impression d'un reçu (fraude) | Watermark « COPIE » sur ré-impressions ; compteur `print_count` en DB |
| C3 | Erreur de saisie sur montant > facture totale | Validation backend `amount <= invoice.remaining_balance` (sauf paiement partiel justifié) |
| C4 | Caissier voit le solde global du tenant (info sensible) | Route `reports/dashboard` doit filtrer en mode « caissier » — pas le solde de trésorerie global |

## 8. Definition of Done

- [ ] 5 stories rédigées en Draft
- [ ] Middlewares `role:` à ajouter listés et acceptés par sécurité
- [ ] Tests E2E : Caissier peut payer mais reçoit 403 sur `refund`, `accounting-export`, `reconciliation`

## 9. Change log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
