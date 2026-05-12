# Story: Professeur — Saisie des Notes - Coverage

**Module** : NotesEvaluations
**Rôle ciblé** : Professeur
**Menu(s) concerné(s)** : `/admin/teacher/grades` (sidebar id supposé `teacher-grades`, à valider FE)
**Status** : Ready for Review

## User Story

En tant que **Professeur**, je veux saisir les notes de mes élèves pour une évaluation donnée (en cellules de tableau, avec auto-save), afin de fluidifier la saisie d'une classe de 60 élèves en moins de 10 minutes sans perdre de données en cas de coupure.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Saisie notes | ✅ | Permission Spatie `manage grades` ([`seeder L116`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php)) |
| Toutes mes évaluations | ✅ | Sous-onglet de Saisie notes |
| Notes d'un autre prof | ❌ | Filtre owner backend |
| Publication des notes | ✅ (bouton dans la modale évaluation) | Le prof publie ses propres notes |
| Statistiques de classe | ✅ (read-only) | Endpoint `evaluations/{id}/statistics` |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister mes évaluations d'un module | Onglet "Évaluations" sur la page module | `GET /api/frontend/teacher/modules/{module}/evaluations` | `module` (path) | 200 + liste paginée | `role:Professeur,tenant` + filtre owner |
| Voir les élèves d'une évaluation | Click sur évaluation → grille de saisie | `GET /api/frontend/teacher/evaluations/{evaluation}/students` | `evaluation` (path) | 200 + grille (student, current_grade, max_score) | `role:Professeur,tenant` |
| Saisir/modifier des notes en lot | Édition cellules + bouton "Enregistrer" | `POST /api/frontend/teacher/grades/batch` | `evaluation_id`, `grades: [{student_id, score, comment?}]` | 200 + toast "Notes enregistrées (N notes)" + grille rafraîchie | `role:Professeur,tenant` |
| Auto-save toutes les 30s | Indicateur "Sauvegardé il y a Xs" en haut de grille | `POST /api/frontend/teacher/grades/auto-save` | `evaluation_id`, `partial_grades: [...]` | 200 + horodatage retourné | `role:Professeur,tenant` |
| Voir l'historique d'une note | Click "i" sur cellule → drawer historique | `GET /api/frontend/teacher/grades/{grade}/history` | `grade` (path) | 200 + tableau changements | `role:Professeur,tenant` |
| Demander une correction d'une note publiée | Bouton "Demander correction" sur note `is_published=true` | `POST /api/frontend/teacher/grades/{grade}/request-correction` | `grade` (path), `reason` (text 255) | 201 + workflow déclenché | `role:Professeur,tenant` |
| Voir statistiques d'une évaluation | Onglet "Stats" de la modale évaluation | `GET /api/frontend/teacher/evaluations/{evaluation}/statistics` | `evaluation` (path) | 200 + min/max/moyenne/médiane/distribution | `role:Professeur,tenant` |
| Exporter notes d'une évaluation en Excel | Bouton "Exporter" | `GET /api/frontend/teacher/evaluations/{evaluation}/export` | `evaluation` (path) | 200 + fichier `.xlsx` | `role:Professeur,tenant` |
| Vérifier la complétude (toutes notes saisies) | Badge "X/60 notes saisies" en haut de grille | `GET /api/frontend/teacher/evaluations/{evaluation}/check-completeness` | `evaluation` (path) | 200 + `{total, saisies, manquantes}` | `role:Professeur,tenant` |
| Publier les notes (rend visibles aux élèves) | Bouton "Publier" + modal de confirmation | `POST /api/frontend/teacher/evaluations/{evaluation}/publish` | `evaluation` (path), `notify_students: boolean` | 200 + `is_published=true` | `role:Professeur,tenant` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Modifier une note d'une évaluation déjà publiée (sans passer par "demande correction") | Cellules grisées (UI) + `POST /api/frontend/teacher/grades/batch` renvoie **422** si une note de la requête appartient à une évaluation `is_published=true` |
| Saisir des notes pour un module qui n'est pas le sien | `POST /api/frontend/teacher/grades/batch` avec `evaluation_id` d'un autre prof → **403** (filter controller sur `$evaluation->teacher_id === auth()->id()`) |
| Saisir un score > `max_score` ou < 0 | UI : input avec min/max + backend : **422** validation `score: ['numeric', 'min:0', 'max:'.$evaluation->max_score]` |
| Supprimer une évaluation existante | Pas de bouton "Supprimer" + endpoint admin only `DELETE /api/admin/evaluations/{id}` → **403** depuis Professeur |
| Voir les notes d'élèves qui ne sont pas dans sa classe | Filter controller `$student->enrollments->where('class_id', $teacher_class_ids)->exists()` |
| Republier des notes déjà dépubliées par admin | Statut workflow — `publish` interdit si état = `pending_correction` |

## Cas limites (edge cases)

- **Coupure réseau pendant saisie** : auto-save tente toutes les 30s ; si offline, message "Mode hors ligne — vos notes sont conservées localement"; reprise auto au retour réseau via IndexedDB ou localStorage.
- **Deux profs éditent la même note (conflit)** : 409 Conflict côté backend + toast "Cette note a été modifiée par un autre utilisateur. Recharger ?".
- **Note hors plage** : `score = 25` sur évaluation `/20` → 422 ; UI bloque saisie au-delà de `max_score`.
- **Absent à l'évaluation** : saisir `null` ou cocher `is_absent` → enregistré comme absent (voir story 04).
- **Évaluation date dans le futur** : avertissement "Cette évaluation n'a pas encore eu lieu — êtes-vous sûr ?" + autorise quand même.
- **60 élèves, perf** : virtual scrolling DataGrid + batch save 50 élèves max par requête.
- **Élève transféré en cours d'année** : présent dans la grille pour les évaluations antérieures à son transfert, masqué pour les suivantes.

## Scenarios de test E2E

1. **Affichage grille** : login Professeur → cliquer "Saisie notes" → choisir module → choisir évaluation → assert grille avec N lignes (= nb élèves classe).
2. **Saisie + sauvegarde** : remplir 5 notes → cliquer "Enregistrer" → assert toast "5 notes enregistrées" → assert DB `grades` contient 5 lignes avec `evaluation_id` et scores attendus.
3. **Auto-save** : remplir une note → attendre 30s sans cliquer "Enregistrer" → assert horodatage "Sauvegardé il y a quelques secondes" → recharger la page → la note est toujours là.
4. **Publier** : cliquer "Publier" sur une éval complète → assert `is_published=true` en DB + assert grille passe en lecture seule.
5. **Action interdite — Notes d'un autre prof** : essayer `POST /api/frontend/teacher/grades/batch` avec `evaluation_id` d'un collègue → assert **403**.
6. **Action interdite — Score hors plage** : POST `{score: 25}` sur éval `/20` → assert **422** + `errors.score`.
7. **Action interdite — Modifier note publiée** : tenter modification → assert **422** + message "Évaluation déjà publiée — demander une correction".
8. **Demande correction** : sur note publiée, cliquer "Demander correction" → renseigner motif → `POST .../request-correction` → assert 201 + workflow `pending` créé.
9. **Edge — Conflit concurrent** : deux navigateurs profs (même prof, deux onglets) modifient la même note → un des deux reçoit 409 + recharge.

## Dépendances backend

- ✅ Tous les endpoints listés existent dans [`teacher.php`](../../../Modules/NotesEvaluations/Routes/teacher.php) lignes 7-49.
- ⚠️ **À ajouter** : `middleware('role:Professeur,tenant')` sur le groupe (currently aucune garde rôle).
- ⚠️ **À vérifier en controller** : `GradeEntryController@storeBatch` valide `evaluation->teacher_id === auth()->id()`.
- ⚠️ **À implémenter** : verrouillage des notes `is_published=true` (`store/storeBatch` renvoient 422 si l'évaluation est publiée).
- ⚠️ **À implémenter** : gestion conflit optimiste (`updated_at` versioning + 409 Conflict).
- ⚠️ **À créer** côté FE : `src/modules/NotesEvaluations/teacher/components/GradeInputTable.tsx` avec virtual scrolling.

## Definition of Done

- [ ] Les 9 scénarios E2E passent
- [ ] Tests unitaires `GradeEntryController@storeBatch` : owner check (3 cas) + max_score (1 cas) + is_published lock (1 cas)
- [ ] Auto-save fonctionne sous Chrome/Firefox/Safari mobile
- [ ] Permission `manage grades` réellement vérifiée côté backend (pas que dans le seeder)

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | Middleware `role:Professeur|Administrator` (teacher.php) + `role:Administrator|Manager|Professeur` (Attendance/Timetable/Exams/Enrollment admin). Tests dans `tests/Feature/RoleCoverage/Professeur/TeacherRoutesProtectionTest.php` (12 tests passants). Ownership controllers déjà en place côté `GradeEntryController` ; à raffiner story par story pour Attendance/Timetable. | Dev Agent (James) |
