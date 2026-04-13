# Backlog Priorisé - Phase 1 MVP Core

> **Projet** : Gestion Scolaire - Système LMD Multi-Tenant
> **Phase** : Phase 1 MVP Core (4 mois)
> **Date** : 2026-01-07
> **Objectif** : Cycle académique minimal fonctionnel - De l'inscription à la génération de relevés de notes

---

## Table des Matières

1. [Vue d'ensemble Phase 1](#1-vue-densemble-phase-1)
2. [Modules de la Phase 1](#2-modules-de-la-phase-1)
3. [Backlog Priorisé](#3-backlog-priorisé)
4. [Graphe de Dépendances](#4-graphe-de-dépendances)
5. [Estimation de Charge](#5-estimation-de-charge)

---

## 1. Vue d'ensemble Phase 1

### 1.1 Objectif

**Permettre à un établissement d'enseignement supérieur de** :
1. Définir sa structure académique (facultés, filières, niveaux, modules)
2. Inscrire des étudiants (administratif + pédagogique)
3. Saisir les notes et calculer automatiquement les moyennes avec règles LMD
4. Générer automatiquement des relevés de notes et attestations en PDF

**Valeur perçue** : ⭐⭐⭐⭐⭐ (résout le pain point #1 : génération automatique de documents officiels avec zéro erreur)

### 1.2 Critères de Succès Phase 1

- ✅ Un établissement pilote peut inscrire 100+ étudiants en moins de 2 heures (vs 1 semaine manuellement)
- ✅ Les enseignants peuvent saisir les notes de 500+ étudiants avec calcul automatique des moyennes
- ✅ Génération d'un relevé de notes en < 2 minutes (vs 30-60 minutes manuellement)
- ✅ Taux d'erreurs dans les documents officiels < 1% (vs 15-20% manuellement)
- ✅ Démo fonctionnelle aux 2 établissements pilotes à la fin du mois 4

### 1.3 Modules Inclus

| # | Module | Status | Priorité | Charge Estimée |
|---|--------|--------|----------|----------------|
| 1 | **UsersGuard** | ✅ Existant | N/A | 0 semaines |
| 2 | **Structure Académique** | 🆕 À développer | 🔴 CRITIQUE | 5 semaines |
| 3 | **Inscriptions** | 🆕 À développer | 🔴 CRITIQUE | 4 semaines |
| 4 | **Notes & Évaluations** | 🆕 À développer | 🔴 CRITIQUE | 7 semaines |
| 5 | **Documents Officiels** | 🆕 À développer | 🔴 CRITIQUE | 5 semaines |
| **TOTAL** | | | | **21 semaines** |

**Note** : Avec développement parallèle (1 backend + 1 frontend) et intégrations, estimation réaliste = **16 semaines (4 mois)**.

---

## 2. Modules de la Phase 1

### 2.1 Module 1 : UsersGuard ✅ EXISTANT

**Statut** : Production-ready, aucun développement requis pour Phase 1.

**Fonctionnalités disponibles** :
- Authentification multi-niveau (SuperAdmin, Admin, Frontend)
- Multi-tenancy avec isolation de bases de données
- Gestion des permissions (Spatie)
- API REST complète

**Documentation** : `docs/brownfield-architecture.md` (section 4)

---

### 2.2 Module 2 : Structure Académique 🆕

**Objectif** : Permettre à l'Admin de définir toute la structure académique de son établissement (facultés, départements, filières, niveaux, modules, groupes).

**Pourquoi prioritaire ?** : Fondation de tous les autres modules. Sans structure académique, impossible d'inscrire des étudiants ou de saisir des notes.

**Epics** :

#### Epic 2.1 : Gestion des Facultés et Départements
- Créer, modifier, supprimer des facultés
- Créer, modifier, supprimer des départements (rattachés à une faculté)
- Hierarchie : Faculté → Départements

#### Epic 2.2 : Gestion des Filières et Niveaux
- Créer des filières (ex: Informatique, Gestion, Droit) rattachées à un département
- Définir les niveaux LMD (L1, L2, L3, M1, M2)
- Associer filières ↔ niveaux

#### Epic 2.3 : Gestion des Modules (UE)
- Créer des modules avec : Code, Nom, Crédits ECTS, Coefficient, Type (Obligatoire/Optionnel)
- Associer modules ↔ filières ↔ niveaux ↔ semestres
- Marquer les modules éliminatoires (règle LMD)

#### Epic 2.4 : Gestion des Groupes
- Créer des groupes (TD1, TD2, TP A, TP B) par niveau/filière
- Affecter des étudiants aux groupes (automatique ou manuel)

#### Epic 2.5 : Affectation Enseignants ↔ Modules
- Assigner un enseignant à un module pour un groupe/niveau/semestre
- Un enseignant peut avoir plusieurs modules
- Un module peut avoir plusieurs enseignants (groupes différents)

**Charge estimée** : 5 semaines (3 sem backend + 2 sem frontend)

---

### 2.3 Module 3 : Inscriptions 🆕

**Objectif** : Permettre à l'Admin d'inscrire des étudiants (administrative + pédagogique) et de gérer leurs statuts.

**Pourquoi prioritaire ?** : Nécessaire pour avoir des étudiants dans le système avant de saisir les notes.

**Epics** :

#### Epic 3.1 : Inscription Administrative
- Formulaire de création d'un étudiant avec : Nom, Prénom, Date de naissance, Sexe, Email, Téléphone, Adresse
- Génération automatique d'un numéro de matricule unique
- Photo de l'étudiant (upload)
- Statut : Actif, Suspendu, Diplômé, Exclu

#### Epic 3.2 : Inscription Pédagogique
- Assigner un étudiant à une filière, un niveau, un semestre
- Inscrire l'étudiant aux modules du semestre (sélection manuelle ou automatique selon filière/niveau)
- Affecter l'étudiant à un groupe (TD/TP)

#### Epic 3.3 : Import en Masse
- Import CSV d'étudiants (format : Nom, Prénom, Email, Filière, Niveau, etc.)
- Prévisualisation des données avec validation
- Détection des doublons (email, matricule)
- Import avec génération automatique des matricules

#### Epic 3.4 : Gestion des Statuts
- Modifier le statut d'un étudiant (Actif → Suspendu → Exclu → Diplômé)
- Filtres par statut dans la liste des étudiants
- Historique des changements de statut

**Charge estimée** : 4 semaines (2 sem backend + 2 sem frontend)

---

### 2.4 Module 4 : Notes & Évaluations 🆕

**Objectif** : Permettre aux enseignants de saisir les notes avec calcul automatique des moyennes selon les règles LMD paramétrables.

**Pourquoi prioritaire ?** : Cœur du système académique. Indispensable pour générer les relevés de notes.

**PRD détaillé** : `docs/prd/module-notes-evaluations.md`

**Epics** :

#### Epic 4.1 : Configuration Académique et Évaluations
- Configuration des règles LMD par tenant (seuil validation, compensation)
- Marquage des modules éliminatoires
- Définition des types d'évaluations par module (CC, TP, Examen) avec coefficients

#### Epic 4.2 : Saisie et Calcul Automatique des Notes
- Liste des modules assignés à un enseignant
- Saisie de notes en tableau avec calcul temps réel de la moyenne
- Import CSV de notes
- Soumission des notes pour validation

#### Epic 4.3 : Workflow de Validation et Publication
- Liste des notes en attente de validation (Admin)
- Prévisualisation et validation/rejet des notes
- Publication des résultats aux étudiants
- Historique des modifications de notes

#### Epic 4.4 : Calcul de Moyenne Semestre et Validation LMD
- Calcul automatique de la moyenne de semestre (pondération par crédits ECTS)
- Application des règles de compensation LMD
- Attribution automatique des crédits ECTS
- Statut global du semestre

#### Epic 4.5 : Gestion de la Session de Rattrapage
- Création d'une session de rattrapage
- Inscription automatique des étudiants "À rattraper"
- Saisie des notes de rattrapage
- Recalcul automatique après rattrapage

#### Epic 4.6 : Consultation et Exports
- Consultation des notes par les étudiants
- Consultation et statistiques pour les enseignants
- Export CSV/Excel des notes
- API pour le Module Documents Officiels

**Charge estimée** : 7 semaines (4 sem backend + 3 sem frontend)

---

### 2.5 Module 5 : Documents Officiels 🆕

**Objectif** : Générer automatiquement des relevés de notes et attestations en PDF avec templates professionnels.

**Pourquoi prioritaire ?** : Différenciateur clé du produit. Démo impressionnante pour les établissements pilotes.

**Epics** :

#### Epic 5.1 : Infrastructure de Génération PDF
- Intégration de `barryvdh/laravel-snappy` + wkhtmltopdf
- Système de templates PDF (Blade → HTML → PDF)
- Service de génération asynchrone (Laravel Queues)
- Tests de performance (100 relevés en < 30 secondes)

#### Epic 5.2 : Template Relevé de Notes
- Template professionnel avec : En-tête établissement, Logo, Informations étudiant
- Tableau des modules avec : Module, CC, TP, Examen, Moyenne, Crédits ECTS, Statut
- Résumé : Moyenne semestre, Crédits acquis, Statut global
- Footer avec signature et tampon

#### Epic 5.3 : Génération de Relevés de Notes
- Écran de sélection : Étudiant(s), Semestre
- Prévisualisation du relevé avant génération
- Génération en un clic avec téléchargement PDF
- Génération par lot (tous les étudiants d'un niveau/filière)

#### Epic 5.4 : Template Attestations
- Template attestation de scolarité (année en cours)
- Template attestation d'inscription (semestre spécifique)
- Template attestation de réussite (après validation semestre)
- Variables dynamiques : Nom, Prénom, Matricule, Filière, Niveau, Date

#### Epic 5.5 : Génération d'Attestations
- Écran de sélection : Type d'attestation, Étudiant(s)
- Génération en un clic avec téléchargement PDF
- Numérotation automatique des attestations (traçabilité)

#### Epic 5.6 : Archivage et Historique
- Historique des documents générés (qui, quand, type, étudiant)
- Réimpression d'un document généré précédemment
- Stockage sécurisé des PDFs (Laravel Filesystem)

**Charge estimée** : 5 semaines (3 sem backend + 2 sem frontend)

---

## 3. Backlog Priorisé

### 3.1 Ordre de Développement

**Principe** : Suivre les dépendances fonctionnelles et livrer des incréments testables.

```
Sprint 1-2 (Semaines 1-4) : Structure Académique
    ↓
Sprint 3-4 (Semaines 5-8) : Inscriptions + Infrastructure PDF
    ↓
Sprint 5-8 (Semaines 9-16) : Notes & Évaluations + Documents Officiels (parallèle)
```

---

### 3.2 Backlog Détaillé par Sprint

#### **SPRINT 1 (Semaines 1-2) : Fondations Structure Académique**

**Objectif** : Créer les fondations du module Structure Académique (facultés, départements, filières).

| ID | Epic | Story | Priorité | Estimation | Assigné |
|----|------|-------|----------|------------|---------|
| **BACK-001** | 2.1 | Créer le module Laravel `AcademicStructure` | 🔴 P0 | 4h | Backend |
| **BACK-002** | 2.1 | Migrations tenant : tables `faculties`, `departments` | 🔴 P0 | 4h | Backend |
| **BACK-003** | 2.1 | Models : `Faculty`, `Department` avec relations | 🔴 P0 | 4h | Backend |
| **BACK-004** | 2.1 | API CRUD Facultés (Controller + Requests + Resources) | 🔴 P0 | 8h | Backend |
| **BACK-005** | 2.1 | API CRUD Départements (Controller + Requests + Resources) | 🔴 P0 | 8h | Backend |
| **BACK-006** | 2.1 | Tests unitaires : relations Faculty ↔ Departments | 🟠 P1 | 4h | Backend |
| **FRONT-001** | 2.1 | Créer le module Next.js `AcademicStructure` (structure) | 🔴 P0 | 4h | Frontend |
| **FRONT-002** | 2.1 | Service API : `facultyService.ts` | 🔴 P0 | 4h | Frontend |
| **FRONT-003** | 2.1 | Composant : Liste des facultés avec CRUD | 🔴 P0 | 8h | Frontend |
| **FRONT-004** | 2.1 | Composant : Liste des départements avec CRUD | 🔴 P0 | 8h | Frontend |
| **FRONT-005** | 2.1 | Types TypeScript : `faculty.types.ts`, `department.types.ts` | 🔴 P0 | 2h | Frontend |

**Livrable Sprint 1** : Admin peut créer facultés et départements. Testable via interface web.

**Total Sprint 1** : ~54 heures (5-6 jours avec 2 développeurs)

---

#### **SPRINT 2 (Semaines 3-4) : Structure Académique - Filières et Modules**

**Objectif** : Compléter le module Structure Académique avec filières, niveaux, modules.

| ID | Epic | Story | Priorité | Estimation | Assigné |
|----|------|-------|----------|------------|---------|
| **BACK-007** | 2.2 | Migrations tenant : tables `programs`, `levels` | 🔴 P0 | 4h | Backend |
| **BACK-008** | 2.2 | Models : `Program`, `Level` avec relations | 🔴 P0 | 4h | Backend |
| **BACK-009** | 2.2 | API CRUD Filières (Programs) | 🔴 P0 | 8h | Backend |
| **BACK-010** | 2.3 | Migrations tenant : tables `modules`, `module_assignments` | 🔴 P0 | 6h | Backend |
| **BACK-011** | 2.3 | Model : `Module` avec crédits ECTS, coefficient, type, is_eliminatory | 🔴 P0 | 6h | Backend |
| **BACK-012** | 2.3 | API CRUD Modules avec marquage éliminatoire | 🔴 P0 | 10h | Backend |
| **BACK-013** | 2.4 | Migrations tenant : tables `groups`, `group_students` | 🔴 P0 | 4h | Backend |
| **BACK-014** | 2.4 | API CRUD Groupes | 🔴 P0 | 6h | Backend |
| **BACK-015** | 2.5 | Migration tenant : table `teacher_module_assignments` | 🔴 P0 | 4h | Backend |
| **BACK-016** | 2.5 | API Affectation Enseignants ↔ Modules | 🔴 P0 | 8h | Backend |
| **FRONT-006** | 2.2 | Composant : Liste des filières avec CRUD | 🔴 P0 | 8h | Frontend |
| **FRONT-007** | 2.3 | Composant : Liste des modules avec CRUD + checkbox éliminatoire | 🔴 P0 | 10h | Frontend |
| **FRONT-008** | 2.4 | Composant : Liste des groupes avec CRUD | 🔴 P0 | 6h | Frontend |
| **FRONT-009** | 2.5 | Composant : Affectation enseignants ↔ modules (drag & drop ou sélection) | 🔴 P0 | 10h | Frontend |

**Livrable Sprint 2** : Structure académique complète. Admin peut définir toute l'architecture (Faculté → Département → Filière → Niveau → Modules → Groupes → Enseignants).

**Total Sprint 2** : ~94 heures (10-12 jours avec 2 développeurs)

---

#### **SPRINT 3 (Semaines 5-6) : Inscriptions + Spike PDF**

**Objectif** : Permettre l'inscription d'étudiants + valider la technologie PDF.

| ID | Epic | Story | Priorité | Estimation | Assigné |
|----|------|-------|----------|------------|---------|
| **BACK-017** | 3.1 | Créer le module Laravel `Enrollment` | 🔴 P0 | 4h | Backend |
| **BACK-018** | 3.1 | Migrations tenant : table `students` (données personnelles) | 🔴 P0 | 4h | Backend |
| **BACK-019** | 3.1 | Model : `Student` avec génération auto matricule | 🔴 P0 | 6h | Backend |
| **BACK-020** | 3.1 | API CRUD Étudiants (inscription administrative) | 🔴 P0 | 10h | Backend |
| **BACK-021** | 3.2 | Migration tenant : tables `student_enrollments`, `student_module_enrollments` | 🔴 P0 | 6h | Backend |
| **BACK-022** | 3.2 | API Inscription pédagogique (filière, niveau, semestre, modules, groupe) | 🔴 P0 | 10h | Backend |
| **BACK-023** | 3.4 | Gestion des statuts étudiants (Actif, Suspendu, Exclu, Diplômé) | 🟠 P1 | 4h | Backend |
| **BACK-024** | 5.1 | **SPIKE PDF** : Installer Snappy + générer 100 PDFs test | 🔴 P0 | 8h | Backend |
| **FRONT-010** | 3.1 | Créer le module Next.js `Enrollment` | 🔴 P0 | 4h | Frontend |
| **FRONT-011** | 3.1 | Composant : Formulaire inscription administrative étudiant | 🔴 P0 | 10h | Frontend |
| **FRONT-012** | 3.1 | Composant : Liste des étudiants avec filtres (statut, filière) | 🔴 P0 | 8h | Frontend |
| **FRONT-013** | 3.2 | Composant : Formulaire inscription pédagogique (filière, niveau, modules) | 🔴 P0 | 10h | Frontend |

**Livrable Sprint 3** : Admin peut inscrire des étudiants (admin + pédagogique). Technologie PDF validée (Snappy OK).

**Total Sprint 3** : ~84 heures (9-10 jours avec 2 développeurs)

---

#### **SPRINT 4 (Semaines 7-8) : Import Masse + Configuration Notes**

**Objectif** : Import CSV d'étudiants + configuration des règles LMD et évaluations.

| ID | Epic | Story | Priorité | Estimation | Assigné |
|----|------|-------|----------|------------|---------|
| **BACK-025** | 3.3 | API Import CSV étudiants avec prévisualisation et validation | 🔴 P0 | 12h | Backend |
| **BACK-026** | 3.3 | Détection doublons (email, matricule) | 🟠 P1 | 4h | Backend |
| **BACK-027** | 4.1 | Créer le module Laravel `Grades` | 🔴 P0 | 4h | Backend |
| **BACK-028** | 4.1 | Migration tenant : table `grade_configs` (règles LMD par tenant) | 🔴 P0 | 4h | Backend |
| **BACK-029** | 4.1 | API Configuration règles LMD (seuil validation, compensation) | 🔴 P0 | 8h | Backend |
| **BACK-030** | 4.1 | Migration tenant : table `evaluations` (types évaluations par module) | 🔴 P0 | 4h | Backend |
| **BACK-031** | 4.1 | API Définition types évaluations par module (CC, TP, Examen + coef) | 🔴 P0 | 8h | Backend |
| **FRONT-014** | 3.3 | Composant : Import CSV avec prévisualisation et gestion erreurs | 🔴 P0 | 12h | Frontend |
| **FRONT-015** | 4.1 | Créer le module Next.js `Grades` | 🔴 P0 | 4h | Frontend |
| **FRONT-016** | 4.1 | Composant : Formulaire configuration règles LMD | 🔴 P0 | 8h | Frontend |
| **FRONT-017** | 4.1 | Composant : Configuration évaluations par module | 🔴 P0 | 8h | Frontend |

**Livrable Sprint 4** : Admin peut importer des étudiants en masse. Configuration LMD et évaluations prête.

**Total Sprint 4** : ~76 heures (8-9 jours avec 2 développeurs)

---

#### **SPRINT 5 (Semaines 9-10) : Saisie et Calcul Notes**

**Objectif** : Enseignants peuvent saisir les notes avec calcul automatique en temps réel.

| ID | Epic | Story | Priorité | Estimation | Assigné |
|----|------|-------|----------|------------|---------|
| **BACK-032** | 4.2 | Migration tenant : table `grades` (notes individuelles) | 🔴 P0 | 4h | Backend |
| **BACK-033** | 4.2 | Model : `Grade` avec relations student, evaluation, teacher | 🔴 P0 | 4h | Backend |
| **BACK-034** | 4.2 | API Liste des modules assignés à un enseignant | 🔴 P0 | 6h | Backend |
| **BACK-035** | 4.2 | API Saisie de notes par module avec calcul moyenne en temps réel | 🔴 P0 | 12h | Backend |
| **BACK-036** | 4.2 | Service de calcul de moyenne module : `Σ(Note × Coef) / Σ(Coef)` | 🔴 P0 | 6h | Backend |
| **BACK-037** | 4.2 | Tests unitaires : calcul moyenne avec cas limites (ABS, 10.00, 9.99) | 🔴 P0 | 6h | Backend |
| **BACK-038** | 4.2 | API Import CSV de notes | 🟠 P1 | 8h | Backend |
| **BACK-039** | 4.2 | API Soumission notes pour validation (statut = En attente) | 🔴 P0 | 4h | Backend |
| **FRONT-018** | 4.2 | Composant : Liste des modules assignés (enseignant) | 🔴 P0 | 6h | Frontend |
| **FRONT-019** | 4.2 | Composant : Tableau de saisie notes avec calcul temps réel | 🔴 P0 | 16h | Frontend |
| **FRONT-020** | 4.2 | Hook : `useGradeCalculation` pour calcul moyenne côté client | 🔴 P0 | 4h | Frontend |

**Livrable Sprint 5** : Enseignants peuvent saisir notes avec calcul automatique. Tests unitaires validés.

**Total Sprint 5** : ~76 heures (8-9 jours avec 2 développeurs)

---

#### **SPRINT 6 (Semaines 11-12) : Validation Notes + Moyenne Semestre**

**Objectif** : Workflow de validation Admin + calcul moyenne semestre avec règles LMD.

| ID | Epic | Story | Priorité | Estimation | Assigné |
|----|------|-------|----------|------------|---------|
| **BACK-040** | 4.3 | API Liste des notes en attente de validation (Admin) | 🔴 P0 | 4h | Backend |
| **BACK-041** | 4.3 | API Validation/Rejet des notes par Admin | 🔴 P0 | 8h | Backend |
| **BACK-042** | 4.3 | Migration tenant : table `grade_history` (historique modifications) | 🟠 P1 | 4h | Backend |
| **BACK-043** | 4.3 | API Publication des résultats (statut = Publié, visible étudiants) | 🔴 P0 | 6h | Backend |
| **BACK-044** | 4.4 | Migration tenant : tables `semester_results`, `semester_results_details` | 🔴 P0 | 4h | Backend |
| **BACK-045** | 4.4 | Service calcul moyenne semestre : `Σ(Moy × ECTS) / Σ(ECTS)` | 🔴 P0 | 8h | Backend |
| **BACK-046** | 4.4 | Service application règles compensation LMD (Validé, Compensé, À rattraper) | 🔴 P0 | 10h | Backend |
| **BACK-047** | 4.4 | Tests unitaires : règles LMD (compensation, modules éliminatoires) | 🔴 P0 | 8h | Backend |
| **BACK-048** | 4.4 | Service attribution automatique crédits ECTS | 🔴 P0 | 4h | Backend |
| **FRONT-021** | 4.3 | Composant : Liste des notes en attente de validation (Admin) | 🔴 P0 | 8h | Frontend |
| **FRONT-022** | 4.3 | Composant : Prévisualisation et validation/rejet notes | 🔴 P0 | 10h | Frontend |
| **FRONT-023** | 4.3 | Composant : Publication des résultats (sélection multiple) | 🔴 P0 | 8h | Frontend |

**Livrable Sprint 6** : Workflow de validation complet. Calcul moyenne semestre et règles LMD appliquées.

**Total Sprint 6** : ~82 heures (9-10 jours avec 2 développeurs)

---

#### **SPRINT 7 (Semaines 13-14) : Consultation Notes + Templates PDF**

**Objectif** : Étudiants peuvent consulter leurs notes + création templates PDF (relevés, attestations).

| ID | Epic | Story | Priorité | Estimation | Assigné |
|----|------|-------|----------|------------|---------|
| **BACK-049** | 4.6 | API Consultation notes étudiant (semestre, modules, moyennes, statuts) | 🔴 P0 | 8h | Backend |
| **BACK-050** | 4.6 | API Statistiques enseignant (moyenne classe, distribution notes) | 🟠 P1 | 6h | Backend |
| **BACK-051** | 4.6 | API Export CSV/Excel notes par module | 🟠 P1 | 6h | Backend |
| **BACK-052** | 4.6 | API données pour relevé de notes (endpoint pour Module Documents) | 🔴 P0 | 6h | Backend |
| **BACK-053** | 5.1 | Créer le module Laravel `Documents` | 🔴 P0 | 4h | Backend |
| **BACK-054** | 5.2 | Template Blade : Relevé de notes (HTML → PDF) | 🔴 P0 | 12h | Backend |
| **BACK-055** | 5.4 | Templates Blade : Attestations (scolarité, inscription, réussite) | 🔴 P0 | 8h | Backend |
| **FRONT-024** | 4.6 | Composant : Consultation notes étudiant (onglets par semestre) | 🔴 P0 | 12h | Frontend |
| **FRONT-025** | 4.6 | Composant : Récapitulatif semestre (moyenne, crédits, statut) | 🔴 P0 | 6h | Frontend |
| **FRONT-026** | 4.6 | Composant : Statistiques enseignant avec graphiques | 🟠 P1 | 8h | Frontend |

**Livrable Sprint 7** : Étudiants peuvent voir leurs notes. Templates PDF prêts.

**Total Sprint 7** : ~76 heures (8-9 jours avec 2 développeurs)

---

#### **SPRINT 8 (Semaines 15-16) : Génération PDF + Rattrapage + Tests Intégration**

**Objectif** : Génération de relevés et attestations PDF + gestion rattrapage + tests d'intégration finaux.

| ID | Epic | Story | Priorité | Estimation | Assigné |
|----|------|-------|----------|------------|---------|
| **BACK-056** | 5.3 | API Génération relevé de notes (individuel ou par lot) | 🔴 P0 | 10h | Backend |
| **BACK-057** | 5.5 | API Génération attestations (types multiples) | 🔴 P0 | 8h | Backend |
| **BACK-058** | 5.6 | Migration tenant : table `document_history` (traçabilité) | 🟠 P1 | 4h | Backend |
| **BACK-059** | 5.6 | API Historique et réimpression documents | 🟠 P1 | 6h | Backend |
| **BACK-060** | 4.5 | Migration tenant : tables `retake_sessions`, `retake_grades` | 🔴 P0 | 4h | Backend |
| **BACK-061** | 4.5 | API Création session rattrapage + inscription auto étudiants | 🔴 P0 | 8h | Backend |
| **BACK-062** | 4.5 | API Saisie notes rattrapage + recalcul moyenne (MAX) | 🔴 P0 | 8h | Backend |
| **BACK-063** | - | Tests d'intégration : Workflow complet (Inscription → Notes → Relevé) | 🔴 P0 | 8h | Backend |
| **FRONT-027** | 5.3 | Créer le module Next.js `Documents` | 🔴 P0 | 4h | Frontend |
| **FRONT-028** | 5.3 | Composant : Génération relevé (sélection étudiant, prévisualisation) | 🔴 P0 | 10h | Frontend |
| **FRONT-029** | 5.5 | Composant : Génération attestations (types, sélection étudiant) | 🔴 P0 | 8h | Frontend |
| **FRONT-030** | 4.5 | Composant : Gestion session rattrapage (Admin) | 🔴 P0 | 8h | Frontend |
| **FRONT-031** | 4.5 | Composant : Saisie notes rattrapage (Enseignant) | 🔴 P0 | 6h | Frontend |
| **FRONT-032** | - | Tests E2E : Parcours complet utilisateur (Cypress ou Playwright) | 🟠 P1 | 8h | Frontend |

**Livrable Sprint 8** : MVP Core complet. Génération de relevés et attestations fonctionnelle. Tests validés.

**Total Sprint 8** : ~100 heures (11-12 jours avec 2 développeurs)

---

### 3.3 Résumé du Backlog

| Sprint | Semaines | Focus | Stories Backend | Stories Frontend | Total Heures |
|--------|----------|-------|-----------------|------------------|--------------|
| **Sprint 1** | 1-2 | Structure Académique (Fondations) | 6 | 5 | 54h |
| **Sprint 2** | 3-4 | Structure Académique (Complet) | 10 | 4 | 94h |
| **Sprint 3** | 5-6 | Inscriptions + Spike PDF | 8 | 4 | 84h |
| **Sprint 4** | 7-8 | Import + Config Notes | 7 | 4 | 76h |
| **Sprint 5** | 9-10 | Saisie Notes + Calcul | 8 | 3 | 76h |
| **Sprint 6** | 11-12 | Validation + Moyenne Semestre | 9 | 3 | 82h |
| **Sprint 7** | 13-14 | Consultation + Templates PDF | 7 | 3 | 76h |
| **Sprint 8** | 15-16 | Génération PDF + Rattrapage + Tests | 8 | 6 | 100h |
| **TOTAL** | **16 semaines** | | **63 stories** | **32 stories** | **642h** |

**Note** : 642 heures ÷ 2 développeurs ÷ 8h/jour = **40 jours** = **8 semaines théoriques**. Avec temps d'intégration, bugs, et buffer : **16 semaines (4 mois)** réaliste.

---

## 4. Graphe de Dépendances

```
SPRINT 1-2: Structure Académique
         │
         │ (Facultés, Filières, Modules définis)
         ↓
    ┌────┴────┐
    ↓         ↓
SPRINT 3: Inscriptions    SPRINT 3: Spike PDF (parallèle)
    │                              │
    │ (Étudiants inscrits)         │ (Technologie validée)
    ↓                              ↓
SPRINT 4: Import + Config Notes
    │
    │ (Règles LMD configurées, Évaluations définies)
    ↓
SPRINT 5: Saisie Notes + Calcul
    │
    │ (Notes saisies)
    ↓
SPRINT 6: Validation + Moyenne Semestre
    │
    │ (Notes validées, Moyennes calculées)
    ↓
    ┌────┴────┐
    ↓         ↓
SPRINT 7: Consultation    SPRINT 7: Templates PDF (parallèle)
    │                              │
    │                              ↓
    │                         SPRINT 8: Génération PDF
    ↓
SPRINT 8: Rattrapage + Tests Intégration
```

**Dépendances critiques** :
- Sprint 3 (Inscriptions) **dépend de** Sprint 1-2 (Structure Académique)
- Sprint 5 (Saisie Notes) **dépend de** Sprint 3 (Étudiants inscrits) + Sprint 4 (Config)
- Sprint 6 (Validation) **dépend de** Sprint 5 (Notes saisies)
- Sprint 8 (Génération PDF) **dépend de** Sprint 6 (Moyennes calculées) + Sprint 7 (Templates)

**Parallélisation possible** :
- Sprint 3 : Spike PDF en parallèle des Inscriptions
- Sprint 7 : Templates PDF en parallèle de Consultation Notes

---

## 5. Estimation de Charge

### 5.1 Charge par Module

| Module | Backend | Frontend | Total |
|--------|---------|----------|-------|
| Structure Académique | 148h | 96h | 244h |
| Inscriptions | 88h | 72h | 160h |
| Notes & Évaluations | 192h | 112h | 304h |
| Documents Officiels | 80h | 52h | 132h |
| **TOTAL** | **508h** | **332h** | **840h** |

**Note** : 840h incluent tests + buffer (vs 642h du backlog pur).

### 5.2 Charge par Développeur

**Avec 2 développeurs (1 backend + 1 frontend)** :
- Backend : 508h ÷ 8h/jour = 63.5 jours ≈ **13 semaines**
- Frontend : 332h ÷ 8h/jour = 41.5 jours ≈ **8.5 semaines**

**En parallèle** : 13 semaines (backend est le chemin critique)

**Avec buffer 20% + intégration** : 13 × 1.2 = **15.6 semaines** ≈ **16 semaines (4 mois)**

### 5.3 Charge par Rôle

| Rôle | Charge Totale | Semaines (8h/jour) |
|------|---------------|---------------------|
| **Développeur Backend** | 508h | 13 semaines |
| **Développeur Frontend** | 332h | 8.5 semaines |
| **Product Manager** | 40h (review sprints) | Temps partiel |
| **QA / Testeur** | 80h (tests manuels) | Phase finale (Sprint 8) |

---

## 6. Définition de Done (DoD)

### 6.1 DoD par Story

Une story est considérée comme **DONE** si :
- ✅ Code développé conforme aux acceptance criteria
- ✅ Tests unitaires écrits et passants (coverage ≥ 80% pour logique métier)
- ✅ Code review effectué par un pair
- ✅ Code formaté avec Laravel Pint (`vendor/bin/pint --dirty`)
- ✅ API documentée (endpoints + payloads)
- ✅ Interface frontend responsive (desktop + tablette + mobile)
- ✅ Testé manuellement par le développeur
- ✅ Merged dans la branche `develop`

### 6.2 DoD par Sprint

Un sprint est considéré comme **DONE** si :
- ✅ Toutes les stories P0 sont DONE
- ✅ Demo fonctionnelle réalisée au PM ou aux pilotes
- ✅ Pas de bugs bloquants (P0)
- ✅ Documentation mise à jour (README, API docs)
- ✅ Déployé en environnement staging pour tests pilotes

### 6.3 DoD Phase 1 MVP Core

La Phase 1 est considérée comme **DONE** si :
- ✅ Workflow complet fonctionnel : Inscription → Saisie notes → Génération relevé
- ✅ Tests d'intégration passants (workflow end-to-end)
- ✅ 2 établissements pilotes ont testé et validé le MVP
- ✅ Performance validée : Génération 100 relevés en < 2 minutes
- ✅ Taux d'erreurs < 1% sur documents générés
- ✅ Documentation utilisateur (manuel Admin, Enseignant, Étudiant)
- ✅ Déployé en production pour les pilotes

---

## 7. Risques et Mitigation

### 7.1 Risques Identifiés

| Risque | Probabilité | Impact | Mitigation |
|--------|-------------|--------|------------|
| **Retard Sprint 5-6** (Notes complexes) | Haute | Élevé | Buffer 20% intégré, tests dès Sprint 5 |
| **Performance PDF** (génération lente) | Moyenne | Élevé | Spike dès Sprint 3, tests 100 PDFs |
| **Qualité données pilotes** (import CSV buggy) | Haute | Moyen | Templates fournis Sprint 4, validation stricte |
| **Changement règles LMD** (pilotes demandent modifs) | Moyenne | Moyen | Paramétrage dès Sprint 4, config flexible |
| **Indisponibilité développeur** (maladie, congé) | Faible | Élevé | Documentation code, code review systématique |
| **Feedback pilotes tardif** (Sprint 8) | Moyenne | Moyen | Demos intermédiaires (Sprint 4, 6, 8) |

### 7.2 Plan de Contingence

**Si retard > 1 sprint** :
1. Prioriser les stories P0, reporter les P1 en Phase 2
2. Réduire le scope : Reporter le rattrapage (Epic 4.5) en Phase 2
3. Augmenter la capacité : Ajouter un développeur fullstack temporaire

**Si performance PDF insuffisante** :
1. Optimiser les templates (réduction images, CSS)
2. Génération asynchrone avec notification email (au lieu de téléchargement immédiat)
3. En dernier recours : Alternative à Snappy (Browsershot)

---

## 8. Critères de Succès Phase 1

| Critère | Cible | Mesure |
|---------|-------|--------|
| **Fonctionnalité** | Workflow complet sans papier | ✅ Démo end-to-end validée |
| **Performance** | Génération relevé < 2 min | ⏱️ Tests de charge (100 relevés) |
| **Adoption pilotes** | 2 établissements actifs | 📊 2 établissements utilisent quotidiennement |
| **Qualité** | < 1% erreurs documents | 📄 Vérification 100 relevés générés |
| **Satisfaction** | NPS ≥ 50 (Admins) | 📝 Questionnaire post-déploiement |
| **Délais** | 16 semaines maximum | 📅 Date de livraison respectée |

---

## 9. Prochaines Étapes

### 9.1 Avant le Sprint 1

- [ ] Valider ce backlog avec l'équipe de développement
- [ ] Identifier les 2 établissements pilotes
- [ ] Créer les environnements (dev, staging, production)
- [ ] Configurer les outils (Git, CI/CD, Jira/Trello)
- [ ] Kickoff meeting : présentation du backlog à l'équipe

### 9.2 Pendant la Phase 1

- [ ] Daily standups (15 min, async Slack acceptable)
- [ ] Sprint reviews (fin de chaque sprint, avec PM)
- [ ] Demos aux pilotes (Sprint 4, 6, 8)
- [ ] Rétrospectives (fin de chaque sprint)
- [ ] Mise à jour hebdomadaire du backlog (ajustements)

### 9.3 Après la Phase 1

- [ ] Collecte du feedback pilotes (questionnaires + interviews)
- [ ] Analyse des métriques de succès
- [ ] Planification Phase 2 (Emplois du Temps, Présences, Examens)
- [ ] Déploiement production pour établissements payants

---

**Document créé par** : John (Product Manager Agent)
**Date** : 2026-01-07
**Version** : 1.0
**Statut** : Ready for Team Review