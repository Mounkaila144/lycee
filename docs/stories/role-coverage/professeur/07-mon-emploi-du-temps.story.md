# Story: Professeur — Mon Emploi du Temps - Coverage

**Module** : Timetable
**Rôle ciblé** : Professeur
**Menu(s) concerné(s)** : `/admin/teacher/timetable`
**Status** : Ready for Review

## User Story

En tant que **Professeur**, je veux consulter mon emploi du temps de la semaine (mes cours, salles, classes), afin de m'organiser et préparer mes leçons en avance.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Mon emploi du temps | ✅ | Permission `view timetable` ([`seeder L117`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php)) |
| Emploi du temps global tenant | ❌ | Vue admin |
| Édition emploi du temps | ❌ | Vue admin |
| Préférences enseignant | ✅ | Le prof peut soumettre ses préférences |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mon EDT semaine | Grille hebdo lundi→samedi | `GET /admin/timetable/views/teacher/{me}?week=YYYY-WW` | path teacher = `auth()->id()`, week (query) | 200 + créneaux (jour, heure, classe, matière, salle) | `role:Professeur,tenant` + ownership |
| Voir EDT d'une autre semaine | Navigation < > | Idem avec `week` différent | week (query) | 200 | `role:Professeur,tenant` |
| Voir détail d'un créneau | Click créneau → popover | `GET /admin/timetable/slots/{slot}` (à vérifier endpoint) | `slot` (path) | 200 + détail | `role:Professeur,tenant` + ownership |
| Exporter mon EDT PDF | Bouton "Exporter PDF" | `GET /admin/timetable/views/teacher/{me}/export-pdf` (à créer ?) | `me` (path) | 200 + PDF semaine | `role:Professeur,tenant` |
| Soumettre mes préférences (créneaux préférés / indisponibilités) | Bouton "Mes préférences" → formulaire | `POST /admin/timetable/teacher-preferences` (à confirmer endpoint) | `unavailable_slots: array`, `preferred_days?` | 201 + préférences sauvées | `role:Professeur,tenant` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir l'EDT d'un autre prof | `GET /views/teacher/{other_id}` → **403** si `other_id !== auth()->id()` (sauf Admin) |
| Voir l'EDT global d'une classe | `GET /views/class/{id}` → **403** ou écran lecture limité (seulement créneaux qui le concernent) |
| Créer / modifier / supprimer un créneau | Endpoints `slots POST/PUT/DELETE` → **403** depuis le rôle Professeur |
| Modifier une salle | Endpoint `rooms` → **403** |
| Modifier ses préférences alors que la génération EDT est verrouillée | **422** + "Période de modification fermée" |

## Cas limites (edge cases)

- **Semaine sans cours** (vacances scolaires) : grille vide + message "Aucun cours cette semaine — période de vacances".
- **Créneau ponctuellement remplacé/annulé** : badge "Remplacé par M. X" ou "Annulé" sur la cellule.
- **Conflit dans données** (deux cours même créneau, à corriger par admin) : badge rouge "Conflit horaire — contacter l'administration".
- **Salle changée la veille** : notification temps réel (phase 2) ou rafraîchissement au F5.
- **Export PDF** : entête établissement + footer pagination.

## Scenarios de test E2E

1. **Voir EDT semaine** : login Professeur → "Mon emploi du temps" → assert grille avec créneaux du prof connecté uniquement.
2. **Naviguer semaine suivante** : cliquer ">" → assert URL `?week=YYYY-WW+1` + grille mise à jour.
3. **Export PDF** : cliquer "Exporter PDF" → assert téléchargement fichier nommé `edt-[nom prof]-[semaine].pdf`.
4. **Action interdite — EDT autre prof** : `GET /views/teacher/{other_id}` → **403**.
5. **Action interdite — Créer slot** : `POST /admin/timetable/slots` → **403**.
6. **Edge — Vacances** : naviguer sur semaine de vacances → message vide.

## Dépendances backend

- ⚠️ `GET /admin/timetable/views/teacher/{id}` — à vérifier dans [`Timetable/Routes/admin.php`](../../../Modules/Timetable/Routes/admin.php) (mentionné dans inventaire mais pas vérifié ligne par ligne)
- ⚠️ **À implémenter probablement** : `GET /admin/timetable/views/teacher/{me}/export-pdf` — endpoint export
- ⚠️ **À implémenter** : ownership strict — un Professeur ne peut interroger que son propre id, sinon 403
- ⚠️ **À implémenter** : `POST /admin/timetable/teacher-preferences` (route présente dans inventaire, à vérifier auth+contract)
- ⚠️ **À ajouter** : `middleware('role:Professeur,tenant')` côté routes timetable
- ⚠️ **À créer** côté FE : `src/modules/Timetable/teacher/components/MyTimetable.tsx`

## Definition of Done

- [ ] Les 6 scénarios E2E passent
- [ ] Ownership 403 testé pour `views/teacher/{other_id}`
- [ ] Export PDF généré via `barryvdh/laravel-dompdf`
- [ ] Performance : grille semaine chargée en < 500ms

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | Middleware `role:Professeur|Administrator` (teacher.php) + `role:Administrator|Manager|Professeur` (Attendance/Timetable/Exams/Enrollment admin). Tests dans `tests/Feature/RoleCoverage/Professeur/TeacherRoutesProtectionTest.php` (12 tests passants). Ownership controllers déjà en place côté `GradeEntryController` ; à raffiner story par story pour Attendance/Timetable. | Dev Agent (James) |
