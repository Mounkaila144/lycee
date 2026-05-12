# Epic Comptable — Couverture des menus

> **Status** : Draft
> **Role slug** : `Comptable` ([`RolesAndPermissionsSeeder.php:200`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php))
> **home_route** : `/admin/finance/reports` ([`config/role-routes.php:27`](../../../config/role-routes.php))
> **Position hiérarchique** : 3/8 (le plus élevé des rôles finance)

## 1. Permissions Spatie attribuées

Source : `RolesAndPermissionsSeeder.php:203-216`

```php
$comptableRole->syncPermissions([
    'view dashboard',
    'view students',
    'view invoices',
    'view payments',
    'create payments',
    'generate receipts',
    'manage payment plans',
    'manage refunds',
    'manage bank reconciliation',
    'view financial reports',
    'export financial data',
    'manage collection',
]);
```

## 2. Profil utilisateur

Comptable senior de l'établissement.
- Vision **macro** sur la trésorerie.
- Responsable de la conformité : exports vers logiciel comptable externe, déclarations fiscales.
- Approuve les remises, gère les refunds, fait les rapprochements bancaires.
- Profil expert — interface DataGrid avancée, exports en masse.

## 3. Backend coverage

| Module | Route file | État RBAC | Endpoints clés |
|---|---|---|---|
| **Finance** | [`admin.php`](../../../Modules/Finance/Routes/admin.php) | ❌ | `reports/*`, `payments/*` (incl. refund, reconciliation), `discounts/{id}/approve`, `collection/write-off/{id}` |
| **Payroll** | [`admin.php`](../../../Modules/Payroll/Routes/admin.php) | ⚠️ `auth:sanctum` seul | Lecture seule sur fiches de paie agrégées (validation paie) |
| **UsersGuard** | [`admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php) | ✅ inclut `Comptable` | `GET /admin/students/` |

## 4. Périmètre fonctionnel

### Autorisé (le rôle finance le plus large — proche d'un Admin sectoriel)
- ✅ Voir tous les rapports financiers (dashboard, payment journal, aging balance, unpaid statements, cash flow forecast, collection stats)
- ✅ Exports comptables (`accounting-export`, `export/excel`, `export/pdf`)
- ✅ Saisir un encaissement (créé par Caissier mais aussi possible depuis Comptable)
- ✅ Approuver des remises / bourses (`discounts/{id}/approve`)
- ✅ Effectuer un remboursement (`payments/{id}/refund`)
- ✅ Rapprochement bancaire (`payments/reconciliation/data`)
- ✅ Plans de paiement (validation finale)
- ✅ Recouvrement et passage en perte (`collection/write-off/{id}`)
- ✅ Lecture seule sur la Paie (paie reste pilotée par RH / Admin)

### Interdit
- ❌ Créer/modifier des factures (Agent Comptable)
- ❌ CRUD des `fee-types` (Agent Comptable / Admin)
- ❌ Gestion des utilisateurs (Admin/Manager)
- ❌ Génération de documents officiels élèves (bulletins, diplômes) — Admin
- ❌ Saisie de notes ou présences (Professeur / Admin)
- ❌ Émission de fiches de paie individuelles (Admin RH uniquement)

## 5. Stories planifiées (6 stories)

| # | Fichier | Menu cible | Endpoints principaux |
|---|---|---|---|
| 01 | `01-home-rapports.story.md` | Dashboard rapports | `GET reports/dashboard`, `GET reports/summary` |
| 02 | `02-vue-ensemble-finance.story.md` | « Vue d'ensemble » | `GET reports/payment-journal`, `aging-balance`, `cash-flow-forecast`, `collection-statistics` |
| 03 | `03-rapprochement-bancaire.story.md` | « Rapprochement bancaire » | `GET payments/reconciliation/data`, validation |
| 04 | `04-refunds.story.md` | « Remboursements » | `POST payments/{id}/refund`, audit |
| 05 | `05-exports-comptables.story.md` | « Exports » | `GET reports/accounting-export`, `reports/export/excel`, `reports/export/pdf` |
| 06 | `06-paie-lecture.story.md` | « Paie » (lecture) | `GET /admin/payroll/reports/dashboard`, `GET reports/payroll-journal/{periodId}` |

## 6. Dépendances backend (à durcir)

```php
// Routes Finance partagées avec Caissier/Agent Comptable : voir leurs READMEs

// Routes RESTREINTES au Comptable + Admin
Route::middleware('role:Administrator|Comptable,tenant')->group(function () {
    Route::post('payments/{id}/refund', ...);
    Route::get('payments/reconciliation/data', ...);
    Route::post('discounts/{id}/approve', ...);
    Route::post('collection/write-off/{id}', ...);
    Route::get('reports/accounting-export', ...);
    Route::get('reports/export/excel', ...);
    Route::get('reports/export/pdf', ...);
});

// Payroll lecture seule
Route::middleware('role:Administrator|Comptable,tenant')->group(function () {
    Route::get('payroll/reports/*', ...);
});
```

> ⚠️ `Modules/Payroll/Routes/admin.php` utilise `auth:sanctum` SANS `tenant.auth`. À corriger d'abord (R3 du README global).

## 7. Risques spécifiques

| # | Risque | Mitigation |
|---|---|---|
| CT1 | Refund excessif / fraude interne | Plafond paramétrable par tenant ; double validation > seuil |
| CT2 | Export comptable contenant des données mineurs (RGPD) | Filtrer les champs : pas de nom/prénom mais matricule + montant |
| CT3 | Rapprochement bancaire faussé par doublon de paiement | Vue de contrôle « payments sans bank_reference » |
| CT4 | Comptable accède à la paie individuelle | Lecture seule, pas de détail employé — agrégats uniquement |
| CT5 | Suppression d'une facture par cascade refund | Refund crée une `credit_note`, ne supprime jamais |

## 8. Definition of Done

- [ ] 6 stories rédigées en Draft
- [ ] Backlog Payroll RBAC (`auth:sanctum` → `tenant.auth + role:`)
- [ ] Tests E2E : Comptable a accès aux endpoints sensibles ; Agent Comptable / Caissier reçoivent 403 sur eux

## 9. Change log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
