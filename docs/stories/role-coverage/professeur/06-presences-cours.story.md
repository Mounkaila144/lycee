# Story: Professeur — Présences en Cours - Coverage

**Module** : Attendance
**Rôle ciblé** : Professeur
**Menu(s) concerné(s)** : `/admin/teacher/attendance`
**Status** : Ready for Review

## User Story

En tant que **Professeur**, je veux marquer la présence/absence de mes élèves au début de chaque cours (idéalement en moins de 2 minutes), afin d'avoir une trace officielle des absences pour le surveillant général et les parents.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Présences en cours | ✅ | Le prof peut pointer ses cours |
| Toutes les sessions du tenant | ❌ | Vue admin uniquement |
| Justificatifs | 👁️ (lecture seule) | Le prof peut voir si un absent a un justificatif |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mes sessions du jour | Page principale | `GET /admin/attendance/sessions?teacher_id={me}&date=today` | query params | 200 + liste sessions | `role:Professeur,tenant` + filtre owner |
| Créer une session de pointage | Bouton "Nouvelle session" → modale | `POST /admin/attendance/sessions` | `class_id`, `subject_id`, `date`, `time_slot` | 201 + session créée | `role:Professeur,tenant` |
| Ouvrir la feuille de présence | Click sur session | `GET /admin/attendance/sessions/{sessionId}/sheet` | `sessionId` (path) | 200 + liste élèves + statuts par défaut | `role:Professeur,tenant` |
| Marquer présence/absence/retard | Click sur badge statut élève | `POST /admin/attendance/record` | `session_id`, `student_id`, `status: Présent\|Absent\|Retard\|Excusé` | 201 + record créé/maj | `role:Professeur,tenant` |
| Modifier un enregistrement | Edit icon → repaint badge | `PUT /admin/attendance/records/{recordId}` | `recordId` (path), `status`, `notes?` | 200 + record updated | `role:Professeur,tenant` |
| Clôturer la session | Bouton "Clôturer" | `POST /admin/attendance/sessions/{sessionId}/complete` | `sessionId` (path) | 200 + session `closed` | `role:Professeur,tenant` |
| Pointage QR (mode classe connectée) | Scanner QR badge élève | `POST /admin/attendance/record-qr` | `qr_token`, `session_id` | 201 + record automatique | `role:Professeur,tenant` |
| Voir l'historique absences d'un élève | Click sur nom élève → drawer | `GET /admin/attendance/monitoring/students/{studentId}/history` | `studentId` (path) | 200 + historique | `role:Professeur,tenant` + ownership "ses classes" |
| Voir justificatifs d'un élève absent | Bouton "Justifs" sur ligne absent | `GET /admin/justifications/students/{studentId}` | `studentId` (path) | 200 + liste justifs | `role:Professeur,tenant` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir/modifier une session d'un autre prof | `GET sessions/{id}/sheet` → **403** si `session->teacher_id !== auth()->id()` |
| Créer une session pour une classe qui n'est pas la sienne | **422** + message "Vous n'êtes pas assigné à cette classe" |
| Valider un justificatif | `POST /admin/justifications/{id}/validate` → **403** (réservé Surveillant Général / Admin) |
| Supprimer une session déjà clôturée | **422** + workflow correction admin |
| Modifier statut d'un élève qui n'est pas dans la classe de la session | **422** |
| Pointer un cours du passé > 7 jours | **422** + message "Pointage rétroactif au-delà de 7 jours interdit" |

## Cas limites (edge cases)

- **Session vide** : tous présents par défaut, prof clique "Tous présents".
- **Élève transféré en cours d'année** : présent dans la liste pour les dates antérieures à son transfert.
- **Coupure réseau pendant pointage** : queue locale (IndexedDB), synchro au retour.
- **Double pointage même élève même cours** : update (upsert) côté backend.
- **QR scan d'un élève d'une autre classe** : 422 + alerte "Élève hors classe".
- **Session créée par admin pour le prof** : le prof peut la modifier (owner OR `created_by_admin = true`).
- **Élève marqué `Absent` puis présenté en retard** : modifier en `Retard` ; trace dans `notes`.

## Scenarios de test E2E

1. **Création session + pointage** : login Professeur → "Nouvelle session" → classe 3A, matière Math, 08h-10h → assert 201 → ouvrir feuille → marquer 3 absents → cliquer "Clôturer" → assert DB `attendances` contient les 60 lignes (3 absents + 57 présents).
2. **QR pointage** : scanner QR badge → assert `attendance_record` créé statut `Présent`.
3. **Action interdite — Session autre prof** : `GET sessions/{other_session}/sheet` → **403**.
4. **Action interdite — Valider justif** : `POST /admin/justifications/{id}/validate` → **403**.
5. **Action interdite — Rétroactif** : créer session date `J-10` → **422**.
6. **Edge — Coupure réseau** : pointer en mode avion → assert sauvegardé localement → revenir online → assert synchro auto.
7. **Edge — Élève hors classe** : QR scan badge élève autre classe → **422**.

## Dépendances backend

- ✅ Tous les endpoints `attendance/*` existent ([`Attendance/Routes/admin.php`](../../../Modules/Attendance/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Professeur,tenant')` (route ouverte à tout authentifié)
- ⚠️ **À implémenter** : owner check sur sessions (le prof ne voit que ses sessions, ou celles assignées par admin)
- ⚠️ **À implémenter** : limitation pointage rétroactif (config tenant `attendance_retroactive_max_days`)
- ⚠️ **À implémenter** : permission `validate justifications` réservée à `Surveillant Général` + `Administrator` (rôle Surveillant Général non encore créé — pour MVP, fallback Administrator)
- ⚠️ **À créer** côté FE : `src/modules/Attendance/teacher/components/AttendanceSheet.tsx`

## Definition of Done

- [ ] Les 7 scénarios E2E passent
- [ ] Owner check appliqué et testé
- [ ] Mode offline (synchro IndexedDB) opérationnel
- [ ] Performance : pointage 60 élèves en < 2 minutes en E2E

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | Middleware `role:Professeur|Administrator` (teacher.php) + `role:Administrator|Manager|Professeur` (Attendance/Timetable/Exams/Enrollment admin). Tests dans `tests/Feature/RoleCoverage/Professeur/TeacherRoutesProtectionTest.php` (12 tests passants). Ownership controllers déjà en place côté `GradeEntryController` ; à raffiner story par story pour Attendance/Timetable. | Dev Agent (James) |
