# Story: Parent — Emploi du Temps de mon Enfant - Coverage

**Module** : PortailParent + Timetable
**Rôle ciblé** : Parent
**Menu(s) concerné(s)** : `/admin/parent/children/{id}/timetable`
**Status** : Approved

## User Story

En tant que **Parent**, je veux consulter l'emploi du temps de l'enfant sélectionné et le télécharger en PDF, afin de connaître ses horaires de cours et l'aider à s'organiser.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Emploi du temps (enfant) | ✅ | `view children timetable` |
| Modification | ❌ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir EDT enfant (semaine active) | Grille | `GET /api/admin/parent/children/{student}/timetable?week=YYYY-WW` (**À CRÉER**) | student, week? | 200 + créneaux classe enfant | `role:Parent,tenant` + `ChildPolicy::view` |
| Naviguer semaines | < > | idem | week | 200 | idem |
| Exporter EDT PDF | Bouton "Exporter PDF" | `GET /api/admin/parent/children/{student}/timetable/export-pdf?week=...` (**À CRÉER**) | path + week | 200 + PDF | idem |
| Voir exceptions/annulations | Badge cellule | inclus dans response | — | — | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir EDT d'un autre enfant non lié | `ChildPolicy::view` → **403** |
| Modifier EDT | `POST/PUT /admin/timetable/slots` → **403** |
| Voir EDT d'un prof | **403** |

## Cas limites (edge cases)

- **Vacances** : "Période de vacances".
- **Enfant non encore affecté à une classe** : "Affectation en attente — contactez l'administration".
- **Cours annulé/remplacé** : badges colorés.

## Scenarios de test E2E

1. **Voir EDT enfant** : login Parent → sélectionner enfant → "Emploi du temps" → assert grille classe de l'enfant.
2. **Export PDF** : "Exporter PDF" → assert PDF reçu.
3. **Action interdite — Autre enfant** : `GET .../children/{other_kid}/timetable` → **403**.
4. **Action interdite — Modifier** : `POST /admin/timetable/slots` → **403**.

## Dépendances backend

- ⚠️ **À créer** : `/api/admin/parent/children/{student}/timetable` + export-pdf
- ⚠️ **À implémenter** : `ChildPolicy::view`
- ⚠️ Bloque sur Story Parent 01

## Definition of Done

- [ ] Endpoints créés
- [ ] Les 4 scénarios E2E passent
- [ ] Ownership testé

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
