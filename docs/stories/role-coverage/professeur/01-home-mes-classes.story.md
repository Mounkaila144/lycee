# Story: Professeur — Home & Mes Classes - Coverage

**Module** : NotesEvaluations + StructureAcademique + UsersGuard
**Rôle ciblé** : Professeur
**Menu(s) concerné(s)** : `/admin/teacher/home` (sidebar id supposé `teacher-home`, à valider FE)
**Status** : Ready for Review

## User Story

En tant que **Professeur**, je veux atterrir sur une page d'accueil qui me montre **mes** modules/matières, **mes** classes, mon prochain cours et mes alertes (notes à publier, examens à surveiller), afin de démarrer ma journée en un coup d'œil sans naviguer dans 5 menus.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Accueil enseignant | ✅ | Le rôle Professeur a `view dashboard` ([`RolesAndPermissionsSeeder.php:114`](../../../Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php)) |
| Mes classes / Mes modules | ✅ | Routes `/api/frontend/teacher/my-modules` ([`teacher.php:7`](../../../Modules/NotesEvaluations/Routes/teacher.php)) |
| Élèves (liste complète) | ❌ | Le Professeur passe par "Élèves de mes classes" (story 09), pas la liste globale |
| Utilisateurs (Users) | ❌ | `role:Administrator\|Manager` seulement ([`UsersGuard/admin.php:26`](../../../Modules/UsersGuard/Routes/admin.php)) |
| Finance / Paie | ❌ | Hors rôle |
| Structure académique (CRUD) | ❌ | Lecture seule sur ses propres modules uniquement |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mon profil | Avatar header → menu déroulant "Mon profil" | `GET /api/admin/auth/me` | — | 200 + objet utilisateur | `tenant.auth` |
| Lister mes modules de l'année active | Cards "Mes modules" sur le dashboard | `GET /api/frontend/teacher/my-modules` | `academic_year_id` optionnel | 200 + liste modules avec count élèves | `role:Professeur,tenant` (À AJOUTER) |
| Voir le détail d'un module | Click sur card module → page module | `GET /api/frontend/teacher/modules/{module}/evaluations` | `module` (path) | 200 + liste évaluations du module | `role:Professeur,tenant` + filtre `teacher_id = auth()->id()` |
| Voir mes alertes (à publier, à surveiller) | Bandeau alertes sur le dashboard | `GET /api/frontend/teacher/grades/submission-status` | — | 200 + tableau de bord soumissions | `role:Professeur,tenant` |
| Logout | Bouton "Déconnexion" du header | `POST /api/admin/auth/logout` | — | 200 + token invalidé | `tenant.auth` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir les modules d'un autre enseignant | `GET /api/frontend/teacher/my-modules` doit toujours filtrer `WHERE teacher_id = auth()->id()` — pas de query param `teacher_id=X` accepté → 403 ou ignoré silencieusement |
| Voir la liste globale des utilisateurs | Pas d'item "Utilisateurs" dans la sidebar + `GET /admin/users/` → 403 (middleware `role:Administrator\|Manager` actif sur cette route, donc 403 garanti) |
| Voir la trésorerie de l'établissement | Pas d'item "Finance" dans la sidebar |
| Atterrir sur `/admin/dashboard` après login | Redirect serveur basé sur `config/role-routes.php#home_routes` → `/admin/teacher/home` |

## Cas limites (edge cases)

- **Enseignant sans aucun module assigné** (nouveau prof, début d'année) : page affiche état vide "Aucun module ne vous est encore attribué. Contactez l'administration." + CTA "Voir mon emploi du temps" + email lien admin.
- **Enseignant avec module assigné mais aucune évaluation créée** : card module visible, badge "0 évaluations", CTA "Créer une évaluation" (lien story 02).
- **Année académique non active** : si aucune année avec `is_active = true`, dashboard affiche bandeau "Aucune année scolaire active. Contactez l'administration."
- **Token expiré pendant que le dashboard charge** : intercepteur Axios renvoie sur `/admin/login` ; toast "Session expirée".
- **N+1 lors du chargement des modules** : eager-load `with('classes', 'subject', 'students_count')` requis côté backend (cf. `coding-standards.md#eager-loading`).
- **Erreur 500 sur `my-modules`** : afficher fallback "Impossible de charger vos modules pour le moment" + bouton "Réessayer".

## Scenarios de test E2E

1. **Login → Atterrissage Professeur** : POST `/api/admin/auth/login` avec compte `prof1` ⇒ token retourné ⇒ redirect vers `/admin/teacher/home` ⇒ page contient "Bonjour [Prénom]".
2. **Sidebar conforme au rôle** : assert sidebar contient `[Accueil enseignant, Mes classes, Saisie notes, Présences, Mon emploi du temps]` ; assert sidebar NE contient PAS `[Utilisateurs, Rôles, Finance, Paie, Réglages]`.
3. **Mes modules visibles** : `GET /api/frontend/teacher/my-modules` ⇒ 200 ⇒ tableau filtré sur l'enseignant connecté (vérifier que tous les modules retournés ont `teacher_id == $loggedUser->id`).
4. **Action interdite — Liste users** : `GET /api/admin/users/` avec token Professeur ⇒ **403** (déjà bloqué par `role:Administrator|Manager`).
5. **Action interdite — Modules d'un autre prof** : `GET /api/frontend/teacher/modules/{module_of_other_teacher}/evaluations` ⇒ **403** (filter controller) **OU 404** (resource not found pour ce prof).
6. **Edge case — Aucun module** : créer un prof sans assignment, login ⇒ dashboard affiche l'état vide attendu.
7. **Logout invalide le token** : `POST /api/admin/auth/logout` ⇒ 200 ⇒ refaire un `GET /api/admin/auth/me` avec le même token ⇒ 401.

## Dépendances backend

- ✅ `GET /api/frontend/teacher/my-modules` — existe ([`teacher.php:7`](../../../Modules/NotesEvaluations/Routes/teacher.php))
- ✅ `GET /api/admin/auth/me` — existe ([`UsersGuard/admin.php:17`](../../../Modules/UsersGuard/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Professeur,tenant')` sur le groupe `Modules/NotesEvaluations/Routes/teacher.php` (ligne ~5)
- ⚠️ **À vérifier en controller** : `GradeEntryController@myModules` filtre bien sur `auth()->id()` (sinon 100 lignes d'IDOR potentiel)
- ⚠️ **À créer** côté frontend : `src/modules/Teacher/menu.config.ts` avec items + `requiredRoles: ['Professeur']`

## Definition of Done

- [ ] Les 7 scénarios E2E passent (Playwright + auth Bearer)
- [ ] `middleware('role:Professeur,tenant')` ajouté sur `teacher.php`
- [ ] Test unitaire `GradeEntryController@myModules` : un prof ne voit que ses modules (3 cas : owner, autre prof, admin)
- [ ] Items de menu interdits absents du DOM côté Next.js
- [ ] Couverture documentée dans `docs/stories/role-coverage/professeur/README.md` (cette story cochée)

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | Middleware `role:Professeur\|Administrator` appliqué sur `teacher.php`. Ownership `auth()->id()` validé dans GradeEntryController@myModules et @moduleEvaluations (déjà présent). 7 tests Feature ajoutés (`tests/Feature/RoleCoverage/Professeur/HomeMesClassesTest.php`) tous passants. | Dev Agent (James) |
