# Story: Parent — Documents de mon Enfant - Coverage

**Module** : PortailParent + Documents
**Rôle ciblé** : Parent
**Menu(s) concerné(s)** : `/admin/parent/children/{id}/documents`
**Status** : Ready for Review

## User Story

En tant que **Parent**, je veux consulter et télécharger les documents officiels de l'enfant sélectionné (bulletins, attestations, certificat de scolarité), et demander de nouvelles attestations, afin d'avoir mes pièces justificatives sans déranger l'administration.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Documents (enfant) | ✅ | `view children documents` |
| Demander attestation | ✅ | Endpoint requests |
| Génération documents masse | ❌ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister documents disponibles enfant | Page principale | `GET /api/admin/parent/children/{student}/documents` (**À CRÉER**) | student (path), `type?` | 200 + liste | `role:Parent,tenant` + `ChildPolicy::view` |
| Télécharger document | Bouton | `GET /api/admin/parent/children/{student}/documents/{document}/download` (**À CRÉER**) | path | 200 + PDF | idem (filter owner) |
| Demander une attestation | Bouton "Nouvelle demande" → formulaire | `POST /api/admin/parent/children/{student}/document-requests` (**À CRÉER** ; ou réutiliser `POST /admin/documents/certificates/requests` avec restriction parent) | `type`, `motif?` | 201 + request `pending` | idem |
| Voir mes demandes | Onglet | `GET /api/admin/parent/children/{student}/document-requests` (**À CRÉER**) | student | 200 + liste | idem |
| Vérifier authenticité (QR) | Scanner QR | `POST /admin/documents/verification/qr-code` | `qr_code` | 200 + verif (limité aux docs de mes enfants) | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir documents d'un enfant non lié | `ChildPolicy::view` → **403** |
| Générer directement un document | Endpoints `POST /admin/documents/certificates/generate*` → **403** |
| Approuver / refuser une demande | **403** (Admin) |
| Signer électroniquement / révoquer signature | **403** |
| Lister TOUS les documents du tenant | **403** |

## Cas limites (edge cases)

- **Aucun document** : "Aucun document disponible pour cet enfant".
- **Demande en attente** : badge "En attente de validation".
- **Bulletin pas encore publié** : grisé.
- **Tentative IDOR** : `GET .../children/{my_kid}/documents/{other_doc}` (doc lié à un autre élève même si dans ma liste) → **403** + log security.

## Scenarios de test E2E

1. **Lister documents enfant** : login Parent → sélectionner enfant → "Documents" → assert liste filtrée.
2. **Télécharger bulletin** : click → assert PDF.
3. **Demander attestation** : "Nouvelle demande" → type "Certificat scolarité" → assert 201.
4. **Action interdite — Enfant non lié** : `GET .../children/{other_kid}/documents` → **403**.
5. **Action interdite — Generate direct** : `POST /admin/documents/certificates/generateEnrollmentCertificate` → **403**.
6. **Edge — IDOR doc** : modifier URL doc → **403**.

## Dépendances backend

- ⚠️ **Critique** : `Documents/Routes/admin.php` utilise `auth:sanctum` sans `tenant.auth` — à corriger (R3 README global) AVANT cette story
- ⚠️ **À créer** : endpoints Parent dédiés
- ⚠️ **À implémenter** : `ChildPolicy::view` (réutilisée)
- ⚠️ **À implémenter** : ownership strict `document->student_id IN $my_children_ids`
- ⚠️ Bloque sur Story Parent 01

## Definition of Done

- [ ] Faille cross-tenant Documents corrigée
- [ ] Endpoints Parent créés
- [ ] Les 6 scénarios E2E passent
- [ ] IDOR testé

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
