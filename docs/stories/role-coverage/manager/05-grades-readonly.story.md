# Story: Manager — Notes (Lecture seule) - Coverage

**Module** : NotesEvaluations
**Rôle ciblé** : Manager
**Menu(s) concerné(s)** : `/admin/grades` (lecture)
**Status** : Ready for Review

## User Story

En tant que **Manager**, je veux consulter les notes et résultats au niveau classe/élève (sans pouvoir saisir ni modifier), afin de superviser la qualité pédagogique et préparer les conseils de classe.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Notes / Évaluations (lecture) | ✅ | Lecture transverse |
| Résultats semestriels (lecture) | ✅ | Lecture |
| Publications notes | ✅ (lecture) | Voir quelles évals sont publiées |
| Saisie / Édition | ❌ | Professeur |
| Délibérations | 👁️ Lecture | Lire les décisions |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister évaluations | DataGrid | `GET /api/admin/evaluations` (à vérifier endpoint exact dans `NotesEvaluations/admin.php`) | filters | 200 | `role:Administrator\|Manager,tenant` (à ajouter) |
| Voir détail évaluation | Click | `GET /api/admin/evaluations/{id}` | path | 200 + notes | idem |
| Voir résultats classe | Page | `GET /api/admin/results/class/{class}/semester/{semester}` (à vérifier) | path | 200 | idem |
| Voir bulletins (lecture) | Page | `GET /api/admin/bulletins?class_id=X` | filters | 200 | idem |
| Voir délibérations | Page | `GET /api/admin/deliberations` | filters | 200 | idem |
| Voir stats notes | Onglet | `GET /api/admin/grades/statistics` | filters | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Saisir / modifier note | `POST/PUT /api/admin/grades` → **403** (Prof via teacher.php) |
| Publier / dépublier évaluation | `POST .../publish` → **403** |
| Créer délibération | **403** |
| Modifier coefficients | **403** |
| Demander correction note | **403** (Prof workflow) |

## Cas limites (edge cases)

- **Aucune note saisie pour la période** : "Aucune donnée pour cette période".
- **Évaluation non publiée** : visible côté Manager avec badge "Non publié".
- **Délibération en cours** : statut visible.
- **Performance** : agrégats cachés.

## Scenarios de test E2E

1. **Lister évaluations** : login Manager → "Notes" → assert DataGrid lecture.
2. **Voir résultats classe** : assert affichage notes + moyennes.
3. **Action interdite — Saisir note** : `POST /api/admin/grades` → **403**.
4. **Action interdite — Publier** : `POST .../publish` → **403**.
5. **Action interdite — Modifier délibération** : **403**.

## Dépendances backend

- ❌ Aucun middleware role sur `Modules/NotesEvaluations/Routes/admin.php`
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager,tenant')` sur GET routes
- ⚠️ **À ajouter** : middlewares restreignant l'écriture (Prof via teacher.php OR Admin uniquement)
- ⚠️ **À implémenter** : `NotesEvaluations` lecture pour Manager doit inclure non publiées (à confirmer politique)

## Definition of Done

- [ ] Middlewares appliqués
- [ ] Les 5 scénarios E2E passent
- [ ] Manager bloqué sur toute écriture

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
