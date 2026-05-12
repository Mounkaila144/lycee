# Story: Étudiant — Mes Documents Officiels - Coverage

**Module** : Documents
**Rôle ciblé** : Étudiant
**Menu(s) concerné(s)** : `/admin/student/documents`
**Status** : Ready for Review

## User Story

En tant qu'**Étudiant**, je veux télécharger mes documents officiels (bulletins, attestations de scolarité, certificat de scolarité, relevé annuel) et demander de nouvelles attestations, afin d'avoir mes pièces justificatives sans déranger l'administration.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Mes documents | ✅ | `upload documents`, `request attestations` |
| Demander une attestation | ✅ | Endpoint requests |
| Documents d'un autre élève | ❌ | Ownership |
| Génération bulletin de masse | ❌ | Admin |
| Vérification documents (QR) | ⚠️ Limité | Vérifier les siens uniquement |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister mes documents disponibles | Page principale | `GET /api/frontend/student/documents` (**À CRÉER**) ou `GET /admin/documents/students/{me}/list` | `me` | 200 + liste docs (bulletin, attestation, etc.) | `role:Étudiant,tenant` + ownership |
| Télécharger un document | Bouton "Télécharger" | `GET /api/frontend/student/documents/{document}/download` (**À CRÉER**) | document (path) | 200 + PDF | idem (filter owner) |
| Demander une attestation (scolarité, statut, etc.) | Bouton "Nouvelle demande" → formulaire | `POST /admin/documents/certificates/requests` | `type`, `motif?` | 201 + request `pending` | `role:Étudiant,tenant` |
| Voir mes demandes en cours | Onglet "Mes demandes" | `GET /admin/documents/certificates/requests?student_id={me}` | filtrée owner | 200 + liste | idem |
| Vérifier authenticité d'un document via QR | Scanner QR depuis app | `POST /admin/documents/verification/qr-code` | `qr_code` | 200 + verif result | `role:Étudiant,tenant` (limité à ses propres docs) |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Télécharger document d'un autre élève | `GET .../documents/{document}/download` → check `document->student_id === auth()->user()->student_id` → **403** |
| Générer un bulletin / attestation directement (sans workflow request) | Endpoints `POST /admin/documents/certificates/generate*` → **403** |
| Approuver / refuser une demande | `POST .../requests/{id}/approve` ou `reject` → **403** (Admin) |
| Signer électroniquement / révoquer une signature | **403** |
| Archiver / cold-storage | **403** |
| Lister TOUS les documents du tenant | `GET /admin/documents/verification/register` → **403** ou filter owner |

## Cas limites (edge cases)

- **Aucun document** : "Aucun document disponible pour le moment".
- **Demande en attente** : badge "En attente de validation".
- **Demande refusée** : motif visible.
- **Bulletin pas encore publié** : grisé.
- **Tentative download IDOR** : `GET .../documents/{other_doc}/download` → 403 logué.

## Scenarios de test E2E

1. **Liste mes documents** : login Étudiant → assert liste contient bulletins, attestations etc. filtrés owner.
2. **Télécharger bulletin** : cliquer → assert PDF reçu.
3. **Demander attestation** : "Nouvelle demande" type "Certificat de scolarité" → assert 201 + `pending`.
4. **Action interdite — Doc autre élève** : `GET .../documents/{other_doc}/download` → **403**.
5. **Action interdite — Approuver demande** : `POST .../requests/{id}/approve` → **403**.
6. **Action interdite — Generate direct** : `POST .../certificates/generateEnrollmentCertificate` → **403**.
7. **Edge — Verif QR** : scanner QR d'un bulletin tiers → message "Document valide" + détail (sans révéler les notes).

## Dépendances backend

- ⚠️ **Critique** : `Documents/Routes/admin.php` utilise `auth:sanctum` sans `tenant.auth` — à corriger (R3 README global)
- ⚠️ **À créer** : `GET /api/frontend/student/documents` (lecture filtrée owner) OU adapter `Documents/admin.php` avec filtre
- ⚠️ **À implémenter** : ownership strict `document->student_id === auth()->user()->student_id`
- ⚠️ **À implémenter** : middleware role sur tous les `certificates/generate*` (exclure Étudiant)
- ⚠️ Endpoint `verification/qr-code` peut être ouvert mais résultat masque infos sensibles

## Definition of Done

- [ ] Faille cross-tenant `Documents` corrigée
- [ ] Les 7 scénarios E2E passent
- [ ] IDOR testé et bloqué
- [ ] Workflow requests opérationnel

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
