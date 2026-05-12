# Story: Étudiant — Réinscription - Coverage

**Module** : Enrollment (frontend)
**Rôle ciblé** : Étudiant
**Menu(s) concerné(s)** : `/admin/student/reenrollment`
**Status** : Ready for Review

## User Story

En tant qu'**Étudiant**, je veux pouvoir me réinscrire à l'année suivante via une campagne ouverte par l'établissement, vérifier mon éligibilité, soumettre ma demande et télécharger ma confirmation, afin d'éviter les démarches papier en début d'année.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Réinscription | ✅ | Endpoints `/frontend/enrollment/reenrollment/*` |
| Mes demandes | ✅ | Suivi statut |
| Validation des demandes | ❌ | Admin |
| Création campagne | ❌ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir campagnes ouvertes | Page principale | `GET /frontend/enrollment/reenrollment/campaigns` | — | 200 + liste campagnes `active` | `role:Étudiant,tenant` |
| Vérifier mon éligibilité | Bouton "Vérifier éligibilité" | `POST /frontend/enrollment/reenrollment/check-eligibility` | `campaign_id` | 200 + `{eligible: bool, reasons: []}` | idem |
| Créer ma demande | Bouton "Me réinscrire" → formulaire | `POST /frontend/enrollment/reenrollment/` | `campaign_id`, formulaire (modules choisis, options) | 201 + draft demande | idem |
| Modifier ma demande draft | Édition formulaire | `PUT /frontend/enrollment/reenrollment/{reenrollment}` | reenrollment (path) + payload | 200 | idem (filter owner) |
| Soumettre ma demande | Bouton "Soumettre" | `POST /frontend/enrollment/reenrollment/{reenrollment}/submit` | reenrollment (path) | 200 + statut `submitted` | idem |
| Voir mon statut | Page "Ma demande" | `GET /frontend/enrollment/reenrollment/my-status` | — | 200 + statut + détails | idem |
| Voir détail ma demande | Click demande | `GET /frontend/enrollment/reenrollment/{reenrollment}` | path (filtré owner) | 200 + détail | idem |
| Télécharger confirmation | Bouton "Télécharger confirmation" (si approved) | `GET /frontend/enrollment/reenrollment/{reenrollment}/confirmation` | path | 200 + PDF | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir demande d'un autre élève | Endpoint filtre owner sur `reenrollment.student_id = me` |
| Approuver / refuser une demande | `POST /admin/enrollment/reenrollments/{id}/validate` ou `reject` → **403** |
| Valider en lot | `POST /admin/.../batch-validate` → **403** |
| Créer une campagne | `POST /admin/.../reenrollment-campaigns` → **403** |
| Soumettre après deadline campagne | **422** + message |
| Soumettre sans formulaire complet | **422** validation |

## Cas limites (edge cases)

- **Aucune campagne ouverte** : "Aucune campagne de réinscription actuellement active. Revenez plus tard."
- **Élève non éligible** (note < 5, dette financière) : `check-eligibility` retourne `{eligible: false, reasons: [...]}`.
- **Demande déjà soumise** : impossible de la modifier ; CTA "Voir mon statut".
- **Demande refusée** : motif visible + CTA "Contacter l'administration".
- **Deadline dépassée pendant la saisie** : 422 lors du submit + message.

## Scenarios de test E2E

1. **Voir campagnes** : login Étudiant → "Réinscription" → assert campagnes actives listées.
2. **Vérifier éligibilité** : cliquer → assert `{eligible: true}` (ou false avec motifs).
3. **Créer + soumettre demande** : remplir formulaire → "Soumettre" → assert statut `submitted` + DB row.
4. **Télécharger confirmation** (après approval admin) : assert PDF.
5. **Action interdite — Approuver** : `POST .../validate` → **403**.
6. **Action interdite — Demande autre élève** : `GET .../{other_id}` → **403**.
7. **Edge — Deadline dépassée** : modifier date système → submit → **422**.

## Dépendances backend

- ✅ Endpoints existent ([`Enrollment/Routes/frontend.php`](../../../Modules/Enrollment/Routes/frontend.php))
- ⚠️ **À ajouter** : `middleware('role:Étudiant,tenant')` + ownership strict
- ⚠️ **À implémenter** : `ReenrollmentController@create` lie `reenrollment.student_id = auth()->user()->student_id`
- ⚠️ **À implémenter** : check deadline campagne dans `submit`
- ⚠️ **À implémenter** : éligibilité basée sur règles métier (configurables)

## Definition of Done

- [ ] Les 7 scénarios E2E passent
- [ ] Owner check testé
- [ ] Workflow draft → submitted → approved → confirmation downloadable
- [ ] Règles éligibilité testées

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
