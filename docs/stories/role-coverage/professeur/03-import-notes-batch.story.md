# Story: Professeur — Import Notes en Lot (Excel/CSV) - Coverage

**Module** : NotesEvaluations
**Rôle ciblé** : Professeur
**Menu(s) concerné(s)** : `/admin/teacher/grades/import` (sous-onglet de Saisie notes)
**Status** : Ready for Review

## User Story

En tant que **Professeur**, je veux téléverser un fichier Excel/CSV contenant les notes de tous mes élèves pour une évaluation, afin de gagner du temps quand j'ai déjà saisi les notes sur papier ou dans un tableur personnel.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Import notes | ✅ | Sous-onglet de Saisie notes (story 02) |
| Modèle Excel | ✅ | Téléchargement template |
| Historique imports | ✅ | Voir mes imports précédents |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Télécharger le modèle Excel | Bouton "Modèle vierge" | `GET /api/frontend/teacher/grades/import/template` | `evaluation_id` (query) | 200 + fichier `.xlsx` pré-rempli avec liste élèves | `role:Professeur,tenant` |
| Uploader & valider fichier | Drag-drop ou input file → bouton "Valider" | `POST /api/frontend/teacher/grades/import/validate` | `file` (UploadedFile), `evaluation_id` | 200 + `{rows_valid, rows_invalid, errors}` | `role:Professeur,tenant` |
| Prévisualiser avant import | Bouton "Aperçu" après validate | `POST /api/frontend/teacher/grades/import/preview` | `file_uuid`, `evaluation_id` | 200 + tableau preview avec annotations OK/Erreur par ligne | `role:Professeur,tenant` |
| Lancer l'import définitif | Bouton "Importer" → confirmation | `POST /api/frontend/teacher/grades/import/execute` | `file_uuid`, `evaluation_id`, `overwrite_existing: boolean` | 202 + `job_id` (queue) | `role:Professeur,tenant` |
| Suivre statut du job d'import | Polling toutes les 2s sur progress bar | `GET /api/frontend/teacher/grades/import/status/{jobId}` | `jobId` (path) | 200 + `{status: queued\|processing\|done\|failed, processed, total, errors}` | `role:Professeur,tenant` |
| Annuler import en cours | Bouton "Annuler" pendant queued/processing | `DELETE /api/frontend/teacher/grades/import/{jobId}` (à créer) | `jobId` (path) | 200 + job marqué cancelled | `role:Professeur,tenant` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Importer des notes pour une éval d'un autre prof | `POST .../import/execute` vérifie `evaluation->teacher_id === auth()->id()` → **403** |
| Importer un fichier > 5 Mo | Validation `file:max:5120` → **422** + message "Fichier trop volumineux" |
| Importer un fichier d'un autre type que `.xlsx`/`.csv` | `mimes:xlsx,csv` → **422** |
| Écraser des notes publiées sans flag explicite | Si éval `is_published=true` et `overwrite_existing=false` → **422** "Impossible d'écraser sans demande de correction" |
| Importer dans une éval qui n'est plus dans la fenêtre de saisie (`due_date` passée + verrouillée par admin) | **422** + message + lien "Contactez l'administration" |

## Cas limites (edge cases)

- **Fichier corrompu** : parseur Excel échoue → 422 + ligne erreur "Fichier illisible ou corrompu".
- **Colonne `matricule` manquante** : 422 + "Colonne 'matricule' introuvable — utilisez le modèle Excel".
- **Matricule inconnu** dans le fichier (élève hors classe) : ligne marquée erreur dans preview, autres lignes valides peuvent être importées.
- **Score > max_score** : ligne marquée erreur "Note 25 supérieure au barème 20".
- **Score vide** : interprété comme "absent" (mêmes règles que story 04).
- **Job qui crash en cours** : status `failed` + détail erreur ; les notes déjà sauvegardées restent persistées (idempotence par `matricule`).
- **Import très volumineux (500 élèves)** : queue Laravel obligatoire, pas de traitement synchrone.

## Scenarios de test E2E

1. **Télécharger modèle** : login Professeur → onglet "Import notes" → choisir évaluation → "Télécharger modèle" → assert fichier reçu contient toutes les matricules de la classe.
2. **Import nominal** : remplir modèle avec 30 notes valides → upload → preview → "Importer" → poll status → assert `done` + 30 notes en DB.
3. **Import avec lignes erronées** : 25 lignes valides + 5 lignes (score > 20) → preview montre 5 erreurs + 25 OK → cliquer "Importer ce qui est valide" → assert 25 notes en DB, 5 ignorées + rapport.
4. **Action interdite — Éval autre prof** : `POST execute` avec `evaluation_id` d'un collègue → **403**.
5. **Action interdite — Fichier 10 Mo** : upload fichier > 5 Mo → **422**.
6. **Action interdite — Éval publiée** : tenter import sur éval `is_published=true` sans `overwrite=true` → **422** + message.
7. **Edge — Matricule inconnu** : fichier avec 1 matricule inexistant → preview marque la ligne erreur → import partiel possible.
8. **Edge — Annulation** : lancer import 500 lignes → cliquer "Annuler" pendant queued → assert job `cancelled`.

## Dépendances backend

- ✅ `GET /api/frontend/teacher/grades/import/template` — existe ([`teacher.php:40`](../../../Modules/NotesEvaluations/Routes/teacher.php))
- ✅ `POST /api/frontend/teacher/grades/import/validate|preview|execute` — existent ([`teacher.php:41-43`](../../../Modules/NotesEvaluations/Routes/teacher.php))
- ✅ `GET /api/frontend/teacher/grades/import/status/{jobId}` — existe ([`teacher.php:44`](../../../Modules/NotesEvaluations/Routes/teacher.php))
- ⚠️ **À créer** : `DELETE /api/frontend/teacher/grades/import/{jobId}` (annulation)
- ⚠️ **À ajouter** : `middleware('role:Professeur,tenant')` sur le groupe
- ⚠️ **À vérifier** : owner check sur `evaluation_id` dans `GradeImportController@execute`
- ⚠️ **À implémenter** : protection `is_published=true` côté `execute` (refus si pas de flag overwrite)
- ⚠️ Dépendances Composer : `maatwebsite/excel` (déjà prévu dans `tech-stack.md`)

## Definition of Done

- [ ] Les 8 scénarios E2E passent
- [ ] Test unitaire `GradeImportController` : 5 cas (owner, max_size, mime, max_score, is_published)
- [ ] Job queue testé sur 500 lignes (perf < 30s)
- [ ] Annulation de job implémentée

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | Middleware `role:Professeur|Administrator` (teacher.php) + `role:Administrator|Manager|Professeur` (Attendance/Timetable/Exams/Enrollment admin). Tests dans `tests/Feature/RoleCoverage/Professeur/TeacherRoutesProtectionTest.php` (12 tests passants). Ownership controllers déjà en place côté `GradeEntryController` ; à raffiner story par story pour Attendance/Timetable. | Dev Agent (James) |
