# Story: Étudiant — Portail / Accueil - Coverage

**Module** : UsersGuard + agrégation
**Rôle ciblé** : Étudiant
**Menu(s) concerné(s)** : `/admin/student/home`
**Status** : Ready for Review

## User Story

En tant qu'**Étudiant**, je veux atterrir sur un portail qui me montre ma moyenne, mon prochain cours, mes dernières absences et mes alertes (factures impayées, documents disponibles), afin de suivre ma scolarité d'un coup d'œil.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Accueil | ✅ | `view dashboard` |
| Mes notes / bulletins | ✅ | `view own grades` |
| Mon emploi du temps | ✅ | `view own timetable` |
| Mes présences | ✅ | `view own attendance` |
| Mes factures | ✅ | Lecture seule autorisée |
| Mes documents | ✅ | `upload documents`, `request attestations` |
| Ma carte étudiante | ✅ | Frontend enrollment |
| Réinscription | ✅ | Campagnes accessibles |
| Utilisateurs / Admin | ❌ | Hors rôle |
| Finance admin / Paie | ❌ | Hors rôle |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mon profil | Header → "Mon profil" | `GET /api/admin/auth/me` | — | 200 + user | `tenant.auth` |
| Voir mon dashboard étudiant | Page principale | `GET /api/frontend/student/dashboard` (**À CRÉER**) | — | 200 + `{moyenne_actuelle, next_class, recent_absences, pending_invoices, available_documents}` | `role:Étudiant,tenant` + filtre `auth()->user()->student` |
| Voir prochain cours | Widget "Prochain cours" | inclus dans `/dashboard` | — | — | idem |
| Voir solde factures | Widget "Mes factures" | inclus dans `/dashboard` (lit `Modules/Finance` filtré owner) | — | — | idem |
| Logout | Header → "Déconnexion" | `POST /api/admin/auth/logout` | — | 200 | `tenant.auth` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir dashboard d'un autre étudiant | `GET /api/frontend/student/dashboard` ne prend PAS de `student_id` — toujours `auth()->user()->student_id` |
| Accéder aux endpoints admin | Sidebar masque ces items + endpoints `/admin/users`, `/admin/enrollment/students` (en mode CRUD) → **403** (à enforcer) |
| Atterrir sur `/admin/dashboard` | Redirect post-login vers `/admin/student/home` (config) |
| Voir la liste des élèves | `GET /admin/students/` → **403** (middleware actuel n'inclut pas Étudiant — vérifié [`UsersGuard/admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php)) |

## Cas limites (edge cases)

- **Élève fraîchement inscrit, pas encore de notes** : widget "Ma moyenne" affiche "Pas encore de notes saisies".
- **Toutes les factures payées** : widget "Mes factures" affiche "À jour ✅".
- **Pas de prochain cours (vacances)** : widget "Prochain cours" affiche "Période de vacances".
- **Token expiré pendant utilisation** : redirect login + toast "Session expirée".
- **Profil incomplet (photo manquante)** : avatar placeholder + CTA optionnel "Ajouter une photo".

## Scenarios de test E2E

1. **Login → Home** : login Étudiant `eleve1` ⇒ redirect `/admin/student/home` ⇒ dashboard chargé.
2. **Sidebar conforme** : assert sidebar contient `[Accueil, Mes notes, Mon emploi du temps, Mes présences, Mes factures, Mes documents, Ma carte]` ; NE contient PAS `[Utilisateurs, Finance admin, Saisie notes]`.
3. **Dashboard renvoie mes données** : assert `student_id == auth()->user()->student_id`.
4. **Action interdite — Liste élèves** : `GET /admin/students/` → **403**.
5. **Action interdite — Liste utilisateurs** : `GET /admin/users/` → **403**.
6. **Logout** : `POST logout` → reuse token → **401**.

## Dépendances backend

- ⚠️ **À créer** : `GET /api/frontend/student/dashboard` (n'existe pas — agrège grades + attendance + finance + timetable)
- ⚠️ **À ajouter** : `middleware('role:Étudiant,tenant')` sur le futur groupe `/api/frontend/student/*`
- ⚠️ **À implémenter** : aucun query param `student_id` accepté — toujours dérivé de `auth()->user()->student`

## Definition of Done

- [ ] Endpoint `/api/frontend/student/dashboard` créé
- [ ] Les 6 scénarios E2E passent
- [ ] Sidebar Next.js filtre par `role:Étudiant`
- [ ] Aucun query param student_id accepté côté backend

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
