# Story: Manager — Structure Académique (Lecture seule) - Coverage

**Module** : StructureAcademique
**Rôle ciblé** : Manager
**Menu(s) concerné(s)** : `/admin/structure`
**Status** : Ready for Review

## User Story

En tant que **Manager**, je veux consulter la structure académique du tenant (années scolaires, classes, matières, coefficients), sans pouvoir la modifier, afin de comprendre le contexte pédagogique et opérer mes actions de gestion utilisateurs en cohérence.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Structure (lecture) | ✅ | Lecture transverse |
| Détails années/classes/matières | ✅ | Lecture |
| Modification / CRUD | ❌ | Admin |
| Activation année | ❌ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister années scolaires | Page | `GET /admin/academic-years` | — | 200 | `role:Administrator\|Manager,tenant` (à ajouter) |
| Voir année active | Widget | `GET /admin/academic-years/{id}` (avec is_active) | path | 200 | idem |
| Lister classes | Page | `GET /admin/classes` | filters | 200 | idem |
| Voir détail classe | Click | `GET /admin/classes/{id}` | path | 200 | idem |
| Lister matières + coefficients | Page | `GET /admin/subjects`, `GET /admin/subject-class-coefficients` | filters | 200 | idem |
| Voir stats classe (effectif, taux remplissage) | Onglet | `GET /admin/classes/{id}/stats` (à vérifier) | path | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Créer / modifier / supprimer une année | `POST/PUT/DELETE /admin/academic-years` → **403** (à ajouter middleware) |
| Activer une année | `POST .../activate` → **403** |
| Créer / modifier / supprimer une classe | **403** |
| Affecter / changer PP (professeur principal) | **403** |
| Créer matière | **403** |
| Modifier coefficient | **403** |

## Cas limites (edge cases)

- **Aucune année active** : badge "Année non active" + lien CTA "Admin > Activer une année".
- **Classes sans élèves** : affichées mais grisées.
- **Coefficient non défini** : alerte visuelle pour info Admin.

## Scenarios de test E2E

1. **Lister structure** : login Manager → "Structure" → assert pages années/classes/matières/coefficients en lecture.
2. **Détail classe** : assert nom, effectif, PP affiché.
3. **Action interdite — Créer année** : `POST /admin/academic-years` → **403**.
4. **Action interdite — Modifier coef** : `PUT /admin/subject-class-coefficients/{id}` → **403**.
5. **Action interdite — Activer année** : `POST /admin/academic-years/{id}/activate` → **403**.

## Dépendances backend

- ❌ Aucun middleware role sur `Modules/StructureAcademique/Routes/admin.php` (currently tout authentifié peut tout faire) ([`StructureAcademique/Routes/admin.php`](../../../Modules/StructureAcademique/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager,tenant')` sur GET routes
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur POST/PUT/DELETE routes (write réservé Admin)

## Definition of Done

- [ ] Middlewares appliqués
- [ ] Les 5 scénarios E2E passent
- [ ] Manager bloqué sur toute écriture structure

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
