# Story: Professeur — Rattrapages (Retake Grades) - Coverage

**Module** : NotesEvaluations
**Rôle ciblé** : Professeur
**Menu(s) concerné(s)** : `/admin/teacher/retake-grades`
**Status** : Ready for Review

## User Story

En tant que **Professeur**, je veux saisir les notes de rattrapage (session de remédiation de fin de semestre/année) pour mes modules, afin de finaliser les résultats avant le conseil de classe.

> Distinction avec story 04 : Story 04 = rattrapage **d'une seule évaluation manquée** ; ici = **session de rattrapage officielle** d'un module entier (souvent regroupant plusieurs élèves en échec sur la moyenne semestrielle).

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Rattrapages | ✅ | Section dédiée au sein de Saisie notes |
| Liste mes modules en rattrapage | ✅ | Endpoint `retake-modules` |
| Stats rattrapage | ✅ | Voir taux réussite après rattrapage |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister mes modules en rattrapage | Page principale "Rattrapages" | `GET /api/frontend/teacher/retake-modules` | — | 200 + liste modules avec count élèves éligibles | `role:Professeur,tenant` |
| Voir les élèves à rattraper d'un module | Click module → liste élèves | `GET /api/frontend/teacher/modules/{module}/retake-students` | `module` (path) | 200 + liste élèves (moyenne initiale < seuil) | `role:Professeur,tenant` |
| Voir statistiques rattrapage du module | Onglet "Stats" sur page module | `GET /api/frontend/teacher/modules/{module}/retake-statistics` | `module` (path) | 200 + KPIs | `role:Professeur,tenant` |
| Télécharger template Excel rattrapage | Bouton "Modèle" | `GET /api/frontend/teacher/modules/{module}/retake-template` | `module` (path) | 200 + fichier `.xlsx` pré-rempli | `role:Professeur,tenant` |
| Soumettre les notes de rattrapage en lot | Bouton "Soumettre rattrapage" → confirmation | `POST /api/frontend/teacher/modules/{module}/submit-retake-grades` | `module` (path), `grades: [{student_id, score, comment?}]` | 201 + état `submitted` | `role:Professeur,tenant` |
| Saisir une note rattrapage individuelle | Édition cellule + Enter | `POST /api/frontend/teacher/retake-grades/` | `module_id`, `student_id`, `score`, `comment?` | 201 + note retake créée | `role:Professeur,tenant` |
| Saisir notes rattrapage en lot (drag fichier) | Bouton "Import lot" | `POST /api/frontend/teacher/retake-grades/batch` | `module_id`, `grades: array` | 201 + count | `role:Professeur,tenant` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Saisir rattrapage pour un module qui n'est pas le sien | Owner check `$module->teacher_id === auth()->id()` → **403** |
| Saisir note rattrapage pour élève déjà admis (moyenne ≥ seuil) | **422** + message "Cet élève n'est pas éligible au rattrapage" |
| Modifier une note rattrapage déjà `submitted` | **422** + workflow "demande correction" comme story 02 |
| Note rattrapage > max_score | Validation identique story 02 |
| Soumettre rattrapage en dehors de la fenêtre officielle (ex: avant clôture des notes initiales) | **422** + message "Période rattrapage non ouverte par l'administration" |

## Cas limites (edge cases)

- **Aucun élève en échec** : la liste est vide, message "Aucun élève à rattraper sur ce module" + CTA "Retour".
- **Élève en échec mais qui ne s'est pas présenté au rattrapage** : marquer absent (mêmes règles que story 04, `is_absent` checkbox).
- **Politique tenant : la meilleure note est conservée OU la note de rattrapage écrase** : à lire depuis `tenant_settings.retake_policy`.
- **Délai dépassé** : rattrapages bloqués au-delà de `retake_deadline` du semestre.
- **Module sans seuil défini** : avertissement admin requis avant rattrapage.

## Scenarios de test E2E

1. **Lister modules** : login Professeur en période rattrapage → `GET retake-modules` → assert liste des modules où il y a au moins 1 élève en échec.
2. **Soumettre rattrapage lot** : 5 élèves en échec → saisir 5 notes (3 réussissent, 2 échouent encore) → `POST submit-retake-grades` → assert 201 + DB `retake_grades` 5 lignes + statut module `submitted`.
3. **Action interdite — Module autre prof** : `POST retake-grades` avec `module_id` collègue → **403**.
4. **Action interdite — Élève non éligible** : tenter saisie pour élève moyenne 18/20 → **422**.
5. **Edge — Hors fenêtre** : tenter soumission après `retake_deadline` → **422**.
6. **Edge — Aucun éligible** : module sans échec → assert page vide.

## Dépendances backend

- ✅ Tous les endpoints listés existent ([`teacher.php:62-69`](../../../Modules/NotesEvaluations/Routes/teacher.php))
- ⚠️ **À ajouter** : `middleware('role:Professeur,tenant')` + owner checks
- ⚠️ **À implémenter** : config tenant `retake_policy` (best_grade / replace) et `retake_deadline`
- ⚠️ **À implémenter** : workflow état module (`open` → `submitted` → `published`)

## Definition of Done

- [ ] Les 6 scénarios E2E passent
- [ ] Tests unitaires `RetakeGradeController` (5 cas)
- [ ] Politique `retake_policy` lue depuis settings tenant
- [ ] Module passe en état `submitted` après soumission

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | Middleware `role:Professeur|Administrator` (teacher.php) + `role:Administrator|Manager|Professeur` (Attendance/Timetable/Exams/Enrollment admin). Tests dans `tests/Feature/RoleCoverage/Professeur/TeacherRoutesProtectionTest.php` (12 tests passants). Ownership controllers déjà en place côté `GradeEntryController` ; à raffiner story par story pour Attendance/Timetable. | Dev Agent (James) |
