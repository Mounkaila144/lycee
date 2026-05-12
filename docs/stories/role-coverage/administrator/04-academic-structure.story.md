# Story: Administrator — Structure Académique (CRUD complet) - Coverage

**Module** : StructureAcademique
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/structure`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux configurer la structure académique du tenant (années scolaires, semestres, cycles, niveaux, séries, classes, matières, coefficients), afin de paramétrer l'établissement avant chaque rentrée.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Années scolaires | ✅ | CRUD + activation |
| Semestres / Périodes éval | ✅ | CRUD |
| Cycles / Niveaux | ✅ | Lecture (seeder) |
| Séries (Littéraire, Math-Phy, etc.) | ✅ | CRUD |
| Classes | ✅ | CRUD + PP assignment |
| Matières | ✅ | CRUD |
| Coefficients | ✅ | CRUD + duplication |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| CRUD années scolaires | Pages | `GET/POST/PUT/DELETE /admin/academic-years/*` | — | 200/201 | `role:Administrator,tenant` (à ajouter) |
| Activer année | Bouton | `POST /admin/academic-years/{id}/activate` | path | 200 | idem |
| CRUD semestres + clôture | Pages | `GET/POST/PUT/DELETE /admin/semesters/*`, `POST .../close` | — | — | idem |
| CRUD séries | Pages | `GET/POST/PUT/DELETE /admin/series/*` | — | — | idem |
| CRUD classes + stats | Pages | `GET/POST/PUT/DELETE /admin/classes/*`, `GET /classes/{id}/stats`, `GET /classes/available-head-teachers` | — | — | idem |
| CRUD matières | Pages | `GET/POST/PUT/DELETE /admin/subjects/*` | — | — | idem |
| CRUD coefficients + duplication | Pages | `GET/POST/PUT/DELETE /admin/subject-class-coefficients/*`, `POST .../duplicate`, `GET .../compare` | — | — | idem |
| Lecture cycles/levels (seeder) | Page | `GET /admin/cycles`, `GET /admin/levels` | — | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Modifier structure avec notes saisies | Warn / verrou : impossible modifier coefficients d'année en cours si notes existent |
| Supprimer année active | **422** + "Désactiver d'abord" |
| Supprimer classe avec élèves | **422** + workflow transfert |
| Cross-tenant | tenancy |

## Cas limites (edge cases)

- **Première rentrée tenant** : tout vide, Admin crée 1 année → active → ajoute semestres → classes → matières → coefficients.
- **Duplication structure année N+1** : copie structure année active.
- **Année avec notes** : modification coefficients bloquée — Admin doit délibérer.
- **Class avec PP changé** : audit log + notification au nouvel PP.

## Scenarios de test E2E

1. **Setup complet** : créer année → semestres → classes → matières → coefficients → activer → assert tout OK.
2. **Modifier classe** : changer PP → assert 200 + audit.
3. **Action interdite — Supprimer année active** : `DELETE` → **422**.
4. **Action interdite — Coef avec notes** : `PUT coefficient` → **422**.
5. **Manager bloqué** : login Manager `POST /admin/academic-years` → **403**.

## Dépendances backend

- ❌ Aucun middleware sur `StructureAcademique/Routes/admin.php`
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur POST/PUT/DELETE
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager,tenant')` sur GET
- ⚠️ **À implémenter** : verrou modification coefficients post-notes
- ⚠️ **À implémenter** : cascade verrous (classe avec élèves, etc.)

## Definition of Done

- [ ] Middlewares
- [ ] Les 5 scénarios E2E passent
- [ ] Verrous métier opérationnels

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
