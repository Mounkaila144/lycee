# Story: Manager — Emplois du Temps (Lecture seule) - Coverage

**Module** : Timetable
**Rôle ciblé** : Manager
**Menu(s) concerné(s)** : `/admin/timetable/views`
**Status** : Ready for Review

## User Story

En tant que **Manager**, je veux consulter les emplois du temps par classe / professeur / salle (sans pouvoir les modifier), afin de vérifier la couverture et préparer la gestion opérationnelle.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Vue EDT par classe | ✅ | Lecture |
| Vue EDT par prof | ✅ | Lecture |
| Vue EDT par salle | ✅ | Lecture |
| Édition / Création slot | ❌ | Admin |
| Génération auto EDT | ❌ | Admin |
| Préférences enseignant (lecture) | ✅ | Lecture |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir EDT classe | Sélecteur | `GET /admin/timetable/views/class/{class}?week=...` | filters | 200 | `role:Administrator\|Manager,tenant` (à ajouter) |
| Voir EDT prof | Sélecteur | `GET /admin/timetable/views/teacher/{teacher}?week=...` | filters | 200 | idem |
| Voir EDT salle | Sélecteur | `GET /admin/timetable/views/room/{room}?week=...` | filters | 200 | idem |
| Voir conflits/exceptions | Onglet | `GET /admin/timetable/conflicts` (à vérifier endpoint) | filters | 200 | idem |
| Exporter PDF | Bouton "Exporter" | `GET /admin/timetable/export-pdf?type=class&id=...` (à confirmer) | filters | 200 + PDF | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Créer / modifier / supprimer slot | **403** Admin |
| Génération auto EDT | **403** Admin |
| Modifier salle ou créneau | **403** |
| Modifier préférences enseignant | **403** |

## Cas limites (edge cases)

- **Conflits horaires détectés** : badge + lien "Demander Admin de résoudre".
- **Salle changée la veille** : Manager voit la dernière version.
- **Période vacances** : grille vide.

## Scenarios de test E2E

1. **Voir EDT classe** : assert grille.
2. **Voir EDT prof** : assert grille.
3. **Voir EDT salle** : assert grille.
4. **Action interdite — Créer slot** : `POST /admin/timetable/slots` → **403**.
5. **Action interdite — Génération auto** : `POST .../generate` → **403**.

## Dépendances backend

- ❌ Aucun middleware role sur `Timetable/Routes/admin.php`
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager,tenant')` sur GET views
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur write (slots, rooms, generation)

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Middlewares appliqués

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
