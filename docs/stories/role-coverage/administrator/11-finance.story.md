# Story: Administrator — Finance (admin complet) - Coverage

**Module** : Finance
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/finance/*`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux superviser toute la chaîne financière (facturation, encaissements, remises, recouvrement, write-offs, rapports), afin d'assurer la gouvernance financière de l'établissement.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Fee Types CRUD | ✅ | Barèmes |
| Factures CRUD + génération auto + late-fees + payment-schedule | ✅ | Plein contrôle |
| Paiements + refunds + reconciliation + daily summary + partial | ✅ | Plein contrôle |
| Remises/Bourses (apply + approve + revoke) | ✅ | Workflow |
| Recouvrement (reminders, blocks, payment-plans, write-off) | ✅ | Plein contrôle |
| Rapports (dashboard, payment-journal, aging, unpaid, cash-flow, collection, accounting-export, exports excel/pdf, summary, clear-cache) | ✅ | Complet |

## Actions autorisées dans ce menu

Toutes les routes de [`Modules/Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php) (214 lignes).

L'Administrator est explicitement inclus dans tous les `role:Administrator|...,tenant` middlewares à ajouter selon les autres stories rôles finance (Caissier, Agent Comptable, Comptable).

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Cross-tenant | tenancy |
| Bypass validation 2-étapes refund | Workflow obligatoire même pour Admin (audit fort) |

## Cas limites (edge cases)

- **Validation 2 étapes refund > seuil** : 2 Admins minimum.
- **Write-off** : Admin signe + audit fort.

## Scenarios de test E2E

1. **Workflow complet rentrée** : fee-types → génération factures → encaissements → relances → reporting → assert.
2. **Refund grande somme** : 2-step → assert workflow.
3. **Exports comptables** : assert SYSCOHADA fichier.
4. **Rapprochement** : import OFX → match → close period → assert.

## Dépendances backend

- ❌ Aucun middleware role
- ⚠️ **À ajouter** : middlewares par story rôle (voir Caissier/Agent Comptable/Comptable READMEs)
- ⚠️ **À implémenter** : workflow 2-step pour refund > seuil
- ⚠️ **À implémenter** : audit log Admin

## Definition of Done

- [ ] Middlewares appliqués selon stories autres rôles
- [ ] Les 4 scénarios E2E passent
- [ ] Workflow refund 2-step testé

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
