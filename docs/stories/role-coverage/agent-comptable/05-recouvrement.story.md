# Story: Agent Comptable — Recouvrement (Relances) - Coverage

**Module** : Finance
**Rôle ciblé** : Agent Comptable
**Menu(s) concerné(s)** : `/admin/finance/collection/reminders`
**Status** : Ready for Review

## User Story

En tant qu'**Agent Comptable**, je veux générer et envoyer des relances aux parents pour les factures impayées, suivre les relances envoyées et leurs réponses, afin de maintenir le taux de recouvrement.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Relances | ✅ | `manage collection` |
| Génération auto relances | ✅ | Endpoint dédié |
| Envoi relances | ✅ | Email/SMS |
| Historique relances | ✅ | Suivi |
| Stats recouvrement | ✅ | KPIs |
| Write-off | ❌ | Comptable |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Générer liste relances (factures en retard) | Bouton "Générer relances" | `POST /admin/finance/collection/reminders/generate` | `from_days_overdue?`, `min_amount?` | 201 + count + draft reminders | `role:Administrator\|Agent Comptable\|Comptable,tenant` |
| Envoyer relances draft (email + SMS) | Bouton "Envoyer" | `POST /admin/finance/collection/reminders/send` | `reminder_ids: array` ou `all=true` | 200 + count envoyés | idem |
| Lister relances envoyées | Page principale | `GET /admin/finance/collection/reminders` | filters | 200 + liste | idem |
| Voir stats recouvrement | Onglet "Stats" | `GET /admin/finance/collection/statistics` | — | 200 + KPIs | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Envoyer plus de 1 relance / 7 jours / élève | Backend throttle → **422** |
| Envoyer relance sans contact (téléphone/email manquant) | **422** + flag élève |
| Write-off (passer en perte) | `POST .../write-off/{id}` → **403** (Comptable) |
| Modifier template relance | **403** (Admin) |

## Cas limites (edge cases)

- **Aucune facture en retard** : "Aucune relance à générer 👍".
- **Parent injoignable** : skip + rapport "Contact manquant pour N élèves".
- **Relance déjà envoyée < 7 jours** : skip + raison "Relance récente".
- **Échec envoi SMS** : retry queue + fallback email.
- **Réponse parent** : tracking via mail-tracker (optionnel V2).

## Scenarios de test E2E

1. **Générer relances** : login Agent Comptable → "Générer relances" filtre 30j+ → assert 201 + liste draft.
2. **Envoyer relances** : sélectionner 10 → "Envoyer" → assert 10 envois (email simulés en test).
3. **Action interdite — Re-envoi <7j** : tenter 2e envoi sur même élève → **422**.
4. **Action interdite — Write-off** : `POST .../write-off/{id}` → **403**.
5. **Edge — Contact manquant** : élève sans email/SMS → relance flag "skip" dans rapport.
6. **Stats** : `GET .../statistics` → assert KPIs (relances envoyées, % retour, etc.).

## Dépendances backend

- ✅ Endpoints `collection/reminders/*` existent ([`Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php))
- ✅ `collection/statistics` existe
- ⚠️ **À ajouter** : `middleware('role:Administrator|Agent Comptable|Comptable,tenant')`
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable,tenant')` sur `write-off`
- ⚠️ **À implémenter** : throttle 1/7j/élève
- ⚠️ **À implémenter** : intégration provider SMS (Twilio / Orange API)
- ⚠️ **À implémenter** : templates mailable + SMS configurables tenant

## Definition of Done

- [ ] Les 6 scénarios E2E passent
- [ ] Throttle 7 jours testé
- [ ] Templates email/SMS opérationnels
- [ ] Stats recouvrement précises

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
