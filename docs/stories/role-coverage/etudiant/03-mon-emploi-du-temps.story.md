# Story: Étudiant — Mon Emploi du Temps - Coverage

**Module** : Timetable
**Rôle ciblé** : Étudiant
**Menu(s) concerné(s)** : `/admin/student/timetable`
**Status** : Approved

## User Story

En tant qu'**Étudiant**, je veux consulter mon emploi du temps semaine par semaine et le télécharger en PDF, afin de m'organiser et préparer mes affaires de cours.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Mon emploi du temps | ✅ | `view own timetable` |
| EDT global / Autre classe | ❌ | Ownership |
| Édition EDT | ❌ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mon EDT semaine actuelle | Grille lundi→samedi | `GET /api/frontend/student/timetable?week=YYYY-WW` (**À CRÉER**) | `week?` | 200 + créneaux de MA classe | `role:Étudiant,tenant` + filtre `class_id = my_class` |
| Naviguer semaines passées/futures | Boutons < > | idem avec `week` | week | 200 | idem |
| Voir détail d'un créneau | Click cellule | inclus dans EDT response | — | 200 | idem |
| Exporter EDT semaine en PDF | Bouton "Exporter PDF" | `GET /api/frontend/student/timetable/export-pdf?week=...` (**À CRÉER**) | week | 200 + PDF | idem |
| Voir exceptions/annulations | Badge sur cellule | inclus dans EDT response | — | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir EDT d'une autre classe | Endpoint ignore `class_id` param — déduit de `auth()->user()->student->current_enrollment->class_id` |
| Modifier un créneau | Pas d'interface + `POST/PUT /admin/timetable/slots` → **403** |
| Voir EDT d'un prof | Pas d'item menu + `GET /admin/timetable/views/teacher/{id}` → **403** |

## Cas limites (edge cases)

- **Semaine de vacances** : "Période de vacances".
- **Élève transféré récemment** : EDT de sa nouvelle classe à partir de sa date de transfert.
- **Pas encore affecté à une classe** : "Votre affectation à une classe est en attente".
- **Cours annulé** : cellule rouge "Cours annulé — [motif]".
- **Cours remplacé (changement prof/salle)** : cellule jaune + détail nouveau prof/salle.

## Scenarios de test E2E

1. **Voir EDT** : login Étudiant → "Mon emploi du temps" → assert créneaux de SA classe uniquement.
2. **Export PDF** : "Exporter PDF" → assert fichier reçu avec nom élève + classe + créneaux.
3. **Naviguer semaines** : cliquer ">" → assert URL et grille mises à jour.
4. **Action interdite — Autre classe** : `GET /timetable?class_id=X` → assert résultat = MA classe (param ignoré).
5. **Action interdite — EDT prof** : `GET /admin/timetable/views/teacher/{id}` → **403**.
6. **Edge — Vacances** : naviguer sur semaine vacances → message attendu.

## Dépendances backend

- ⚠️ **À créer** : `GET /api/frontend/student/timetable` (lecture filtrée à la classe de l'élève)
- ⚠️ **À créer** : `GET /api/frontend/student/timetable/export-pdf`
- ⚠️ **À implémenter** : ownership = classe courante de l'élève
- ⚠️ Côté FE : `src/modules/Timetable/student/components/MyTimetable.tsx` (à créer)
- ⚠️ Fichier `Modules/Timetable/Routes/frontend.php` actuellement vide ([`inventaire`](../README.md#4-inventaire-des-modules-backend-10-modules))

## Definition of Done

- [ ] Endpoints créés
- [ ] Les 6 scénarios E2E passent
- [ ] Ownership testé (3 cas)
- [ ] PDF généré avec contenu correct

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
