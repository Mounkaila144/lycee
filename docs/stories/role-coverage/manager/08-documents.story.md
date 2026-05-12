# Story: Manager — Documents Officiels - Coverage

**Module** : Documents
**Rôle ciblé** : Manager
**Menu(s) concerné(s)** : `/admin/documents`
**Status** : Ready for Review

## User Story

En tant que **Manager**, je veux générer les documents officiels pour les élèves (attestations de scolarité, certificats, cartes étudiantes, bulletins), en lot ou individuellement, afin de répondre aux demandes administratives.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Documents (Génération) | ✅ | Volume rentrée |
| Cartes étudiantes | ✅ | Génération + impression |
| Diplômes | ✅ | Fin d'année |
| Attestations | ✅ | À la demande |
| Vérification / Archive | ✅ (lecture) | Audit |
| Signature électronique | ❌ | Admin |
| Cold storage | ❌ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Générer attestation scolarité | Bouton | `POST /admin/documents/certificates/enrollment` | `student_id` | 201 + PDF | `role:Administrator\|Manager,tenant` (à ajouter) |
| Générer certificat statut | Bouton | `POST /admin/documents/certificates/status` | `student_id` | 201 + PDF | idem |
| Générer certificat scolarité | Bouton | `POST /admin/documents/certificates/schooling` | `student_id` | 201 | idem |
| Générer transcript semestriel/global | Bouton | `POST /admin/documents/transcripts/semester` ou `global` | `student_id`, `semester_id?` | 201 + PDF | idem |
| Batch transcripts | "Génération lot" | `POST /admin/documents/transcripts/batch` | `class_id`, `semester_id` | 202 + job | idem |
| Cartes étudiantes (CRUD lecture + génération individuelle) | Page Cartes | `POST /admin/documents/cards/student-card` ou `batch` | `student_id` ou `class_id` | 201 / 202 | idem |
| Vérifier authenticité doc | Onglet | `POST /admin/documents/verification/qr-code` ou `document-number` | `qr` ou `number` | 200 | idem |
| Voir registre documents | Page | `GET /admin/documents/verification/register` | filters | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Signature électronique | `POST .../signature` → **403** (Admin) |
| Invalider signature | **403** |
| Cold storage / archive long terme | **403** Admin |
| Annuler un document émis | `POST .../cancel` → **403** Admin |
| Suppression document | **403** |

## Cas limites (edge cases)

- **Élève bloqué pour impayés** : génération bloquée si `collection/blocks` actif (vérif cross-module) — selon politique, 403 ou avertissement.
- **Document déjà émis (numéro existant)** : génération dupliquée → flag "Réémission".
- **Volume rentrée** : batch en queue (jusqu'à 1000 élèves).
- **Encre imprimante** : pas de gestion ; fichier PDF dispo + impression côté admin.

## Scenarios de test E2E

1. **Générer attestation** : login Manager → élève → "Attestation" → assert 201 + PDF.
2. **Batch transcripts** : choisir classe + semestre → "Générer lot" → assert 202 + job → polling status → assert tous PDFs disponibles.
3. **Carte étudiante** : générer pour élève → assert 201 + PDF.
4. **Vérifier QR doc** : scanner QR → assert 200 + valide.
5. **Action interdite — Signature** : `POST .../signature` → **403**.
6. **Action interdite — Cold storage** : `POST .../cold-storage` → **403**.
7. **Edge — Élève bloqué impayés** : assert message + refus génération bulletin (selon politique).

## Dépendances backend

- ⚠️ **Critique** : `Documents/Routes/admin.php` `auth:sanctum` SANS `tenant.auth` (R3)
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager,tenant')` sur génération
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur signature/cold-storage/cancel
- ⚠️ **À implémenter** : check `collection/blocks` avant génération (story Agent Comptable 06)
- ⚠️ **À implémenter** : compteur émissions + flag réémission

## Definition of Done

- [ ] Faille `auth:sanctum` corrigée
- [ ] Middlewares appliqués
- [ ] Les 7 scénarios E2E passent
- [ ] Batch fonctionnel

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
