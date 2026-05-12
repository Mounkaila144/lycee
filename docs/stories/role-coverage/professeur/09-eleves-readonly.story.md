# Story: Professeur — Élèves de Mes Classes (Lecture seule) - Coverage

**Module** : UsersGuard + Enrollment
**Rôle ciblé** : Professeur
**Menu(s) concerné(s)** : `/admin/teacher/students`
**Status** : Ready for Review

## User Story

En tant que **Professeur**, je veux consulter en lecture seule la liste des élèves de mes classes avec leurs informations académiques (notes, présences, contacts d'urgence en cas de besoin), afin de mieux suivre individuellement chaque élève sans avoir à demander à l'administration.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Élèves (mes classes) | ✅ | Permission `view students` ([`seeder L115`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php)) + middleware actif ([`UsersGuard/admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php)) |
| Création/Suppression élève | ❌ | Admin/Manager uniquement |
| Liste GLOBALE des élèves du tenant | ❌ | Filtre owner — Professeur ne voit que ses classes |
| Fiche complète (incl. discipline, santé) | ⚠️ Lecture filtrée | Pas tous les champs visibles (cf. §Cas limites) |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister élèves de mes classes | Page principale (DataGrid) | `GET /admin/students/?class_id={ma_classe}` | `class_id` query (filtré côté backend à ses classes) | 200 + liste paginée | `role:Administrator\|Manager\|Caissier\|Comptable\|Agent Comptable\|Professeur,tenant` ([`UsersGuard/admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php)) |
| Voir liste élèves d'une de mes classes spécifiquement | Filtre classe dans la grille | `GET /admin/students/?class_id=X` (X dans `$myClassIds`) | `class_id` | 200 | idem |
| Recherche élève (autocomplete) | Barre recherche | `GET /admin/enrollment/students/search/autocomplete?q=...` | `q` | 200 + suggestions (filtrées à ses classes) | `role:Professeur,tenant` |
| Voir fiche détaillée d'un élève | Click sur ligne | `GET /admin/enrollment/students/{student}` | `student` (path) | 200 + fiche (champs filtrés pour Prof) | `role:Professeur,tenant` + ownership "élève dans une de mes classes" |
| Voir notes d'un élève (mes matières) | Onglet "Notes" sur fiche | `GET /admin/enrollment/students/{student}/grades?subject_id={mine}` (à créer ou à filtrer) | `student`, `subject_id` | 200 + notes de l'élève dans MA matière | `role:Professeur,tenant` |
| Voir présence d'un élève | Onglet "Présences" sur fiche | `GET /admin/attendance/monitoring/students/{studentId}/history` | `studentId` (path) | 200 + historique | `role:Professeur,tenant` + ownership |
| Voir contact d'urgence | Sur fiche : section "Contact" | (inclus dans `GET /students/{id}`) | — | 200 + champs `emergency_contact_*` | `role:Professeur,tenant` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Créer un élève | Endpoint `POST /admin/enrollment/students` → **403** (à ajouter middleware role) |
| Modifier un élève | Endpoint `PUT /admin/enrollment/students/{id}` → **403** |
| Supprimer un élève | Endpoint `DELETE` → **403** |
| Voir liste globale tous tenants (cross-tenancy) | Tenant.auth garantit isolation |
| Voir élèves d'une classe qui n'est pas la sienne | Filter controller : query `students` jointure `class_enrollments` avec `WHERE class_id IN ($myClasses)` |
| Voir données sensibles : dossier discipline, santé (`health_notes`, `blood_group`) | `StudentResource` filtre ces champs si `auth()->user()->hasRole('Professeur')` |
| Voir notes d'un élève dans une matière qui n'est pas sa matière | Filter `WHERE subject_id IN ($mySubjects)` |
| Changer statut d'un élève (`actif → exclu`) | `POST students/{id}/status` → **403** (réservé Admin) |
| Uploader documents pour un élève | `POST students/{id}/documents` → **403** |

## Cas limites (edge cases)

- **Élève transféré récemment** : visible pour les périodes où il était en classe du Professeur ; non visible après transfert.
- **Élève nouvellement inscrit en cours d'année** : visible dès enregistrement.
- **Professeur principal (PP)** : voir TOUS les élèves de sa classe principale + plus de champs (numéros parents, etc.) — flag `is_head_teacher` côté controller.
- **Champs sensibles** : `health_notes`, `blood_group`, `disciplinary_records` masqués sauf si PP de la classe.
- **Photo manquante** : avatar placeholder.
- **Recherche cross-classe** : un nom commun peut renvoyer plusieurs élèves de différentes classes — seulement les SIENS apparaissent.

## Scenarios de test E2E

1. **Liste élèves** : login Professeur → "Élèves" → assert liste contient SES élèves uniquement (pas ceux des autres classes).
2. **Recherche** : taper "Dia" dans autocomplete → assert résultats filtrés à ses classes.
3. **Fiche élève** : ouvrir fiche → assert visibles `firstname, lastname, matricule, classe, parents (PP only)` ; assert masqués `health_notes, blood_group` (sauf PP).
4. **Notes d'un élève dans ma matière** : assert notes filtrées à `subject_id == $myAssignedSubject`.
5. **Action interdite — Créer** : `POST /admin/enrollment/students` → **403**.
6. **Action interdite — Modifier** : `PUT /admin/enrollment/students/{id}` → **403**.
7. **Action interdite — Élève autre classe** : `GET /admin/enrollment/students/{id_other_class}` → **403** ou **404**.
8. **Edge — PP voit plus** : login Prof qui est PP d'une classe → `GET /students/{id}` → assert `parents` et `health_notes` visibles ; même Prof sur élève d'une autre classe → masqués.

## Dépendances backend

- ✅ `GET /admin/students/` — middleware actif inclut `Professeur` ([`UsersGuard/admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php))
- ⚠️ **À implémenter** : filtre owner dans `UserController@students` (un prof ne voit que ses classes)
- ⚠️ **À implémenter** : `StudentResource` conditionnelle par rôle (filtrer `health_notes`, `blood_group`, etc.)
- ⚠️ **À implémenter** : flag `is_head_teacher` ou check `Classe::where('head_teacher_id', auth()->id())`
- ⚠️ **À implémenter** : middleware sur `POST/PUT/DELETE /admin/enrollment/students/*` qui exclut Professeur (currently aucun garde rôle)
- ⚠️ **À implémenter** : endpoint `GET /admin/enrollment/students/{student}/grades?subject_id=` filtré aux matières du prof connecté

## Definition of Done

- [ ] Les 8 scénarios E2E passent
- [ ] Filtre owner appliqué côté `UserController@students` et `StudentController@show`
- [ ] `StudentResource` masque les champs sensibles selon le rôle
- [ ] PP voit plus de champs (test dédié)
- [ ] Middlewares `role:Administrator|Manager,tenant` ajoutés sur les routes CRUD élèves

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | Middleware `role:Professeur|Administrator` (teacher.php) + `role:Administrator|Manager|Professeur` (Attendance/Timetable/Exams/Enrollment admin). Tests dans `tests/Feature/RoleCoverage/Professeur/TeacherRoutesProtectionTest.php` (12 tests passants). Ownership controllers déjà en place côté `GradeEntryController` ; à raffiner story par story pour Attendance/Timetable. | Dev Agent (James) |
