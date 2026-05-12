# Story: Administrator — Paie Personnel (admin complet) - Coverage

**Module** : Payroll
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/payroll/*`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux gérer la paie du personnel (employés, contrats, composants, périodes paie, déclarations sociales, rapports), afin d'assurer la conformité légale et le versement des salaires.

> Note V1 : Admin assume la fonction RH. Un rôle dédié `RH` peut être créé en V2.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Employés CRUD + contrats + amendements + activate + terminate | ✅ | Plein contrôle |
| Composants paie (salary scales, components, advances) | ✅ | CRUD + approve advances |
| Périodes paie (CRUD + calculate + validate + payslips + bank-transfers + mark-as-paid) | ✅ | Cycle complet |
| Déclarations sociales (CNSS, IGR, AMO, annual-summary + validate + submit + payment) | ✅ | Conformité |
| Rapports (dashboard, payroll-journal, social-charges, salary-statistics) | ✅ | KPIs |

## Actions autorisées dans ce menu

Toutes les routes de [`Modules/Payroll/Routes/admin.php`](../../../Modules/Payroll/Routes/admin.php) (54 lignes).

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Cross-tenant | tenancy |
| Suppression hard d'un employé après paiement | Soft delete + archive |

## Cas limites (edge cases)

- **Période non clôturée** : modifications autorisées, après clôture verrou.
- **Erreur déclaration** : workflow correction.

## Scenarios de test E2E

1. **Workflow paie mensuelle** : créer période → calculer → valider → générer payslips → bank-transfers → mark-as-paid → assert.
2. **Déclaration CNSS** : générer → submit → assert.
3. **Avance employé** : request → approve → disburse → assert.
4. **Action interdite — Suppression post-paiement** : `DELETE employee` → soft delete + audit.

## Dépendances backend

- ⚠️ **Critique** : `Payroll/Routes/admin.php` `auth:sanctum` SANS `tenant.auth` — à corriger (R3)
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur tous endpoints (rôle RH non encore créé)
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable,tenant')` sur `reports/*` (Comptable lecture)
- ⚠️ **À implémenter** : audit log paie

## Definition of Done

- [ ] Faille corrigée
- [ ] Middlewares appliqués
- [ ] Les 4 scénarios E2E passent

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
