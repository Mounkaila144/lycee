# Story: Administrator — Notes & Évaluations (admin complet) - Coverage

**Module** : NotesEvaluations
**Rôle ciblé** : Administrator
**Menu(s) concerné(s)** : `/admin/grades/*`
**Status** : Ready for Review

## User Story

En tant qu'**Administrator**, je veux superviser le cycle des notes (validation, publication, corrections, délibérations, ECTS/équivalences, statistiques), afin de garantir la qualité pédagogique et l'intégrité des bulletins.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Évaluations admin | ✅ | Lecture + correction |
| Validations notes | ✅ | Workflow |
| Corrections demandées par profs | ✅ | Approuver/refuser |
| Absences évaluations | ✅ | Vue admin |
| Résultats modules + semestres | ✅ | Lecture |
| Coefficients (config évals) | ✅ | Modification |
| Délibérations | ✅ | CRUD complet |
| Publications | ✅ | Publier/Dépublier |
| Rattrapages | ✅ | Vue admin |
| Compensation / Élimination | ✅ | Gestion |
| Statistiques + Classement | ✅ | Lecture |
| PVs (Procès-Verbaux) | ✅ | Génération |
| Analytics | ✅ | Lecture |

## Actions autorisées dans ce menu

L'Administrator a accès à **toutes** les routes de [`Modules/NotesEvaluations/Routes/admin.php`](../../../Modules/NotesEvaluations/Routes/admin.php) (336 lignes, 100+ endpoints).

Groupes principaux :
- Évaluations (CRUD + validation publication)
- Corrections (workflow : profs demandent → admin approuve)
- Absences (vue admin transversale)
- Module Results, Semester Results
- Coefficients per évaluation
- Eliminatory modules, ECTS, Compensation
- Délibérations (jury + decisions)
- Publications (date publication officielle)
- Retakes (cycle de rattrapage)
- Final Results (consolidation)
- Statistics, Ranking, Analytics
- PVs (PDF générés)

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Saisir une note brute (étape Prof) | Admin n'est pas censé saisir en tant que prof ; passer par teacher.php ou edit policy |
| Cross-tenant | tenancy |

## Cas limites (edge cases)

- **Délibération en cours** : verrou publications jusqu'à validation finale.
- **Demande correction post-publication** : workflow approbation Admin obligatoire.
- **PV signé** : immutabilité.

## Scenarios de test E2E

1. **Workflow correction** : prof demande → Admin approuve → assert note modifiée + audit.
2. **Publication / dépublication** : Admin publie → élèves voient → dépublie → assert 200 + élèves ne voient plus.
3. **Délibération jury** : créer délibération → décisions → PV → assert PDF généré.
4. **Compensation** : régle de compensation appliquée → assert calcul correct.
5. **Stats classement** : assert KPIs complets.
6. **Cross-tenant** : 404.

## Dépendances backend

- ❌ Aucun middleware role sur `Modules/NotesEvaluations/Routes/admin.php` (336 lignes)
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur write critique (publications, deliberations, corrections, coefficients)
- ⚠️ **À ajouter** : `middleware('role:Administrator|Manager,tenant')` sur lecture
- ⚠️ **À implémenter** : verrou délibération
- ⚠️ **À implémenter** : audit log corrections post-publication

## Definition of Done

- [ ] Middlewares appliqués
- [ ] Les 6 scénarios E2E passent
- [ ] Workflow corrections opérationnel
- [ ] PV signés et immutables

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
