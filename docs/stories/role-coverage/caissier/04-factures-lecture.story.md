# Story: Caissier — Factures (Lecture seule) - Coverage

**Module** : Finance
**Rôle ciblé** : Caissier
**Menu(s) concerné(s)** : `/admin/finance/invoices` (lecture)
**Status** : Ready for Review

## User Story

En tant que **Caissier**, je veux consulter les factures émises par l'établissement (statut, élève, montant, échéancier) afin de vérifier les informations avant un encaissement et répondre aux questions des parents au guichet.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Factures (lecture) | ✅ | `view invoices` |
| Détail facture | ✅ | Lecture |
| Création / modification / suppression facture | ❌ | Agent Comptable |
| Génération automatique | ❌ | Agent Comptable |
| Échéancier (modification) | ❌ | Agent Comptable |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lister factures | DataGrid avec filtres | `GET /admin/finance/invoices/` | `student_id?`, `status?`, `from?`, `to?` | 200 + liste paginée | `role:Administrator\|Comptable\|Agent Comptable\|Caissier,tenant` (à ajouter) |
| Filtrer par élève | Autocomplete | combiné avec recherche élève | — | 200 | idem |
| Voir détail facture | Click | `GET /admin/finance/invoices/{id}` | path | 200 + détail | idem |
| Voir échéancier (lecture) | Onglet | inclus dans détail | — | — | idem |
| Voir pénalités calculées | Onglet | `GET /admin/finance/invoices/{id}/late-fees` | path | 200 + pénalités | idem |
| Exporter liste filtrée | Bouton "Exporter" | (selon endpoint dispo) ou refus pour Caissier | — | — | À décider — recommandation : laisser à Comptable |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Créer / modifier / supprimer facture | `POST/PUT/DELETE /admin/finance/invoices` → **403** |
| Générer automatiquement des factures | `POST /admin/finance/invoices/generate-automated` → **403** |
| Créer / modifier `fee-types` | **403** |
| Créer un échéancier | `POST /admin/finance/invoices/{id}/payment-schedule` → **403** |
| Appliquer pénalité manuelle | Réservé Agent Comptable |
| Voir factures avec données sensibles fiscales | (Si fiscalité incluse) filtrer selon rôle |

## Cas limites (edge cases)

- **Élève sans factures** : liste vide avec message.
- **Très grand volume** (1000+ factures) : pagination + filtres performants.
- **Facture annulée** : badge "Annulée".
- **Facture en retard** : badge rouge + montant majoré.
- **Tenants multiples — visibilité** : `tenant.auth` garantit isolation.

## Scenarios de test E2E

1. **Lister factures** : login Caissier → "Factures" → assert DataGrid avec factures du tenant.
2. **Filtrer par élève** : recherche élève → assert liste filtrée.
3. **Détail facture** : click → assert affichage + échéancier + pénalités.
4. **Action interdite — Créer** : `POST /admin/finance/invoices` → **403**.
5. **Action interdite — Modifier** : `PUT /admin/finance/invoices/{id}` → **403**.
6. **Action interdite — Generate auto** : `POST .../generate-automated` → **403**.

## Dépendances backend

- ✅ Endpoints `invoices` (GET) existent ([`Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable|Agent Comptable|Caissier,tenant')` sur GET routes
- ⚠️ **À ajouter** : `middleware('role:Administrator|Agent Comptable|Comptable,tenant')` sur POST/PUT/DELETE (exclure Caissier)

## Definition of Done

- [ ] Middlewares appliqués
- [ ] Les 6 scénarios E2E passent
- [ ] Caissier reçoit 403 sur écriture facture

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
