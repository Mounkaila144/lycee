# Prochaines Etapes - Systeme de Gestion Scolaire (Colleges & Lycees du Niger)

[← Retour a l'index](./index.md)

---

## Plan de Developpement

Le developpement suit 3 phases principales alignees avec le PRD, couvrant 12 modules pour un systeme complet de gestion des colleges et lycees au Niger.

---

## Phase 1 : MVP Core (4-6 mois)

**Focus** : Cycle academique + bulletins scolaires

### Sprint 1-2 : Structure Academique (Fondation)

**Objectif** : Etablir la structure academique de base adaptee au systeme educatif nigerien

#### Backend

- [ ] Migrations tenant : 9 tables
  - `academic_years` (annees scolaires)
  - `semesters` (semestres)
  - `cycles` (College, Lycee)
  - `levels` (niveaux : 6e, 5e, 4e, 3e, 2nde, 1ere, Tle)
  - `series` (series : A, C, D)
  - `classes` (classes avec effectifs)
  - `subjects` (matieres)
  - `subject_class_coefficients` (coefficients par matiere et classe)
  - `teacher_subject_assignments` (affectations enseignants-matieres)
- [ ] Models + Relations Eloquent
- [ ] Factories pour tests
- [ ] Controllers CRUD complets (Admin)
- [ ] Form Requests (validation)
- [ ] API Resources
- [ ] Routes admin.php
- [ ] Tests unitaires + feature (80%+ couverture)

#### Frontend

- [ ] Types TypeScript (cycle, level, series, class, subject, coefficient, etc.)
- [ ] Services API
- [ ] Hooks personnalises
- [ ] Composants admin :
  - ClassList + Table + Modals
  - SubjectList + Table + Modals
  - ClassStructureView (vue de la structure par cycle/niveau/serie)
  - CoefficientManager (gestion des coefficients par matiere et classe)
- [ ] Pages Next.js
- [ ] Tests Jest + React Testing Library

#### Seed : Donnees initiales Niger

- [ ] Cycles : College, Lycee
- [ ] Niveaux : 6e, 5e, 4e, 3e (College) / 2nde, 1ere, Tle (Lycee)
- [ ] Series : A (Lettres), C (Mathematiques), D (Sciences)
- [ ] Matieres standard avec coefficients par serie et niveau

#### Livrables

- Structure academique complete fonctionnelle
- CRUD Cycles, Niveaux, Series, Classes, Matieres
- Gestion des coefficients par matiere/classe
- Affectations enseignants aux matieres/classes

**Rationale** : Tous les autres modules dependent de la structure academique. Impossible de continuer sans cette fondation.

---

### Sprint 3-4 : Inscriptions

**Objectif** : Permettre l'inscription des eleves et la gestion des parents

#### Backend

- [ ] Migrations tenant : 5 tables
  - `students` (eleves)
  - `parents` (parents/tuteurs)
  - `student_parent` (relation eleve-parent, pivot)
  - `class_enrollments` (inscriptions aux classes)
  - `student_status_history` (historique des statuts eleve)
- [ ] MatriculeGeneratorService (generation de matricules uniques)
- [ ] StudentImportService (import CSV en masse)
- [ ] Creation automatique parent lors de l'inscription eleve
- [ ] Import CSV avec previsualisation avant validation
- [ ] Controllers (StudentController, ParentController, EnrollmentController, StudentImportController)
- [ ] Form Requests (validation)
- [ ] API Resources
- [ ] Tests (80%+ couverture)

#### Frontend

- [ ] Types TypeScript (student, parent, enrollment, etc.)
- [ ] Services API
- [ ] Composants admin :
  - StudentList + Table avec filtres (classe, niveau, serie)
  - StudentAddModal (avec saisie informations parent integree)
  - ParentList + Table
  - CsvImportWizard (multi-etapes : upload, previsualisation, validation, import)
  - StudentCard (fiche eleve avec photo, infos, historique)
- [ ] Tests

#### Livrables

- Inscription administrative fonctionnelle
- Inscription pedagogique (classe, niveau, serie)
- Import CSV en masse avec previsualisation
- Generation matricules uniques
- Gestion parents avec creation automatique

**Rationale** : Sans eleves inscrits, impossible de tester les modules suivants (notes, presences, bulletins, etc.).

---

### Sprint 5-6 : Notes & Evaluations

**Objectif** : Permettre la saisie des notes et le calcul des moyennes

#### Backend

- [ ] Migrations tenant : 5 tables
  - `evaluations` (devoirs, compositions, interrogations)
  - `grades` (notes individuelles)
  - `subject_semester_averages` (moyennes par matiere et semestre)
  - `semester_report_cards` (bulletins semestriels)
  - `grading_scales` (bareme de notation configurable)
- [ ] GradeCalculatorService :
  - Calcul moyennes par matiere avec poids (devoirs, compositions)
  - Calcul moyenne generale avec coefficients
  - Gestion du bareme (sur 20 par defaut)
- [ ] RankingService :
  - Classement par classe (rang)
  - Statistiques (min, max, moyenne classe)
- [ ] Controllers (EvaluationController, GradeController)
- [ ] Form Requests (validation)
- [ ] API Resources
- [ ] Tests (80%+ couverture)

#### Frontend

- [ ] Types TypeScript (evaluation, grade, average, report_card, etc.)
- [ ] Services API
- [ ] Composants enseignant :
  - GradeInputTable (saisie notes en grille pour une classe)
  - AppreciationForm (appreciation par matiere et generale)
- [ ] Composants eleve :
  - MyGrades (consultation des notes)
  - MyReportCards (consultation des bulletins)
- [ ] Tests

#### Livrables

- Saisie des notes par les enseignants
- Calcul automatique des moyennes avec coefficients
- Classement par classe
- Consultation notes et bulletins par l'eleve

---

### Sprint 7 : Conseil de Classe

**Objectif** : Gerer les conseils de classe et les decisions

#### Backend

- [ ] Migrations tenant : 3 tables
  - `class_councils` (conseils de classe)
  - `council_decisions` (decisions par eleve : admis, redouble, exclu, etc.)
  - `council_attendees` (participants au conseil)
- [ ] ClassCouncilService :
  - Calcul statistiques de classe (taux de reussite, moyennes)
  - Decisions automatiques suggerees selon seuils configurables
  - Attribution mentions (Tres Bien, Bien, Assez Bien, Passable)
- [ ] Controllers (ClassCouncilController, CouncilDecisionController)
- [ ] Form Requests (validation)
- [ ] API Resources
- [ ] Tests (80%+ couverture)

#### Frontend

- [ ] Types TypeScript (council, decision, attendee, etc.)
- [ ] Services API
- [ ] Composants admin :
  - ClassCouncilDashboard (vue d'ensemble avec statistiques)
  - DecisionForm (saisie decisions par eleve)
  - CouncilMinutes (proces-verbal du conseil)
- [ ] Tests

#### Livrables

- Gestion complete du conseil de classe
- Decisions et mentions automatiques suggerees
- Proces-verbal generable

---

### Sprint 8 : Documents Officiels

**Objectif** : Generer les bulletins et documents administratifs en PDF

#### Backend

- [ ] Migrations tenant : 1 table
  - `generated_documents` (historique des documents generes)
- [ ] PdfGeneratorService :
  - Template `bulletin-semestriel` (notes, moyennes, rang, appreciation, decision)
  - Template `bulletin-annuel` (synthese annuelle)
  - Template `attestation-scolarite` (attestation d'inscription)
  - Template `carte-scolaire` (carte d'identite scolaire)
- [ ] Generation par lot (tous les bulletins d'une classe en un clic)
- [ ] Queue jobs pour generation PDF asynchrone (GenerateBulletinJob, GenerateBatchBulletinsJob)
- [ ] Controllers (DocumentController, BulletinController)
- [ ] Tests (80%+ couverture)

#### Frontend

- [ ] Types TypeScript (document, template, batch, etc.)
- [ ] Services API
- [ ] Composants admin :
  - DocumentGenerator (generation individuelle par type)
  - BulletinBatchGenerator (generation en lot par classe)
- [ ] Composants eleve :
  - MyDocuments (telechargement de mes bulletins et documents)
- [ ] Tests

#### Livrables

- Generation PDF bulletins semestriels et annuels
- Attestation de scolarite et carte scolaire
- Generation en lot optimisee (queue)
- Telechargement par l'eleve

---

## Phase 2 : Vie Scolaire & Operations (3 mois)

### Sprint 9-10 : Presences & Absences + Discipline

**Objectif** : Gerer les presences, absences, retards et la discipline

#### Presences & Absences

- [ ] Migrations tenant : 2 tables
  - `attendances` (presences/absences/retards par seance)
  - `attendance_justifications` (justificatifs d'absence)
- [ ] Marquage des presences par seance
- [ ] Notification parents en cas d'absence
- [ ] Seuils d'alerte configurables (ex: 3 absences non justifiees)
- [ ] Tests

#### Discipline

- [ ] Migrations tenant : 4 tables
  - `discipline_incidents` (incidents disciplinaires)
  - `sanctions` (sanctions prononcees)
  - `disciplinary_councils` (conseils de discipline)
  - `disciplinary_council_members` (membres du conseil)
- [ ] Enregistrement incidents et sanctions
- [ ] Conseil de discipline
- [ ] Notification parents
- [ ] Tests

#### Frontend

- [ ] Composants enseignant :
  - AttendanceSheet (feuille de presence par seance)
- [ ] Composants administration :
  - IncidentForm (declaration incident)
  - SanctionForm (attribution sanction)
  - DisciplinaryRecord (dossier disciplinaire eleve)
- [ ] Tests

#### Livrables

- Suivi des presences/absences/retards
- Gestion disciplinaire complete
- Notifications parents automatiques

---

### Sprint 11 : Emplois du Temps

**Objectif** : Gerer les salles et les creneaux horaires

#### Backend

- [ ] Migrations tenant : 2 tables
  - `rooms` (salles de classe avec capacite)
  - `timetable_slots` (creneaux horaires)
- [ ] Detection automatique des conflits (salle, enseignant, classe)
- [ ] Controllers (RoomController, TimetableController)
- [ ] Form Requests (validation)
- [ ] API Resources
- [ ] Tests (80%+ couverture)

#### Frontend

- [ ] Composants admin :
  - TimetableGrid (grille emploi du temps editable)
  - RoomManager (gestion des salles)
- [ ] Composants enseignant/eleve :
  - MyTimetable (consultation emploi du temps personnel)
- [ ] Tests

#### Livrables

- Emploi du temps par classe, enseignant, salle
- Detection conflits en temps reel
- Consultation par enseignant et eleve

---

### Sprint 12 : Portail Parent

**Objectif** : Fournir un acces parent agrege a toutes les donnees de l'enfant

#### Backend

- [ ] Aucune table propre : couche d'agregation sur les modules existants
- [ ] ParentDashboardService (agregation donnees enfant)
- [ ] Controllers (ParentPortalController)
- [ ] API Resources
- [ ] Tests (80%+ couverture)

#### Frontend

- [ ] Composants parent :
  - ParentDashboard (vue d'ensemble enfant)
  - ChildGrades (notes de l'enfant)
  - ChildAbsences (absences de l'enfant)
  - ChildDiscipline (dossier disciplinaire)
  - ChildReportCards (bulletins de l'enfant)
  - ChildFees (frais et paiements)
- [ ] Tests

#### Livrables

- Tableau de bord parent complet
- Acces en lecture aux notes, absences, discipline, bulletins, frais

---

## Phase 3 : Gestion Financiere (2 mois)

### Sprint 13 : Comptabilite

**Objectif** : Gerer les frais de scolarite, paiements et depenses

#### Backend

- [ ] Migrations tenant : 5 tables
  - `fee_types` (types de frais : scolarite, inscription, examen, etc.)
  - `student_fees` (frais assignes aux eleves)
  - `payments` (paiements recus)
  - `expenses` (depenses de l'etablissement)
  - `payment_schedules` (echeanciers de paiement)
- [ ] Gestion des frais par type et par classe
- [ ] Enregistrement paiements + generation recus PDF
- [ ] Echeanciers de paiement
- [ ] Controllers (FeeController, PaymentController, ExpenseController)
- [ ] Form Requests (validation)
- [ ] API Resources
- [ ] Tests (80%+ couverture)

#### Frontend

- [ ] Composants admin :
  - FeeManager (configuration frais par classe/niveau)
  - PaymentForm (enregistrement paiement)
  - FinancialDashboard (tableau de bord financier avec graphiques)
- [ ] Composants eleve/parent :
  - MyFees (mes frais et paiements)
- [ ] Tests

#### Livrables

- Gestion complete des frais de scolarite
- Enregistrement paiements avec recus PDF
- Suivi depenses
- Tableau de bord financier

---

### Sprint 14 : Paie Personnel

**Objectif** : Gerer les contrats et la paie du personnel

#### Backend

- [ ] Migrations tenant : 2 tables
  - `staff_contracts` (contrats personnel : type, salaire de base, indemnites)
  - `payroll_records` (fiches de paie mensuelles)
- [ ] Calcul automatique paie (salaire de base + indemnites - retenues)
- [ ] Generation bulletins de paie PDF
- [ ] Controllers (ContractController, PayrollController)
- [ ] Form Requests (validation)
- [ ] API Resources
- [ ] Tests (80%+ couverture)

#### Frontend

- [ ] Composants admin :
  - ContractManager (gestion des contrats personnel)
  - PayrollForm (generation paie mensuelle)
- [ ] Composants personnel :
  - MyPayroll (consultation mes bulletins de paie)
- [ ] Tests

#### Livrables

- Gestion contrats personnel
- Calcul et generation paie mensuelle
- Bulletins de paie PDF

---

## Installation et Configuration Initiale

### 1. Backend - Nouvelles Dependances

```bash
cd C:\laragon\www\lycee

# Generation PDF
composer require barryvdh/laravel-dompdf

# Export Excel/CSV
composer require maatwebsite/excel

# Queue Redis
composer require predis/predis

# Publier configs
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"
```

### 2. Frontend - Nouvelles Dependances

```bash
# Date Picker
npm install @mui/x-date-pickers dayjs

# Data Grid Avance
npm install @mui/x-data-grid

# Validation Formulaires
npm install react-hook-form zod
```

### 3. Configuration Backend

**Fichier .env** :
```env
# PDF Generation
PDF_ENABLE_REMOTE=true
PDF_ENABLE_PHP=true

# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache
CACHE_STORE=redis
```

### 4. Lancement Queue Worker

```bash
php artisan queue:work redis --queue=high,default,low
```

---

## Workflow de Developpement d'un Module

### 1. Backend (Laravel)

```bash
# Creer module
php artisan module:make {ModuleName}

# Creer migration tenant
php artisan module:make-migration create_{table}_table {ModuleName} --tenant

# Creer Model
php artisan module:make-model {Entity} {ModuleName}

# Creer Controller
php artisan module:make-controller Admin/{Entity}Controller {ModuleName}

# Creer Form Request
php artisan module:make-request Store{Entity}Request {ModuleName}

# Creer Resource
php artisan module:make-resource {Entity}Resource {ModuleName}

# Creer Factory
php artisan module:make-factory {Entity}Factory {ModuleName}

# Creer Test
php artisan module:make-test Feature/{Entity}ControllerTest {ModuleName}

# Executer migrations tenant
php artisan tenants:migrate

# Lancer tests
php artisan test --filter={Entity}ControllerTest
```

### 2. Frontend (Next.js)

```bash
# Structure a creer manuellement
src/modules/{ModuleName}/
-- index.ts
-- admin/
|   -- components/
|   -- hooks/
|   -- services/
-- frontend/
|   -- components/
|   -- hooks/
|   -- services/
-- types/
```

---

## Criteres de Reussite

### Fonctionnels

- 12 modules completement fonctionnels
- Workflow complet : Inscription -> Notes -> Conseil de Classe -> Bulletins -> Paiements
- Generation bulletins < 5 minutes pour 60 eleves
- Multi-tenant teste avec 2+ etablissements
- Import CSV fonctionnel (100+ eleves)

### Techniques

- Couverture tests >= 80% (backend)
- Couverture tests >= 70% (frontend)
- Aucun warning Laravel Pint
- Aucune erreur TypeScript
- Performance : Liste 1000+ eleves < 2s
- Performance : Generation PDF bulletin < 10s

### Qualite

- Respect strict des standards de codage
- Documentation API complete
- Logs securite actifs
- Isolation tenant verifiee
- HTTPS + Rate limiting en production

---

## Checklist de Mise en Production

### Infrastructure

- [ ] Serveur Linux (Ubuntu 22.04+)
- [ ] PHP 8.3.26
- [ ] MySQL 8.0+
- [ ] Redis 6.0+
- [ ] Node.js 18+
- [ ] Nginx configure
- [ ] SSL/TLS (Let's Encrypt)

### Backend

- [ ] `.env` production configure
- [ ] Migrations executees
- [ ] Seeders executes (donnees initiales Niger : cycles, niveaux, series, matieres, coefficients)
- [ ] Queues configurees (Supervisor)
- [ ] Cron Laravel configure
- [ ] Logs rotatifs configures
- [ ] Backups automatiques (quotidiens)

### Frontend

- [ ] Build production (`npm run build`)
- [ ] PM2 configure pour Next.js
- [ ] Variables d'environnement production

### Securite

- [ ] HTTPS active
- [ ] Rate limiting configure
- [ ] Firewall configure
- [ ] Tokens Sanctum avec expiration
- [ ] Backups chiffres

### Monitoring

- [ ] Laravel Horizon (queues)
- [ ] Logs centralises
- [ ] Alertes erreurs critiques
- [ ] Monitoring performances

---

## References

### Documentation Existante

- **Architecture Brownfield** : `docs/brownfield-architecture.md`
- **Brief Projet** : `docs/brief.md`
- **Documentation Modules** : `DOCUMENTATION_MODULES.md`

### PRD par Module

- [ ] `module-structure-academique.md`
- [ ] `module-inscriptions.md`
- [ ] `module-notes-evaluations.md`
- [ ] `module-conseil-de-classe.md`
- [ ] `module-documents-officiels.md`
- [ ] `module-presences-absences.md`
- [ ] `module-discipline.md`
- [ ] `module-emplois-du-temps.md`
- [ ] `module-portail-parent.md`
- [ ] `module-comptabilite.md`
- [ ] `module-paie-personnel.md`

### References Techniques

**Backend** :
- Laravel 12 Documentation : https://laravel.com/docs/12.x
- nwidart/laravel-modules : https://nwidart.com/laravel-modules
- stancl/tenancy : https://tenancyforlaravel.com
- Spatie Permission : https://spatie.be/docs/laravel-permission
- DomPDF : https://github.com/barryvdh/laravel-dompdf
- Laravel Excel : https://docs.laravel-excel.com

**Frontend** :
- Next.js 15 : https://nextjs.org/docs
- Material-UI : https://mui.com/material-ui
- TypeScript : https://www.typescriptlang.org/docs
- React Hook Form : https://react-hook-form.com
- React Query : https://tanstack.com/query/latest

---

## Conclusion

Ce plan de developpement fournit une feuille de route complete pour la construction d'un systeme de gestion scolaire adapte aux colleges et lycees du Niger. L'approche en 3 phases garantit :

- **Phase 1 (MVP Core)** : Un systeme fonctionnel de bout en bout, de l'inscription a la generation des bulletins
- **Phase 2 (Vie Scolaire)** : Un suivi complet de la vie quotidienne (presences, discipline, emplois du temps, portail parent)
- **Phase 3 (Finance)** : Une gestion financiere complete (frais, paiements, paie)

**Prochaine etape immediate** : Commencer le developpement du **Sprint 1-2 : Structure Academique** avec les 9 tables fondamentales et le seed des donnees Niger.

---

**Version** : 2.0
**Date** : 2026-03-16
**Contexte** : Systeme de gestion scolaire - Colleges et Lycees du Niger
**Statut** : Final pour implementation

---

[← Retour a l'index](./index.md)
