# Story: Administrator — Emplois du Temps (CRUD complet) - Coverage

**Module** : Timetable
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/timetable/*`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux configurer et gérer les emplois du temps (salles, créneaux, générateur automatique, exceptions, préférences enseignants), afin d'organiser efficacement la rentrée.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Salles CRUD | ✅ | Bâtiments, disponibilité |
| Créneaux CRUD | ✅ | Planification |
| Génération automatique EDT | ✅ | Algorithme |
| Vues (classe/prof/salle/élève) | ✅ | Consultation |
| Exceptions / Annulations | ✅ | Gestion ponctuelle |
| Préférences enseignant | ✅ | Lecture + override |
| Rapports + notifications | ✅ | KPIs |

## Actions autorisées dans ce menu

L'Administrator a accès à **toutes** les routes de [`Modules/Timetable/Routes/admin.php`](../../../Modules/Timetable/Routes/admin.php) (115 lignes).

Groupes :
- Rooms (CRUD + buildings + available + suggested + stats + occupation + block/unblock)
- Slots (CRUD + check-conflicts)
- Views (class/teacher/room/student)
- Generation auto
- Duplication
- Exceptions (cours annulé/remplacé)
- Teacher preferences (override)
- Reports + notifications

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Cross-tenant | tenancy |
| Modification post-publication EDT en cours sans audit | Audit log + notification automatique aux concernés |

## Cas limites (edge cases)

- **Conflit horaire** : validation backend bloque création.
- **Salle bloquée** : remplaçant suggéré.
- **Génération auto échoue** : rapport conflits.

## Scenarios de test E2E

1. **CRUD salle** : créer/modifier/supprimer → assert.
2. **CRUD créneau** : assert pas de conflit.
3. **Génération auto** : run algorithme → assert EDT créé.
4. **Exception cours annulé** : ajouter exception → assert notification générée.
5. **Action interdite — Conflit** : créer slot conflictuel → **422**.

## Dépendances backend

- ❌ Aucun middleware role
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur POST/PUT/DELETE
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager|Professeur|Étudiant,tenant')` sur GET views (filtre selon rôle)
- ⚠️ **À implémenter** : audit log + notifications automatiques

## Definition of Done

- [ ] Middlewares
- [ ] Les 5 scénarios E2E passent
- [ ] Audit + notifications

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
