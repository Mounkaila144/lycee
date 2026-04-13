# Module Paie Personnel - Product Requirements Document (PRD)

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Version** : v5
> **Date** : 2026-03-16
> **Phase** : Phase 3 - Gestion Financiere
> **Priorite** : MOYENNE 🟡

---

## Goals and Background Context

### Goals

- Permettre au comptable/intendant de gerer les fiches du personnel avec informations contractuelles (type de contrat, salaire ou taux horaire)
- Calculer automatiquement la paie mensuelle pour chaque employe (salaire fixe ou taux horaire x heures travaillees)
- Gerer les heures supplementaires des enseignants avec calcul automatique de la remuneration correspondante
- Generer automatiquement les bulletins de paie en PDF avec details complets (salaire brut, deductions, net a payer)
- Suivre l'historique des paiements de salaire pour chaque employe
- Fournir des etats mensuels de la masse salariale et des charges pour pilotage budgetaire
- Reduire le temps de calcul et generation de la paie de plusieurs jours a quelques heures
- Eliminer les erreurs de calcul manuel et garantir l'exactitude des bulletins

### Background Context

La gestion de la paie du personnel dans les colleges et lycees au Niger repose actuellement sur des calculs manuels dans Excel. L'intendant ou le comptable calcule manuellement les salaires (fixes ou horaires), applique les deductions (cotisations, avances), et genere les bulletins de paie sur Word. Ce processus est chronophage, source d'erreurs, et ne permet pas de suivre facilement l'historique des paiements ni la masse salariale globale.

Le personnel d'un etablissement secondaire se compose de plusieurs categories :
- **Enseignants permanents** : Salaire fixe mensuel, souvent fonctionnaires ou contractuels de l'Etat
- **Enseignants vacataires** : Payes a l'heure, remuneration variable selon le volume horaire effectue
- **Contractuels** : Contrat temporaire avec salaire fixe (enseignants ou administratifs)
- **Personnel d'appui** : Surveillants, gardiens, secretaires, personnel d'entretien, avec salaire fixe

Le Module Paie Personnel numerise l'ensemble du processus de gestion de la paie : de la configuration des fiches employes a la generation automatique des bulletins de paie. L'integration avec le Module Emplois du Temps (optionnelle) permet de recuperer automatiquement les heures de cours pour les enseignants vacataires. Les bulletins PDF generes sont professionnels et conformes. Les tableaux de bord permettent au comptable/intendant et au directeur de suivre la masse salariale et les charges en temps reel.

### Change Log

| Date | Version | Description | Author |
|------|---------|-------------|---------|
| 2026-03-16 | 2.0 | Refonte complete - Passage du LMD (superieur) au secondaire (colleges/lycees) | John (PM) |
| 2026-01-07 | 1.0 | Version initiale du PRD Module Paie Personnel (LMD) | Claude (PM Agent) |

---

## Requirements

### Functional

**FR1:** Le systeme doit permettre de creer des fiches pour chaque employe incluant : nom, prenom, fonction (enseignant, surveillant, gardien, secretaire, etc.), discipline/matiere enseignee (pour les enseignants), type de contrat (permanent/contractuel/vacataire), date d'embauche, numero de securite sociale (optionnel)

**FR2:** Chaque fiche employe doit inclure les informations salariales : mode de remuneration (salaire fixe mensuel ou taux horaire), montant du salaire brut ou taux horaire

**FR3:** Le systeme doit permettre de configurer des deductions applicables (cotisations sociales, impots, avances, autres) avec taux ou montant fixe

**FR4:** Le systeme doit permettre l'enregistrement des heures travaillees pour les employes a taux horaire (saisie manuelle ou import depuis emplois du temps pour enseignants vacataires)

**FR5:** Le systeme doit calculer automatiquement la paie mensuelle pour chaque employe :
- Employe a salaire fixe : Salaire brut - Deductions = Net a payer
- Employe a taux horaire : (Taux horaire x Heures travaillees) - Deductions = Net a payer

**FR6:** Le systeme doit permettre l'ajout de primes ou bonus ponctuels (ex: prime de fin d'annee, heures supplementaires) a la paie d'un mois specifique

**FR7:** Le systeme doit gerer les heures supplementaires des enseignants permanents : enregistrement du nombre d'heures supplementaires, taux horaire specifique, calcul automatique du montant

**FR8:** Le systeme doit permettre l'enregistrement d'avances sur salaire avec deduction automatique lors du calcul de la paie

**FR9:** Le systeme doit generer automatiquement un bulletin de paie en PDF pour chaque employe incluant : periode, salaire brut, detail des deductions, primes, heures supplementaires, avances deduites, net a payer

**FR10:** Le systeme doit permettre la validation de la paie mensuelle (workflow : Calculee -> Validee -> Payee) pour verrouillage

**FR11:** Le systeme doit permettre l'enregistrement de la date de paiement effective pour chaque employe

**FR12:** Le systeme doit fournir un historique complet des bulletins de paie pour chaque employe, telechargeables en PDF

**FR13:** Le systeme doit generer un etat mensuel de la masse salariale avec : total brut, total deductions, total net, detail par categorie de personnel (enseignants, personnel d'appui) ou par fonction

**FR14:** Le systeme doit generer un etat mensuel des charges (cotisations sociales, impots) pour declaration aux organismes competents

**FR15:** Le systeme doit permettre l'export de la paie en Excel pour import dans logiciel comptable externe (optionnel)

**FR16:** Les employes doivent pouvoir consulter leurs bulletins de paie depuis leur portail (acces lecture seule)

**FR17:** Le systeme doit logger toutes les modifications apportees aux fiches employes et paies pour audit

### Non Functional

**NFR1:** Le calcul automatique de la paie pour 100 employes doit prendre moins de 30 secondes

**NFR2:** La generation d'un bulletin de paie PDF doit prendre moins de 5 secondes

**NFR3:** Le systeme doit supporter la generation de 200 bulletins de paie en batch (une fois par mois) en moins de 5 minutes

**NFR4:** Les donnees salariales doivent etre hautement securisees avec encryption au repos et en transit (sensibilite elevee)

**NFR5:** L'acces aux donnees de paie doit etre strictement controle (uniquement Comptable/Intendant et Directeur, pas d'acces pour enseignants standards ou eleves)

**NFR6:** Les donnees de paie doivent etre isolees par tenant (multi-tenant) avec audit trail complet

**NFR7:** L'historique des paies doit etre conserve indefiniment pour conformite legale et audit

---

## User Interface Design Goals

### Overall UX Vision

L'interface de gestion de la paie doit etre professionnelle et structuree, type logiciel RH classique. Formulaires clairs pour les fiches employes, tableau de bord de calcul de la paie mensuelle avec validation en plusieurs etapes, et generation batch de bulletins. Le design privilegie la clarte des informations et la prevention des erreurs.

L'utilisateur principal est le comptable/intendant de l'etablissement. L'interface doit lui permettre de traiter rapidement la paie mensuelle de l'ensemble du personnel (enseignants permanents, vacataires, contractuels, personnel d'appui) en un minimum d'etapes.

Pour les employes, l'interface de consultation doit etre simple et transparente : acces facile a leurs bulletins avec possibilite de telechargement.

### Key Interaction Paradigms

- **Gestion des fiches employes** : CRUD classique avec formulaires complets, organises par categorie de personnel
- **Calcul de la paie mensuelle** : Selection du mois -> Affichage de tous les employes avec calculs -> Validation globale
- **Edition avant validation** : Possibilite d'ajuster les montants (primes, heures supplementaires, deductions exceptionnelles) avant validation finale
- **Generation batch** : Bouton "Generer tous les bulletins" lancant un job async avec barre de progression
- **Validation par workflow** : Statuts clairs (Brouillon -> Validee -> Payee) avec actions restreintes par statut

### Core Screens and Views

1. **Page Liste du Personnel (Comptable/Intendant)** : Tableau avec tous les employes, colonnes (Nom, Fonction, Discipline/Matiere, Type contrat, Salaire), filtres par categorie, boutons CRUD
2. **Page Detail Fiche Employe** : Infos personnelles, infos contractuelles, historique de paie
3. **Page Calcul Paie Mensuelle (Comptable/Intendant)** : Selection du mois -> Tableau de tous les employes avec calculs detailles, bouton "Valider la paie"
4. **Page de Saisie des Heures (Comptable/Intendant)** : Pour employes a taux horaire (vacataires), saisie manuelle ou import automatique depuis EDT
5. **Page de Gestion des Deductions et Primes (Comptable/Intendant)** : Configuration globale des deductions applicables
6. **Page de Generation de Bulletins (Comptable/Intendant)** : Generation batch avec barre de progression
7. **Portail Employe - Mes Bulletins de Paie** : Liste des bulletins avec telechargement PDF
8. **Dashboard Masse Salariale (Comptable/Directeur)** : KPIs, graphiques d'evolution de la masse salariale, etat des charges

### Accessibility

- **WCAG AA** : Navigation clavier, contrastes suffisants
- Montants affiches clairement avec separation milliers (format FCFA)
- Confirmation obligatoire avant validation de paie (etape irreversible)

### Branding

Coherence avec l'application Gestion Scolaire. Bulletins de paie avec logo de l'etablissement, cachet et signature numerique du directeur. Montants en francs CFA (FCFA).

### Target Device and Platforms

- **Web Desktop uniquement** : Interface de gestion paie necessite ecran large pour tableaux detailles
- **Mobile pour consultation employes** : Portail employe responsive pour telechargement bulletins
- Bulletins PDF optimises pour impression A4

---

## Technical Assumptions

### Repository Structure

**Polyrepo** : Backend Laravel dans `crm-api`, frontend Next.js dans `crm-frontend`.

### Service Architecture

**Architecture modulaire Laravel** :
- Module backend : `Modules/Paie` contenant Models (`Employee`, `PayrollPeriod`, `Payslip`, `Deduction`, `Advance`, `Bonus`), Controllers, Services
- Frontend Next.js : Pages `/admin/paie`, `/employee/mes-bulletins`
- API RESTful avec routes admin (comptable/intendant role) et employee guards

### Testing Requirements

- **Tests unitaires** : Calcul de paie (salaire fixe, taux horaire, deductions, primes, heures supplementaires, avances)
- **Tests feature** : API CRUD pour employes, payslips, deductions
- **Tests de validation** : Verification des regles metier (validation de paie, verrouillage)
- **Tests frontend** : Composants React pour formulaires employes et calcul de paie
- **Tests E2E** : Playwright pour workflow complet de calcul et generation de paie mensuelle

### Additional Technical Assumptions and Requests

- **Base de donnees** : Tables `employees` (fiches employes), `payroll_periods` (periodes de paie mensuelles), `payslips` (bulletins individuels), `payslip_deductions` (detail deductions par bulletin), `deductions` (deductions configurables), `advances` (avances sur salaire), `bonuses` (primes et heures supplementaires)
- **Encryption** : Encryption des montants de salaire au repos (Laravel encrypted casting)
- **Calcul de paie** : Service `PayrollCalculationService` centralisant toute la logique de calcul
- **Generation bulletins PDF** : Service `PayslipGeneratorService` avec template Blade professionnel
- **Batch generation** : Queue Laravel pour generation async de bulletins avec progress tracking
- **Permissions** : Guard `comptable` (acces complet paie), `director` (consultation et validation), `employee` (lecture propres bulletins uniquement)
- **Audit trail** : Logging de toutes les operations sensibles (creation employe, modification salaire, validation paie) avec `spatie/laravel-activitylog`
- **Integration emplois du temps** : Optionnel pour MVP, service recuperant les heures de cours depuis Module EDT pour calcul automatique des vacataires
- **Devise** : Tous les montants en francs CFA (FCFA), pas de gestion multi-devises

---

## Epic List

### Epic 1: Gestion des Fiches du Personnel
Creer les entites employes et permettre la gestion complete des fiches avec informations contractuelles et salariales, adaptees aux categories de personnel d'un college/lycee (enseignants permanents, vacataires, contractuels, personnel d'appui).

### Epic 2: Configuration des Deductions, Primes et Avances
Permettre la configuration des deductions applicables et l'enregistrement des primes, heures supplementaires et avances ponctuelles.

### Epic 3: Calcul Automatique de la Paie Mensuelle
Implementer la logique de calcul automatique de la paie pour salaires fixes et taux horaires avec application des deductions, heures supplementaires, primes et avances.

### Epic 4: Generation de Bulletins de Paie PDF
Generer automatiquement les bulletins de paie professionnels en PDF avec details complets pour chaque employe.

### Epic 5: Consultation des Bulletins pour Employes
Permettre aux employes de consulter et telecharger leurs bulletins de paie depuis leur portail.

### Epic 6: Tableaux de Bord, Rapports et Etats de la Masse Salariale
Creer des dashboards et rapports pour suivre la masse salariale, les charges et les couts RH.

---

## Epic 1: Gestion des Fiches du Personnel

**Objectif** : Creer les entites employes et permettre la gestion complete des fiches avec informations contractuelles, salariales et coordonnees, adaptees aux differentes categories de personnel d'un etablissement secondaire.

### Story 1.1: Creer les Migrations et Models pour les Employes

**En tant qu'** architecte technique,
**Je veux** creer les tables et models pour les employes,
**Afin de** stocker les donnees du personnel de l'etablissement.

**Acceptance Criteria:**

1. Migration `create_employees_table` creee avec colonnes : `id`, `tenant_id`, `employee_number`, `first_name`, `last_name`, `email`, `phone`, `position` (fonction : enseignant, surveillant, gardien, secretaire, etc.), `subject_id` (nullable, FK vers matiere/discipline pour les enseignants), `contract_type` (enum: permanent/fixed_term/contractor), `hire_date`, `social_security_number` (optionnel), `payment_mode` (enum: fixed_salary/hourly_rate), `fixed_salary` (encrypted), `hourly_rate` (encrypted), `overtime_rate` (encrypted, nullable, taux horaire pour heures supplementaires), `bank_account`, `is_active`, `timestamps`, `deleted_at`
2. Model `Employee` cree avec relations : `belongsTo(Subject)` (matiere enseignee), `hasMany(Payslip)`, `hasMany(Advance)`
3. Enum `ContractType` cree : `Permanent`, `FixedTerm`, `Contractor`
4. Enum `PaymentMode` cree : `FixedSalary`, `HourlyRate`
5. Enum `EmployeePosition` cree : `Teacher`, `Supervisor`, `Guard`, `Secretary`, `Janitor`, `Accountant`, `Other`
6. Utilisation du casting `encrypted` de Laravel pour `fixed_salary`, `hourly_rate` et `overtime_rate`
7. Model utilisant le trait `BelongsToTenant` et `SoftDeletes`
8. Factory creee pour generation de donnees de test
9. Tests unitaires verifiant les relations et l'encryption

### Story 1.2: Creer les API Endpoints CRUD pour les Employes

**En tant que** comptable/intendant,
**Je veux** creer, lire, mettre a jour et desactiver des fiches employes via l'API,
**Afin de** gerer le personnel de mon etablissement.

**Acceptance Criteria:**

1. Route `GET /api/admin/employees` retournant la liste des employes actifs avec filtres (position, contract_type, payment_mode, subject_id)
2. Route `GET /api/admin/employees/{id}` retournant un employe avec toutes ses relations
3. Route `POST /api/admin/employees` creant un nouvel employe avec validation (StoreEmployeeRequest)
4. `StoreEmployeeRequest` validant : `first_name`, `last_name` (required), `email` (email, unique), `position` (enum), `subject_id` (exists, required if position=teacher), `contract_type` (enum), `payment_mode` (enum), `fixed_salary` (required if payment_mode=fixed), `hourly_rate` (required if payment_mode=hourly)
5. Generation automatique d'un `employee_number` unique (format : EMP-2026-0001) via `SequenceNumberService`
6. Route `PUT /api/admin/employees/{id}` mettant a jour un employe avec validation (UpdateEmployeeRequest)
7. Route `DELETE /api/admin/employees/{id}` desactivant un employe (soft delete + is_active = false)
8. `EmployeeResource` transformant les donnees (sans exposer les montants de salaire dans les listes, uniquement dans la vue detail)
9. Middleware `auth:sanctum` et `ability:comptable`
10. Tests feature couvrant tous les endpoints avec verification de l'encryption

### Story 1.3: Creer la Page de Liste du Personnel

**En tant que** comptable/intendant,
**Je veux** voir la liste de tout le personnel,
**Afin de** gerer les fiches employes.

**Acceptance Criteria:**

1. Page Next.js `/admin/paie/personnel` creee avec authentification guard comptable
2. Tableau avec colonnes : Nom complet, Fonction, Discipline/Matiere (pour enseignants), Type de contrat, Mode de paiement, Statut (Actif/Inactif), Actions
3. Filtres : Fonction (Enseignant, Surveillant, Gardien, etc.), Type de contrat, Mode de paiement, Statut
4. Bouton "Ajouter un employe" ouvrant un modal avec formulaire
5. Recherche par nom avec autocompletion
6. Boutons d'action : "Voir details", "Modifier", "Desactiver"
7. Badge colore pour statut : vert (Actif), gris (Inactif)
8. Compteurs en haut : "X employes actifs", repartition par categorie (enseignants, personnel d'appui)
9. Tests E2E verifiant l'affichage et les filtres

### Story 1.4: Creer le Formulaire de Creation/Modification d'Employe

**En tant que** comptable/intendant,
**Je veux** creer ou modifier une fiche employe via un formulaire complet,
**Afin d'** enregistrer toutes les informations necessaires.

**Acceptance Criteria:**

1. Modal `EmployeeForm` avec onglets : "Informations personnelles", "Informations contractuelles", "Informations bancaires"
2. Onglet "Informations personnelles" : Prenom, Nom, Email, Telephone, Photo (upload optionnel)
3. Onglet "Informations contractuelles" :
   - Fonction (select : Enseignant, Surveillant, Gardien, Secretaire, etc.)
   - Discipline/Matiere enseignee (select, visible uniquement si fonction = Enseignant, alimente depuis le module Structure Academique)
   - Type de contrat (select : Permanent, Contractuel, Vacataire)
   - Date d'embauche (date picker)
   - Numero de securite sociale (input optionnel)
   - Mode de paiement (radio: Salaire fixe / Taux horaire)
   - Si Salaire fixe : Input "Salaire mensuel brut (FCFA)"
   - Si Taux horaire : Input "Taux horaire (FCFA)"
   - Taux heures supplementaires (input optionnel, FCFA/heure)
4. Onglet "Informations bancaires" : Nom de la banque, Numero de compte (inputs optionnels)
5. Validation frontend : champs requis, email valide, montants > 0
6. Appel API `POST /api/admin/employees` ou `PUT /api/admin/employees/{id}`
7. Toast de succes et rechargement de la liste
8. Tests E2E verifiant la creation complete

### Story 1.5: Creer la Page de Detail d'un Employe

**En tant que** comptable/intendant,
**Je veux** voir tous les details d'un employe sur une page dediee,
**Afin de** consulter son profil complet et son historique de paie.

**Acceptance Criteria:**

1. Page Next.js `/admin/paie/personnel/[id]` creee
2. En-tete avec photo, nom complet, fonction, discipline/matiere (si enseignant), badges (Type contrat, Actif/Inactif)
3. Section "Informations personnelles" : Email, Telephone, Date d'embauche
4. Section "Informations contractuelles" : Type contrat, Mode de paiement, Salaire/Taux (affiche uniquement si user a permission comptable ou directeur)
5. Section "Informations bancaires" : Banque, Numero de compte
6. Section "Historique de paie" : Liste des 12 derniers bulletins avec Date, Periode, Montant net, Bouton "Telecharger"
7. Boutons d'action : "Modifier", "Desactiver", "Voir tous les bulletins"
8. Tests verifiant l'affichage et les permissions

---

## Epic 2: Configuration des Deductions, Primes et Avances

**Objectif** : Permettre la configuration des deductions applicables globalement et l'enregistrement des primes, heures supplementaires et avances ponctuelles pour ajuster les paies individuelles.

### Story 2.1: Creer les Migrations et Models pour Deductions

**En tant qu'** architecte technique,
**Je veux** creer les tables et models pour les deductions configurables,
**Afin de** stocker les parametres de deductions.

**Acceptance Criteria:**

1. Migration `create_deductions_table` creee avec colonnes : `id`, `tenant_id`, `name` (ex: Cotisation sociale, Impot sur revenu, Cotisation retraite), `type` (enum: percentage/fixed_amount), `value` (taux en % ou montant fixe), `applies_to` (enum: all/teachers_only/support_staff_only, nullable, pour cibler des categories de personnel), `is_active`, `timestamps`
2. Model `Deduction` cree avec trait `BelongsToTenant`
3. Enum `DeductionType` cree : `Percentage`, `FixedAmount`
4. Factory et tests unitaires

### Story 2.2: Creer l'Interface de Configuration des Deductions

**En tant que** comptable/intendant,
**Je veux** configurer les deductions applicables a la paie,
**Afin de** parametrer les cotisations et impots de mon etablissement.

**Acceptance Criteria:**

1. Page Next.js `/admin/paie/configuration-deductions` creee
2. Tableau avec colonnes : Nom, Type (Pourcentage/Montant fixe), Valeur, Applicable a (Tous/Enseignants/Personnel d'appui), Actif, Actions
3. Bouton "Ajouter une deduction" ouvrant un modal avec formulaire
4. Formulaire avec champs : Nom, Type (select), Valeur (input adapte : % si pourcentage, montant FCFA si fixe), Applicable a (select), Actif (checkbox)
5. Endpoint API `POST /api/admin/deductions` creant une deduction
6. Boutons "Modifier" et "Desactiver" sur chaque ligne
7. Tests E2E verifiant la creation

### Story 2.3: Creer les Migrations et Models pour Primes et Avances

**En tant qu'** architecte technique,
**Je veux** creer les tables pour primes et avances,
**Afin de** stocker les ajustements ponctuels de paie.

**Acceptance Criteria:**

1. Migration `create_advances_table` creee avec colonnes : `id`, `tenant_id`, `employee_id`, `amount`, `advance_date`, `deducted_in_payroll_period_id` (nullable, FK), `status` (pending/deducted), `reason`, `timestamps`
2. Migration `create_bonuses_table` creee avec colonnes : `id`, `tenant_id`, `employee_id`, `payroll_period_id`, `amount`, `type` (enum: year_end_bonus/overtime/responsibility_premium/other), `description`, `hours` (nullable, pour les heures supplementaires), `timestamps`
3. Model `Advance` cree avec relations : `belongsTo(Employee)`, `belongsTo(PayrollPeriod, 'deducted_in_payroll_period_id')`
4. Model `Bonus` cree avec relations : `belongsTo(Employee)`, `belongsTo(PayrollPeriod)`
5. Enum `AdvanceStatus` : `Pending`, `Deducted`
6. Enum `BonusType` : `YearEndBonus`, `Overtime`, `ResponsibilityPremium`, `Other`
7. Factories et tests unitaires

### Story 2.4: Creer l'Interface d'Enregistrement d'Avance

**En tant que** comptable/intendant,
**Je veux** enregistrer une avance sur salaire pour un employe,
**Afin de** la deduire automatiquement lors du calcul de sa prochaine paie.

**Acceptance Criteria:**

1. Sur la page de detail d'un employe, bouton "Enregistrer une avance"
2. Modal avec formulaire : Montant (FCFA), Date, Raison (textarea)
3. Endpoint API `POST /api/admin/advances` creant l'avance avec status "pending"
4. Affichage de la liste des avances sur la page de detail employe avec colonnes : Date, Montant, Statut, Action "Annuler" (si pending)
5. Tests verifiant la creation

### Story 2.5: Creer l'Interface d'Ajout de Prime et Heures Supplementaires

**En tant que** comptable/intendant,
**Je veux** ajouter une prime ponctuelle ou des heures supplementaires a la paie d'un employe pour un mois donne,
**Afin d'** inclure des bonus, primes de responsabilite ou heures supplementaires.

**Acceptance Criteria:**

1. Lors du calcul de la paie mensuelle, possibilite d'ajouter une prime ou des heures supplementaires a un employe specifique
2. Modal avec formulaire : Type de prime (select : Prime de fin d'annee, Heures supplementaires, Prime de responsabilite, Autre), Montant (FCFA), Description
3. Si type = Heures supplementaires : champ supplementaire "Nombre d'heures" et calcul automatique du montant base sur le taux horaire supplementaire de l'employe
4. Endpoint API `POST /api/admin/bonuses` creant la prime liee a la periode de paie
5. Prime prise en compte automatiquement dans le calcul du net a payer
6. Affichage de la prime dans le bulletin de paie genere
7. Tests verifiant l'integration dans le calcul

---

## Epic 3: Calcul Automatique de la Paie Mensuelle

**Objectif** : Implementer la logique de calcul automatique de la paie mensuelle pour tous les employes avec application des deductions, primes, heures supplementaires et avances.

### Story 3.1: Creer les Migrations et Models pour Periodes de Paie

**En tant qu'** architecte technique,
**Je veux** creer les tables pour les periodes de paie et bulletins,
**Afin de** stocker les donnees de paie mensuelles.

**Acceptance Criteria:**

1. Migration `create_payroll_periods_table` creee avec colonnes : `id`, `tenant_id`, `month` (1-12), `year`, `status` (enum: draft/validated/paid), `validated_at`, `validated_by`, `paid_at`, `timestamps`
2. Migration `create_payslips_table` creee avec colonnes : `id`, `tenant_id`, `payroll_period_id`, `employee_id`, `gross_salary`, `total_deductions`, `total_bonuses`, `overtime_amount`, `advances_deducted`, `net_salary`, `hours_worked` (pour taux horaire), `overtime_hours` (pour heures supplementaires), `generated_at`, `timestamps`
3. Migration `create_payslip_deductions_table` (detail deductions par bulletin) avec colonnes : `id`, `payslip_id`, `deduction_id`, `amount`, `timestamps`
4. Model `PayrollPeriod` cree avec relations : `hasMany(Payslip)`, `belongsTo(TenantUser, 'validated_by')`
5. Model `Payslip` cree avec relations : `belongsTo(PayrollPeriod)`, `belongsTo(Employee)`, `hasMany(PayslipDeduction)`, `hasMany(Bonus)`
6. Model `PayslipDeduction` avec relations
7. Enum `PayrollPeriodStatus` : `Draft`, `Validated`, `Paid`
8. Models utilisant `BelongsToTenant`
9. Factories et tests unitaires

### Story 3.2: Creer le Service de Calcul de Paie

**En tant que** developpeur backend,
**Je veux** creer un service centralisant la logique de calcul de paie,
**Afin de** garantir l'exactitude et la coherence des calculs.

**Acceptance Criteria:**

1. Service `PayrollCalculationService` cree dans `Modules/Paie/Services/`
2. Methode `calculatePayslipForEmployee(employee_id, payroll_period_id)` retournant un objet avec tous les montants calcules
3. Logique pour salaire fixe :
   - `gross_salary` = employee.fixed_salary
   - Recuperer toutes les deductions actives applicables a la categorie de l'employe
   - Pour chaque deduction : si `type = percentage`, `amount = gross_salary * value / 100`, sinon `amount = value`
   - `total_deductions` = somme des deductions
   - Recuperer les primes liees a cette periode + cet employe
   - `total_bonuses` = somme des primes (hors heures supplementaires)
   - Recuperer les heures supplementaires
   - `overtime_amount` = overtime_hours * employee.overtime_rate (ou taux par defaut)
   - Recuperer les avances pending de cet employe
   - `advances_deducted` = somme des avances (marquer les avances comme "deducted" apres calcul)
   - `net_salary` = gross_salary - total_deductions + total_bonuses + overtime_amount - advances_deducted
4. Logique pour taux horaire (vacataires) :
   - `gross_salary` = employee.hourly_rate * hours_worked
   - Reste identique au salaire fixe
5. Validation : net_salary ne peut pas etre negatif (si deductions + avances > brut + primes + heures sup, limiter les avances)
6. Tests unitaires exhaustifs couvrant tous les cas de figure (salaire fixe, horaire, avec/sans primes, avec/sans avances, avec/sans heures supplementaires, multiple deductions, deductions par categorie)

### Story 3.3: Creer l'Endpoint de Calcul de Paie Mensuelle

**En tant que** comptable/intendant,
**Je veux** declencher le calcul de la paie pour un mois donne via l'API,
**Afin de** preparer les bulletins.

**Acceptance Criteria:**

1. Endpoint API `POST /api/admin/payroll-periods/calculate` acceptant `month` et `year`
2. Logique :
   - Creer un `PayrollPeriod` avec status "draft" si n'existe pas deja
   - Pour chaque employe actif : appeler `PayrollCalculationService.calculatePayslipForEmployee()`
   - Creer un `Payslip` pour chaque employe avec tous les montants calcules
   - Creer les `PayslipDeduction` detaillant chaque deduction appliquee
3. Si periode existe deja et status = "draft", recalculer (ecraser les bulletins existants)
4. Si status != "draft", retourner erreur "Periode deja validee, impossible de recalculer"
5. Reponse : `PayrollPeriod` avec toutes les `Payslips` calculees
6. Tests feature verifiant le calcul complet et les erreurs

### Story 3.4: Creer la Page de Calcul de Paie Mensuelle

**En tant que** comptable/intendant,
**Je veux** calculer la paie d'un mois donne et voir tous les bulletins avant validation,
**Afin de** verifier et ajuster si necessaire.

**Acceptance Criteria:**

1. Page Next.js `/admin/paie/calcul-paie` creee
2. Selecteur de mois et annee + bouton "Calculer la paie"
3. Clic declenche API `POST /api/admin/payroll-periods/calculate`
4. Affichage d'un tableau avec tous les employes et leurs calculs :
   - Colonnes : Employe, Fonction, Salaire brut, Deductions, Primes, Heures sup, Avances, Net a payer, Actions
   - Lignes regroupees par categorie de personnel (Enseignants, Personnel d'appui)
5. Indicateur de status de la periode : Badge "Brouillon" (orange), "Validee" (vert), "Payee" (bleu)
6. Si status = "draft" : bouton "Ajuster" sur chaque ligne permettant d'ajouter une prime, des heures supplementaires ou modifier manuellement un montant (avec justification obligatoire)
7. Bouton "Valider la paie" en haut (desactive si status != "draft")
8. Validation demande confirmation : "Etes-vous sur ? Cette action est irreversible."
9. Appel API `POST /api/admin/payroll-periods/{id}/validate` changeant le status a "validated"
10. Apres validation, bouton "Generer tous les bulletins" apparait
11. Recapitulatif en haut : Total brut, Total deductions, Total primes, Total heures sup, Total net, Nombre d'employes (par categorie)
12. Tests E2E verifiant le workflow complet de calcul et validation

### Story 3.5: Implementer l'Enregistrement des Heures Travaillees

**En tant que** comptable/intendant,
**Je veux** enregistrer les heures travaillees pour les enseignants vacataires,
**Afin que** leur paie soit calculee correctement.

**Acceptance Criteria:**

1. Sur la page de calcul de paie, pour les employes a taux horaire (vacataires), colonne "Heures travaillees" editable
2. Saisie manuelle du nombre d'heures (input number)
3. Mise a jour en temps reel du salaire brut et net a payer lors de la saisie
4. Sauvegarde automatique (debounce 1 seconde) via API `PUT /api/admin/payslips/{id}/hours`
5. Optionnel (V2) : Bouton "Import automatique depuis emplois du temps" recuperant les heures de cours depuis Module EDT pour les enseignants vacataires
6. Tests verifiant le calcul correct apres saisie des heures

---

## Epic 4: Generation de Bulletins de Paie PDF

**Objectif** : Generer automatiquement les bulletins de paie professionnels en PDF avec details complets pour chaque employe.

### Story 4.1: Creer le Template PDF de Bulletin de Paie

**En tant que** designer backend,
**Je veux** creer un template PDF professionnel pour les bulletins de paie,
**Afin que** les documents generes soient conformes et imprimables.

**Acceptance Criteria:**

1. Template Blade `payslip-pdf.blade.php` cree avec :
   - En-tete : Logo etablissement, titre "BULLETIN DE PAIE", periode (Mois Annee)
   - Infos etablissement : Nom du college/lycee, Adresse, Telephone
   - Infos employe : Nom, Prenom, Fonction, Discipline/Matiere (si enseignant), Numero employe, N deg Securite sociale
   - Section "REMUNERATION BRUTE" : Salaire de base (ou Taux horaire x Heures), Heures supplementaires (detail), Primes (detail), **Total brut**
   - Section "DEDUCTIONS" : Tableau avec Libelle deduction, Montant (detail de chaque deduction), **Total deductions**
   - Section "AVANCES DEDUITES" : Montant total des avances deduites
   - Section "NET A PAYER" : Montant net en grand format et en lettres (ex: "Cent cinquante mille francs CFA")
   - Infos paiement : Mode de paiement (virement), Banque, Numero de compte
   - Pied de page : "Bulletin genere le {Date}", Signature du directeur, Cachet de l'etablissement
2. CSS optimise pour impression A4
3. Mise en page professionnelle type fiche de paie standard
4. Montants formates en FCFA avec separateurs de milliers
5. Tests visuels manuels sur PDFs generes

### Story 4.2: Creer le Service de Generation de Bulletins PDF

**En tant que** developpeur backend,
**Je veux** creer un service generant les bulletins PDF,
**Afin de** centraliser cette logique.

**Acceptance Criteria:**

1. Service `PayslipGeneratorService` cree dans `Modules/Paie/Services/`
2. Methode `generate(payslip_id)` generant le PDF pour un bulletin
3. Recuperation de toutes les donnees necessaires : employee, payroll_period, payslip avec deductions, primes, avances
4. Conversion du montant net en lettres (helper `numberToWords()` en francais, format FCFA)
5. Utilisation de `barryvdh/laravel-snappy` pour generation
6. Stockage du PDF dans `storage/app/public/payslips/{tenant_id}/{year}/{month}/{employee_number}.pdf`
7. Mise a jour de payslip.generated_at apres generation
8. Retour : chemin du fichier genere
9. Tests verifiant la generation et le contenu du PDF

### Story 4.3: Creer l'Endpoint de Generation de Bulletin Individuel

**En tant que** comptable/intendant,
**Je veux** generer le bulletin d'un employe specifique via l'API,
**Afin de** le telecharger ou l'envoyer individuellement.

**Acceptance Criteria:**

1. Endpoint API `GET /api/admin/payslips/{id}/generate` generant le bulletin
2. Validation : payslip doit appartenir a une periode validee ou payee
3. Utilisation du `PayslipGeneratorService`
4. Reponse : lien de telechargement du PDF
5. Headers HTTP pour telechargement direct
6. Tests feature verifiant la generation

### Story 4.4: Creer l'Endpoint de Generation Batch de Bulletins

**En tant que** comptable/intendant,
**Je veux** generer tous les bulletins d'une periode en une seule action,
**Afin de** preparer la distribution mensuelle.

**Acceptance Criteria:**

1. Endpoint API `POST /api/admin/payroll-periods/{id}/generate-all-payslips` generant tous les bulletins
2. Validation : periode doit avoir status "validated"
3. Dispatch d'un job async `GenerateAllPayslipsJob` dans la queue
4. Job itere sur tous les payslips de la periode et appelle `PayslipGeneratorService.generate()` pour chacun
5. Job met a jour un compteur de progression (stocker en cache Redis ou DB)
6. Endpoint `GET /api/admin/payroll-periods/{id}/generation-progress` retournant le statut (X/Y generes)
7. Frontend affiche une barre de progression polling cet endpoint toutes les 2 secondes
8. A la fin, possibilite de telecharger un ZIP contenant tous les bulletins
9. Tests verifiant la generation batch complete

### Story 4.5: Creer l'Interface de Generation de Bulletins

**En tant que** comptable/intendant,
**Je veux** generer les bulletins depuis l'interface web,
**Afin de** preparer la distribution sans passer par l'API directement.

**Acceptance Criteria:**

1. Sur la page de calcul de paie, apres validation, bouton "Generer tous les bulletins" visible
2. Clic declenche API `POST /api/admin/payroll-periods/{id}/generate-all-payslips`
3. Affichage d'une barre de progression avec texte "Generation en cours... X/Y bulletins"
4. Polling de l'endpoint de progression toutes les 2 secondes
5. A la fin : toast "Tous les bulletins ont ete generes" + bouton "Telecharger le ZIP"
6. Sur chaque ligne employe, bouton "Telecharger le bulletin" pour telechargement individuel
7. Tests E2E verifiant le workflow complet

---

## Epic 5: Consultation des Bulletins pour Employes

**Objectif** : Permettre aux employes (enseignants, personnel d'appui) de consulter et telecharger leurs bulletins de paie depuis leur portail personnel.

### Story 5.1: Creer l'Endpoint de Consultation pour Employes

**En tant qu'** employe,
**Je veux** consulter mes bulletins de paie via l'API,
**Afin de** voir mon historique de paie.

**Acceptance Criteria:**

1. Endpoint API `GET /api/employee/my-payslips` retournant tous les bulletins de l'employe authentifie
2. Filtres optionnels : `year`
3. Tri par defaut : date decroissante (les plus recents en premier)
4. `PayslipResource` simplifie pour vue employe (sans exposer details trop techniques)
5. Middleware `auth:sanctum` et `ability:employee`
6. Tests verifiant que l'employe ne voit QUE ses propres bulletins

### Story 5.2: Creer le Portail Employe - Mes Bulletins de Paie

**En tant qu'** employe,
**Je veux** avoir une page dediee a mes bulletins de paie,
**Afin de** les consulter et telecharger facilement.

**Acceptance Criteria:**

1. Page Next.js `/employee/mes-bulletins` creee avec authentification guard employee
2. Tableau avec colonnes : Periode (Mois Annee), Salaire brut, Deductions, Net paye, Statut, Actions
3. Badge colore pour statut : orange (Brouillon, pas encore accessible), vert (Valide), bleu (Paye)
4. Bouton "Telecharger" sur chaque ligne (desactive si status = "draft")
5. Filtrage par annee avec select
6. Affichage du bulletin le plus recent en haut
7. Possibilite d'exporter la liste en Excel pour usage personnel
8. Tests E2E verifiant l'affichage et le telechargement

### Story 5.3: Creer l'Endpoint de Telechargement de Bulletin pour Employe

**En tant qu'** employe,
**Je veux** telecharger mes bulletins de paie,
**Afin de** les conserver.

**Acceptance Criteria:**

1. Endpoint API `GET /api/employee/payslips/{id}/download` telechargeant le bulletin PDF
2. Validation : bulletin doit appartenir a l'employe authentifie ET status periode != "draft"
3. Si PDF n'existe pas encore, le generer a la volee
4. Headers HTTP pour telechargement direct
5. Tests verifiant les permissions et la generation a la demande

### Story 5.4: Afficher un Recapitulatif sur le Dashboard Employe

**En tant qu'** employe,
**Je veux** voir un widget de mon dernier bulletin sur mon dashboard,
**Afin d'** avoir une vue rapide.

**Acceptance Criteria:**

1. Composant `LastPayslipWidget` ajoute au dashboard employe (`/employee/dashboard`)
2. Affichage :
   - Periode du dernier bulletin
   - Montant net en grand format (FCFA)
   - Lien "Telecharger le bulletin"
   - Lien "Voir tous mes bulletins"
3. Widget affiche uniquement si au moins 1 bulletin existe
4. Tests verifiant l'affichage conditionnel

### Story 5.5: Envoyer une Notification Lors de la Disponibilite d'un Bulletin (Preparation)

**En tant qu'** employe,
**Je veux** recevoir une notification lorsque mon bulletin de paie est disponible,
**Afin d'** etre informe sans avoir a consulter regulierement.

**Acceptance Criteria:**

1. Lors de la validation d'une periode de paie, dispatch job `NotifyEmployeesPayslipReadyJob`
2. Job prepare les donnees : lien vers bulletin, montant net
3. Envoi effectif reporte en V2 (integration module Notifications)
4. Tests verifiant le dispatch du job

---

## Epic 6: Tableaux de Bord, Rapports et Etats de la Masse Salariale

**Objectif** : Creer des dashboards et rapports pour suivre la masse salariale, les charges et les couts RH en temps reel, fournissant au comptable/intendant et au directeur une vision complete de la situation financiere liee au personnel.

### Story 6.1: Creer le Dashboard de Masse Salariale

**En tant que** directeur ou comptable/intendant,
**Je veux** avoir un dashboard de masse salariale,
**Afin de** suivre les couts RH en temps reel.

**Acceptance Criteria:**

1. Page Next.js `/admin/paie/dashboard` creee (accessible aux roles comptable et directeur)
2. KPIs (grandes cartes visuelles) :
   - Masse salariale du mois en cours (total net a payer)
   - Masse salariale de l'annee scolaire en cours (cumul)
   - Nombre d'employes actifs (par categorie : enseignants, personnel d'appui)
   - Cout moyen par employe (masse salariale / nombre employes)
   - Total charges sociales du mois
3. Graphique 1 : Evolution de la masse salariale sur les 12 derniers mois (line chart)
4. Graphique 2 : Repartition de la masse salariale par categorie de personnel (bar chart : Enseignants permanents, Vacataires, Contractuels, Personnel d'appui)
5. Graphique 3 : Repartition Salaires fixes vs Taux horaires (pie chart)
6. Section "Dernieres paies" : Liste des 5 dernieres periodes de paie avec statut
7. Tests verifiant les calculs des KPIs

### Story 6.2: Creer les Endpoints pour les Statistiques de Paie

**En tant que** developpeur backend,
**Je veux** creer les endpoints fournissant les statistiques de paie,
**Afin d'** alimenter les dashboards.

**Acceptance Criteria:**

1. Endpoint API `GET /api/admin/paie/statistics` retournant :
   - `current_month_payroll` : masse salariale du mois en cours
   - `school_year_payroll` : cumul de l'annee scolaire
   - `active_employees_count` (avec repartition par categorie)
   - `average_cost_per_employee`
   - `total_social_charges` : total des charges sociales du mois
2. Endpoint API `GET /api/admin/paie/payroll-by-month` retournant la masse salariale des 12 derniers mois
3. Endpoint API `GET /api/admin/paie/payroll-by-category` retournant la repartition par categorie de personnel
4. Middleware `auth:sanctum` et `ability:comptable,director`
5. Caching des resultats pour 10 minutes pour performances
6. Tests verifiant les calculs

### Story 6.3: Creer le Rapport Mensuel de Masse Salariale

**En tant que** directeur,
**Je veux** generer un rapport mensuel de masse salariale,
**Afin de** presenter les couts RH aux parties prenantes (Conseil d'administration, APE, Inspection academique).

**Acceptance Criteria:**

1. Page `/admin/paie/rapport-mensuel` creee
2. Selection du mois et annee
3. Endpoint API `GET /api/admin/payroll-periods/{id}/report` generant les donnees du rapport
4. Affichage structure :
   - Section RESUME : Total brut, Total deductions, Total primes, Total heures sup, Total avances, Total net
   - Section PAR CATEGORIE DE PERSONNEL : Tableau avec categorie (Enseignants permanents, Vacataires, Contractuels, Personnel d'appui), nombre employes, total net
   - Section PAR TYPE DE CONTRAT : Tableau avec type, nombre employes, total net
   - Section CHARGES SOCIALES : Detail des cotisations et impots retenus
   - Graphiques visuels (barres, camemberts)
5. Export PDF professionnel du rapport pour presentation
6. Export Excel pour analyse detaillee
7. Tests verifiant les calculs

### Story 6.4: Creer l'Etat Annuel de la Masse Salariale

**En tant que** directeur,
**Je veux** voir un etat annuel de la masse salariale,
**Afin de** suivre l'evolution sur l'annee scolaire.

**Acceptance Criteria:**

1. Page `/admin/paie/etat-annuel` creee
2. Selection de l'annee scolaire (ex: 2025-2026)
3. Tableau recapitulatif mensuel : Septembre-Aout (annee scolaire) avec colonnes (Mois, Brut, Deductions, Charges sociales, Net)
4. Ligne de total en bas
5. Graphique : Evolution mois par mois (line chart)
6. Comparaison avec annee scolaire precedente : variation en % pour chaque mois
7. Export PDF et Excel de l'etat annuel
8. Tests verifiant les calculs et comparaisons

### Story 6.5: Creer l'Etat Mensuel des Charges

**En tant que** comptable/intendant,
**Je veux** generer un etat mensuel des charges (cotisations, impots),
**Afin de** preparer les declarations aux organismes competents (CNSS, impots).

**Acceptance Criteria:**

1. Page `/admin/paie/etat-charges` creee
2. Selection du mois et annee
3. Tableau detaille avec colonnes : Type de charge (ex: Cotisation CNSS, Impot sur revenu), Nombre d'employes concernes, Assiette (base de calcul), Taux, Montant total
4. Total general en bas du tableau
5. Export PDF et Excel pour transmission aux organismes
6. Tests verifiant les calculs

### Story 6.6: Implementer le Logging Complet des Operations de Paie

**En tant qu'** administrateur systeme,
**Je veux** avoir un historique de toutes les operations sensibles de paie,
**Afin d'** auditer les modifications.

**Acceptance Criteria:**

1. Utilisation du package `spatie/laravel-activitylog`
2. Logging automatique de toutes les actions CRUD sur `Employee`, `PayrollPeriod`, `Payslip`, modifications manuelles
3. Informations loggees : utilisateur, action, timestamp, donnees modifiees (avant/apres)
4. Table `activity_log` avec isolation par tenant
5. Endpoint API `GET /api/admin/paie/activity` retournant l'historique complet
6. Page frontend "Historique des operations de paie" avec filtres (utilisateur, type d'operation, periode)
7. Affichage chronologique avec details : Date, Utilisateur, Action, Details
8. Logs conserves indefiniment pour conformite legale
9. Tests verifiant l'enregistrement correct des logs

---

## Next Steps

### Architect Prompt

Le PRD du Module Paie Personnel est complet. Veuillez creer le document d'architecture technique detaille couvrant :
- Structure de base de donnees (tables employees, payroll_periods, payslips, payslip_deductions, deductions, advances, bonuses, indexes, relations)
- Architecture API (endpoints CRUD, permissions par guard comptable/director/employee)
- Services metier (PayrollCalculationService, PayslipGeneratorService, SequenceNumberService)
- Encryption des donnees salariales sensibles (salaires, taux horaires) avec Laravel encrypted casting
- Generation PDF optimisee pour bulletins de paie (template professionnel, performances)
- Generation batch avec queues et progress tracking
- Strategie de caching pour dashboards de masse salariale
- Gestion des heures supplementaires et calcul automatique
- Etat mensuel des charges sociales (CNSS, impots)
- Plan de tests (unitaires pour calculs de paie, features, E2E, tests de securite)
- Securite renforcee : permissions strictes, audit trail complet, encryption au repos

### UX Expert Prompt

Merci de creer les wireframes et maquettes pour les ecrans principaux du Module Paie Personnel :
- Page de liste du personnel avec CRUD et filtres par categorie (enseignants, personnel d'appui)
- Page de calcul de paie mensuelle avec tableau detaille par categorie et workflow de validation
- Interface de gestion des heures supplementaires et saisie des heures vacataires
- Interface de generation batch de bulletins avec barre de progression
- Portail employe pour consultation de bulletins de paie
- Dashboard de masse salariale avec KPIs et graphiques
- Template PDF de bulletin de paie professionnel (type fiche de paie standard, montants en FCFA)
- Rapports mensuels et annuels de masse salariale
- Etat mensuel des charges sociales

Assurez-vous que le design soit coherent avec l'application Gestion Scolaire et que les donnees salariales sensibles soient protegees visuellement (affichage conditionnel selon permissions). Les montants doivent etre affiches en FCFA avec separateurs de milliers.
