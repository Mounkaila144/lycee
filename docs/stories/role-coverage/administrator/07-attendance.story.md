# Story: Administrator — Présences (admin complet) - Coverage

**Module** : Attendance
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/attendance/*`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux superviser tout le système de présences (sessions, records, justificatifs, monitoring, rapports), afin de garantir la conformité de la vie scolaire.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Sessions présence (CRUD admin) | ✅ | Vue transversale |
| Records (modifications) | ✅ | Corrections admin |
| Justificatifs (validation) | ✅ | Approbation |
| Monitoring + seuils alerte | ✅ | Config |
| Rapports + exports | ✅ | KPIs |

## Actions autorisées dans ce menu

L'Administrator a accès à **toutes** les routes de [`Modules/Attendance/Routes/admin.php`](../../../Modules/Attendance/Routes/admin.php). Voir inventaire global README §4.

Groupes principaux :
- Sessions (`GET/POST sessions`, `POST sessions/{id}/complete`, `POST record`, `PUT records/{id}`, `POST record-qr`)
- Justifications (`GET/POST`, `pending`, `students/{id}`, `validate`, `download`)
- Monitoring (thresholds, alerts, history, stats)
- Reports (rates, absentees, statistics, export)

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Cross-tenant | tenancy |

## Cas limites (edge cases)

- **Audit** : toutes les modifications de records loggées.
- **Configuration seuils** : tenant level, par cycle.

## Scenarios de test E2E

1. **Voir toutes sessions du tenant** : assert pas de filtre owner.
2. **Modifier record corrigé prof** : `PUT records/{id}` → assert audit.
3. **Valider justificatif** : `POST justifications/{id}/validate` → assert 200.
4. **Configurer seuils** : `POST monitoring/check-thresholds` → assert.
5. **Exporter rapport** : `GET reports/export` → assert fichier.

## Dépendances backend

- ❌ Aucun middleware role sur `Attendance/Routes/admin.php`
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur monitoring config + write records (sauf record qui peut être prof)
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur justifications/validate
- ⚠️ **À implémenter** : audit log modifications records

## Definition of Done

- [ ] Middlewares appliqués
- [ ] Les 5 scénarios E2E passent
- [ ] Audit log opérationnel

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
