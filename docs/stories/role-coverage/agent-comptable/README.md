# Epic Agent Comptable — Couverture des menus

> **Status** : Draft
> **Role slug** : `Agent Comptable` ([`RolesAndPermissionsSeeder.php:183`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php))
> **home_route** : `/admin/finance/invoices` ([`config/role-routes.php:28`](../../../config/role-routes.php))
> **Position hiérarchique** : 4/8

## 1. Permissions Spatie attribuées

Source : `RolesAndPermissionsSeeder.php:186-196`

```php
$agentComptableRole->syncPermissions([
    'view dashboard',
    'view students',
    'view invoices',
    'create invoices',
    'edit invoices',
    'manage payment plans',
    'manage late fees',
    'manage collection',
    'view financial reports',
]);
```

## 2. Profil utilisateur

Spécialiste de la **facturation** et du **recouvrement**.
- Génère les factures de scolarité au début de chaque trimestre.
- Suit les impayés et applique les pénalités.
- Communique avec les familles en retard (relances).
- Plateforme : poste fixe, écran double souvent.

## 3. Backend coverage

| Module | Route file | État RBAC | Endpoints clés |
|---|---|---|---|
| **Finance** | [`admin.php`](../../../Modules/Finance/Routes/admin.php) | ❌ | `fee-types/*`, `invoices/*` (CRUD), `invoices/generate-automated`, `invoices/{id}/payment-schedule`, `invoices/{id}/late-fees`, `collection/*` |
| **UsersGuard** | [`admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php) | ✅ inclut `Agent Comptable` | `GET /admin/students/` |
| **Enrollment** | [`admin.php`](../../../Modules/Enrollment/Routes/admin.php) | ❌ | `students/autocomplete` (recherche), `students/statistics/summary` (effectifs facturables) |

## 4. Périmètre fonctionnel

### Autorisé
- ✅ CRUD facture (créer, modifier, supprimer si non payée)
- ✅ Génération automatique de factures (en masse, par classe ou par cycle)
- ✅ Gestion des types de frais (`fee-types/*` — créer/modifier les barèmes)
- ✅ Échéanciers (`invoices/{id}/payment-schedule`)
- ✅ Calcul/application des pénalités de retard
- ✅ Suivi du recouvrement : relances (générer/envoyer), blocages de service, plans de paiement
- ✅ Voir les rapports financiers (lecture)

### Interdit
- ❌ Saisir un encaissement (Caissier / Comptable)
- ❌ Approuver une remise / bourse (Comptable)
- ❌ Refund (Comptable)
- ❌ Rapprochement bancaire (Comptable)
- ❌ Write-off / passage en perte (`POST /admin/finance/collection/write-off/{id}`) — décision Comptable senior
- ❌ Exports comptables vers logiciel externe (Comptable)
- ❌ Toute action hors module Finance

## 5. Stories planifiées (6 stories)

| # | Fichier | Menu cible | Endpoints principaux |
|---|---|---|---|
| 01 | `01-home-facturation.story.md` | Dashboard facturation | `GET reports/dashboard`, `GET reports/unpaid-statements` |
| 02 | `02-factures-crud.story.md` | « Factures » | `GET/POST/PUT/DELETE /admin/finance/invoices`, `POST invoices/generate-automated`, `fee-types/*` |
| 03 | `03-echeanciers.story.md` | « Échéanciers » | `POST invoices/{id}/payment-schedule`, `POST collection/payment-plans` |
| 04 | `04-penalites-retard.story.md` | « Pénalités de retard » | `GET invoices/{id}/late-fees`, paramétrage |
| 05 | `05-recouvrement.story.md` | « Recouvrement » | `POST collection/reminders/generate`, `POST reminders/send`, `GET collection/reminders` |
| 06 | `06-blocage-services.story.md` | « Blocages » | `POST collection/blocks`, `POST blocks/{id}/unblock`, `GET blocks`, `POST blocks/auto-process` |

## 6. Dépendances backend (à durcir)

```php
Route::middleware(['tenant', 'tenant.auth', 'role:Administrator|Agent Comptable|Comptable,tenant'])
    ->group(function () {
        Route::apiResource('invoices', InvoiceController::class);
        Route::post('invoices/generate-automated', ...);
        Route::apiResource('fee-types', ...);
        Route::post('collection/reminders/generate', ...);
        Route::post('collection/blocks', ...);
        // etc.
    });

// Reservé Comptable
Route::middleware('role:Administrator|Comptable,tenant')->group(function () {
    Route::post('collection/write-off/{id}', ...);
});
```

## 7. Risques spécifiques

| # | Risque | Mitigation |
|---|---|---|
| AC1 | Agent crée une facture pour un mauvais élève (homonyme) | Recherche `autocomplete` avec photo + classe ; double validation visuelle |
| AC2 | Génération de masse erronée (mauvais montant pour toute une classe) | Endpoint `generate-automated` exige preview + confirmation explicite ; audit log |
| AC3 | Suppression d'une facture déjà partiellement payée | Validation backend : 422 si `payments_sum > 0` |
| AC4 | Relance abusive (parents harcelés) | Throttle 1 relance / 7 jours / élève au niveau service |
| AC5 | Blocage de service injuste (élève en cours d'examen) | Flag `protected_period` sur la table académique ; respect dans `processAutomaticBlocking` |

## 8. Definition of Done

- [ ] 6 stories rédigées en Draft
- [ ] Middlewares `role:` proposés validés
- [ ] Tests E2E : Agent Comptable bloqué sur `refund`, `write-off`, `reconciliation`, `accounting-export`

## 9. Change log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
