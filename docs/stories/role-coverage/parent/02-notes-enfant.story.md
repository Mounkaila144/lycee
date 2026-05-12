# Story: Parent — Notes de mon Enfant - Coverage

**Module** : PortailParent (À CRÉER)
**Rôle ciblé** : Parent
**Menu(s) concerné(s)** : `/admin/parent/children/{id}/grades`
**Status** : Ready for Review

## User Story

En tant que **Parent**, je veux consulter les notes de l'enfant sélectionné par matière/semestre et télécharger son bulletin, afin de suivre ses performances scolaires.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Notes (enfant sélectionné) | ✅ | `view children grades` |
| Bulletins | ✅ | Lecture + download |
| Modifier note | ❌ | Lecture seule |
| Notes d'un autre élève | ❌ | Ownership |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir notes enfant — semestre actif | Page principale | `GET /api/admin/parent/children/{student}/grades` (**À CRÉER**) | student (path), `semester_id?`, `subject_id?` | 200 + notes (uniquement `is_published=true`) | `role:Parent,tenant` + `ChildPolicy::viewGrades` |
| Voir notes par semestre passé | Sélecteur | idem avec `semester_id` | semester_id | 200 | idem |
| Voir moyenne par matière | Onglet "Matières" | inclus avec `aggregate=subject` | — | 200 + map | idem |
| Voir moyenne générale + rang anonymisé | Banner haut de page | inclus avec `aggregate=general` | — | 200 + `{moyenne, rang}` | idem |
| Télécharger bulletin semestriel | Bouton "Télécharger bulletin S1" | `GET /api/admin/parent/children/{student}/grades/semester/{semester}` (**À CRÉER**) avec format PDF OU endpoint dédié `bulletin/download` | path | 200 + PDF | idem |
| Voir statistiques anonymisées de la classe | Graphe moyenne/médiane | inclus dans response | — | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir notes d'un enfant qui n'est pas le sien | `ChildPolicy::viewGrades` → **403** |
| Voir notes non publiées (`is_published=false`) | Filter `WHERE is_published = true` |
| Modifier / contester directement une note | Pas d'endpoint Parent — passe par messagerie (story 07) |
| Voir liste nominative des camarades dans stats | Stats anonymisées |
| Accéder aux endpoints admin notes | **403** |

## Cas limites (edge cases)

- **Aucune note encore** : "Pas encore de notes publiées".
- **Bulletin pas encore publié** : grisé.
- **Enfant en redoublement** : voir années précédentes.
- **Politique d'établissement masquant le rang** : flag tenant `show_ranking_to_parents` (lecture conditionnelle).
- **Parent divorcé sans droit (Phase 2)** : `parent_student.allow_view_grades = false` → 403.

## Scenarios de test E2E

1. **Voir notes** : login Parent → sélectionner enfant 1 → "Notes" → assert notes filtrées is_published.
2. **Bulletin PDF** : "Télécharger bulletin S1" → assert PDF avec nom enfant + notes + moyennes.
3. **Action interdite — Autre enfant** : `GET .../children/{other_kid}/grades` → **403**.
4. **Action interdite — Modifier note** : pas d'UI ; tenter `POST /api/admin/grades` → **403**.
5. **Action interdite — Notes non publiées** : insérer note `is_published=false` → assert absente.
6. **Edge — Pas de notes** : enfant nouvellement inscrit → message vide.
7. **Edge — Politique anti-rang** : flag `show_ranking_to_parents=false` → champ rang masqué.

## Dépendances backend

- ⚠️ **À créer** : endpoint `/api/admin/parent/children/{student}/grades`
- ⚠️ **À créer** : endpoint bulletin PDF (peut être agrégateur de `NotesEvaluations` controllers existants)
- ⚠️ **À implémenter** : `ChildPolicy::viewGrades` avec check pivot
- ⚠️ Bloque sur Story Parent 01 (création rôle + module)

## Definition of Done

- [ ] Endpoint créé
- [ ] `ChildPolicy::viewGrades` testée (3 cas)
- [ ] Notes non publiées masquées
- [ ] Bulletin PDF généré
- [ ] Les 7 scénarios E2E passent

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
