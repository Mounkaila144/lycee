# Story: Administrator — Inscriptions (CRUD complet) - Coverage

**Module** : Enrollment
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/enrollment/*`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux gérer l'intégralité des inscriptions (élèves, options, groupes, transferts, équivalences, cartes étudiantes, campagnes de réinscription, statistiques), afin de piloter la rentrée et tout le cycle de vie scolaire.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Élèves CRUD + import + status + audit + delete | ✅ | Admin complet |
| Inscriptions pédagogiques | ✅ | CRUD |
| Options + groupes (LMD-héritage à scoper) | ⚠️ | À confirmer scope secondaire |
| Transferts / Équivalences | ✅ | Workflow complet |
| Dispenses | ✅ | Workflow complet |
| Cartes étudiantes (CRUD + batch) | ✅ | Génération + gestion |
| Campagnes réinscription | ✅ | CRUD + activate/close |
| Statistiques | ✅ | KPIs |

## Actions autorisées dans ce menu

L'Administrator a accès à **toutes** les routes de [`Modules/Enrollment/Routes/admin.php`](../../../Modules/Enrollment/Routes/admin.php) (438 lignes). Liste exhaustive non répétée — voir l'inventaire dans le README global §4.

Groupes :
- `students/*` (CRUD complet, import, audit, status, documents, check-duplicates)
- `enrollments/*` (CRUD pédagogique, modules, available-modules)
- `options/*` (CRUD + assignments + choices) — **scope LMD à confirmer**
- `groups/*` (CRUD + auto-assign) — **scope LMD à confirmer**
- `validation/*` (workflow validation pédagogique)
- `student-cards/*` (CRUD + batch)
- `reenrollment-campaigns/*`, `reenrollments/*`
- `transfers/*`, `equivalences/*`, `exemptions/*`
- `statistics/*`, `reports/*`, `group-exports/*`

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Opérations cross-tenant | tenancy garantit |
| Suppression hard sans audit | Audit log obligatoire |

## Cas limites (edge cases)

- **Migration brownfield** : nombreuses entités LMD à nettoyer (cf. Story 7.1)
- **Suppression élève avec notes** : refusé (cf. Story 7.5 du PRD Inscriptions — à venir)

## Scenarios de test E2E

1. **CRUD élève complet** : créer + modifier + soft delete + audit → assert.
2. **Import CSV gros volume** : 500 élèves → assert tous importés.
3. **Workflow campagne réinscription** : créer → activer → élèves soumettent → batch-validate → assert.
4. **Workflow transfert** : create → review → analyze → validate → integrate → certificate → assert.
5. **Suppression élève sans note** : `DELETE` → assert 200 + soft delete.
6. **Action interdite — Suppression élève avec notes** : `DELETE` → **422**.
7. **Cross-tenant** : token Admin tenant A → ressource tenant B → **404**.

## Dépendances backend

- ❌ Aucun middleware role sur `Modules/Enrollment/Routes/admin.php`
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager,tenant')` sur students GET/POST/PUT/import/status
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur DELETE students + équivalences validate + write-off
- ⚠️ Scope LMD-héritage à valider (options/groupes/transferts pertinents secondaire ?)
- ⚠️ Story 7.1 (Inscriptions Création Élève) bloque cleanup

## Definition of Done

- [ ] Middlewares appliqués sur toutes routes
- [ ] Les 7 scénarios E2E passent
- [ ] Scope LMD-héritage statué

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
