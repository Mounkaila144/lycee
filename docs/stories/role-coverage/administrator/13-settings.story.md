# Story: Administrator — Réglages Tenant - Coverage

**Module** : (transverse — Settings central)
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/settings/*`
**Status** : Approved

> ⚠️ **À CRÉER** : aucun endpoint Settings centralisé identifié dans `Modules/*/Routes/*.php`. Cette story propose la création d'un module `Modules/Settings/` ou l'extension de `Modules/UsersGuard/` pour centraliser les paramètres tenant.

## User Story

En tant qu'**Administrator**, je veux configurer les paramètres globaux du tenant (informations établissement, branding, intégrations gateways, politiques métier configurables — late_fee_policy, retake_policy, attendance_thresholds, etc.), afin de personnaliser l'application aux spécificités locales.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Réglages généraux (nom, logo, devise, fuseau) | ✅ | Admin only |
| Politiques métier (late fees, retake, absences, blocking) | ✅ | Configuration |
| Intégrations (CinetPay, SMS provider, etc.) | ✅ | Clés API |
| Templates documents | ✅ | Personnalisation PDF |
| Branding (logo, couleurs) | ✅ | UI |
| Audit log tenant | ✅ | Lecture |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir paramètres tenant | Page | `GET /admin/settings/` (**À CRÉER**) | — | 200 | `role:Administrator,tenant` |
| Modifier paramètres | "Sauvegarder" | `PUT /admin/settings/` | payload | 200 | idem |
| Upload logo | Drag-drop | `POST /admin/settings/logo` (**À CRÉER**) | `file` (PNG/JPG max 2 Mo) | 200 | idem |
| Configurer gateway paiement | Page "Intégrations" | `PUT /admin/settings/integrations/cinetpay` (**À CRÉER**) | clés API | 200 | idem |
| Voir audit log tenant | Page | `GET /admin/audit-logs` (**À CRÉER**) | filters | 200 + liste | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Modifier paramètres global SuperAdmin | SuperAdmin only |
| Modifier `tenant_id` ou clé tenant elle-même | Validation backend (immutable) |
| Supprimer audit logs | Conservation légale (lecture seule) |
| Manager / autres rôles accès Settings | Pas dans sidebar + endpoint **403** |

## Cas limites (edge cases)

- **Clé API gateway invalide** : test API automatique avant sauvegarde.
- **Logo trop grand** : redimensionnement auto.
- **Modification politique en cours d'année** : avertissement.

## Scenarios de test E2E

1. **Modifier nom établissement** : `PUT /admin/settings` → assert 200 + reflet UI.
2. **Upload logo** : assert OK + URL retournée.
3. **Configurer CinetPay** : entrer clés → test API → assert OK.
4. **Voir audit log** : assert liste accessible.
5. **Action interdite — Manager** : `GET /admin/settings` login Manager → **403**.
6. **Edge — Modif politique** : changer `late_fee_rate` en cours d'année → avertissement.

## Dépendances backend

- ⚠️ **Critique — À créer** : module `Modules/Settings/` OU intégration dans `UsersGuard`
- ⚠️ **À créer** : table `tenant_settings` (key, value JSON, type)
- ⚠️ **À créer** : table `audit_logs` (user_id, action, model, model_id, changes JSON, ip, ua, created_at)
- ⚠️ **À créer** : tous les endpoints `/admin/settings/*`
- ⚠️ **À implémenter** : middleware `role:Administrator,tenant`
- ⚠️ **À implémenter** : tests connexion gateway (CinetPay sandbox call)

## Definition of Done

- [ ] Module/Settings créé + table + endpoints
- [ ] Audit log opérationnel sur toutes les actions Admin
- [ ] Les 6 scénarios E2E passent
- [ ] Tests gateway connexion opérationnels

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
