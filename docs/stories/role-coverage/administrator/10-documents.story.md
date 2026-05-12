# Story: Administrator — Documents Officiels (admin complet) - Coverage

**Module** : Documents
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/documents/*`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux gérer la génération, vérification, signature électronique et archivage des documents officiels, afin d'assurer la traçabilité et la validité juridique.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Transcripts (semestriels/globaux/batch) | ✅ | Génération |
| Diplômes (CRUD + duplicate + supplement + deliver) | ✅ | Workflow complet |
| Certificats (enrollment/status/achievement/attendance/schooling/transfer + requests CRUD) | ✅ | Workflow |
| Cartes étudiantes (CRUD + replace + print + suspend/activate + access permissions) | ✅ | Gestion |
| Vérification (QR + number + register + statistics + archives) | ✅ | Audit |
| Signature électronique (add/list/verify/invalidate) | ✅ | Admin only |
| Archives + cold storage | ✅ | Long terme |
| Annulation documents | ✅ | Admin only |

## Actions autorisées dans ce menu

Toutes les routes de [`Modules/Documents/Routes/admin.php`](../../../Modules/Documents/Routes/admin.php) (85 lignes).

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Cross-tenant | tenancy |
| Suppression d'un document signé électroniquement | **403** + workflow Annulation officielle |

## Cas limites (edge cases)

- **Document signé doit rester immutable** : archive uniquement.
- **Annulation requiert motif et signature 2e admin** : workflow.

## Scenarios de test E2E

1. **Génération diplôme** : créer + signer électroniquement → assert.
2. **Vérification QR** : scanner → assert validité.
3. **Suspendre carte** : élève transféré → "Suspendre" → assert.
4. **Annuler document** : workflow 2-admins → assert.
5. **Cold storage** : ancien doc → archive → assert.

## Dépendances backend

- ⚠️ **Critique** : `Documents/Routes/admin.php` `auth:sanctum` SANS `tenant.auth` (R3)
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur signature/cold-storage/cancel
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager,tenant')` sur génération
- ⚠️ **À implémenter** : workflow 2 admins pour annulation

## Definition of Done

- [ ] Faille corrigée
- [ ] Middlewares
- [ ] Les 5 scénarios E2E passent
- [ ] Workflow 2-admins testé

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
