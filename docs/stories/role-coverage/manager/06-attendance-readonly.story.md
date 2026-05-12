# Story: Manager — Présences (Lecture seule) - Coverage

**Module** : Attendance
**Rôle ciblé** : Manager
**Menu(s) concerné(s)** : `/admin/attendance/reports`
**Status** : Ready for Review

## User Story

En tant que **Manager**, je veux consulter les rapports de présence (taux, absentéismes, alertes) sans pouvoir saisir, afin de superviser la vie scolaire et identifier les élèves en difficulté.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Rapports présence | ✅ | Lecture |
| Justificatifs (lecture) | ✅ | Voir |
| Saisie pointage | ❌ | Prof |
| Validation justifs | ❌ | Surveillant Général / Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir taux présence | Page | `GET /admin/attendance/reports/rates` | filters | 200 | `role:Administrator\|Manager,tenant` (à ajouter) |
| Liste absentéistes | Page | `GET /admin/attendance/reports/absentees` | filters | 200 | idem |
| Stats détaillées | Page | `GET /admin/attendance/reports/statistics` | filters | 200 | idem |
| Exporter rapport | Bouton "Exporter" | `GET /admin/attendance/reports/export?format=xlsx\|pdf` | filters | 200 + fichier | idem |
| Voir alertes seuils | Widget | `GET /admin/attendance/monitoring/alerts` | — | 200 | idem |
| Voir justificatifs en attente | Liste | `GET /admin/justifications/pending` (lecture) | — | 200 | idem (lecture seulement) |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Saisir pointage | `POST /admin/attendance/record` → **403** (Prof) |
| Modifier record | `PUT /admin/attendance/records/{id}` → **403** |
| Valider/refuser justif | `POST /admin/justifications/{id}/validate` → **403** |
| Configurer seuils alerte | `POST .../monitoring/check-thresholds` → **403** (Admin) |

## Cas limites (edge cases)

- **Aucune donnée présence** : "Pas encore de pointage".
- **Export volumineux** : queue + email.
- **Seuil dépassé** : alerte visible Manager.

## Scenarios de test E2E

1. **Voir rapports** : login Manager → assert taux/absentéistes/stats accessibles.
2. **Exporter** : assert fichier.
3. **Action interdite — Saisir** : `POST /admin/attendance/record` → **403**.
4. **Action interdite — Valider justif** : **403**.
5. **Edge — Seuil dépassé** : alerte visible.

## Dépendances backend

- ❌ Aucun middleware role sur `Attendance/Routes/admin.php`
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager|Professeur,tenant')` sur reports (Prof y a accès aussi)
- ⚠️ **À ajouter** : `middleware('role:Administrator|Professeur,tenant')` sur record/save (exclure Manager)
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur validate justif (jusqu'à création rôle Surveillant Général)

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Manager bloqué saisie
- [ ] Exports opérationnels

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
