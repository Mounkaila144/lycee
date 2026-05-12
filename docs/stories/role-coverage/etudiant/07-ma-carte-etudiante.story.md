# Story: Étudiant — Ma Carte Étudiante - Coverage

**Module** : Enrollment (frontend)
**Rôle ciblé** : Étudiant
**Menu(s) concerné(s)** : `/admin/student/card`
**Status** : Approved

## User Story

En tant qu'**Étudiant**, je veux consulter ma carte étudiante, télécharger sa version numérique (PDF/QR), voir son historique et la régénérer si je l'ai perdue, afin d'accéder à l'établissement et bénéficier des avantages associés.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Ma carte | ✅ | Endpoints `/frontend/enrollment/my-card/*` |
| Carte d'un autre élève | ❌ | Ownership |
| Génération en lot | ❌ | Admin |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir ma carte (année active) | Page principale | `GET /frontend/enrollment/my-card` | — | 200 + objet carte | `role:Étudiant,tenant` |
| Voir mes cartes historiques | Onglet "Historique" | `GET /frontend/enrollment/my-card/history` | — | 200 + liste années | idem |
| Télécharger carte PDF | Bouton "Télécharger" | `GET /frontend/enrollment/my-card/download` | — | 200 + PDF | idem |
| Voir carte d'une année passée | Sélecteur année | `GET /frontend/enrollment/my-card/year/{academicYearId}` | academicYearId (path) | 200 + carte | idem + filtre owner |
| Voir QR code | Bouton "QR" | `GET /frontend/enrollment/my-card/qr-code` | — | 200 + QR (image ou data) | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir carte d'un autre élève | Endpoint pas paramétré sur `student_id` — toujours `auth()->user()->student` |
| Générer / dupliquer la carte | `POST /admin/enrollment/student-cards/generate/{studentId}` → **403** (Admin) |
| Modifier statut de la carte | `PATCH .../student-cards/{id}/status` → **403** |
| Imprimer (impression officielle) | `POST .../student-cards/{id}/printCard` → **403** |
| Vérifier carte d'un tiers via API | `POST /admin/enrollment/student-cards/verify` → **403** ou résultat anonymisé |

## Cas limites (edge cases)

- **Carte non encore générée** : "Votre carte sera disponible après validation de votre inscription".
- **Carte suspendue (statut `suspended`)** : badge "Suspendue — contacter l'administration".
- **Carte expirée** : badge "Expirée — réémission requise" + CTA "Demander une nouvelle".
- **Mode hors ligne** : QR mis en cache local pour scan offline (sécurité : QR signé + timestamp).

## Scenarios de test E2E

1. **Voir carte** : login Étudiant → "Ma carte" → assert nom élève + photo + matricule + année active.
2. **Download PDF** : "Télécharger" → assert PDF reçu.
3. **QR code** : afficher QR → scanner → assert validation OK.
4. **Historique** : "Historique" → assert liste années passées avec cartes archivées.
5. **Action interdite — Carte autre élève** : tenter d'accéder par modification URL → assert backend force owner.
6. **Action interdite — Generate** : `POST /admin/enrollment/student-cards/generate/{me}` → **403** (réservé Admin).
7. **Edge — Suspendue** : carte avec status `suspended` → badge visible.

## Dépendances backend

- ✅ Endpoints `/frontend/enrollment/my-card/*` existent ([`Enrollment/Routes/frontend.php`](../../../Modules/Enrollment/Routes/frontend.php))
- ⚠️ **À ajouter** : `middleware('role:Étudiant,tenant')` (currently `tenant.auth` seul)
- ⚠️ **À implémenter** : `MyCardController@show` filtre toujours sur `auth()->user()->student_id`
- ⚠️ **À implémenter** : QR signé HMAC + timestamp pour vérification hors ligne

## Definition of Done

- [ ] Les 7 scénarios E2E passent
- [ ] Owner check testé
- [ ] QR sécurisé (HMAC)
- [ ] PDF correctement généré

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
