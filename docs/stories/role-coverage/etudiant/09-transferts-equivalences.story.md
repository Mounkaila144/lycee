# Story: Étudiant — Transferts & Dispenses (Exemptions) - Coverage

**Module** : Enrollment (frontend)
**Rôle ciblé** : Étudiant
**Menu(s) concerné(s)** : `/admin/student/transfers`, `/admin/student/exemptions`
**Status** : Approved

> ⚠️ **Contexte** : ces fonctionnalités (transferts inter-établissements, équivalences inter-modules, dispenses) viennent de l'héritage LMD. Pour le secondaire, elles ont un sens limité (transferts entre établissements, dispense d'EPS). Confirmer le scope avec le PO avant impl.

## User Story

En tant qu'**Étudiant**, je veux pouvoir demander un transfert vers un autre programme/établissement OU une dispense de module (ex : EPS pour raison médicale), afin de formaliser administrativement ma situation.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Mes transferts | ✅ | Endpoints transfer |
| Mes dispenses | ✅ | Endpoints exemption |
| Décider d'un transfert | ❌ | Admin |
| Toutes les demandes du tenant | ❌ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir programmes éligibles transfert | Sélecteur | `GET /frontend/enrollment/transfer/programs` | — | 200 + liste | `role:Étudiant,tenant` |
| Voir année active | Info | `GET /frontend/enrollment/transfer/academic-year` | — | 200 + année | idem |
| Soumettre demande transfert | Bouton "Nouvelle demande" | `POST /frontend/enrollment/transfer/` | `target_program`, `motif`, justificatifs | 201 + status `pending` | idem |
| Voir mes demandes transfert | Onglet "Mes demandes" | `GET /frontend/enrollment/transfer/my-requests` | — | 200 + liste filtrée owner | idem |
| Vérifier statut | Bouton "Statut" | `POST /frontend/enrollment/transfer/check-status` | `transfer_id` | 200 + statut + commentaires admin | idem (filter owner) |
| Voir modules dispensables | Onglet dispenses | `GET /frontend/enrollment/exemption/available-modules` | — | 200 + liste modules | idem |
| Demander une dispense | Bouton "Demander dispense" | `POST /frontend/enrollment/exemption/` | `module_id`, `motif`, `file_justificatif?` | 201 + status `pending` | idem |
| Voir mes dispenses | Onglet | `GET /frontend/enrollment/exemption/my-requests` | — | 200 + liste filtrée owner | idem |
| Détail dispense | Click | `GET /frontend/enrollment/exemption/{exemption}` | exemption (path) | 200 | idem (filter owner) |
| Télécharger certificat dispense (après approval) | Bouton "Télécharger" | `GET /frontend/enrollment/exemption/{exemption}/certificate` | exemption (path) | 200 + PDF | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Approuver / refuser transfert | `POST /admin/enrollment/transfers/{id}/validate` ou `reject` → **403** |
| Analyser équivalences | `POST .../analyze` → **403** |
| Intégrer / certifier transfert | `POST .../integrate` ou `/certificate` (admin) → **403** |
| Approuver / révoquer dispense | `POST /admin/enrollment/exemptions/{id}/validate` ou `revoke` → **403** |
| Voir transferts/dispenses d'un autre élève | Owner filter → **403** |

## Cas limites (edge cases)

- **Aucun programme éligible** : "Aucun programme disponible pour transfert actuellement".
- **Demande en doublon** (déjà 1 demande pending) : 422 + "Vous avez déjà une demande en cours".
- **Pièce justificative médicale** : upload PDF/JPG/PNG max 5 Mo.
- **Refus** : motif visible + possibilité de soumettre nouvelle demande.
- **Délai d'examen 7 jours dépassé** : escalade automatique à un autre validateur (phase 2).

## Scenarios de test E2E

1. **Demander transfert** : login Étudiant → "Mes transferts" → "Nouvelle demande" → assert 201.
2. **Demander dispense EPS** : "Mes dispenses" → choisir module EPS → motif médical + certif → assert 201.
3. **Voir statuts** : assert statut visible + commentaires admin.
4. **Télécharger certificat dispense** (post-approval) : assert PDF.
5. **Action interdite — Approuver transfert** : `POST /admin/.../transfers/{id}/validate` → **403**.
6. **Action interdite — Demande autre élève** : `GET .../exemption/{other_id}` → **403**.
7. **Edge — Doublon** : 2e demande pending → **422**.

## Dépendances backend

- ✅ Endpoints `transfer/*` et `exemption/*` existent ([`Enrollment/Routes/frontend.php`](../../../Modules/Enrollment/Routes/frontend.php))
- ⚠️ **À ajouter** : `middleware('role:Étudiant,tenant')` + ownership
- ⚠️ **À implémenter** : `TransferRequestController` et `ExemptionRequestController` filtrent `student_id = me`
- ⚠️ **À confirmer scope** : ces endpoints LMD pertinents en secondaire ? Sinon désactiver la story et masquer le menu

## Definition of Done

- [ ] Scope confirmé avec PO (LMD-héritage vs secondaire)
- [ ] Si maintenu : 7 scénarios E2E passent
- [ ] Owner check testé
- [ ] Workflow draft → pending → approved/rejected fonctionnel

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
| 2026-05-12 | 1.1 | **Story hors scope V1** — décision PO §A.4 : concepts LMD (transferts, équivalences, options) exclus du secondaire. Reportée V2 si université secondaire ajoutée. | Dev Agent (James) |
