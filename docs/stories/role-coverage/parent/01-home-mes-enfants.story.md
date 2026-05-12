# Story: Parent — Home & Mes Enfants - Coverage

**Module** : PortailParent (À CRÉER)
**Rôle ciblé** : Parent
**Menu(s) concerné(s)** : `/admin/parent/home`
**Status** : Ready for Review

> 🆕 **Prérequis bloquant** : Rôle `Parent` à créer (cf. [`parent/README.md`](./README.md) §1-3) avant toute story Parent. Aucun endpoint listé ici n'existe aujourd'hui.

## User Story

En tant que **Parent**, je veux atterrir sur un portail qui me liste **mes enfants** (1 à 5 généralement), me permet de sélectionner un enfant pour voir ses informations, et m'affiche une synthèse rapide (alertes notes, absences, factures impayées), afin de suivre la scolarité de mes enfants sans navigation lourde.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Accueil parent | ✅ | `view dashboard` + `view children` |
| Mes enfants (sélecteur) | ✅ | Critère central de tout le portail |
| Mon profil | ✅ | Compte utilisateur |
| Utilisateurs / Admin | ❌ | Hors rôle |
| Finance globale | ❌ | Hors rôle |
| Saisie notes / Présences (édition) | ❌ | Hors rôle |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mon profil | Header → "Mon profil" | `GET /api/admin/auth/me` | — | 200 + user | `tenant.auth` |
| Voir mes infos parent | Profil étendu | `GET /api/admin/parent/me` (**À CRÉER**) | — | 200 + Parent (firstname, lastname, relationship, phone, etc.) | `role:Parent,tenant` |
| Lister mes enfants | Sélecteur + cards "Mes enfants" | `GET /api/admin/parent/me/children` (**À CRÉER**) | — | 200 + liste enfants (filtrée via pivot `parent_student`) | `role:Parent,tenant` |
| Voir détails d'un enfant | Click card enfant | `GET /api/admin/parent/children/{student}` (**À CRÉER**) | student (path) | 200 + détail (nom, classe, matricule, photo) | `role:Parent,tenant` + `ChildPolicy::view` |
| Voir KPIs synthèse enfant | Widgets sur dashboard | inclus dans `children/{student}` ou endpoint `/dashboard` (**À CRÉER**) | — | 200 + `{moyenne_actuelle, taux_presence, solde_factures, derniers_documents}` | `role:Parent,tenant` + `ChildPolicy::view` |
| Logout | Header → "Déconnexion" | `POST /api/admin/auth/logout` | — | 200 | `tenant.auth` |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir un enfant qui n'est pas le mien | `GET /api/admin/parent/children/{other_student}` → **403** via `ChildPolicy::view` (pivot `parent_student` n'existe pas) |
| Voir liste TOUS élèves du tenant | `GET /admin/students/` → **403** (Parent absent du middleware [`UsersGuard/admin.php:57`](../../../Modules/UsersGuard/Routes/admin.php)) |
| Atterrir sur `/admin/dashboard` après login | Redirect serveur basé sur `config/role-routes.php#home_routes[Parent]` |
| Modifier infos d'un enfant | `PUT /admin/enrollment/students/{id}` → **403** |
| Accéder aux endpoints admin Finance | **403** |

## Cas limites (edge cases)

- **Parent avec 1 seul enfant** : pas de sélecteur, l'enfant est sélectionné par défaut.
- **Parent avec 5+ enfants** : sélecteur avec recherche.
- **Enfant qui quitte l'établissement** (status `Transféré`/`Exclu`) : badge visible, données historiques accessibles uniquement.
- **Aucun enfant lié** (compte créé par erreur sans pivot) : "Aucun enfant n'est associé à votre compte. Contactez l'administration."
- **Token expiré** : redirect login.
- **Parent divorcé sans droit de visite** : flag `parent_student.allow_view_grades` (Phase 2 — pas dans MVP).

## Scenarios de test E2E

1. **Login → Atterrissage** : login Parent ⇒ redirect `/admin/parent/home` (`role-routes.php`).
2. **Sidebar conforme** : assert `[Accueil, Mes enfants, Notes, Présences, EDT, Factures, Messages, Annonces]` ; NE PAS contenir `[Utilisateurs, Saisie notes, Finance admin]`.
3. **Lister mes enfants** : `GET /api/admin/parent/me/children` → assert liste = SES enfants uniquement (via pivot `parent_student`).
4. **Voir enfant** : click card enfant 1 → `GET /api/admin/parent/children/{child1}` → 200.
5. **Action interdite — Autre enfant** : tenter `GET /api/admin/parent/children/{other_kid}` → **403**.
6. **Action interdite — Liste users** : `GET /admin/users/` → **403**.
7. **Action interdite — Liste élèves** : `GET /admin/students/` → **403** (Parent absent du `role:` middleware).
8. **Edge — Aucun enfant** : créer Parent sans pivot → assert message vide.

## Dépendances backend

- ⚠️ **Critique — À créer** : tout le module `Modules/PortailParent/` (cf. [`parent/README.md#3.4`](./README.md))
- ⚠️ **À créer** : Migration + Model `ParentModel` + pivot `parent_student` (cf. [`parent/README.md#3.2-3.3`](./README.md))
- ⚠️ **À créer** : `ChildPolicy::view` + autres méthodes (cf. [`parent/README.md#4`](./README.md))
- ⚠️ **À créer** : Endpoints `/api/admin/parent/me`, `/me/children`, `/children/{student}` (cf. [`parent/README.md#5`](./README.md))
- ⚠️ **À modifier** : `config/role-routes.php` ajouter `Parent` (hierarchy + home_routes)
- ⚠️ **À modifier** : `RolesAndPermissionsSeeder.php` (9 permissions + rôle Parent)
- ⚠️ **À implémenter** : middleware route group `['tenant', 'tenant.auth', 'role:Parent,tenant']`

## Definition of Done

- [ ] Module `PortailParent` créé avec module.json
- [ ] Migrations `parents` + `parent_student` exécutées
- [ ] `ChildPolicy` implémentée avec `view` (3 tests : owner, autre, admin)
- [ ] Endpoint `/api/admin/parent/me/children` retourne uniquement les enfants liés
- [ ] Les 8 scénarios E2E passent
- [ ] `config/role-routes.php` mis à jour
- [ ] Seeder mis à jour
- [ ] Frontend `src/modules/PortailParent/menu.config.ts` créé avec `requiredRoles: ['Parent']`

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
