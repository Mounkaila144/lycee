# Story: Étudiant — Mes Présences & Absences - Coverage

**Module** : Attendance
**Rôle ciblé** : Étudiant
**Menu(s) concerné(s)** : `/admin/student/attendance`
**Status** : Ready for Review

## User Story

En tant qu'**Étudiant**, je veux voir mes présences/absences/retards avec leurs statuts (justifié ou non), uploader des justificatifs, afin de gérer mes obligations de présence et fournir les pièces requises.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Mes présences | ✅ | `view own attendance` |
| Uploader justificatif | ✅ | `upload documents` |
| Liste générale absentéisme | ❌ | Surveillant Général/Admin |
| Valider un justificatif | ❌ | Surveillant Général/Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mon historique présences | Calendrier ou liste | `GET /admin/attendance/monitoring/students/{me}/history` (avec filtre owner) | path = `auth()->user()->student_id` | 200 + liste records | `role:Étudiant,tenant` + ownership |
| Voir mes statistiques (taux présence) | Widget | `GET /admin/attendance/monitoring/students/{me}/stats` | path | 200 + stats | idem |
| Soumettre un justificatif | Bouton "Soumettre justificatif" | `POST /admin/justifications/` | `attendance_record_id`, `reason`, `file` (PDF/JPG) | 201 + status `pending` | `role:Étudiant,tenant` |
| Voir mes justificatifs | Onglet "Justificatifs" | `GET /admin/justifications/students/{me}` | path | 200 + liste | idem |
| Télécharger un justificatif soumis | Bouton download | `GET /admin/justifications/{justificationId}/download` | path | 200 + file | idem (filter owner) |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir présences d'un autre élève | Endpoint ignore `studentId` autre → **403** ou substitué |
| Valider/refuser un justificatif | `POST /admin/justifications/{id}/validate` → **403** (Surveillant Général/Admin) |
| Voir liste générale `pending justifications` | `GET /admin/justifications/pending` → **403** |
| Modifier un record de présence | `PUT /admin/attendance/records/{id}` → **403** |
| Uploader fichier > 5 Mo, mime autre que PDF/JPG/PNG | **422** |

## Cas limites (edge cases)

- **Aucune absence** : "Tu n'as aucune absence ce semestre 👍".
- **Absence non encore régularisée** : badge orange "À justifier sous 7 jours".
- **Justif rejeté par admin** : badge rouge "Refusé — motif : ...".
- **Seuil d'alerte dépassé** : bandeau rouge "Vous avez dépassé X absences — attention au conseil de classe".
- **Fichier corrompu** : 422 + message.

## Scenarios de test E2E

1. **Voir mes présences** : login Étudiant → "Mes présences" → assert calendrier coloré pour MOI seulement.
2. **Soumettre justif** : choisir une absence → upload PDF certif médical → assert 201 + statut `pending`.
3. **Action interdite — Autre élève** : `GET /admin/attendance/monitoring/students/{other_id}/history` → **403**.
4. **Action interdite — Valider justif** : `POST /admin/justifications/{id}/validate` → **403**.
5. **Action interdite — Liste pending** : `GET /admin/justifications/pending` → **403**.
6. **Edge — Seuil dépassé** : créer 11 absences → bandeau d'alerte affiché.

## Dépendances backend

- ✅ Endpoints `attendance/monitoring/*` et `justifications/*` existent ([`Attendance/Routes/admin.php`](../../../Modules/Attendance/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Étudiant,tenant')` + ownership strict
- ⚠️ **À implémenter** : `JustificationController@submit` vérifie `attendance_record->student_id === auth()->user()->student_id`
- ⚠️ **À implémenter** : restriction `POST /admin/justifications/{id}/validate` au rôle Surveillant Général/Admin (rôle Surveillant Général à créer ultérieurement)
- ⚠️ **À implémenter** : `seuil_alerte_absences` configurable par tenant

## Definition of Done

- [ ] Les 6 scénarios E2E passent
- [ ] Ownership testé
- [ ] Upload sécurisé (mime + size)
- [ ] Seuil d'alerte fonctionnel

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
