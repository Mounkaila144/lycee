# Story: Professeur — Surveillance d'Examens - Coverage

**Module** : Exams
**Rôle ciblé** : Professeur
**Menu(s) concerné(s)** : `/admin/teacher/exams/supervision`
**Status** : Ready for Review

## User Story

En tant que **Professeur**, je veux voir mon planning de surveillance d'examens, pointer ma présence à la surveillance, enregistrer la présence des candidats et déclarer un incident éventuel, afin de remplir mon rôle de surveillant pendant les sessions d'examen.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Mes surveillances | ✅ | Endpoint `supervision/teachers/{id}/schedule` |
| Tableau global des sessions examen | ❌ | Admin |
| Déclaration d'incident | ✅ | Pendant la surveillance |
| Approbation d'incidents | ❌ | Admin / Censeur |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mon planning surveillance | Page principale | `GET /admin/exams/supervision/teachers/{me}/schedule` | path = `auth()->id()` | 200 + liste sessions surveillance | `role:Professeur,tenant` + ownership |
| Marquer ma présence à la surveillance | Bouton "Je suis présent" | `PUT /admin/exams/supervision/supervisors/{supervisor}/present` | `supervisor` (path) | 200 + statut `present` | `role:Professeur,tenant` + ownership |
| Enregistrer présence candidat | Click badge "Présent/Absent" sur ligne candidat | `PUT /admin/exams/supervision/attendance-sheets/{sheet}/status` | `sheet` (path), `status: Présent\|Absent\|Retard` | 200 | `role:Professeur,tenant` |
| Enregistrer remise de copie | Bouton "A rendu" | `POST /admin/exams/supervision/attendance-sheets/{sheet}/submit` | `sheet` (path), `submitted_at?` | 201 | `role:Professeur,tenant` |
| Vérifier feuille (signature finale) | Bouton "Valider feuille" en fin de session | `POST /admin/exams/supervision/attendance-sheets/{sheet}/verify` | `sheet` (path), `signature: text` | 200 + sheet `verified` | `role:Professeur,tenant` |
| Déclarer un incident | Bouton "Incident" → modale | `POST /admin/exams/supervision/incidents` | `session_id`, `student_id?`, `type`, `description` | 201 + incident `open` | `role:Professeur,tenant` |
| Ajouter preuve à un incident | Drag-drop image/pdf | `POST /admin/exams/supervision/incidents/{incident}/evidence` | `incident` (path), `file` | 201 + evidence rattachée | `role:Professeur,tenant` |
| Voir statistiques présence session | Onglet "Stats" en fin de session | `GET /admin/exams/supervision/sessions/{session}/attendance-stats` | `session` (path) | 200 + count présents/absents | `role:Professeur,tenant` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir planning d'un autre surveillant | `GET supervision/teachers/{other_id}/schedule` → **403** |
| Annuler/replacer un surveillant | `POST supervisors/{id}/replace` → **403** (Admin seul) |
| Approuver/clore un incident | `PUT incidents/{id}/status` ou `escalate` → **403** (Admin/Censeur) |
| Modifier session d'examen (créer/dupliquer/publier) | `POST sessions/*` → **403** |
| Déclarer un incident sur une session où il n'est pas surveillant | **422** + "Vous n'êtes pas assigné à cette session" |
| Voir feuille de présence d'une session non assignée | **403** |

## Cas limites (edge cases)

- **Surveillant absent (lui-même)** : le statut reste `pending` ; un autre prof peut être assigné par admin (`replace` endpoint).
- **Candidat absent à l'examen** : statut `Absent` + lien automatique avec module rattrapage (story 05).
- **Incident grave (fraude flagrante)** : badge rouge + alerte temps réel admin.
- **Pas de connexion en salle d'examen** : mode hors ligne pour pointage candidats + synchro à la sortie.
- **Salle change à la dernière minute** : push notification (phase 2) ou rafraîchissement manuel.
- **Le prof oublie de valider la feuille** : tâche de rappel admin J+1.

## Scenarios de test E2E

1. **Planning** : login Professeur → "Mes surveillances" → assert liste sessions où il est assigné.
2. **Pointer présence (soi) + valider feuille** : cliquer "Je suis présent" → marquer 30 candidats → "A rendu" sur chaque → "Valider feuille" + signature → assert sheet `verified`.
3. **Déclarer incident** : pendant session, cliquer "Incident" → renseigner type Fraude + description → upload photo → assert incident créé status `open`.
4. **Action interdite — Planning autre prof** : `GET supervision/teachers/{other_id}/schedule` → **403**.
5. **Action interdite — Approuver incident** : `PUT incidents/{id}/status` → **403**.
6. **Action interdite — Replace** : `POST supervisors/{id}/replace` → **403**.
7. **Edge — Mode offline** : déconnecter réseau pendant pointage → assert pointages stockés localement.

## Dépendances backend

- ✅ Endpoints `supervision/*` existent ([`Exams/Routes/admin.php`](../../../Modules/Exams/Routes/admin.php))
- ⚠️ **Critique** : `Exams/Routes/admin.php` utilise `auth:sanctum` SANS `tenant.auth` → faille cross-tenant à corriger d'urgence
- ⚠️ **À ajouter** : `middleware(['tenant', 'tenant.auth', 'role:Professeur|Administrator,tenant'])` + ownership
- ⚠️ **À implémenter** : ownership = `supervisor->teacher_id === auth()->id()`
- ⚠️ **À implémenter** : restriction `replace` / `incidents/{id}/status` aux rôles Admin / Censeur (Censeur non encore créé)

## Definition of Done

- [ ] Les 7 scénarios E2E passent
- [ ] Faille cross-tenant `Exams` corrigée (`auth:sanctum` → `tenant.auth`)
- [ ] Ownership testé sur 3 cas (own / autre prof / admin)
- [ ] Mode offline opérationnel pour pointage candidats

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | Middleware `role:Professeur|Administrator` (teacher.php) + `role:Administrator|Manager|Professeur` (Attendance/Timetable/Exams/Enrollment admin). Tests dans `tests/Feature/RoleCoverage/Professeur/TeacherRoutesProtectionTest.php` (12 tests passants). Ownership controllers déjà en place côté `GradeEntryController` ; à raffiner story par story pour Attendance/Timetable. | Dev Agent (James) |
