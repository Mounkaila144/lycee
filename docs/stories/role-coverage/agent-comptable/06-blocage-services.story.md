# Story: Agent Comptable — Blocage de Services (Impayés) - Coverage

**Module** : Finance
**Rôle ciblé** : Agent Comptable
**Menu(s) concerné(s)** : `/admin/finance/collection/blocks`
**Status** : Ready for Review

## User Story

En tant qu'**Agent Comptable**, je veux bloquer certains services (accès examens, retrait bulletin, accès carte étudiante) pour les élèves en grand retard de paiement, afin d'inciter au règlement, dans le respect des règles éthiques de l'établissement.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Blocages de services | ✅ | `manage collection` |
| Lister blocages | ✅ | Suivi |
| Auto-blocage périodique | ✅ | Process planifié |
| Débloquer après paiement | ✅ | Action principale |
| Configuration politique blocage | ⚠️ Lecture seule | Admin pour modification |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister blocages actifs | Page principale | `GET /admin/finance/collection/blocks` | filters | 200 + liste | `role:Administrator\|Agent Comptable\|Comptable,tenant` |
| Bloquer un service pour un élève | Bouton "Bloquer" | `POST /admin/finance/collection/blocks` | `student_id`, `service: examen\|bulletin\|carte\|...`, `reason`, `until?` | 201 + blocage actif | idem |
| Débloquer après paiement | Bouton "Débloquer" | `POST /admin/finance/collection/blocks/{id}/unblock` | path, `reason` | 200 + blocage `inactive` | idem |
| Lancer process automatique | Bouton "Process auto" | `POST /admin/finance/collection/blocks/auto-process` | filters (seuil retard, montant) | 201 + count blocages créés | idem |
| Vérifier si élève est bloqué (côté autres modules) | API interne | `GET /admin/finance/collection/blocks/check?student_id=X&service=Y` | query | 200 + `{blocked: bool, reason}` | idem ; aussi consommée par autres modules pour bloquer leurs propres actions |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Bloquer un élève en période protégée (ex: pendant examens en cours) | Vérification `protected_period` → **422** |
| Bloquer l'accès "présence en cours" (humain, droit fondamental) | Liste blanche services bloquables — pas d'accès cours |
| Modifier politique tenant blocage | **403** réservé Admin |
| Bloquer manuellement sans motif | **422** validation `reason` obligatoire |

## Cas limites (edge cases)

- **Élève en bourse complète** : exclu des blocages auto.
- **Période protégée** : élections, examens nationaux → flag `protected_period=true` tenant.
- **Élève paie après blocage** : webhook paiement déclenche auto-unblock.
- **Blocage > 30 jours** : escalade Admin obligatoire.
- **Service inexistant** : 422.

## Scenarios de test E2E

1. **Bloquer accès bulletin** : login Agent Comptable → choisir élève impayé → "Bloquer" service=bulletin → assert 201 + élève flag bloqué.
2. **Vérification côté Documents** : essayer `GET /api/admin/parent/.../documents/{bulletin}/download` pour cet élève → assert **403** "Service bloqué — solde à régler".
3. **Auto-process** : "Process auto" critères 60j retard + 50 000 → assert N blocages créés.
4. **Débloquer après paiement** : paiement reçu → webhook OU manuel "Débloquer" → assert blocage `inactive`.
5. **Action interdite — Période protégée** : tenter blocage pendant examens en cours → **422**.
6. **Action interdite — Bloquer présence cours** : `POST blocks service=attendance_cours` → **422** (whitelist).
7. **Edge — Bourse** : élève en bourse complète → auto-process skip cet élève.

## Dépendances backend

- ✅ Endpoints `collection/blocks/*` existent ([`Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Administrator|Agent Comptable|Comptable,tenant')`
- ⚠️ **À implémenter** : whitelist services bloquables (`examen, bulletin, carte, reinscription` — pas `cours, attendance`)
- ⚠️ **À implémenter** : flag tenant `protected_periods` (calendrier)
- ⚠️ **À implémenter** : event `InvoicePaid` → listener `AutoUnblockServicesForStudent`
- ⚠️ **À intégrer côté modules** : `Documents`, `Exams`, `Enrollment` (réinscription) vérifient `collection/blocks/check` avant de servir

## Definition of Done

- [ ] Les 7 scénarios E2E passent
- [ ] Whitelist services testée
- [ ] Période protégée respectée
- [ ] Auto-unblock fonctionnel
- [ ] Intégration cross-modules testée (Documents bloqués vérifient avant download)

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
