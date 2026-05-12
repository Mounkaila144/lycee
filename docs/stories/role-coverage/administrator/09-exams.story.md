# Story: Administrator — Examens (admin complet) - Coverage

**Module** : Exams
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/exams/*`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux gérer le cycle complet des examens (sessions, planning, assignement candidats, supervision, incidents, rapports), afin d'organiser les épreuves nationales/internes en conformité.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Sessions examen CRUD + publish/cancel/duplicate | ✅ | Planning |
| Management (matériels, instructions, assign students, auto-assign) | ✅ | Préparation |
| Supervision (assign supervisors, attendance, incidents) | ✅ | Suivi |
| Reports (attendance, incidents, stats, workload, room utilization) | ✅ | KPIs |

## Actions autorisées dans ce menu

Toutes les routes de [`Modules/Exams/Routes/admin.php`](../../../Modules/Exams/Routes/admin.php) (71 lignes).

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Cross-tenant | tenancy |
| Modification post-épreuve sans audit | Audit log |

## Cas limites (edge cases)

- **Surveillant absent** : réassignation auto/manuelle.
- **Incident grave** : alerte temps réel.
- **Salle conflit horaire** : validation backend.

## Scenarios de test E2E

1. **Workflow complet** : créer session → publier → assigner étudiants → supervisors → assert.
2. **Incident** : déclarer + escalader → assert.
3. **Rapports** : assert reports/attendance + incidents accessibles.
4. **Action interdite — Modifier post-épreuve** : verrou avec audit.

## Dépendances backend

- ⚠️ **Critique** : `Exams/Routes/admin.php` `auth:sanctum` SANS `tenant.auth` — à corriger (R3)
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur write (sessions, supervisors/replace, incidents/status, escalate)
- ⚠️ **À ajouter** : `middleware('role:Administrator|Professeur,tenant')` sur supervision actions (Prof = story 08 prof)

## Definition of Done

- [ ] Faille `auth:sanctum` corrigée
- [ ] Middlewares appliqués
- [ ] Les 4 scénarios E2E passent

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
