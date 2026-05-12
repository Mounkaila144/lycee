# Story: Parent — Annonces de l'École - Coverage

**Module** : PortailParent + Announcements (À CRÉER ou réutiliser)
**Rôle ciblé** : Parent
**Menu(s) concerné(s)** : `/admin/parent/announcements`
**Status** : Approved

## User Story

En tant que **Parent**, je veux consulter les annonces publiées par l'établissement (réunions parents-profs, fermetures exceptionnelles, événements), afin de rester informé.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Annonces | ✅ | `view announcements` |
| Créer annonce | ❌ | Admin |
| Marquer comme lu | ✅ | Suivi lecture |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister annonces actives | Page principale | `GET /api/admin/parent/announcements` (**À CRÉER**) | `unread_only?`, `category?` | 200 + liste annonces (audience: parent OR all) | `role:Parent,tenant` |
| Voir détail annonce | Click | `GET /api/admin/parent/announcements/{announcement}` (**À CRÉER**) | path | 200 + détail (avec pièces jointes éventuelles) | `role:Parent,tenant` |
| Télécharger pièce jointe | Bouton | `GET /api/admin/parent/announcements/{announcement}/attachments/{attachment}/download` (**À CRÉER**) | path | 200 + file | idem |
| Marquer comme lu | Auto à l'ouverture détail | `POST /api/admin/parent/announcements/{announcement}/read` (**À CRÉER**) | path | 200 + flag user_announcement_reads | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Créer / modifier / supprimer une annonce | Endpoints admin → **403** |
| Voir annonces ciblées à un autre rôle (ex: profs uniquement) | Filter `audience IN ('parent', 'all')` |
| Voir annonces expirées (`end_date < now`) | Filter date côté query |
| Marquer comme lu pour un autre utilisateur | endpoint déduit `user_id = me` |

## Cas limites (edge cases)

- **Aucune annonce** : "Aucune annonce pour le moment".
- **Annonce avec attachement volumineux** : streaming download.
- **Annonce épinglée** : tri pinned en premier.
- **Notification push à la publication** : event-based (Phase 2 — V1 = consultation seulement).
- **Annonce ciblée à une classe spécifique** : filtre `target_class_id IN ($mes_enfants_classes)`.

## Scenarios de test E2E

1. **Lister annonces** : login Parent → "Annonces" → assert annonces visibles (audience parent + all).
2. **Voir détail + pièce jointe** : click → assert détail + bouton "Télécharger".
3. **Marquer lu** : ouvrir → assert flag persisté en DB.
4. **Action interdite — Annonces internes profs** : assert annonces `audience='teacher'` absentes de la liste.
5. **Action interdite — Créer annonce** : `POST /admin/announcements` → **403**.
6. **Edge — Aucune annonce** : tenant sans annonce → message attendu.

## Dépendances backend

- ⚠️ **À créer** : Module ou intégration `Announcements`. Aucun module dédié trouvé via `grep`. Recommandation : créer `Modules/Announcements/` avec :
  - Entité `Announcement` (title, body, audience: enum['admin','teacher','parent','student','all'], target_class_id?, start_date, end_date, is_pinned, attachments)
  - Endpoints admin `POST/PUT/DELETE /admin/announcements`
  - Endpoints Parent `/api/admin/parent/announcements/*`
- ⚠️ **À créer** : Pivot `user_announcement_reads (user_id, announcement_id, read_at)`
- ⚠️ **À implémenter** : filtre audience + dates dans endpoint Parent
- ⚠️ Bloque sur Story Parent 01

## Definition of Done

- [ ] Module Announcements créé (entité, migration, controllers)
- [ ] Endpoints Parent fonctionnels
- [ ] Les 6 scénarios E2E passent
- [ ] Audience filter testé (parent ne voit pas annonces teachers)

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
