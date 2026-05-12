# Story: Comptable — Paie Personnel (Lecture seule) - Coverage

**Module** : Payroll
**Rôle ciblé** : Comptable
**Menu(s) concerné(s)** : `/admin/payroll/reports`
**Status** : Ready for Review

## User Story

En tant que **Comptable**, je veux consulter les rapports de paie agrégés (masse salariale, charges sociales, journal de paie) sans accéder aux fiches individuelles des employés, afin d'avoir la vision financière de la paie sans intrusion RH.

## Couverture UI (sidebar)

| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |
| Paie - Rapports | ✅ | `manage refunds`/`view financial reports` étendu paie |
| Détail fiche employé | ❌ | RH/Admin |
| Saisie composants paie | ❌ | RH/Admin |
| Validation période paie | ❌ | RH/Admin |
| Déclarations sociales (lecture) | ✅ | Lecture |

## Actions autorisées dans ce menu

| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |
| Voir dashboard paie | Page principale | `GET /admin/payroll/reports/dashboard` | — | 200 + KPIs agrégés | `role:Administrator\|Comptable,tenant` |
| Voir journal de paie d'une période | Page | `GET /admin/payroll/reports/payroll-journal/{periodId}` | path | 200 + lignes | idem |
| Voir charges sociales d'une période | Page | `GET /admin/payroll/reports/social-charges/{periodId}` | path | 200 + détail | idem |
| Voir stats salaires (agrégées) | Page | `GET /admin/payroll/reports/salary-statistics` | filters | 200 + KPIs anonymisés | idem |
| Voir déclarations CNSS/AMO/IGR | Onglet "Déclarations" | `GET /admin/payroll/declarations` | filters | 200 + liste | idem (lecture seule) |
| Voir détail déclaration | Click | `GET /admin/payroll/declarations/{id}` | path | 200 | idem |

## Actions INTERDITES dans ce menu

| Action interdite | Blocage attendu |
| --- | --- |
| Voir fiche individuelle employé | `GET /admin/payroll/employees/{id}` → **403** |
| Saisir / modifier composants paie | `POST/PUT /admin/payroll/components` → **403** |
| Calculer/valider une période | `POST /admin/payroll/payroll-periods/{id}/calculate` ou `validate` → **403** |
| Générer fiches de paie | `POST .../generate-payslips` → **403** |
| Soumettre déclaration | `POST .../declarations/{id}/submit` → **403** |
| Effectuer paiement employé | `POST .../mark-as-paid` → **403** |

## Cas limites (edge cases)

- **Période non clôturée** : rapports en mode "préliminaire" + bandeau "Données provisoires".
- **Aucune fiche calculée** : "Aucune fiche de paie pour cette période".
- **Anonymisation** : stats salariales sans nominatif (matricule employé OK, pas nom).
- **Cross-tenant** : actuellement Payroll utilise `auth:sanctum` SANS `tenant.auth` → bug critique R3.

## Scenarios de test E2E

1. **Voir dashboard paie** : login Comptable → "Paie - Rapports" → assert KPIs masse salariale + charges.
2. **Voir journal période** : sélectionner mois → assert lignes anonymisées (matricule + montant).
3. **Voir charges sociales** : assert CNSS, IGR, AMO calculés.
4. **Action interdite — Fiche employé** : `GET /admin/payroll/employees/{id}` → **403**.
5. **Action interdite — Calculer période** : `POST .../calculate` → **403**.
6. **Action interdite — Validate période** : `POST .../validate` → **403**.

## Dépendances backend

- ⚠️ **Critique** : corriger `Payroll/Routes/admin.php` `auth:sanctum` → `['tenant', 'tenant.auth']`
- ⚠️ **À ajouter** : `middleware('role:Administrator|Comptable,tenant')` sur `reports/*` + `declarations` (lecture)
- ⚠️ **À ajouter** : `middleware('role:Administrator,tenant')` sur calculate, validate, generate-payslips, mark-as-paid, components CRUD, employee CRUD (RH = Admin par défaut V1, créer rôle RH dédié plus tard)
- ⚠️ **À implémenter** : anonymisation des rapports (nominatifs → matricule)

## Definition of Done

- [ ] Faille `auth:sanctum` corrigée
- [ ] Les 6 scénarios E2E passent
- [ ] Anonymisation testée
- [ ] Comptable bloqué sur toutes les actions RH

## Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale | SM Agent (Claude Opus 4.7) |
