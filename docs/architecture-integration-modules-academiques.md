# Architecture d'Intégration des Modules Académiques - Système CRM Multi-Tenant

> **Document d'Architecture Brownfield**
>
> Intégration de 9 modules métier académiques dans le système CRM existant
>
> **Version** : 1.0
>
> **Date** : 2026-01-07
>
> **Statut** : Final pour implémentation
>
> **Auteur** : Winston - Architecte Système

---

## Table des Matières

1. [Introduction et Vue d'Ensemble](#1-introduction-et-vue-densemble)
2. [Analyse du Système Existant](#2-analyse-du-système-existant)
3. [Périmètre de l'Amélioration](#3-périmètre-de-lamélioration)
4. [Stratégie d'Intégration](#4-stratégie-dintégration)
5. [Tech Stack](#5-tech-stack)
6. [Modèles de Données](#6-modèles-de-données)
7. [Architecture des Composants](#7-architecture-des-composants)
8. [Standards de Codage](#8-standards-de-codage)
9. [Stratégie de Tests](#9-stratégie-de-tests)
10. [Plan de Développement](#10-plan-de-développement)
11. [Références](#11-références)

---

## 1. Introduction et Vue d'Ensemble

### 1.1 Objectif du Document

Ce document définit l'architecture d'intégration de **9 modules métier académiques** dans le système CRM multi-tenant existant. L'objectif est de transformer la base d'authentification actuelle (module **UsersGuard** uniquement) en une **plateforme complète de gestion scolaire** pour les établissements d'enseignement supérieur nigériens suivant le système LMD (Licence-Master-Doctorat).

### 1.2 Relation avec la Documentation Existante

Ce document complète la documentation brownfield existante (`docs/brownfield-architecture.md`) en définissant précisément comment les nouveaux modules s'intégreront avec :

- L'architecture multi-tenant déjà opérationnelle (stancl/tenancy)
- Le système d'authentification et permissions (UsersGuard + Spatie)
- Les patterns établis (API Resources, Form Requests, migrations tenant)
- La structure modulaire backend/frontend (Laravel Modules + Next.js)

### 1.3 Modules à Intégrer

**9 Modules Critiques pour MVP** :

| # | Module | Priorité | Dépendances |
|---|--------|----------|-------------|
| 1 | Structure Académique | 🔴 Critique | UsersGuard |
| 2 | Inscriptions | 🔴 Critique | Structure Académique |
| 3 | Notes & Évaluations | 🔴 Critique | Inscriptions, Structure |
| 4 | Emplois du Temps | 🟠 Haute | Structure, Inscriptions |
| 5 | Présences/Absences | 🟠 Haute | EDT, Inscriptions |
| 6 | Examens & Planning | 🟠 Haute | Structure, Inscriptions |
| 7 | Comptabilité Étudiants | 🟠 Haute | Inscriptions |
| 8 | Paie Personnel | 🟡 Moyenne | UsersGuard |
| 9 | Documents Officiels | 🔴 Critique | Notes, Comptabilité |

### 1.4 Contexte Business

**Problème Résolu** : Les établissements d'enseignement supérieur au Niger gèrent actuellement leurs opérations manuellement (papier, Excel), entraînant des pertes de temps, erreurs fréquentes, et difficultés à produire des documents officiels.

**Proposition de Valeur** :
- Numérisation complète du cycle académique (inscription → diplômes)
- Génération automatique de documents officiels (relevés, diplômes)
- Gestion financière intégrée (étudiants + personnel)
- Architecture multi-tenant (plusieurs établissements sur une instance)

---

## 2. Analyse du Système Existant

### 2.1 État Actuel

**Architecture Globale** :
- **Type** : Polyrepo (backend Laravel + frontend Next.js séparés)
- **Backend** : `C:\laragon\www\crm-api` (Laravel 12, PHP 8.3.26)
- **Frontend** : `C:\Users\Mounkaila\PhpstormProjects\icall26-front` (Next.js 15, React 18)
- **Base de données** : MySQL avec architecture multi-tenant
  - Base centrale : `mysql` (users superadmin, tenants, domains)
  - Bases tenant : `tenant_{id}` (isolation complète par établissement)

### 2.2 Module Opérationnel : UsersGuard ✅

**Fonctionnalités** :
- Authentification multi-niveaux (SuperAdmin central, Admin tenant, Frontend tenant)
- Multi-tenancy fonctionnel avec isolation BD complète
- Permissions Spatie au niveau tenant (rôles et permissions)
- API Sanctum pour authentification par tokens Bearer
- Structure backend/frontend complète et opérationnelle

**Patterns Architecturaux Établis** :

**Backend** (`Modules/UsersGuard/`) :
- `Entities/` : Models Eloquent avec `$connection` explicite (mysql/tenant)
- `Http/Controllers/{Admin|Frontend|Superadmin}/` : Controllers par niveau d'accès
- `Http/Requests/` : Form Requests pour validation (array syntax)
- `Http/Resources/` : API Resources (jamais de models bruts en réponse)
- `Database/Migrations/` : Migrations centrales
- `Database/Migrations/tenant/` : Migrations tenant
- `Routes/{admin|frontend|superadmin}.php` : Routes par niveau avec middleware appropriés

**Frontend** (`src/modules/UsersGuard/`) :
- Structure miroir du backend
- 3 couches : `admin/`, `superadmin/`, `frontend/`
- Chaque couche : `components/`, `hooks/`, `services/`
- `types/` : TypeScript interfaces
- `index.ts` : Barrel exports pour API publique du module

### 2.3 Stack Technique Confirmée

**Backend** :
- Laravel 12 + nwidart/laravel-modules v12
- stancl/tenancy v3.9 (multi-tenant avec isolation BD)
- spatie/laravel-permission v6.24 (RBAC)
- laravel/sanctum v4.0 (auth API)

**Frontend** :
- Next.js 15 avec App Router
- React 18 + TypeScript 5.5
- Material-UI (MUI) 6.2
- Axios 1.13.2

### 2.4 Dépendances Entre Modules

```
UsersGuard (✅ Existant - Base d'authentification)
    ↓
Structure Académique (Fondation - DOIT être développé en premier)
    ↓
Inscriptions (Dépend de Structure)
    ↓
├─→ Notes & Évaluations
├─→ Emplois du Temps
├─→ Présences/Absences
├─→ Examens & Planning
├─→ Comptabilité Étudiants
└─→ Paie Personnel
    ↓
Documents Officiels (Dépend de Notes + Comptabilité)
```

### 2.5 Capacité Actuelle vs Besoins

**Forces** :
- ✅ Multi-tenancy opérationnel (peut supporter 15+ établissements)
- ✅ Authentification et permissions robustes
- ✅ Patterns de code bien établis et documentés
- ✅ Architecture modulaire scalable

**Gaps Identifiés** :
- ❌ Aucun module métier académique développé
- ❌ Génération de PDF non configurée (nécessite barryvdh/laravel-dompdf)
- ❌ Queues non configurées (nécessaire pour génération documents asynchrone)
- ❌ Cache Redis non configuré (recommandé pour performances)

---

## 3. Périmètre de l'Amélioration

### 3.1 Vue d'Ensemble

**Type d'Amélioration** : Extension fonctionnelle majeure (Major Feature Extension)

**Scope** : Intégration de 9 modules métier académiques transformant une base d'authentification en plateforme complète de gestion scolaire couvrant l'ensemble du cycle académique, administratif et financier.

**Niveau d'Impact** : **Élevé**

Cette amélioration :
- ✅ N'altère PAS l'architecture existante (respect des patterns établis)
- ✅ Étend significativement les capacités du système (×10 modules)
- ✅ Crée des dépendances entre nouveaux modules
- ✅ Nécessite des ajouts à la BD tenant (40+ nouvelles tables)
- ✅ Ajoute de nouveaux rôles utilisateurs (Professeur, Étudiant, Comptable)
- ✅ Introduit de nouvelles fonctionnalités transverses (génération PDF, queues)

### 3.2 Fonctionnalités Clés par Module

**Module 1 : Structure Académique**
- Facultés, Départements, Filières/Programmes
- Niveaux (L1-L3, M1-M2), Semestres, Années académiques
- Modules/UE avec crédits ECTS et coefficients
- Groupes TD/TP/CM
- Affectations enseignants ↔ modules

**Module 2 : Inscriptions**
- Inscription administrative (données personnelles, génération matricule)
- Inscription pédagogique (filière, niveau, modules, groupes)
- Import en masse via CSV avec prévisualisation
- Gestion des statuts (Actif, Suspendu, Exclu, Diplômé) avec historique

**Module 3 : Notes & Évaluations**
- Types d'évaluations (CC, TP, Projet, Examen, Rattrapage)
- Saisie de notes par enseignants (/20)
- Calcul automatique des moyennes (coefficients + crédits ECTS)
- Application règles de compensation LMD (paramétrable)
- Résultats finaux par module et par semestre

**Module 4 : Emplois du Temps**
- Création d'emplois du temps par groupe
- Séances avec jour, heure, salle, enseignant, module
- Détection automatique des conflits (enseignant/salle/groupe occupé)
- Consultation multi-rôles (Enseignant, Étudiant, Admin)

**Module 5 : Présences/Absences**
- Appel par séance (Présent, Absent, Retard, Excusé)
- Justificatifs d'absence avec upload de documents
- Historique par étudiant et par module
- Alertes si seuil d'absences dépassé

**Module 6 : Examens & Planning**
- Sessions d'examen (Normale, Rattrapage)
- Planning avec date, heure, module, salle, surveillants
- Détection de conflits (étudiant avec 2 examens simultanés)
- Calendrier d'examens pour étudiants et enseignants

**Module 7 : Comptabilité Étudiants**
- Paramétrage des types de frais (inscription, scolarité, etc.)
- Facturation automatique, statuts (Impayé, Partiellement payé, Payé)
- Enregistrement des paiements avec génération reçus PDF
- Tableau de bord financier (impayés, trésorerie)

**Module 8 : Paie Personnel**
- Fiches personnel avec type contrat (Permanent, Temporaire, Horaire)
- Calcul automatique de la paie (salaire fixe ou taux horaire)
- Génération bulletins de paie PDF
- États mensuels (masse salariale)

**Module 9 : Documents Officiels**
- Relevés de notes par semestre/année (template professionnel, PDF)
- Diplômes avec mentions légales, numérotation unique
- Attestations (inscription, scolarité, réussite)
- Génération en un clic avec historique

---

## 4. Stratégie d'Intégration

### 4.1 Approche Globale

**Principe** : **Extension modulaire isolée avec réutilisation stricte des patterns établis**

### 4.2 Intégration du Code

**Backend (Laravel)** :
- ✅ Création de 9 nouveaux modules dans `Modules/` suivant exactement la structure UsersGuard
- ✅ **Aucune modification du module UsersGuard** existant
- ✅ Réutilisation des services existants :
  - Middleware tenant (`tenant`, `tenant.auth`)
  - Configuration multi-tenant (stancl/tenancy)
  - Système de permissions Spatie
  - Guards Sanctum (superadmin, tenant)
- ✅ Ajout de services transverses partagés :
  - `App\Services\PdfGeneratorService` : Génération PDF
  - `Modules\Enrollment\Services\MatriculeGeneratorService` : Génération matricules uniques
  - `Modules\Grades\Services\GradeCalculatorService` : Calculs moyennes LMD

**Frontend (Next.js)** :
- ✅ Création de 9 nouveaux modules dans `src/modules/` avec structure miroir backend
- ✅ Réutilisation du client API existant : `createApiClient(tenantId)` avec header `X-Tenant-ID`
- ✅ Réutilisation des hooks existants : `useTenant()`, patterns d'authentification
- ✅ Extension des types TypeScript : Nouveaux types pour entités académiques
- ✅ Cohérence UI : Réutilisation des composants Material-UI établis

**Principes Clés** :
1. **Isolation** : Chaque nouveau module est autonome avec ses propres Entities, Controllers, Routes
2. **Non-régression** : UsersGuard reste 100% fonctionnel et inchangé
3. **Cohérence** : Respect strict des patterns (Form Requests, API Resources, Type hints)
4. **Découplage** : Communication inter-modules via Eloquent relationships uniquement

### 4.3 Intégration de la Base de Données

**Approche** : **Extension des bases tenant avec nouvelles tables, AUCUNE modification des tables existantes**

**Base Centrale (`mysql`)** : ❌ **AUCUNE MODIFICATION**
- Tables existantes préservées (`users`, `tenants`, `domains`)

**Bases Tenant (`tenant_{id}`)** : ✅ **AJOUT DE 40+ NOUVELLES TABLES**

**Stratégie de Migration** :
1. Migrations tenant séparées pour chaque module (`Modules/{Module}/Database/Migrations/tenant/`)
2. Exécution via `php artisan tenants:migrate` (applique à tous les tenants)
3. Rollback possible par module
4. SoftDeletes sur toutes les tables métier (audit trail)

**Intégrité Référentielle** :
- Foreign keys avec `ON DELETE CASCADE` ou `ON DELETE RESTRICT` selon logique métier
- Indexes sur colonnes fréquemment requêtées
- Contraintes d'unicité (matricules étudiants, codes modules, etc.)

### 4.4 Intégration API

**Approche** : **Extension de l'API REST existante avec nouveaux endpoints, respect des conventions**

**Convention URL** : `/api/{niveau}/{module}/{ressource}`

**Exemples de nouveaux endpoints** :

```
# Module Structure Académique
GET    /api/admin/faculties
POST   /api/admin/faculties
GET    /api/admin/modules              # Modules/UE
POST   /api/admin/modules

# Module Inscriptions
GET    /api/admin/students
POST   /api/admin/students              # Inscription administrative
POST   /api/admin/students/{id}/enroll  # Inscription pédagogique
POST   /api/admin/students/import       # Import CSV

# Module Notes (Professeur)
GET    /api/frontend/my-modules         # Modules assignés
POST   /api/frontend/grades             # Saisie notes

# Module Notes (Étudiant)
GET    /api/frontend/my-grades          # Notes de l'étudiant
GET    /api/frontend/my-transcripts     # Relevés de notes
```

**Authentification** :
- ✅ Réutilisation du système Sanctum existant
- ✅ Middleware `['tenant', 'tenant.auth']` sur toutes les routes protégées
- ✅ Header `X-Tenant-ID` obligatoire pour routes tenant

**Format des Réponses** :
- Success : `{ "message": "...", "data": {...} }` (201 pour création)
- Liste : Pagination Laravel avec `data`, `links`, `meta`
- Erreur validation : `{ "message": "...", "errors": {...} }` (422)
- Erreur auth : 401 (non authentifié), 403 (permissions insuffisantes)

### 4.5 Intégration UI

**Approche** : **Extension de l'interface avec nouveaux modules frontend, cohérence stricte avec l'existant**

**Composants UI Réutilisés** :
- ✅ Layout principal (Header, Sidebar, Footer)
- ✅ Composants Material-UI (Tables, Forms, Modals, Buttons)
- ✅ Patterns de formulaires (validation inline, messages d'erreur)

**Nouveaux Composants UI** :
- `AcademicTreeView` : Vue arborescente de la structure académique
- `TimetableGrid` : Grille hebdomadaire pour emplois du temps
- `AttendanceSheet` : Feuille d'appel interactive
- `GradeInputTable` : Table de saisie de notes avec calculs automatiques
- `StudentCard` : Carte étudiant avec photo
- `CsvImportWizard` : Wizard multi-étapes pour import CSV

**Cohérence Visuelle** :
- ✅ Même palette de couleurs (Bleu primaire, Vert succès, Orange warning, Rouge erreur)
- ✅ Même typographie et espacements (Material-UI theme)
- ✅ Responsive design maintenu (mobile, tablette, desktop)

### 4.6 Exigences de Compatibilité

**Garanties** :

1. **Compatibilité API** : ✅ Aucune modification des endpoints UsersGuard existants
2. **Compatibilité BD** : ✅ Aucune modification des tables existantes
3. **Cohérence UI/UX** : ✅ Même expérience utilisateur pour nouveaux modules
4. **Impact Performance** : ⚠️ Impact modéré acceptable avec mitigations :
   - Indexes optimaux, eager loading, pagination stricte
   - Queues asynchrones pour génération PDF
   - Cache Redis pour rapports fréquents

---

## 5. Tech Stack

### 5.1 Stack Existante (Opérationnelle) ✅

**Backend - Laravel 12** :

| Package | Version | Usage | Statut |
|---------|---------|-------|--------|
| `laravel/framework` | v12.0 | Framework principal | ✅ Opérationnel |
| `nwidart/laravel-modules` | v12.0 | Architecture modulaire | ✅ Opérationnel |
| `stancl/tenancy` | v3.9 | Multi-tenancy isolation BD | ✅ Opérationnel |
| `spatie/laravel-permission` | v6.24 | Rôles & permissions | ✅ Opérationnel |
| `laravel/sanctum` | v4.0 | Auth API tokens | ✅ Opérationnel |

**Frontend - Next.js 15** :

| Package | Version | Usage | Statut |
|---------|---------|-------|--------|
| `next` | 15.1.2 | Framework React SSR | ✅ Opérationnel |
| `react` | 18.3.1 | Library UI | ✅ Opérationnel |
| `typescript` | 5.5.4 | Typage statique | ✅ Opérationnel |
| `@mui/material` | 6.2.1 | Composants UI | ✅ Opérationnel |
| `axios` | 1.13.2 | Client HTTP | ✅ Opérationnel |

### 5.2 Nouvelles Dépendances Requises 🆕

**Backend** :

```bash
# Génération PDF (CRITIQUE)
composer require barryvdh/laravel-dompdf

# Export Excel/CSV (HAUTE PRIORITÉ)
composer require maatwebsite/excel

# Manipulation Images (MOYENNE PRIORITÉ)
composer require intervention/image

# Queue Redis (RECOMMANDÉ)
composer require predis/predis
```

**Frontend** :

```bash
# Date Picker (HAUTE PRIORITÉ)
npm install @mui/x-date-pickers dayjs

# Data Grid avancé (HAUTE PRIORITÉ)
npm install @mui/x-data-grid

# Validation formulaires (MOYENNE PRIORITÉ)
npm install react-hook-form zod
```

---

## 6. Modèles de Données

### 6.1 Module 1 : Structure Académique

**Tables (10 tables)** :

**1. `faculties` (Facultés)** :
```sql
CREATE TABLE faculties (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
```

**2. `departments` (Départements)** :
```sql
CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    faculty_id BIGINT UNSIGNED NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE CASCADE
);
```

**3. `programs` (Filières)** :
```sql
CREATE TABLE programs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL,
    duration_years TINYINT UNSIGNED NOT NULL COMMENT '3=Licence, 2=Master',
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);
```

**4. `program_levels` (Niveaux disponibles par filière)** :
```sql
CREATE TABLE program_levels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id BIGINT UNSIGNED NOT NULL,
    level ENUM('L1','L2','L3','M1','M2') NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_program_level (program_id, level)
);
```

**5. `academic_years` (Années académiques)** :
```sql
CREATE TABLE academic_years (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT 'ex: 2025-2026',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE COMMENT 'Une seule active à la fois',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**6. `semesters` (Semestres)** :
```sql
CREATE TABLE semesters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    name ENUM('S1','S2') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
);
```

**7. `modules` (Unités d'Enseignement)** :
```sql
CREATE TABLE modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL COMMENT 'ex: INF101',
    name VARCHAR(255) NOT NULL,
    credits_ects TINYINT UNSIGNED NOT NULL COMMENT '2-6',
    coefficient DECIMAL(3,1) NOT NULL COMMENT 'Pour moyennes',
    type ENUM('Obligatoire','Optionnel') NOT NULL,
    semester ENUM('S1','S2','S3','S4','S5','S6','S7','S8','S9','S10') NOT NULL,
    level ENUM('L1','L2','L3','M1','M2') NOT NULL,
    is_eliminatory BOOLEAN DEFAULT FALSE COMMENT 'Pas de compensation',
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
```

**8. `module_programs` (Pivot Module ↔ Filière)** :
```sql
CREATE TABLE module_programs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_module_program (module_id, program_id)
);
```

**9. `groups` (Groupes TD/TP)** :
```sql
CREATE TABLE groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT 'ex: TD1, TP A',
    type ENUM('TD','TP','CM') NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    level ENUM('L1','L2','L3','M1','M2') NOT NULL,
    max_capacity TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
);
```

**10. `teacher_module_assignments` (Affectations Enseignant ↔ Module)** :
```sql
CREATE TABLE teacher_module_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id BIGINT UNSIGNED NOT NULL COMMENT 'FK -> users.id',
    module_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    level ENUM('L1','L2','L3','M1','M2') NOT NULL,
    group_id BIGINT UNSIGNED NULL COMMENT 'Groupe spécifique optionnel',
    semester_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (teacher_id, module_id, program_id, level, group_id, semester_id)
);
```

### 6.2 Module 2 : Inscriptions (Enrollment)

**Tables (4 tables)** :

**1. `students` (Étudiants)** :
```sql
CREATE TABLE students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricule VARCHAR(50) UNIQUE NOT NULL COMMENT 'Auto-généré',
    firstname VARCHAR(255) NOT NULL,
    lastname VARCHAR(255) NOT NULL,
    birthdate DATE NOT NULL,
    birthplace VARCHAR(255) NULL,
    sex ENUM('M','F','O') NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NULL,
    mobile VARCHAR(20) NULL,
    address TEXT NULL,
    city VARCHAR(255) NULL,
    country VARCHAR(255) DEFAULT 'Niger',
    photo VARCHAR(255) NULL COMMENT 'URL photo',
    status ENUM('Actif','Suspendu','Exclu','Diplômé') DEFAULT 'Actif',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_matricule (matricule),
    INDEX idx_email (email),
    INDEX idx_status (status)
);
```

**2. `student_enrollments` (Inscriptions Pédagogiques)** :
```sql
CREATE TABLE student_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    level ENUM('L1','L2','L3','M1','M2') NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED NULL,
    enrollment_date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL
);
```

**3. `student_module_enrollments` (Inscriptions aux Modules)** :
```sql
CREATE TABLE student_module_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    enrollment_date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE RESTRICT,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_enrollment (student_id, module_id, semester_id)
);
```

**4. `student_status_history` (Historique des Statuts)** :
```sql
CREATE TABLE student_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    old_status ENUM('Actif','Suspendu','Exclu','Diplômé') NOT NULL,
    new_status ENUM('Actif','Suspendu','Exclu','Diplômé') NOT NULL,
    changed_by BIGINT UNSIGNED NOT NULL COMMENT 'FK -> users.id',
    changed_at TIMESTAMP NOT NULL,
    comment TEXT NULL COMMENT 'Raison du changement',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT
);
```

### 6.3 Module 3 : Notes & Évaluations (Grades)

**Tables (4 tables)** :

**1. `evaluations` (Types d'Évaluation)** :
```sql
CREATE TABLE evaluations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    type ENUM('CC','TP','Projet','Examen','Rattrapage') NOT NULL,
    name VARCHAR(255) NOT NULL COMMENT 'ex: CC1, TP2',
    coefficient DECIMAL(3,1) NOT NULL COMMENT 'Pour moyenne module',
    max_score DECIMAL(4,2) DEFAULT 20.00,
    date DATE NULL,
    is_published BOOLEAN DEFAULT FALSE COMMENT 'Résultats publiés',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
);
```

**2. `grades` (Notes des Étudiants)** :
```sql
CREATE TABLE grades (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    evaluation_id BIGINT UNSIGNED NOT NULL,
    score DECIMAL(4,2) NOT NULL COMMENT 'Note /20',
    entered_by BIGINT UNSIGNED NOT NULL COMMENT 'Enseignant',
    entered_at TIMESTAMP NOT NULL,
    is_absent BOOLEAN DEFAULT FALSE COMMENT 'ABS',
    comment TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_grade (student_id, evaluation_id)
);
```

**3. `module_results` (Résultats Finaux par Module)** :
```sql
CREATE TABLE module_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    average DECIMAL(4,2) NOT NULL COMMENT 'Moyenne module /20',
    status ENUM('Validé','Non validé','Rattrapage','Compensé') NOT NULL,
    credits_acquired TINYINT UNSIGNED DEFAULT 0 COMMENT 'Crédits ECTS acquis',
    is_rattrapage BOOLEAN DEFAULT FALSE COMMENT 'Résultat après rattrapage',
    validated_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_result (student_id, module_id, semester_id, is_rattrapage)
);
```

**4. `semester_results` (Résultats par Semestre)** :
```sql
CREATE TABLE semester_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    average DECIMAL(4,2) NOT NULL COMMENT 'Moyenne semestre /20',
    total_credits TINYINT UNSIGNED NOT NULL COMMENT 'Total crédits ECTS acquis',
    status ENUM('Validé','Non validé','Rattrapage') NOT NULL,
    validated_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_semester_result (student_id, semester_id)
);
```

### 6.4 Module 4 : Emplois du Temps (Timetable)

**Tables (2 tables)** :

**1. `rooms` (Salles de Cours)** :
```sql
CREATE TABLE rooms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL COMMENT 'ex: A101',
    name VARCHAR(255) NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL COMMENT 'Nb places',
    type ENUM('CM','TD','TP','Labo') NOT NULL,
    building VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
```

**2. `timetable_slots` (Séances d'Emploi du Temps)** :
```sql
CREATE TABLE timetable_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    day_of_week ENUM('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    type ENUM('CM','TD','TP') NOT NULL,
    is_recurring BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    INDEX idx_conflict_detection (day_of_week, start_time, end_time),
    INDEX idx_teacher_timetable (teacher_id, semester_id),
    INDEX idx_group_timetable (group_id, semester_id)
);
```

### 6.5 Module 5 : Présences/Absences (Attendance)

**Tables (2 tables)** :

**1. `attendances` (Présence/Absence par Séance)** :
```sql
CREATE TABLE attendances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    timetable_slot_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL COMMENT 'Date de la séance',
    status ENUM('Présent','Absent','Retard','Excusé') NOT NULL,
    marked_by BIGINT UNSIGNED NOT NULL COMMENT 'Enseignant',
    marked_at TIMESTAMP NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (timetable_slot_id) REFERENCES timetable_slots(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_attendance (student_id, timetable_slot_id, date)
);
```

**2. `attendance_justifications` (Justificatifs d'Absence)** :
```sql
CREATE TABLE attendance_justifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_id BIGINT UNSIGNED NOT NULL,
    document_path VARCHAR(255) NOT NULL COMMENT 'Fichier justificatif',
    reason TEXT NOT NULL,
    submitted_at TIMESTAMP NOT NULL,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    reviewed_by BIGINT UNSIGNED NULL COMMENT 'Admin validateur',
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (attendance_id) REFERENCES attendances(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 6.6 Module 6 : Examens & Planning (Exams)

**Tables (3 tables)** :

**1. `exam_sessions` (Sessions d'Examen)** :
```sql
CREATE TABLE exam_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'ex: Session Normale S1',
    semester_id BIGINT UNSIGNED NOT NULL,
    type ENUM('Normale','Rattrapage') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
);
```

**2. `exam_schedules` (Planning d'Examen)** :
```sql
CREATE TABLE exam_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_session_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (exam_session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT
);
```

**3. `exam_supervisors` (Surveillants d'Examen)** :
```sql
CREATE TABLE exam_supervisors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_schedule_id BIGINT UNSIGNED NOT NULL,
    supervisor_id BIGINT UNSIGNED NOT NULL COMMENT 'FK -> users.id',
    role ENUM('Principal','Adjoint') DEFAULT 'Principal',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (exam_schedule_id) REFERENCES exam_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 6.7 Module 7 : Comptabilité Étudiants (Finance)

**Tables (4 tables)** :

**1. `fee_types` (Types de Frais)** :
```sql
CREATE TABLE fee_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL COMMENT 'ex: SCOL, INSC',
    name VARCHAR(255) NOT NULL COMMENT 'ex: Frais de scolarité',
    default_amount DECIMAL(10,2) NOT NULL,
    is_mandatory BOOLEAN DEFAULT TRUE,
    applies_to ENUM('All','L1','L2','L3','M1','M2') DEFAULT 'All',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
```

**2. `student_fees` (Frais Attribués aux Étudiants)** :
```sql
CREATE TABLE student_fees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    fee_type_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT 'Montant dû',
    amount_paid DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Montant payé',
    status ENUM('Unpaid','Partial','Paid') DEFAULT 'Unpaid',
    due_date DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_fee (student_id, fee_type_id, semester_id)
);
```

**3. `student_payments` (Paiements Étudiants)** :
```sql
CREATE TABLE student_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    student_fee_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','Bank','Mobile','Check') NOT NULL,
    reference VARCHAR(100) NULL COMMENT 'Référence paiement',
    received_by BIGINT UNSIGNED NOT NULL COMMENT 'Caissier',
    payment_date DATE NOT NULL,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE RESTRICT,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE RESTRICT
);
```

**4. `student_payment_receipts` (Reçus de Paiement)** :
```sql
CREATE TABLE student_payment_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_payment_id BIGINT UNSIGNED NOT NULL,
    receipt_pdf_path VARCHAR(255) NOT NULL,
    generated_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_payment_id) REFERENCES student_payments(id) ON DELETE CASCADE
);
```

### 6.8 Module 8 : Paie Personnel (Payroll)

**Tables (2 tables)** :

**1. `staff_contracts` (Contrats Personnel)** :
```sql
CREATE TABLE staff_contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'FK -> users.id',
    contract_type ENUM('Permanent','Temporary','Hourly') NOT NULL,
    position VARCHAR(255) NOT NULL COMMENT 'Enseignant, Admin, etc.',
    base_salary DECIMAL(10,2) NULL COMMENT 'Salaire fixe mensuel',
    hourly_rate DECIMAL(8,2) NULL COMMENT 'Taux horaire',
    start_date DATE NOT NULL,
    end_date DATE NULL COMMENT 'Si temporaire',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**2. `payroll_records` (Fiches de Paie)** :
```sql
CREATE TABLE payroll_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_contract_id BIGINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    period_year YEAR NOT NULL,
    hours_worked DECIMAL(6,2) NULL COMMENT 'Si horaire',
    gross_salary DECIMAL(10,2) NOT NULL,
    deductions DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Impôts, etc.',
    net_salary DECIMAL(10,2) NOT NULL,
    payment_date DATE NULL,
    status ENUM('Draft','Approved','Paid') DEFAULT 'Draft',
    bulletin_pdf_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (staff_contract_id) REFERENCES staff_contracts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payroll (staff_contract_id, period_month, period_year)
);
```

### 6.9 Module 9 : Documents Officiels (Documents)

**Tables (1 table)** :

**1. `generated_documents` (Historique Documents Générés)** :
```sql
CREATE TABLE generated_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NULL COMMENT 'Si doc étudiant',
    document_type ENUM('Transcript','Diploma','Certificate','Receipt','Payslip') NOT NULL,
    document_pdf_path VARCHAR(255) NOT NULL,
    generated_by BIGINT UNSIGNED NOT NULL COMMENT 'Utilisateur générateur',
    generated_at TIMESTAMP NOT NULL,
    reference_number VARCHAR(100) UNIQUE NULL COMMENT 'Numéro unique document',
    metadata JSON NULL COMMENT 'Métadonnées additionnelles',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE RESTRICT
);
```

---

## 7. Architecture des Composants

### 7.1 Architecture Backend (Laravel - Modulaire)

**Structure Standard d'un Module** :

```
Modules/{ModuleName}/
├── Config/
│   └── config.php                      # Config module
├── Console/
│   └── Commands/                       # Artisan commands spécifiques
├── Database/
│   ├── Factories/                      # Factories pour tests
│   ├── Migrations/                     # Migrations centrales (rare)
│   ├── Migrations/tenant/              # Migrations tenant (majorité)
│   │   └── 2025_01_xx_create_xxx_table.php
│   └── Seeders/
│       └── {Module}Seeder.php
├── Entities/                           # Models Eloquent
│   ├── {EntityName}.php
│   └── ...
├── Http/
│   ├── Controllers/
│   │   ├── Admin/                      # Controllers Admin (tenant)
│   │   │   └── {Resource}Controller.php
│   │   ├── Frontend/                   # Controllers Frontend (tenant)
│   │   │   └── {Resource}Controller.php
│   │   └── Superadmin/                 # Controllers Superadmin (central)
│   │       └── ...
│   ├── Middleware/                     # Middleware spécifiques (optionnel)
│   ├── Requests/                       # Form Requests
│   │   ├── Store{Entity}Request.php
│   │   ├── Update{Entity}Request.php
│   │   └── ...
│   └── Resources/                      # API Resources
│       ├── {Entity}Resource.php
│       └── ...
├── Providers/
│   ├── {Module}ServiceProvider.php     # Service Provider principal
│   └── RouteServiceProvider.php        # Enregistrement routes
├── Routes/
│   ├── admin.php                       # Routes admin tenant
│   ├── frontend.php                    # Routes frontend tenant
│   └── superadmin.php                  # Routes superadmin central
├── Services/                           # Services métier
│   └── {Service}Service.php
├── Tests/
│   ├── Feature/
│   └── Unit/
└── module.json                         # Métadonnées module
```

**Exemple Concret : Module Inscriptions** :

```
Modules/Enrollment/
├── Entities/
│   ├── Student.php
│   ├── StudentEnrollment.php
│   ├── StudentModuleEnrollment.php
│   └── StudentStatusHistory.php
├── Http/
│   ├── Controllers/Admin/
│   │   ├── StudentController.php
│   │   ├── EnrollmentController.php
│   │   └── StudentImportController.php
│   ├── Requests/
│   │   ├── StoreStudentRequest.php
│   │   ├── UpdateStudentRequest.php
│   │   ├── EnrollStudentRequest.php
│   │   └── ImportStudentsRequest.php
│   └── Resources/
│       ├── StudentResource.php
│       └── StudentEnrollmentResource.php
├── Services/
│   ├── MatriculeGeneratorService.php
│   ├── StudentImportService.php
│   └── GroupAssignmentService.php
└── Routes/
    └── admin.php
```

### 7.2 Architecture Frontend (Next.js - Modulaire)

**Structure Standard d'un Module** :

```
src/modules/{ModuleName}/
├── index.ts                            # Barrel export (API publique)
├── admin/                              # Couche Admin
│   ├── components/
│   │   ├── {Entity}List.tsx
│   │   ├── {Entity}ListTable.tsx
│   │   ├── {Entity}AddModal.tsx
│   │   ├── {Entity}EditModal.tsx
│   │   └── ...
│   ├── hooks/
│   │   ├── use{Entities}.ts
│   │   ├── use{Entity}Mutations.ts
│   │   └── ...
│   ├── services/
│   │   └── {entity}Service.ts
│   └── utils/
│       └── ...
├── superadmin/                         # Couche Superadmin (optionnel)
│   └── ...
├── frontend/                           # Couche Frontend (Étudiant/Prof)
│   ├── components/
│   ├── hooks/
│   └── services/
├── types/                              # Types TypeScript partagés
│   └── {entity}.types.ts
└── translations/                       # i18n (optionnel)
    └── fr.json
```

**Exemple Concret : Module Inscriptions** :

```
src/modules/Enrollment/
├── index.ts
├── admin/
│   ├── components/
│   │   ├── StudentList.tsx
│   │   ├── StudentListTable.tsx
│   │   ├── StudentAddModal.tsx
│   │   ├── StudentEnrollmentForm.tsx
│   │   ├── StudentImportWizard.tsx
│   │   └── StudentCard.tsx
│   ├── hooks/
│   │   ├── useStudents.ts
│   │   ├── useStudentMutations.ts
│   │   └── useStudentImport.ts
│   └── services/
│       └── studentService.ts
├── frontend/
│   ├── components/
│   │   └── MyStudentProfile.tsx
│   ├── hooks/
│   │   └── useMyProfile.ts
│   └── services/
│       └── studentProfileService.ts
└── types/
    ├── student.types.ts
    └── enrollment.types.ts
```

### 7.3 Services Transverses (Partagés)

**1. PDF Generation Service** :

```php
// App\Services\PdfGeneratorService.php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfGeneratorService
{
    public function generateTranscript(Student $student, Semester $semester): string
    {
        $data = [
            'student' => $student,
            'semester' => $semester,
            'results' => $student->moduleResults()
                ->where('semester_id', $semester->id)
                ->with('module')
                ->get(),
        ];

        $pdf = Pdf::loadView('documents.transcript', $data);

        $filename = "transcript_{$student->matricule}_{$semester->name}.pdf";
        $path = "documents/transcripts/{$filename}";

        Storage::disk('tenant')->put($path, $pdf->output());

        return $path;
    }

    public function generateDiploma(Student $student): string
    {
        // Similar logic
    }

    public function generateReceipt(StudentPayment $payment): string
    {
        // Similar logic
    }
}
```

**Usage dans Job Asynchrone** :

```php
// Modules\Documents\Jobs\GenerateTranscriptJob.php

namespace Modules\Documents\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\PdfGeneratorService;

class GenerateTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public Semester $semester
    ) {}

    public function handle(PdfGeneratorService $pdfService): void
    {
        $path = $pdfService->generateTranscript($this->student, $this->semester);

        // Save to generated_documents table
        GeneratedDocument::create([
            'student_id' => $this->student->id,
            'document_type' => 'Transcript',
            'document_pdf_path' => $path,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);

        // Optionally notify user
    }
}
```

**2. Grade Calculator Service** :

```php
// Modules\Grades\Services\GradeCalculatorService.php

namespace Modules\Grades\Services;

class GradeCalculatorService
{
    public function calculateModuleAverage(
        Student $student,
        Module $module,
        Semester $semester
    ): float {
        $grades = Grade::where('student_id', $student->id)
            ->whereHas('evaluation', fn($q) =>
                $q->where('module_id', $module->id)
                  ->where('semester_id', $semester->id)
            )
            ->with('evaluation')
            ->get();

        $totalScore = 0;
        $totalCoefficient = 0;

        foreach ($grades as $grade) {
            $totalScore += $grade->score * $grade->evaluation->coefficient;
            $totalCoefficient += $grade->evaluation->coefficient;
        }

        return $totalCoefficient > 0 ? $totalScore / $totalCoefficient : 0;
    }

    public function calculateSemesterAverage(
        Student $student,
        Semester $semester
    ): float {
        $moduleResults = ModuleResult::where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->with('module')
            ->get();

        $totalScore = 0;
        $totalCredits = 0;

        foreach ($moduleResults as $result) {
            $totalScore += $result->average * $result->module->credits_ects;
            $totalCredits += $result->module->credits_ects;
        }

        return $totalCredits > 0 ? $totalScore / $totalCredits : 0;
    }

    public function applyCompensationRules(
        Student $student,
        Semester $semester
    ): void {
        // Logique de compensation LMD selon configuration tenant
        // À implémenter selon règles spécifiques
    }
}
```

---

## 8. Standards de Codage

### 8.1 Backend - Laravel

**Conventions de Nommage** :

| Élément | Convention | Exemple |
|---------|-----------|---------|
| Module | PascalCase | `AcademicStructure` |
| Model | PascalCase singular | `Student` |
| Table | snake_case plural | `students` |
| Controller | PascalCase + Controller | `StudentController` |
| Request | PascalCase + Request | `StoreStudentRequest` |
| Resource | PascalCase + Resource | `StudentResource` |
| Service | PascalCase + Service | `PdfGeneratorService` |
| Method | camelCase | `calculateAverage()` |
| Variable | camelCase | `$studentEnrollment` |

**Template Model** :

```php
<?php

namespace Modules\{Module}\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';  // TOUJOURS déclarer
    protected $table = 'students';

    protected $fillable = [
        'matricule', 'firstname', 'lastname',
        'birthdate', 'email', 'status',
    ];

    // Laravel 12 : casts() method
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // Relations avec type hints
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
```

**Template Controller** :

```php
<?php

namespace Modules\{Module}\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class {Entity}Controller extends Controller
{
    public function index(Request $request)
    {
        $entities = {Entity}::query()
            ->when($request->search, fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
            )
            ->with(['relations'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return {Entity}Resource::collection($entities);
    }

    public function store(Store{Entity}Request $request): JsonResponse
    {
        $entity = {Entity}::create($request->validated());

        return response()->json([
            'message' => 'Créé avec succès.',
            'data' => new {Entity}Resource($entity),
        ], 201);
    }
}
```

### 8.2 Frontend - Next.js/React

**Template Composant** :

```typescript
'use client';

import React, { useState } from 'react';
import { Box } from '@mui/material';

interface StudentListProps {
  tenantId?: string;
}

export const StudentList: React.FC<StudentListProps> = ({ tenantId }) => {
  const [students, setStudents] = useState([]);

  return (
    <Box>
      {/* Component JSX */}
    </Box>
  );
};
```

**Template Service** :

```typescript
import { createApiClient } from '@/lib/api/apiClient';

class StudentService {
  async getStudents(tenantId?: string) {
    const client = createApiClient(tenantId);
    const response = await client.get('/admin/students');
    return response.data;
  }
}

export const studentService = new StudentService();
```

---

## 9. Stratégie de Tests

### 9.1 Tests Backend (PHPUnit)

**Types de Tests** :

**A. Tests Unitaires** :
```php
class GradeCalculatorServiceTest extends TestCase
{
    #[Test]
    public function it_calculates_module_average_correctly()
    {
        // Arrange
        $student = Student::factory()->create();
        $module = Module::factory()->create();

        // Act
        $average = $this->service->calculateModuleAverage($student, $module, $semester);

        // Assert
        $this->assertEquals(13.33, round($average, 2));
    }
}
```

**B. Tests Feature** :
```php
class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_create_student()
    {
        $admin = TenantUser::factory()->create(['application' => 'admin']);
        $this->actingAs($admin, 'tenant');

        $response = $this->postJson('/api/admin/students', [
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'email' => 'jean@example.com',
            'birthdate' => '2005-03-15',
            'sex' => 'M',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('students', ['email' => 'jean@example.com']);
    }
}
```

**Commandes** :
```bash
php artisan test
php artisan test --filter=StudentControllerTest
php artisan test --coverage
```

### 9.2 Tests Frontend (Jest)

```typescript
describe('StudentList', () => {
  it('renders students correctly', async () => {
    const mockStudents = [
      { id: 1, firstname: 'Jean', lastname: 'Dupont' }
    ];

    (studentService.getStudents as jest.Mock).mockResolvedValue({
      data: mockStudents
    });

    render(<StudentList />);

    await waitFor(() => {
      expect(screen.getByText('Jean Dupont')).toBeInTheDocument();
    });
  });
});
```

### 9.3 Couverture de Tests Cibles

| Composant | Couverture | Priorité |
|-----------|------------|----------|
| Services métier | 90%+ | 🔴 Critique |
| Controllers API | 80%+ | 🔴 Critique |
| Models | 70%+ | 🟠 Haute |
| Composants UI | 70%+ | 🟠 Haute |

---

## 10. Plan de Développement

### 10.1 Phase 1 : Fondations (Semaines 1-2)

**Module 1 : Structure Académique** (Priorité 1 - Dépendance critique)
- Migrations tenant : 10 tables
- Models + Relations
- Controllers CRUD complets
- Tests unitaires + feature
- Frontend : Composants admin

**Rationale** : Tous les autres modules dépendent de la structure académique.

### 10.2 Phase 2 : Inscriptions (Semaines 3-4)

**Module 2 : Inscriptions** (Priorité 2)
- Migrations tenant : 4 tables
- Service génération matricules
- Import CSV avec prévisualisation
- Affectation groupes automatique
- Frontend : Wizard inscription, import CSV

**Rationale** : Sans étudiants inscrits, impossible de tester les modules suivants.

### 10.3 Phase 3 : Modules Parallèles (Semaines 5-8)

**Développement parallèle possible** :

**Développeur 1** :
- Module 3 : Notes & Évaluations
- Module 9 : Documents Officiels (dépend de Notes)

**Développeur 2** :
- Module 4 : Emplois du Temps
- Module 5 : Présences/Absences (dépend de EDT)

**Développeur 3** :
- Module 6 : Examens
- Module 7 : Comptabilité Étudiants

### 10.4 Phase 4 : Paie & Finalisation (Semaines 9-10)

- Module 8 : Paie Personnel
- Intégration & Tests End-to-End
- Workflow complet : Inscription → Notes → Relevés → Paiements
- Tests de charge (1000+ étudiants)
- Optimisation performances

---

## 11. Références

### 11.1 Documentation Existante

- **Architecture Brownfield** : `docs/brownfield-architecture.md`
- **Brief Projet** : `docs/brief.md`
- **Documentation Modules** : `DOCUMENTATION_MODULES.md`

### 11.2 PRD par Module

- `docs/prd/module-structure-academique.md`
- `docs/prd/module-inscriptions.md`
- `docs/prd/module-notes-evaluations.md`
- `docs/prd/module-emplois-du-temps.md`
- `docs/prd/module-presences-absences.md`
- `docs/prd/module-examens-planning.md`
- `docs/prd/module-comptabilite-etudiants.md`
- `docs/prd/module-paie-personnel.md`
- `docs/prd/module-documents-officiels.md`

### 11.3 Références Techniques

**Backend** :
- Laravel 12 Documentation : https://laravel.com/docs/12.x
- nwidart/laravel-modules : https://nwidart.com/laravel-modules
- stancl/tenancy : https://tenancyforlaravel.com
- Spatie Permission : https://spatie.be/docs/laravel-permission

**Frontend** :
- Next.js 15 : https://nextjs.org/docs
- Material-UI : https://mui.com/material-ui
- TypeScript : https://www.typescriptlang.org/docs

---

## Conclusion

Ce document d'architecture brownfield fournit une feuille de route complète pour l'intégration de 9 modules académiques dans le système CRM multi-tenant existant. L'approche par extension modulaire isolée garantit :

✅ **Aucune modification de l'existant** (UsersGuard préservé)
✅ **Respect strict des patterns établis** (cohérence architecturale)
✅ **Développement progressif** (module par module, rollback possible)
✅ **Compatibilité totale** (API, BD, UI/UX)
✅ **Scalabilité garantie** (multi-tenant éprouvé)

**Prochaines étapes recommandées** :
1. Validation de ce document par l'équipe technique
2. Mise en place de l'environnement de développement
3. Installation des nouvelles dépendances (DomPDF, Excel, etc.)
4. Développement du Module 1 (Structure Académique) selon ce plan

---

**Version** : 1.0
**Date** : 2026-01-07
**Auteur** : Winston - Architecte Système
**Statut** : Final pour implémentation
