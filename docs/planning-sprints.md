# Planning Détaillé - Phase 1 MVP Core (4 mois)

> **Projet** : Gestion Scolaire - Système LMD Multi-Tenant
> **Phase** : Phase 1 MVP Core
> **Durée** : 16 semaines (4 mois)
> **Date de début** : 13 janvier 2026
> **Date de fin** : 9 mai 2026
> **Équipe** : 2 développeurs (1 Backend Laravel + 1 Frontend Next.js)

---

## Table des Matières

1. [Vue d'ensemble du Planning](#1-vue-densemble-du-planning)
2. [Calendrier des Sprints](#2-calendrier-des-sprints)
3. [Planning Détaillé par Sprint](#3-planning-détaillé-par-sprint)
4. [Gantt Chart](#4-gantt-chart)
5. [Milestones et Demos](#5-milestones-et-demos)
6. [Gestion des Risques](#6-gestion-des-risques)

---

## 1. Vue d'ensemble du Planning

### 1.1 Objectif Global

**Livrer un MVP fonctionnel** permettant à un établissement d'enseignement supérieur de :
- Définir sa structure académique complète
- Inscrire des étudiants (administratif + pédagogique)
- Saisir des notes avec calcul automatique selon les règles LMD
- Générer automatiquement des relevés de notes et attestations en PDF

### 1.2 Organisation en Sprints

- **Durée d'un sprint** : 2 semaines
- **Nombre de sprints** : 8 sprints
- **Durée totale** : 16 semaines (4 mois)

### 1.3 Équipe et Capacité

| Rôle | Personne | Capacité/Sprint | Total Phase 1 |
|------|----------|-----------------|---------------|
| **Développeur Backend** | Dev Backend | 80h/sprint | 640h (16 sem) |
| **Développeur Frontend** | Dev Frontend | 80h/sprint | 640h (16 sem) |
| **Product Manager** | John (Part-time) | 10h/sprint | 80h |
| **QA / Testeur** | Dev Backend (double casquette) | 10h (Sprint 8 uniquement) | 10h |

**Total capacité équipe** : 160h/sprint × 8 sprints = **1280h**

**Note** : Le frontend a moins de charge (332h vs 508h backend), donc le développeur frontend pourra aider sur des tâches transverses (documentation, tests, design).

---

## 2. Calendrier des Sprints

| Sprint | Semaines | Dates | Objectif Principal | Livrable |
|--------|----------|-------|--------------------|----------|
| **Sprint 1** | S1-S2 | 13 jan - 26 jan | Fondations Structure Académique | Facultés + Départements CRUD |
| **Sprint 2** | S3-S4 | 27 jan - 9 fév | Structure Académique Complète | Filières, Modules, Groupes, Affectations |
| **Sprint 3** | S5-S6 | 10 fév - 23 fév | Inscriptions + Spike PDF | Inscription étudiants + PDF validé |
| **Sprint 4** | S7-S8 | 24 fév - 9 mars | Import Masse + Config Notes | Import CSV + Règles LMD configurées |
| **Sprint 5** | S9-S10 | 10 mars - 23 mars | Saisie Notes + Calcul Auto | Enseignants saisissent notes avec calcul temps réel |
| **Sprint 6** | S11-S12 | 24 mars - 6 avr | Validation Notes + Moyenne Semestre | Workflow validation + Règles LMD appliquées |
| **Sprint 7** | S13-S14 | 7 avr - 20 avr | Consultation + Templates PDF | Étudiants voient notes + Templates prêts |
| **Sprint 8** | S15-S16 | 21 avr - 9 mai | Génération PDF + Tests Finaux | MVP complet + Démo pilotes |

**Milestones critiques** :
- 🎯 **M1** (26 jan) : Structure académique démontrable
- 🎯 **M2** (9 mars) : Étudiants inscrits + Config notes OK
- 🎯 **M3** (6 avr) : Notes saisies et validées
- 🎯 **M4** (9 mai) : MVP Core complet avec génération PDF

---

## 3. Planning Détaillé par Sprint

### **SPRINT 1 : Fondations Structure Académique**

**Dates** : 13 janvier - 26 janvier 2026 (Semaines 1-2)

**Objectif** : Créer les fondations du module Structure Académique (facultés, départements) pour permettre à l'Admin de commencer à définir son établissement.

#### 3.1.1 Équipe

| Rôle | Développeur | Charge |
|------|-------------|--------|
| Backend Laravel | Dev Backend | 32h |
| Frontend Next.js | Dev Frontend | 30h |
| **Total** | | **62h** |

#### 3.1.2 Tâches Backend (Dev Backend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| BACK-001 | Créer le module Laravel `AcademicStructure` | 4h | J1 |
| BACK-002 | Migrations tenant : tables `faculties`, `departments` | 4h | J1-J2 |
| BACK-003 | Models : `Faculty`, `Department` avec relations | 4h | J2 |
| BACK-004 | API CRUD Facultés (Controller + Requests + Resources) | 8h | J3-J4 |
| BACK-005 | API CRUD Départements | 8h | J4-J5 |
| BACK-006 | Tests unitaires : relations Faculty ↔ Departments | 4h | J5 |

**Total Backend** : 32h (4 jours)

#### 3.1.3 Tâches Frontend (Dev Frontend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| FRONT-001 | Créer le module Next.js `AcademicStructure` | 4h | J1 |
| FRONT-002 | Service API : `facultyService.ts` | 4h | J2 |
| FRONT-003 | Composant : Liste des facultés avec CRUD | 8h | J3-J4 |
| FRONT-004 | Composant : Liste des départements avec CRUD | 8h | J5-J6 |
| FRONT-005 | Types TypeScript : `faculty.types.ts`, `department.types.ts` | 2h | J6 |
| FRONT-006 | Tests manuels + ajustements UI | 4h | J7 |

**Total Frontend** : 30h (3.75 jours)

#### 3.1.4 Livrable Sprint 1

✅ **Demo** : Admin peut créer des facultés et des départements via l'interface web. La hiérarchie Faculté → Départements est fonctionnelle.

#### 3.1.5 Ceremonies

| Cérémonie | Date | Durée | Participants |
|-----------|------|-------|--------------|
| **Sprint Planning** | 13 jan (J1) | 2h | PM + Dev Backend + Dev Frontend |
| **Daily Standups** | Tous les jours (async Slack) | 15min | Dev Backend + Dev Frontend |
| **Sprint Review** | 24 jan (J10) | 1h | PM + Devs + Pilotes (optionnel) |
| **Retrospective** | 26 jan (J10) | 1h | PM + Dev Backend + Dev Frontend |

---

### **SPRINT 2 : Structure Académique Complète**

**Dates** : 27 janvier - 9 février 2026 (Semaines 3-4)

**Objectif** : Compléter le module Structure Académique avec filières, niveaux, modules, groupes, et affectation enseignants.

#### 3.2.1 Équipe

| Rôle | Développeur | Charge |
|------|-------------|--------|
| Backend Laravel | Dev Backend | 60h |
| Frontend Next.js | Dev Frontend | 44h |
| **Total** | | **104h** |

#### 3.2.2 Tâches Backend (Dev Backend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| BACK-007 | Migrations tenant : tables `programs`, `levels` | 4h | J1 |
| BACK-008 | Models : `Program`, `Level` avec relations | 4h | J1 |
| BACK-009 | API CRUD Filières (Programs) | 8h | J2-J3 |
| BACK-010 | Migrations tenant : tables `modules`, `module_assignments` | 6h | J3 |
| BACK-011 | Model : `Module` (crédits ECTS, coef, type, is_eliminatory) | 6h | J4 |
| BACK-012 | API CRUD Modules avec marquage éliminatoire | 10h | J4-J5 |
| BACK-013 | Migrations tenant : tables `groups`, `group_students` | 4h | J6 |
| BACK-014 | API CRUD Groupes | 6h | J6-J7 |
| BACK-015 | Migration tenant : table `teacher_module_assignments` | 4h | J7 |
| BACK-016 | API Affectation Enseignants ↔ Modules | 8h | J8-J9 |

**Total Backend** : 60h (7.5 jours)

#### 3.2.3 Tâches Frontend (Dev Frontend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| FRONT-006 | Composant : Liste des filières avec CRUD | 8h | J1-J2 |
| FRONT-007 | Composant : Liste des modules + checkbox éliminatoire | 10h | J3-J4 |
| FRONT-008 | Composant : Liste des groupes avec CRUD | 6h | J5 |
| FRONT-009 | Composant : Affectation enseignants ↔ modules (sélection) | 10h | J6-J7 |
| FRONT-010 | Tests manuels + ajustements UI | 4h | J8 |
| FRONT-011 | Documentation utilisateur (guide Admin) | 6h | J9-J10 |

**Total Frontend** : 44h (5.5 jours)

#### 3.2.4 Livrable Sprint 2

✅ **Demo** : Structure académique complète. Admin peut définir toute l'architecture (Faculté → Département → Filière → Niveau → Modules → Groupes → Enseignants). Modules éliminatoires marqués.

#### 3.2.5 Milestone

🎯 **M1 atteint** (26 jan) : Structure académique démontrable aux pilotes.

---

### **SPRINT 3 : Inscriptions + Spike PDF**

**Dates** : 10 février - 23 février 2026 (Semaines 5-6)

**Objectif** : Permettre l'inscription d'étudiants (administrative + pédagogique) et valider la technologie de génération PDF.

#### 3.3.1 Équipe

| Rôle | Développeur | Charge |
|------|-------------|--------|
| Backend Laravel | Dev Backend | 56h |
| Frontend Next.js | Dev Frontend | 36h |
| **Total** | | **92h** |

#### 3.3.2 Tâches Backend (Dev Backend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| BACK-017 | Créer le module Laravel `Enrollment` | 4h | J1 |
| BACK-018 | Migrations tenant : table `students` | 4h | J1 |
| BACK-019 | Model : `Student` avec génération auto matricule | 6h | J2 |
| BACK-020 | API CRUD Étudiants (inscription administrative) | 10h | J3-J4 |
| BACK-021 | Migrations : `student_enrollments`, `student_module_enrollments` | 6h | J4 |
| BACK-022 | API Inscription pédagogique (filière, niveau, modules, groupe) | 10h | J5-J6 |
| BACK-023 | Gestion des statuts étudiants | 4h | J7 |
| **BACK-024** | **SPIKE PDF : Installer Snappy + générer 100 PDFs test** | **8h** | **J8** |
| BACK-025 | Tests unitaires : génération matricule unique | 4h | J9 |

**Total Backend** : 56h (7 jours)

**🔬 SPIKE PDF** : Tâche critique pour valider la technologie. Le développeur backend doit :
1. Installer `barryvdh/laravel-snappy` + `wkhtmltopdf`
2. Créer un template Blade simple (relevé fictif)
3. Générer 100 PDFs avec données réalistes
4. Mesurer : temps de génération, qualité du rendu, consommation mémoire
5. **Go/No-Go decision** : Si performance acceptable, on continue. Sinon, on explore Browsershot.

#### 3.3.3 Tâches Frontend (Dev Frontend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| FRONT-010 | Créer le module Next.js `Enrollment` | 4h | J1 |
| FRONT-011 | Composant : Formulaire inscription administrative | 10h | J2-J3 |
| FRONT-012 | Composant : Liste des étudiants avec filtres | 8h | J4-J5 |
| FRONT-013 | Composant : Formulaire inscription pédagogique | 10h | J6-J7 |
| FRONT-014 | Tests manuels + ajustements UI | 4h | J8-J9 |

**Total Frontend** : 36h (4.5 jours)

#### 3.3.4 Livrable Sprint 3

✅ **Demo** : Admin peut inscrire des étudiants (administrative + pédagogique). Technologie PDF validée (Snappy OK, 100 relevés en < 30 secondes).

---

### **SPRINT 4 : Import Masse + Configuration Notes**

**Dates** : 24 février - 9 mars 2026 (Semaines 7-8)

**Objectif** : Permettre l'import CSV d'étudiants en masse + configurer les règles LMD et les évaluations par module.

#### 3.4.1 Équipe

| Rôle | Développeur | Charge |
|------|-------------|--------|
| Backend Laravel | Dev Backend | 52h |
| Frontend Next.js | Dev Frontend | 36h |
| **Total** | | **88h** |

#### 3.4.2 Tâches Backend (Dev Backend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| BACK-025 | API Import CSV étudiants avec prévisualisation | 12h | J1-J2 |
| BACK-026 | Détection doublons (email, matricule) | 4h | J2 |
| BACK-027 | Créer le module Laravel `Grades` | 4h | J3 |
| BACK-028 | Migration tenant : table `grade_configs` (règles LMD) | 4h | J3 |
| BACK-029 | API Configuration règles LMD (seuil validation, compensation) | 8h | J4 |
| BACK-030 | Migration tenant : table `evaluations` | 4h | J5 |
| BACK-031 | API Définition types évaluations par module (CC, TP, Examen + coef) | 8h | J5-J6 |
| BACK-032 | Tests unitaires : import CSV avec cas limites | 4h | J7 |
| BACK-033 | Documentation API (endpoints + payloads) | 4h | J8 |

**Total Backend** : 52h (6.5 jours)

#### 3.4.3 Tâches Frontend (Dev Frontend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| FRONT-014 | Composant : Import CSV avec prévisualisation | 12h | J1-J2 |
| FRONT-015 | Créer le module Next.js `Grades` | 4h | J3 |
| FRONT-016 | Composant : Formulaire configuration règles LMD | 8h | J4-J5 |
| FRONT-017 | Composant : Configuration évaluations par module | 8h | J6-J7 |
| FRONT-018 | Tests manuels + ajustements UI | 4h | J8 |

**Total Frontend** : 36h (4.5 jours)

#### 3.4.4 Livrable Sprint 4

✅ **Demo** : Admin peut importer 100+ étudiants via CSV. Règles LMD configurées (seuil 10/20, compensation activée). Évaluations définies par module (CC, TP, Examen).

#### 3.4.5 Milestone

🎯 **M2 atteint** (9 mars) : Étudiants inscrits + Configuration notes OK. Prêt pour la saisie des notes.

---

### **SPRINT 5 : Saisie Notes + Calcul Automatique**

**Dates** : 10 mars - 23 mars 2026 (Semaines 9-10)

**Objectif** : Enseignants peuvent saisir les notes avec calcul automatique des moyennes en temps réel.

#### 3.5.1 Équipe

| Rôle | Développeur | Charge |
|------|-------------|--------|
| Backend Laravel | Dev Backend | 50h |
| Frontend Next.js | Dev Frontend | 30h |
| **Total** | | **80h** |

#### 3.5.2 Tâches Backend (Dev Backend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| BACK-032 | Migration tenant : table `grades` | 4h | J1 |
| BACK-033 | Model : `Grade` avec relations | 4h | J1 |
| BACK-034 | API Liste des modules assignés à un enseignant | 6h | J2 |
| BACK-035 | API Saisie de notes par module avec calcul moyenne | 12h | J3-J4 |
| BACK-036 | Service calcul moyenne module : `Σ(Note × Coef) / Σ(Coef)` | 6h | J5 |
| BACK-037 | **Tests unitaires : calcul moyenne (cas limites ABS, 10.00, 9.99)** | **6h** | **J6** |
| BACK-038 | API Import CSV de notes | 8h | J7 |
| BACK-039 | API Soumission notes pour validation (statut = En attente) | 4h | J8 |

**Total Backend** : 50h (6.25 jours)

**⚠️ Tests critiques** : Les tests unitaires du calcul de moyenne sont essentiels pour garantir zéro erreur. Couvrir tous les cas limites.

#### 3.5.3 Tâches Frontend (Dev Frontend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| FRONT-018 | Composant : Liste des modules assignés (enseignant) | 6h | J1 |
| FRONT-019 | Composant : Tableau de saisie notes avec calcul temps réel | 16h | J2-J4 |
| FRONT-020 | Hook : `useGradeCalculation` pour calcul moyenne côté client | 4h | J5 |
| FRONT-021 | Tests manuels + ajustements UI | 4h | J6-J7 |

**Total Frontend** : 30h (3.75 jours)

#### 3.5.4 Livrable Sprint 5

✅ **Demo** : Enseignants peuvent saisir les notes de leurs étudiants. La moyenne du module se calcule automatiquement en temps réel. Tests unitaires validés (zéro erreur de calcul).

---

### **SPRINT 6 : Validation Notes + Moyenne Semestre**

**Dates** : 24 mars - 6 avril 2026 (Semaines 11-12)

**Objectif** : Workflow de validation Admin + calcul de la moyenne de semestre avec application des règles LMD.

#### 3.6.1 Équipe

| Rôle | Développeur | Charge |
|------|-------------|--------|
| Backend Laravel | Dev Backend | 56h |
| Frontend Next.js | Dev Frontend | 34h |
| **Total** | | **90h** |

#### 3.6.2 Tâches Backend (Dev Backend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| BACK-040 | API Liste des notes en attente de validation (Admin) | 4h | J1 |
| BACK-041 | API Validation/Rejet des notes par Admin | 8h | J2 |
| BACK-042 | Migration tenant : table `grade_history` (historique) | 4h | J3 |
| BACK-043 | API Publication des résultats (statut = Publié) | 6h | J3-J4 |
| BACK-044 | Migrations : `semester_results`, `semester_results_details` | 4h | J4 |
| BACK-045 | Service calcul moyenne semestre : `Σ(Moy × ECTS) / Σ(ECTS)` | 8h | J5 |
| BACK-046 | **Service règles compensation LMD (Validé, Compensé, À rattraper)** | **10h** | **J6-J7** |
| BACK-047 | **Tests unitaires : règles LMD (compensation, modules éliminatoires)** | **8h** | **J8** |
| BACK-048 | Service attribution automatique crédits ECTS | 4h | J9 |

**Total Backend** : 56h (7 jours)

**🔑 Logique critique** : Le service de compensation LMD est le cœur du système. Il doit être testé exhaustivement.

#### 3.6.3 Tâches Frontend (Dev Frontend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| FRONT-021 | Composant : Liste des notes en attente (Admin) | 8h | J1-J2 |
| FRONT-022 | Composant : Prévisualisation et validation/rejet notes | 10h | J3-J4 |
| FRONT-023 | Composant : Publication des résultats (sélection multiple) | 8h | J5-J6 |
| FRONT-024 | Tests manuels + ajustements UI | 4h | J7 |
| FRONT-025 | Documentation utilisateur (guide validation Admin) | 4h | J8-J9 |

**Total Frontend** : 34h (4.25 jours)

#### 3.6.4 Livrable Sprint 6

✅ **Demo** : Workflow de validation complet (Enseignant → Admin → Publication). Les moyennes de semestre sont calculées avec application des règles LMD (modules validés, compensés, à rattraper). Crédits ECTS attribués automatiquement.

#### 3.6.5 Milestone

🎯 **M3 atteint** (6 avr) : Notes saisies et validées. Prêt pour la génération de documents.

---

### **SPRINT 7 : Consultation + Templates PDF**

**Dates** : 7 avril - 20 avril 2026 (Semaines 13-14)

**Objectif** : Étudiants peuvent consulter leurs notes + création des templates PDF professionnels.

#### 3.7.1 Équipe

| Rôle | Développeur | Charge |
|------|-------------|--------|
| Backend Laravel | Dev Backend | 50h |
| Frontend Next.js | Dev Frontend | 32h |
| **Total** | | **82h** |

#### 3.7.2 Tâches Backend (Dev Backend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| BACK-049 | API Consultation notes étudiant (semestre, modules, moyennes) | 8h | J1-J2 |
| BACK-050 | API Statistiques enseignant (moyenne classe, distribution) | 6h | J2 |
| BACK-051 | API Export CSV/Excel notes par module | 6h | J3 |
| BACK-052 | API données pour relevé de notes (endpoint Module Documents) | 6h | J4 |
| BACK-053 | Créer le module Laravel `Documents` | 4h | J5 |
| BACK-054 | **Template Blade : Relevé de notes (HTML → PDF)** | **12h** | **J6-J7** |
| BACK-055 | **Templates Blade : Attestations (scolarité, inscription, réussite)** | **8h** | **J8** |

**Total Backend** : 50h (6.25 jours)

**🎨 Templates PDF** : Temps important pour créer des templates professionnels avec mise en page parfaite (logos, tableaux, signatures).

#### 3.7.3 Tâches Frontend (Dev Frontend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| FRONT-024 | Composant : Consultation notes étudiant (onglets semestres) | 12h | J1-J3 |
| FRONT-025 | Composant : Récapitulatif semestre (moyenne, crédits, statut) | 6h | J4 |
| FRONT-026 | Composant : Statistiques enseignant avec graphiques | 8h | J5-J6 |
| FRONT-027 | Tests manuels + ajustements UI | 4h | J7 |
| FRONT-028 | Documentation utilisateur (guide Étudiant + Enseignant) | 2h | J8 |

**Total Frontend** : 32h (4 jours)

#### 3.7.4 Livrable Sprint 7

✅ **Demo** : Étudiants peuvent consulter leurs notes par semestre avec récapitulatif (moyenne, crédits, statut). Enseignants voient les statistiques de classe. Templates PDF professionnels prêts (relevés + attestations).

---

### **SPRINT 8 : Génération PDF + Rattrapage + Tests Finaux**

**Dates** : 21 avril - 9 mai 2026 (Semaines 15-16)

**Objectif** : Génération de relevés et attestations PDF + gestion rattrapage + tests d'intégration complets. **MVP Core finalisé**.

#### 3.8.1 Équipe

| Rôle | Développeur | Charge |
|------|-------------|--------|
| Backend Laravel | Dev Backend | 64h |
| Frontend Next.js | Dev Frontend | 48h |
| QA / Testeur | Dev Backend (double casquette) | 10h |
| **Total** | | **122h** |

#### 3.8.2 Tâches Backend (Dev Backend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| BACK-056 | API Génération relevé de notes (individuel ou par lot) | 10h | J1-J2 |
| BACK-057 | API Génération attestations (types multiples) | 8h | J2-J3 |
| BACK-058 | Migration tenant : table `document_history` (traçabilité) | 4h | J3 |
| BACK-059 | API Historique et réimpression documents | 6h | J4 |
| BACK-060 | Migrations : `retake_sessions`, `retake_grades` | 4h | J5 |
| BACK-061 | API Création session rattrapage + inscription auto | 8h | J5-J6 |
| BACK-062 | API Saisie notes rattrapage + recalcul moyenne (MAX) | 8h | J6-J7 |
| BACK-063 | **Tests d'intégration : Workflow complet (Inscription → Notes → Relevé)** | **8h** | **J8** |
| BACK-064 | Tests de performance : Génération 100 relevés | 4h | J9 |
| BACK-065 | Corrections bugs + optimisations | 4h | J9-J10 |

**Total Backend** : 64h (8 jours)

#### 3.8.3 Tâches Frontend (Dev Frontend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| FRONT-027 | Créer le module Next.js `Documents` | 4h | J1 |
| FRONT-028 | Composant : Génération relevé (sélection étudiant, prévisualisation) | 10h | J2-J3 |
| FRONT-029 | Composant : Génération attestations (types, sélection) | 8h | J4-J5 |
| FRONT-030 | Composant : Gestion session rattrapage (Admin) | 8h | J6 |
| FRONT-031 | Composant : Saisie notes rattrapage (Enseignant) | 6h | J7 |
| FRONT-032 | Tests E2E : Parcours complet utilisateur | 8h | J8 |
| FRONT-033 | Corrections bugs + optimisations UI | 4h | J9-J10 |

**Total Frontend** : 48h (6 jours)

#### 3.8.4 Tâches QA (Dev Backend)

| ID | Tâche | Estimation | Jour |
|----|-------|------------|------|
| QA-001 | Tests manuels : Workflow complet (tous les rôles) | 4h | J9 |
| QA-002 | Vérification documents générés (100 relevés) | 2h | J9 |
| QA-003 | Tests de régression (modules précédents) | 2h | J10 |
| QA-004 | Rapport de bugs + priorisation | 2h | J10 |

**Total QA** : 10h (1.25 jours)

#### 3.8.5 Livrable Sprint 8

✅ **MVP Core complet** :
- Génération de relevés de notes et attestations en PDF fonctionnelle
- Gestion de la session de rattrapage opérationnelle
- Tests d'intégration et E2E passants
- Performance validée (100 relevés en < 2 minutes)
- Documentation utilisateur complète

#### 3.8.6 Milestone

🎯 **M4 atteint** (9 mai) : **MVP Core complet avec génération PDF**. Prêt pour la démo finale aux pilotes et le déploiement en production.

---

## 4. Gantt Chart

### 4.1 Vue d'ensemble (16 semaines)

```
                    Jan     Fév     Mars    Avr     Mai
                 |-------|-------|-------|-------|-------|
Module 1: UsersGuard [✅ Existant]
                 |
Module 2: Structure Académique
                 [████████]
                 S1  S2  S3  S4

Module 3: Inscriptions
                         [████████]
                         S5  S6  S7  S8

Module 4: Notes & Évaluations
                                 [████████████████]
                                 S7  S8  S9 S10 S11 S12

Module 5: Documents Officiels
                                         [████████]
                                         S13 S14 S15 S16

MILESTONES:
                 M1      M2          M3          M4
                 ↓       ↓           ↓           ↓
              26 jan  9 mars     6 avr       9 mai
```

### 4.2 Vue Détaillée par Sprint

```
SPRINT 1 (S1-S2) : 13-26 jan
├─ Backend:  [███████░░░] 32h / 80h (40%)
└─ Frontend: [███████░░░] 30h / 80h (37%)

SPRINT 2 (S3-S4) : 27 jan - 9 fév
├─ Backend:  [████████████░] 60h / 80h (75%)
└─ Frontend: [██████████░░] 44h / 80h (55%)

SPRINT 3 (S5-S6) : 10-23 fév
├─ Backend:  [█████████████] 56h / 80h (70%)
│            ↳ Spike PDF (8h) - CRITIQUE
└─ Frontend: [████████░░░] 36h / 80h (45%)

SPRINT 4 (S7-S8) : 24 fév - 9 mars
├─ Backend:  [███████████░] 52h / 80h (65%)
└─ Frontend: [████████░░░] 36h / 80h (45%)

SPRINT 5 (S9-S10) : 10-23 mars
├─ Backend:  [████████████░] 50h / 80h (62%)
│            ↳ Tests unitaires calcul - CRITIQUE
└─ Frontend: [███████░░░░] 30h / 80h (37%)

SPRINT 6 (S11-S12) : 24 mars - 6 avr
├─ Backend:  [█████████████] 56h / 80h (70%)
│            ↳ Service compensation LMD - CRITIQUE
└─ Frontend: [█████████░░] 34h / 80h (42%)

SPRINT 7 (S13-S14) : 7-20 avr
├─ Backend:  [████████████░] 50h / 80h (62%)
│            ↳ Templates PDF - CRITIQUE
└─ Frontend: [█████████░░] 32h / 80h (40%)

SPRINT 8 (S15-S16) : 21 avr - 9 mai
├─ Backend:  [██████████████] 64h / 80h (80%)
│            ↳ Tests intégration - CRITIQUE
├─ Frontend: [███████████░] 48h / 80h (60%)
└─ QA:       [██░░░░░░░░] 10h / 80h (12%)
```

---

## 5. Milestones et Demos

### 5.1 Milestones Critiques

| Milestone | Date | Description | Critères de Succès |
|-----------|------|-------------|---------------------|
| **M1** | 26 jan | Structure académique démontrable | Admin peut créer Facultés → Départements → Filières → Modules → Groupes → Affectations |
| **M2** | 9 mars | Étudiants inscrits + Config notes OK | 100+ étudiants inscrits (CSV). Règles LMD configurées. Évaluations définies. |
| **M3** | 6 avr | Notes saisies et validées | Enseignants ont saisi des notes. Workflow validation fonctionne. Moyennes calculées avec règles LMD. |
| **M4** | 9 mai | **MVP Core complet** | Génération de relevés PDF fonctionnelle. Tests passants. Prêt pour déploiement pilotes. |

### 5.2 Démos aux Pilotes

| Demo | Date | Durée | Participants | Contenu |
|------|------|-------|--------------|---------|
| **Demo 1** | 9 mars (Fin Sprint 4) | 1h | PM + Devs + Pilotes | Inscription d'étudiants (admin + pédagogique) + Import CSV |
| **Demo 2** | 6 avr (Fin Sprint 6) | 1h | PM + Devs + Pilotes | Saisie de notes + Validation + Calcul moyennes LMD |
| **Demo 3** | 9 mai (Fin Sprint 8) | 2h | PM + Devs + Pilotes | **Workflow complet** : Inscription → Notes → Génération relevé PDF |

**Objectif des démos** : Collecter le feedback des pilotes pour ajuster les derniers sprints si nécessaire.

---

## 6. Gestion des Risques

### 6.1 Risques par Sprint

#### Sprint 1-2 : Risques faibles
- ✅ Module UsersGuard existant (fondations solides)
- ✅ CRUD simple (facultés, départements, filières)

#### Sprint 3 : **RISQUE MOYEN** 🟠
- 🔬 **Spike PDF (BACK-024)** : Si Snappy ne performe pas, on doit explorer Browsershot ou autre
- **Mitigation** : Spike dès J8 du Sprint 3. Go/No-Go decision immédiate.

#### Sprint 5 : **RISQUE ÉLEVÉ** 🔴
- 🧮 **Calcul de moyennes (BACK-036)** : Logique complexe avec cas limites (ABS, coefficients, etc.)
- **Mitigation** : Tests unitaires exhaustifs (BACK-037, 6h). Revue de code obligatoire.

#### Sprint 6 : **RISQUE ÉLEVÉ** 🔴
- 🎯 **Règles compensation LMD (BACK-046)** : Logique métier critique. Si mal implémentée, tout le système est faux.
- **Mitigation** : Tests unitaires exhaustifs (BACK-047, 8h). Validation avec un responsable scolarité.

#### Sprint 7-8 : **RISQUE MOYEN** 🟠
- 🎨 **Templates PDF (BACK-054, BACK-055)** : Mise en page peut prendre plus de temps que prévu
- **Mitigation** : Prévoir 2h de buffer pour ajustements esthétiques.

### 6.2 Plan de Contingence Global

**Si retard > 1 semaine à la fin du Sprint 6** :
1. **Reporter le rattrapage** (Epic 4.5) en Phase 2
2. **Simplifier les templates PDF** (version minimaliste pour MVP)
3. **Focus strict sur les stories P0** uniquement

**Si performance PDF insuffisante (Sprint 3)** :
1. **Immediate escalade** : PM prend la décision Go/No-Go
2. **Option A** : Optimiser Snappy (réduire qualité, génération asynchrone)
3. **Option B** : Basculer sur Browsershot (ajouter 1 semaine au planning)

---

## 7. Réunions et Ceremonies

### 7.1 Récurrence Hebdomadaire

| Réunion | Fréquence | Jour | Durée | Participants |
|---------|-----------|------|-------|--------------|
| **Daily Standup** | Quotidien (async Slack) | Tous les jours 9h | 15min | Dev Backend + Dev Frontend |
| **Weekly Sync PM** | Hebdomadaire | Vendredi 15h | 30min | PM + Dev Backend + Dev Frontend |
| **Sprint Planning** | Tous les 2 semaines | Lundi 9h (début sprint) | 2h | PM + Dev Backend + Dev Frontend |
| **Sprint Review** | Tous les 2 semaines | Vendredi 14h (fin sprint) | 1h | PM + Devs + (Pilotes si demo) |
| **Retrospective** | Tous les 2 semaines | Vendredi 15h (fin sprint) | 1h | PM + Dev Backend + Dev Frontend |

### 7.2 Format Daily Standup (Async Slack)

**Template** :
```
🟢 Hier : [Ce que j'ai terminé]
🔵 Aujourd'hui : [Ce que je vais faire]
🔴 Blockers : [Problèmes rencontrés, si any]
```

**Exemple** :
```
🟢 Hier : BACK-004 API CRUD Facultés terminé, tests passants
🔵 Aujourd'hui : BACK-005 API CRUD Départements (8h estimées)
🔴 Blockers : Aucun
```

---

## 8. Outils et Infrastructure

### 8.1 Outils de Développement

| Outil | Usage |
|-------|-------|
| **Git** | Versionning (GitHub ou GitLab) |
| **Branches** | `main`, `develop`, `feature/*`, `bugfix/*` |
| **CI/CD** | GitHub Actions (tests automatiques sur PR) |
| **Laravel Pint** | Code formatting (PHP) |
| **ESLint + Prettier** | Code formatting (TypeScript/React) |
| **PHPUnit** | Tests backend |
| **Jest + React Testing Library** | Tests frontend |

### 8.2 Environnements

| Environnement | URL | Déploiement | Usage |
|---------------|-----|-------------|-------|
| **Dev** | http://localhost:8000 | Local (Laragon) | Développement quotidien |
| **Staging** | https://staging.gestion-scolaire.com | Auto (push `develop`) | Tests pilotes + QA |
| **Production** | https://app.gestion-scolaire.com | Manuel (tag release) | Pilotes finaux + Déploiement Phase 1 |

### 8.3 Documentation

| Document | Emplacement | Mise à jour |
|----------|-------------|-------------|
| **README.md** | Racine du projet | Chaque sprint |
| **API Documentation** | `/docs/api/` | Chaque nouvelle API |
| **User Guides** | `/docs/guides/` | Sprint 2, 4, 6, 8 |
| **Architecture** | `docs/brownfield-architecture.md` | Si changements majeurs |

---

## 9. Critères de Succès Phase 1

### 9.1 Critères Fonctionnels

| Critère | Cible | Mesure | Status |
|---------|-------|--------|--------|
| **Workflow complet** | Inscription → Notes → Relevé | Demo end-to-end | 🎯 Sprint 8 |
| **Calcul automatique** | Zéro erreur de calcul | Tests unitaires (100% pass) | 🎯 Sprint 5-6 |
| **Génération PDF** | < 2 minutes/relevé | Tests de performance | 🎯 Sprint 8 |
| **Règles LMD** | Compensation correcte | Tests avec cas réels | 🎯 Sprint 6 |

### 9.2 Critères Non-Fonctionnels

| Critère | Cible | Mesure | Status |
|---------|-------|--------|--------|
| **Performance** | 100 relevés en < 30 sec | Tests de charge | 🎯 Sprint 8 |
| **Accessibilité** | WCAG AA | Audit accessibilité | 🎯 Sprint 7-8 |
| **Responsive** | Desktop + Tablette + Mobile | Tests manuels | 🎯 Chaque sprint |
| **Qualité code** | Coverage ≥ 80% (backend) | PHPUnit coverage | 🎯 Sprint 5-8 |

### 9.3 Critères d'Adoption

| Critère | Cible | Mesure | Status |
|---------|-------|--------|--------|
| **Pilotes actifs** | 2 établissements | Utilisation quotidienne | 🎯 Post-Sprint 8 |
| **Satisfaction** | NPS ≥ 50 (Admins) | Questionnaire | 🎯 Post-Sprint 8 |
| **Étudiants inscrits** | ≥ 200 au total | Compteur système | 🎯 Post-Sprint 8 |
| **Relevés générés** | ≥ 100 | Historique documents | 🎯 Post-Sprint 8 |

---

## 10. Post-Phase 1 : Prochaines Étapes

### 10.1 Semaine 17 (12-16 mai) : Buffer et Déploiement

- Corrections de bugs remontés par les pilotes
- Optimisations de performance
- Documentation finale (manuel utilisateur complet)
- Formation des admins pilotes (1h par établissement)
- **Déploiement en production** pour les 2 pilotes

### 10.2 Semaines 18-20 (19 mai - 6 juin) : Collecte Feedback

- Utilisation réelle par les pilotes (3 semaines)
- Support quotidien (Slack ou WhatsApp)
- Collecte de feedback structuré (interviews + questionnaires)
- Priorisation des bugs et améliorations

### 10.3 Semaines 21-24 (9 juin - 4 juil) : Planification Phase 2

- Analyse du feedback pilotes
- Priorisation des modules Phase 2 :
  - Emplois du Temps
  - Présences/Absences
  - Examens & Planning
- Rédaction des PRD Phase 2
- Estimation et planning Phase 2 (3 mois)

---

## Conclusion

Ce planning détaillé de **16 semaines (4 mois)** est réaliste et tient compte des dépendances, des risques, et des capacités de l'équipe. Les sprints sont équilibrés, avec des milestones clairs et des demos régulières aux pilotes.

**Facteurs de succès** :
- ✅ Backlog priorisé et stories bien définies
- ✅ Tests unitaires dès les sprints critiques (5-6)
- ✅ Spike PDF anticipé (Sprint 3)
- ✅ Demos intermédiaires pour feedback rapide
- ✅ Buffer intégré (20%) pour absorber les imprévus

**Prochaine action** : **Kickoff meeting le 13 janvier 2026** pour lancer le Sprint 1.

---

**Document créé par** : John (Product Manager Agent)
**Date** : 2026-01-07
**Version** : 1.0
**Statut** : Ready for Team Review and Kickoff
