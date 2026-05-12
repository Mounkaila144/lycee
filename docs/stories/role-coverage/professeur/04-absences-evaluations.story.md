# Story: Professeur — Gestion des Absents à une Évaluation - Coverage

**Module** : NotesEvaluations
**Rôle ciblé** : Professeur
**Menu(s) concerné(s)** : `/admin/teacher/evaluations/{id}/absences`
**Status** : Ready for Review

## User Story

En tant que **Professeur**, je veux marquer un élève absent à une évaluation et planifier un rattrapage, afin que la note 0 ne soit pas appliquée par défaut et que la situation soit traitée selon la politique d'établissement (justifié / non justifié).

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Absents à l'évaluation | ✅ | Sous-page de la modale évaluation |
| Politique d'absence | ✅ (info button) | Voir paramètres établissement |
| Planifier rattrapage | ✅ | Action principale |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister les absents d'une évaluation | Onglet "Absents" de la modale éval | `GET /api/frontend/teacher/evaluations/{evaluation}/absences/` | `evaluation` (path) | 200 + liste élèves absents (justifié/non justifié) | `role:Professeur,tenant` |
| Marquer un élève absent | Checkbox "Absent" dans la grille de notes + bouton "Confirmer absents" | `POST /api/frontend/teacher/evaluations/{evaluation}/absences/mark-absent` | `student_ids: array`, `is_justified: boolean`, `reason?: text` | 201 + élèves marqués | `role:Professeur,tenant` |
| Voir la politique absence du tenant | Bouton "ℹ️" en haut de la liste | `GET /api/frontend/teacher/evaluations/{evaluation}/absences/policy` | `evaluation` (path) | 200 + règles (note 0 si non justifié, rattrapage obligatoire si justifié, etc.) | `role:Professeur,tenant` |
| Voir stats absentéisme évaluation | Onglet "Stats" sur sous-page | `GET /api/frontend/teacher/evaluations/{evaluation}/absences/statistics` | `evaluation` (path) | 200 + taux absence + breakdown justifié/non | `role:Professeur,tenant` |
| Lister les rattrapages programmés | Onglet "Rattrapages" | `GET /api/frontend/teacher/evaluations/{evaluation}/absences/replacements` | `evaluation` (path) | 200 + liste rattrapages | `role:Professeur,tenant` |
| Planifier un rattrapage | Bouton "Planifier rattrapage" → modale | `POST /api/frontend/teacher/evaluations/{evaluation}/absences/schedule-replacement` | `student_id`, `date`, `time`, `room_id?`, `notes?` | 201 + rattrapage créé | `role:Professeur,tenant` |
| Annuler un rattrapage | Menu "..." sur ligne rattrapage | `POST /api/frontend/teacher/replacements/{replacement}/cancel` | `replacement` (path), `reason` | 200 + rattrapage `cancelled` | `role:Professeur,tenant` |
| Enregistrer la note du rattrapage | Cliquer "Saisir note" sur rattrapage `completed` | `POST /api/frontend/teacher/replacements/{replacement}/record-grade` | `replacement` (path), `score`, `comment?` | 201 + note créée + lien avec rattrapage | `role:Professeur,tenant` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Marquer absent un élève d'une autre classe | Validation `$student->isInClass($evaluation->class_id)` → **422** |
| Planifier rattrapage pour une éval d'un autre prof | Owner check → **403** |
| Modifier note rattrapage déjà saisie sans demande de correction | Réutiliser flux story 02 (publication + demande correction) |
| Annuler un rattrapage déjà `completed` | **422** + message "Rattrapage déjà effectué" |
| Saisir note négative ou > max_score sur rattrapage | Validation identique story 02 (`min:0`, `max:evaluation.max_score`) |

## Cas limites (edge cases)

- **Élève déjà marqué absent + tentative saisie note ordinaire** : la cellule "score" est grisée et indique "Absent — note non saisissable directement".
- **Justificatif à fournir** : si `is_justified=true`, champ `reason` obligatoire (selon politique tenant).
- **Rattrapage planifié hors fenêtre semestre** : 422 + message "Date hors période académique active".
- **Conflit horaire avec autre cours** : 422 + suggestions de créneaux libres (intégration Timetable — phase 2).
- **Absence collective (>50%)** : alerte UI "Absentéisme massif détecté — vérifier la date d'évaluation".
- **Élève quitté l'établissement (status `Transféré`)** : ne pas apparaître dans la liste des absents marquables.

## Scenarios de test E2E

1. **Marquer absent** : login Professeur → ouvrir éval → cocher "Absent" sur 2 élèves → "Confirmer absents" → assert DB `evaluation_student_absences` contient 2 lignes + cellules grisées dans grille notes.
2. **Planifier rattrapage** : sur élève absent justifié → cliquer "Planifier rattrapage" → renseigner date+heure → assert 201 + rattrapage visible dans onglet "Rattrapages".
3. **Saisir note rattrapage** : rattrapage marqué `completed` → cliquer "Saisir note" → entrer score 14 → assert note créée + reliée au rattrapage.
4. **Action interdite — Élève autre classe** : `POST mark-absent` avec `student_id` d'un élève hors classe → **422**.
5. **Action interdite — Rattrapage éval autre prof** : `POST schedule-replacement` avec `evaluation_id` collègue → **403**.
6. **Edge — Hors semestre** : date rattrapage = `2027-08-01` (hors année active) → **422**.
7. **Edge — Annuler rattrapage déjà fait** : `POST cancel` sur rattrapage `completed` → **422**.

## Dépendances backend

- ✅ `absences/` endpoints existent ([`teacher.php:53-58`](../../../Modules/NotesEvaluations/Routes/teacher.php))
- ✅ `replacements/{replacement}/cancel` et `record-grade` existent ([`teacher.php:59-60`](../../../Modules/NotesEvaluations/Routes/teacher.php))
- ⚠️ **À ajouter** : `middleware('role:Professeur,tenant')` + owner checks
- ⚠️ **À implémenter** : intégration `Timetable` pour suggérer créneaux libres (phase 2 — non bloquant)
- ⚠️ **À implémenter** : event `EvaluationAbsenceMarked` pour notifier le parent (sera consommé par story Parent 03)

## Definition of Done

- [ ] Les 7 scénarios E2E passent
- [ ] Tests unitaires `AbsenceManagementController` (5 cas)
- [ ] Event `EvaluationAbsenceMarked` dispatché lors du mark-absent
- [ ] UI grise les cellules notes pour élèves absents

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | Middleware `role:Professeur|Administrator` (teacher.php) + `role:Administrator|Manager|Professeur` (Attendance/Timetable/Exams/Enrollment admin). Tests dans `tests/Feature/RoleCoverage/Professeur/TeacherRoutesProtectionTest.php` (12 tests passants). Ownership controllers déjà en place côté `GradeEntryController` ; à raffiner story par story pour Attendance/Timetable. | Dev Agent (James) |
