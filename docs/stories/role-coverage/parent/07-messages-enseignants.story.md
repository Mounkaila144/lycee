# Story: Parent — Messagerie avec les Enseignants - Coverage

**Module** : Messaging (À CRÉER) — ou dégradation push unidirectionnelle
**Rôle ciblé** : Parent
**Menu(s) concerné(s)** : `/admin/parent/messages`
**Status** : Approved

> ⚠️ **DÉCISION PRODUIT** : aucun module `Modules/Messaging/` n'existe (vérifié `grep -r "messag" Modules/`). Deux options :
> - **A. Mini-module Messaging** : conversations 1↔1 Parent ↔ Prof, thread persistant, lecture/non-lu, pièces jointes.
> - **B. Notifications unidirectionnelles** : Prof envoie message au parent, le parent répond par email (pas de UI interne).
>
> Story rédigée en supposant **option A**, plus alignée avec l'AC parent §07 « échanger ». À valider avec PO.

## User Story

En tant que **Parent**, je veux échanger des messages avec les enseignants de chacun de mes enfants (1 thread par enseignant×enfant), afin de signaler une situation, demander des explications sur une note ou organiser un rendez-vous.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Messages | ✅ | `message teachers` |
| Nouveau message | ✅ | Bouton CTA |
| Liste tous messages tenant | ❌ | Admin (modération) |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir mes conversations | Page principale (liste threads) | `GET /api/admin/parent/messages` (**À CRÉER**) | `child_id?`, `unread_only?` | 200 + threads | `role:Parent,tenant` |
| Ouvrir un thread | Click | `GET /api/admin/parent/messages/{thread}` (**À CRÉER**) | thread (path) | 200 + messages | idem + filter owner thread |
| Envoyer un message | Composer + bouton "Envoyer" | `POST /api/admin/parent/messages` (**À CRÉER**) | `recipient_user_id` (prof), `child_id`, `body`, `attachments?` | 201 + message créé | `role:Parent,tenant` + `ChildPolicy::view` sur `child_id` |
| Répondre dans un thread existant | Composer en bas du thread | `POST /api/admin/parent/messages/{thread}/reply` (**À CRÉER**) | thread (path), `body`, `attachments?` | 201 | idem |
| Marquer thread comme lu | Auto à l'ouverture | `POST /api/admin/parent/messages/{thread}/read` (**À CRÉER**) | thread | 200 | idem |
| Joindre un fichier | Drag-drop | inclus dans POST | `attachments[]` (PDF/JPG/PNG max 5 Mo chacun, 3 max) | 201 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir un thread qui n'est pas le sien | Filter `thread.parent_user_id = me OR teacher_user_id = me` → **403** |
| Envoyer message à un prof qui n'a pas mon enfant en classe | Validation backend : `prof->isTeachingClass($child->current_class_id)` → **422** |
| Envoyer message sur un enfant non lié | `ChildPolicy::view` → **403** |
| Envoyer messages massifs / spam | Throttle 10 messages/min/parent |
| Modifier / supprimer un message déjà envoyé > 1 minute | **403** ou **422** (soft delete dans la première minute seulement, sinon trace) |
| Voir threads d'autres parents | **403** |

## Cas limites (edge cases)

- **Aucun message** : "Aucune conversation. Démarrez-en une avec un enseignant de votre enfant."
- **Prof a quitté l'établissement** : thread archivé, lecture seule.
- **Enfant transféré** : thread reste accessible en lecture, plus de nouveau message au prof.
- **Fichier joint trop gros** : 422 + message.
- **Modération** : possibilité signaler un message → admin notifié (Phase 2 — non bloquant V1).
- **Notification push/email à la réception** : event-based.

## Scenarios de test E2E

1. **Démarrer thread** : login Parent → "Messages" → "Nouveau message" → choisir enfant + prof (math) → écrire → "Envoyer" → assert 201 + thread créé.
2. **Répondre** : prof envoie réponse (via outil prof) → parent voit notif → ouvre → assert message visible.
3. **Joindre fichier** : envoyer PDF certificat → assert pièce jointe visible.
4. **Action interdite — Thread autre parent** : `GET .../messages/{other_thread}` → **403**.
5. **Action interdite — Prof hors classe** : essayer envoyer à prof qui n'est pas dans la classe de l'enfant → **422**.
6. **Action interdite — Enfant non lié** : `POST messages` avec `child_id` non lié → **403**.
7. **Edge — Throttle** : envoyer 15 messages en 1 minute → 11e renvoie 429.

## Dépendances backend

- ⚠️ **Critique — DÉCISION PRODUIT** : option A (Messaging module) vs B (notifications)
- ⚠️ **À créer** : Module `Modules/Messaging/` avec entités `MessageThread`, `Message`, `MessageAttachment`
- ⚠️ **À créer** : Migrations + Models + ChildPolicy reuse
- ⚠️ **À créer** : Endpoints listés (`/api/admin/parent/messages/*`)
- ⚠️ **À implémenter** : event `MessageReceived` → notif email/push prof + parent
- ⚠️ **À implémenter** : recherche prof = qui enseigne dans la classe de mon enfant
- ⚠️ **À implémenter** : throttle anti-spam
- ⚠️ Bloque sur Story Parent 01 (rôle Parent + `ChildPolicy::view`)

## Definition of Done

- [ ] Décision A/B actée
- [ ] (Si A) Module Messaging créé
- [ ] (Si A) Les 7 scénarios E2E passent
- [ ] (Si B) Story refactorée en "Notifications unidirectionnelles" simplifiée

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
