# Story: Comptable — Exports Comptables - Coverage

**Module** : Finance
**Rôle ciblé** : Comptable
**Menu(s) concerné(s)** : `/admin/finance/reports/export`
**Status** : Ready for Review

## User Story

En tant que **Comptable**, je veux exporter les écritures financières vers le logiciel comptable externe (format Sage/Excel/SYSCOHADA) sur une période donnée, afin d'intégrer les flux dans la comptabilité officielle.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Exports comptables | ✅ | `export financial data` |
| Export Excel | ✅ | Format générique |
| Export PDF | ✅ | Pour archive |
| Export SYSCOHADA | ✅ | Format local Niger/UEMOA |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Lancer export accounting (SYSCOHADA) | Bouton "Export comptable" | `GET /admin/finance/reports/accounting-export` | `from`, `to`, `format: syscohada\|sage` | 200 + fichier OU 202 + job_id (si lourd) | `role:Administrator\|Comptable,tenant` |
| Export Excel rapport | Bouton "Excel" sur rapport | `GET /admin/finance/reports/export/excel?report_type=X` | filters | 200 + xlsx | idem |
| Export PDF rapport | Bouton "PDF" sur rapport | `GET /admin/finance/reports/export/pdf?report_type=X` | filters | 200 + pdf | idem |
| Téléchargement post-job | Notification + lien | `GET /admin/finance/reports/download?job_id={id}` (à implémenter) | path | 200 + fichier | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Export par Agent Comptable / Caissier | **403** |
| Export contenant données identifiantes mineurs (noms complets) | RGPD : filtrer noms vers matricule + classe uniquement |
| Export sur période avec rapprochement non clôturé | Avertissement + override Admin requis |
| Suppression du fichier après téléchargement | Conserver dans `storage` 30 jours (audit) |

## Cas limites (edge cases)

- **Export gros volume (1+ million lignes)** : queue + email "Export prêt".
- **Format inconnu** : 422.
- **Période sans données** : fichier vide avec entête.
- **Export quotidien automatique** : job programmé optionnel (Phase 2).

## Scenarios de test E2E

1. **Export SYSCOHADA** : login Comptable → "Export comptable" mois précédent → assert 200 ou 202 → assert fichier téléchargeable.
2. **Export Excel** : "Excel" sur aging-balance → assert fichier reçu avec données filtrées.
3. **Action interdite — Caissier** : login Caissier `GET .../accounting-export` → **403**.
4. **Action interdite — Données mineurs sans anonymisation** : assert fichier exporté contient `matricule + classe`, pas `prénom + nom` selon politique RGPD.
5. **Edge — Période sans données** : assert fichier vide avec entête.

## Dépendances backend

- ✅ Endpoints `reports/accounting-export`, `export/excel`, `export/pdf` existent ([`Finance/Routes/admin.php`](../../../Modules/Finance/Routes/admin.php))
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable,tenant')`
- ⚠️ **À implémenter** : queue pour exports volumineux + endpoint download
- ⚠️ **À implémenter** : format SYSCOHADA + Sage (templates)
- ⚠️ **À implémenter** : anonymisation RGPD configurable

## Definition of Done

- [ ] Les 5 scénarios E2E passent
- [ ] Format SYSCOHADA validé par un expert-comptable
- [ ] Queue export testée sur 1M lignes
- [ ] Anonymisation RGPD opérationnelle

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
