# Story: Étudiant — Mes Notes & Bulletins - Coverage

**Module** : NotesEvaluations (frontend)
**Rôle ciblé** : Étudiant
**Menu(s) concerné(s)** : `/admin/student/grades`
**Status** : Ready for Review

## User Story

En tant qu'**Étudiant**, je veux consulter mes notes par matière et par semestre, télécharger mon bulletin semestriel et voir ma moyenne générale, afin de suivre mes performances scolaires.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Mes notes | ✅ | `view own grades` |
| Bulletins (semestriels/annuel) | ✅ | Téléchargement PDF |
| Évaluations (détail) | ✅ | Liste évaluations par matière |
| Notes d'autres élèves | ❌ | Ownership strict |
| Modifier/contester une note | ❌ | Pas d'interface ; doit passer par le prof |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mes notes du semestre actif | Page principale | `GET /api/frontend/student/grades` (**À CRÉER**) | `semester_id?`, `subject_id?` | 200 + notes filtrées owner | `role:Étudiant,tenant` + filtre `student_id = auth()->user()->student_id` |
| Voir notes d'un semestre passé | Sélecteur semestre | idem avec `semester_id` | semester_id | 200 | idem |
| Voir détail évaluation (max_score, date, statistiques anonymisées) | Click sur note | `GET /api/frontend/student/grades/{grade}` (**À CRÉER**) | `grade` (path) | 200 + détail SANS classement individuel des camarades | idem |
| Voir ma moyenne par matière | Onglet "Matières" | inclus dans `/grades?aggregate=subject` (**À CRÉER**) | — | 200 + map subject_id → moyenne | idem |
| Voir ma moyenne générale + rang (anonymisé) | Banner haut de page | inclus dans dashboard ou `/grades?aggregate=general` (**À CRÉER**) | — | 200 + `{moyenne, rang}` | idem |
| Télécharger bulletin semestriel PDF | Bouton "Télécharger bulletin" | `GET /api/frontend/student/grades/{semester}/bulletin` (**À CRÉER**) | `semester` (path) | 200 + PDF | idem |
| Télécharger bulletin annuel | Bouton "Bulletin annuel" | `GET /api/frontend/student/bulletin/annual?academic_year_id=X` (**À CRÉER**) | `academic_year_id` | 200 + PDF | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir notes d'un autre élève (changer student_id query) | Endpoint ignore tout `student_id` passé en query — toujours `auth()->user()` |
| Voir les notes nominales des camarades dans une statistique | Stats agrégées anonymisées (min/max/moyenne classe, pas la liste) |
| Modifier une note | Pas d'interface ; endpoint `POST/PUT /api/admin/grades` → **403** |
| Voir notes non publiées (`is_published=false`) | Filter `WHERE is_published = true` côté query |
| Accéder aux endpoints admin notes (`/api/admin/grades/*`) | **403** |

## Cas limites (edge cases)

- **Aucune note saisie encore** : "Pas encore de notes pour ce semestre."
- **Bulletin pas encore publié** : bouton "Télécharger" grisé + tooltip "Bulletin pas encore publié — disponible à partir du JJ/MM".
- **Élève en redoublement** : montrer toutes années + bulletin par année.
- **Note contestée** : badge "En cours de correction" si workflow ouvert.
- **Statistiques classe** : agrégats anonymisés (médiane, écart-type, sans noms) pour respecter la vie privée des camarades.

## Scenarios de test E2E

1. **Voir mes notes** : login Étudiant → `GET /api/frontend/student/grades` → assert TOUTES retournées concernent `student_id = auth()->user()->student_id`.
2. **Bulletin PDF** : cliquer "Télécharger bulletin S1" → assert PDF contient nom élève + matières + moyennes.
3. **Action interdite — Notes camarade** : `GET /api/frontend/student/grades?student_id=99` → assert résultat = mes notes (param ignoré).
4. **Action interdite — Notes non publiées** : assert `is_published=false` masquées.
5. **Action interdite — Endpoint admin** : `POST /api/admin/grades` → **403**.
6. **Edge — Pas de note** : nouvel élève → page vide message attendu.

## Dépendances backend

- ⚠️ **À créer** : `GET /api/frontend/student/grades` (+ filtres semester/subject/aggregate)
- ⚠️ **À créer** : `GET /api/frontend/student/grades/{grade}` (détail)
- ⚠️ **À créer** : `GET /api/frontend/student/grades/{semester}/bulletin` (PDF)
- ⚠️ **À créer** : `GET /api/frontend/student/bulletin/annual`
- ⚠️ **À implémenter** : `StudentGradesController` avec ownership obligatoire (jamais query student_id)
- ⚠️ **À implémenter** : `StudentBulletinResource` qui n'expose pas les notes non publiées
- ⚠️ Dépendance `barryvdh/laravel-dompdf` pour bulletin PDF

## Definition of Done

- [ ] Les 6 scénarios E2E passent
- [ ] Aucun endpoint ne prend `student_id` en query
- [ ] PDF bulletin testé : contenu + signature/QR
- [ ] Notes non publiées masquées

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
