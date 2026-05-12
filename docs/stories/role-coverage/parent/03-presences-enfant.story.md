# Story: Parent — Présences & Absences de mon Enfant - Coverage

**Module** : PortailParent + Attendance
**Rôle ciblé** : Parent
**Menu(s) concerné(s)** : `/admin/parent/children/{id}/attendance`
**Status** : Ready for Review

## User Story

En tant que **Parent**, je veux consulter les présences/absences de l'enfant sélectionné, recevoir des notifications en cas d'absence et soumettre des justificatifs, afin de réagir rapidement aux absences de mon enfant et fournir les pièces justificatives.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Présences (enfant) | ✅ | `view children attendance` |
| Soumettre justificatif | ✅ | Endpoint à créer |
| Valider justif | ❌ | Admin/Surveillant Général |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir historique présences enfant | Calendrier coloré | `GET /api/admin/parent/children/{student}/attendance` (**À CRÉER**) | student (path), `from?`, `to?` | 200 + liste records | `role:Parent,tenant` + `ChildPolicy::view` |
| Voir stats absentéisme enfant | Widget | `GET /api/admin/parent/children/{student}/attendance/stats` (**À CRÉER**) | student | 200 + KPIs | idem |
| Soumettre un justificatif pour son enfant | Bouton "Soumettre justificatif" | `POST /api/admin/parent/children/{student}/justifications` (**À CRÉER**, ou réutiliser `POST /admin/justifications/`) | `attendance_record_id`, `reason`, `file` | 201 + status `pending` | idem |
| Voir justificatifs déposés pour son enfant | Onglet "Justificatifs" | `GET /api/admin/parent/children/{student}/justifications` (**À CRÉER**) | student | 200 + liste | idem |
| Télécharger un justif | Bouton "Voir" | `GET /api/admin/parent/children/{student}/justifications/{justification}/download` (**À CRÉER**) | path | 200 + file | idem |
| Recevoir alerte absence (notification email / SMS / push) | Auto à la création de l'absence | Event `EvaluationAbsenceMarked` + listener `NotifyParentOfAbsence` (**À CRÉER**) | — | — | event-based, pas d'endpoint |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir présences d'un autre enfant | `ChildPolicy::view` → **403** |
| Valider / refuser un justificatif | `POST /admin/justifications/{id}/validate` → **403** |
| Modifier un record de présence | `PUT /admin/attendance/records/{id}` → **403** |
| Upload fichier > 5 Mo ou mime invalide | **422** |

## Cas limites (edge cases)

- **Aucune absence** : "Aucune absence ce semestre 👍".
- **Justif déjà soumis pour cette absence** : 422 + "Justificatif déjà déposé".
- **Justif refusé** : badge rouge + motif visible.
- **Seuil d'alerte dépassé** : email/SMS automatique au parent + bandeau alerte.
- **Délai de justification (ex: 5 jours)** : impossible de soumettre au-delà sans motif spécial.

## Scenarios de test E2E

1. **Voir présences enfant** : login Parent → sélectionner enfant → "Présences" → assert calendrier filtré.
2. **Soumettre justif** : absence du jour → "Soumettre justificatif" → upload PDF certif → assert 201.
3. **Action interdite — Autre enfant** : `GET .../children/{other_kid}/attendance` → **403**.
4. **Action interdite — Valider justif** : `POST /admin/justifications/{id}/validate` → **403**.
5. **Edge — Justif en double** : tenter soumettre 2x sur même record → **422**.
6. **Notification absence** : créer absence côté Prof → assert email/SMS envoyé au parent.

## Dépendances backend

- ⚠️ **À créer** : endpoints listés ci-dessus
- ⚠️ **À implémenter** : `ChildPolicy::view`
- ⚠️ **À implémenter** : Event `AttendanceMarked` + Listener `NotifyParentOfAbsence` (utilise notifs Laravel)
- ⚠️ **À implémenter** : Canal notification (email obligatoire, SMS optionnel selon tenant settings)
- ⚠️ Bloque sur Story Parent 01

## Definition of Done

- [ ] Endpoints créés
- [ ] Event/Listener notifications opérationnels
- [ ] Les 6 scénarios E2E passent
- [ ] Ownership testé

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
