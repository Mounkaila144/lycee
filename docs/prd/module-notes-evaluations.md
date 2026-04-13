# PRD - Module Notes & Evaluations

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Module** : Notes & Evaluations
> **Version** : v5
> **Date** : 2026-03-16
> **Phase** : Phase 1 - MVP Core
> **Priorite** : CRITIQUE

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 5.0 | Refonte complete - Passage du LMD (superieur) au secondaire (colleges/lycees Niger). Suppression ECTS, compensation, rattrapage. Ajout coefficients matieres, classement, appreciations, mentions conseil de classe. | John (PM) |
| 2026-01-07 | 1.0 | Creation initiale du PRD Module Notes (systeme LMD) | John (PM) |

---

## 1. Goals and Background Context

### 1.1 Goals

- **Digitaliser la saisie des notes** : Remplacer les cahiers de notes physiques et fichiers Excel par une saisie en ligne securisee, accessible par chaque enseignant pour ses matieres et classes
- **Automatiser le calcul des moyennes et classements** : Calcul automatique de la moyenne par matiere (avec ponderation configurable devoirs/compositions), de la moyenne generale semestrielle avec coefficients, et du classement (rang) par classe
- **Garantir zero erreur de calcul** : Eliminer les erreurs humaines qui touchent 10-15% des bulletins manuels (moyennes, classements, coefficients)
- **Gerer les appreciations et mentions** : Permettre aux enseignants de saisir des appreciations par matiere et au conseil de classe d'attribuer les mentions (Tableau d'honneur, Encouragements, Felicitations) et sanctions (Avertissement travail, Avertissement conduite, Blame)
- **Assurer la tracabilite** : Workflow de validation (Enseignant saisit, Admin valide, Publication) avec historique des modifications
- **Fournir les donnees aux bulletins** : Alimenter le Module Documents Officiels pour la generation automatique des bulletins semestriels et annuels

### 1.2 Background Context

Le **Module Notes & Evaluations** est le coeur du systeme pedagogique de Gestion Scolaire. Il resout le pain point majeur des colleges et lycees au Niger : la gestion manuelle des notes entraine des erreurs de calcul frequentes, des retards importants dans la production des bulletins (2-3 jours par classe vs < 5 minutes avec le systeme), et une impossibilite de fournir un suivi en temps reel aux parents.

Ce module s'inscrit dans la **Phase 1 MVP Core** car il est indispensable pour :
1. Demontrer la valeur immediate du systeme (calcul automatique = zero erreur, bulletins instantanes)
2. Fournir les donnees au Module Documents Officiels (generation des bulletins semestriels PDF)
3. Alimenter le Module Conseil de Classe (moyennes consolidees, statistiques, classements)
4. Permettre le suivi en temps reel par les parents via le Portail Parent (Phase 2)

Le module doit gerer les specificites du **systeme educatif secondaire nigerien** :
- **Deux semestres** par annee scolaire (S1, S2)
- **Coefficients par matiere** selon la classe et la serie (ex: Maths coeff 5 en Tle C, coeff 2 en Tle A)
- **Types d'evaluations** : Devoirs surveilles, Interrogations ecrites/orales, Compositions semestrielles, TP/Pratique
- **Moyenne par matiere** : Configurable (moyenne simple ou ponderee devoirs/compositions)
- **Moyenne generale** : Ponderee par les coefficients de chaque matiere
- **Classement** : Rang de l'eleve au sein de sa classe
- **Appreciations** : Par matiere (enseignant) + appreciation generale (conseil de classe)
- **Mentions** : Tableau d'honneur, Encouragements, Felicitations, Avertissement travail, Avertissement conduite, Blame
- **PAS de systeme de credits** (ECTS), PAS de compensation inter-matieres, PAS de session de rattrapage

---

## 2. Requirements

### 2.1 Functional Requirements (FR)

#### 2.1.1 Configuration des Evaluations par Matiere

- **FR1** : Le systeme doit permettre de definir les types d'evaluations pour chaque matiere et classe : Devoir surveille (DS), Interrogation ecrite (IE), Interrogation orale (IO), Composition semestrielle, TP/Pratique
- **FR2** : Chaque type d'evaluation doit avoir un poids (ponderation) configurable par l'Admin (ex: Compositions poids 2, Devoirs poids 1)
- **FR3** : L'Admin doit pouvoir configurer le mode de calcul de la moyenne par matiere au niveau du tenant :
  - **Mode simple** : Moyenne arithmetique de toutes les notes
  - **Mode pondere** : Moyenne ponderee selon les poids des types d'evaluations
- **FR4** : Le nombre d'evaluations par type et par matiere doit etre flexible (ex: 2 DS + 3 IE + 1 Composition)
- **FR5** : Les coefficients des matieres doivent etre definis dans le Module Structure Academique et utilises automatiquement pour le calcul de la moyenne generale

#### 2.1.2 Saisie des Notes par les Enseignants

- **FR6** : Un enseignant doit pouvoir saisir les notes uniquement pour les matieres et classes qui lui sont affectees
- **FR7** : Les notes doivent etre saisies sur 20 (0.00 a 20.00 avec 2 decimales)
- **FR8** : Le systeme doit permettre la saisie de notes pour une evaluation specifique (DS1, IE2, Composition, etc.)
- **FR9** : Le systeme doit supporter la saisie par eleve individuel ou par import CSV/Excel
- **FR10** : Un enseignant peut sauvegarder des notes en brouillon (non publiees) et les modifier a tout moment tant qu'elles ne sont pas soumises
- **FR11** : Le systeme doit afficher la moyenne de la matiere en temps reel pendant la saisie pour chaque eleve
- **FR12** : Un enseignant doit pouvoir marquer un eleve comme "Absent" a une evaluation (note = ABS)
- **FR13** : Un enseignant ne peut plus modifier les notes une fois qu'elles sont validees par l'Admin

#### 2.1.3 Saisie des Appreciations par Matiere

- **FR14** : Pour chaque eleve et chaque matiere, l'enseignant doit pouvoir saisir une appreciation textuelle (ex: "Bon travail, continue ainsi", "Efforts insuffisants, doit travailler davantage")
- **FR15** : Les appreciations doivent etre saisies pour chaque semestre
- **FR16** : Le systeme doit proposer des appreciations predefinies configurables par le tenant (ex: "Excellent", "Tres bien", "Bien", "Assez bien", "Passable", "Insuffisant", "Tres insuffisant") tout en permettant la saisie libre
- **FR17** : Les appreciations doivent etre soumises avec les notes dans le meme workflow de validation

#### 2.1.4 Calcul Automatique de la Moyenne par Matiere

- **FR18** : Le systeme doit calculer automatiquement la moyenne de la matiere pour chaque eleve et chaque semestre selon le mode configure :
  - **Mode simple** : `Moyenne Matiere = Somme(notes) / Nombre(notes)`
  - **Mode pondere** : `Moyenne Matiere = Somme(note x poids_evaluation) / Somme(poids_evaluations)`
- **FR19** : Si un eleve est absent a une evaluation (ABS), le systeme doit :
  - Traiter l'absence comme un zero (0/20) par defaut
  - OU exclure cette evaluation du calcul (option configurable par le tenant)
- **FR20** : La moyenne de la matiere doit etre arrondie a 2 decimales
- **FR21** : Le systeme doit recalculer automatiquement les moyennes a chaque modification de note

#### 2.1.5 Calcul de la Moyenne Generale Semestrielle

- **FR22** : Le systeme doit calculer automatiquement la moyenne generale semestrielle de chaque eleve selon la formule :
  ```
  Moyenne Generale = Somme(Moyenne_Matiere x Coefficient_Matiere) / Somme(Coefficients)
  ```
- **FR23** : Les coefficients utilises sont ceux definis dans le Module Structure Academique pour la classe et la serie de l'eleve
- **FR24** : La moyenne generale doit etre arrondie a 2 decimales
- **FR25** : Le systeme doit recalculer la moyenne generale chaque fois qu'une moyenne de matiere est mise a jour

#### 2.1.6 Classement (Rang) par Classe

- **FR26** : Le systeme doit calculer automatiquement le rang de chaque eleve au sein de sa classe, base sur la moyenne generale semestrielle
- **FR27** : En cas d'egalite de moyennes generales, les eleves concernes doivent avoir le meme rang (ex-aequo), et le rang suivant est ajuste (ex: si 2 eleves sont 3e, le suivant est 5e)
- **FR28** : Le classement doit etre recalcule automatiquement apres chaque mise a jour de la moyenne generale
- **FR29** : Le systeme doit aussi calculer les statistiques de classe : moyenne de la classe, note la plus haute, note la plus basse, nombre d'eleves ayant la moyenne (>= 10/20)

#### 2.1.7 Moyenne Annuelle

- **FR30** : Le systeme doit calculer la moyenne annuelle de chaque eleve par matiere :
  ```
  Moyenne Annuelle Matiere = (Moyenne S1 + Moyenne S2) / 2
  ```
- **FR31** : Le systeme doit calculer la moyenne generale annuelle :
  ```
  Moyenne Generale Annuelle = Somme(Moyenne_Annuelle_Matiere x Coefficient) / Somme(Coefficients)
  ```
  OU (option configurable) :
  ```
  Moyenne Generale Annuelle = (Moyenne Generale S1 + Moyenne Generale S2) / 2
  ```
- **FR32** : Le classement annuel doit etre calcule de la meme maniere que le classement semestriel

#### 2.1.8 Mentions et Decisions du Conseil de Classe

- **FR33** : Le systeme doit permettre d'attribuer des mentions positives aux eleves :
  - **Tableau d'honneur** : Moyenne >= seuil configurable (defaut: 14/20) ET bonne conduite
  - **Encouragements** : Progres significatifs ou efforts soutenus
  - **Felicitations** : Moyenne >= seuil configurable (defaut: 16/20) ET tres bonne conduite
- **FR34** : Le systeme doit permettre d'attribuer des sanctions academiques :
  - **Avertissement travail** : Resultats insuffisants
  - **Avertissement conduite** : Comportement inadequat
  - **Blame** : Manquements graves et repetes
- **FR35** : Les seuils des mentions doivent etre configurables par le tenant
- **FR36** : L'attribution des mentions et sanctions doit etre effectuee lors du conseil de classe (integration avec Module Conseil de Classe)
- **FR37** : Le systeme doit proposer automatiquement les mentions basees sur la moyenne, tout en permettant a l'Admin de les ajuster manuellement

#### 2.1.9 Appreciation Generale du Conseil de Classe

- **FR38** : Le systeme doit permettre de saisir une appreciation generale par eleve lors du conseil de classe (ex: "Eleve serieux et travailleur", "Doit fournir plus d'efforts au second semestre")
- **FR39** : L'appreciation generale doit etre saisie par le president du conseil de classe (Admin/Directeur ou Professeur principal)
- **FR40** : L'appreciation generale apparait sur le bulletin semestriel

#### 2.1.10 Workflow de Validation et Publication

- **FR41** : Le workflow de publication doit suivre : **Enseignant (Brouillon) -> Admin (Validation) -> Publication**
- **FR42** : Un enseignant doit pouvoir soumettre les notes et appreciations d'une matiere/classe pour validation (statut = "En attente de validation")
- **FR43** : Un Admin doit pouvoir consulter les notes soumises et les valider ou les rejeter
- **FR44** : Si l'Admin rejete les notes, elles retournent en brouillon avec un commentaire de rejet visible par l'enseignant
- **FR45** : Une fois toutes les notes validees pour une classe et un semestre, l'Admin peut publier les resultats (calcul des moyennes generales, classement, et visibilite pour eleves/parents)
- **FR46** : L'historique des modifications de notes doit etre conserve (qui, quand, ancienne/nouvelle valeur)

#### 2.1.11 Consultation des Resultats

- **FR47** : Un enseignant doit pouvoir consulter les notes qu'il a saisies pour ses matieres (brouillon, validees, publiees) avec statistiques de classe
- **FR48** : Un Admin doit pouvoir consulter toutes les notes de toutes les matieres, toutes les classes, avec tableaux recapitulatifs
- **FR49** : Un eleve doit pouvoir consulter ses notes publiees uniquement (pas les brouillons), avec sa moyenne par matiere, sa moyenne generale, et son rang
- **FR50** : Un parent doit pouvoir consulter les memes informations que l'eleve pour chacun de ses enfants

#### 2.1.12 Exports et Rapports

- **FR51** : Le systeme doit permettre d'exporter les notes d'une matiere/classe en CSV ou Excel (pour l'enseignant et l'Admin)
- **FR52** : Le systeme doit permettre d'exporter le recapitulatif complet d'une classe (toutes matieres, moyennes, rang) en Excel
- **FR53** : Le systeme doit fournir une API interne pour que le Module Documents Officiels puisse recuperer les donnees necessaires a la generation des bulletins semestriels et annuels
- **FR54** : Le systeme doit fournir les statistiques de classe : moyenne de la classe, taux de reussite (% >= 10/20), repartition par tranche de notes

---

### 2.2 Non-Functional Requirements (NFR)

- **NFR1** : Le calcul des moyennes et classement pour une classe de 80 eleves doit s'effectuer en moins de 3 secondes (performance)
- **NFR2** : Le systeme doit empecher la modification de notes publiees sans trace dans l'historique (securite)
- **NFR3** : Les formules de calcul doivent etre documentees et testees unitairement avec des cas limites (maintenabilite)
- **NFR4** : Le systeme doit supporter 50 enseignants saisissant des notes simultanement sans degradation (scalabilite)
- **NFR5** : La configuration des ponderations et coefficients doit etre stockee en base par tenant (configurabilite)
- **NFR6** : Le temps de reponse pour afficher les notes d'un eleve doit etre < 500ms (UX)
- **NFR7** : Le systeme doit fonctionner sur connexion 3G avec optimisation des requetes (contexte Niger)
- **NFR8** : Tous les calculs doivent etre testes avec des cas limites : moyennes exactes a 10.00, 9.99, absences multiples, ex-aequo dans le classement, coefficients differents (fiabilite)
- **NFR9** : Les appreciations doivent supporter les caracteres speciaux et accents du francais (internationalisation)

---

## 3. User Interface Design Goals

### 3.1 Overall UX Vision

L'interface du Module Notes doit etre **simple, rapide, et rassurante** pour les enseignants qui l'utilisent quotidiennement. L'accent est mis sur :
- **Feedback immediat** : Calcul de moyenne en temps reel pendant la saisie
- **Prevention d'erreurs** : Validation des saisies (0-20, pas de caracteres invalides)
- **Visibilite du statut** : Etats clairement identifies (Brouillon, En attente, Valide, Publie)
- **Indicateurs visuels** : Couleurs pour les seuils de performance (Vert >= 14, Bleu >= 10, Orange >= 8, Rouge < 8)

### 3.2 Key Interaction Paradigms

- **Saisie en tableau** : Liste des eleves avec colonnes pour chaque evaluation (type Excel)
- **Edition inline** : Clic sur une cellule pour modifier directement la note
- **Calcul temps reel** : Colonne "Moyenne" qui se met a jour automatiquement
- **Actions par lot** : Import CSV pour saisie rapide de nombreuses notes
- **Saisie appreciation** : Champ texte par eleve avec suggestions predefinies
- **Workflow visuel** : Boutons "Sauvegarder brouillon" / "Soumettre pour validation" (selon le role)

### 3.3 Core Screens and Views

#### 3.3.1 Ecran Enseignant : Liste des Matieres/Classes Assignees
- Tableau avec colonnes : Matiere, Classe, Semestre, Nombre d'eleves, Statut notes (Brouillon/En attente/Valide/Publie)
- Bouton "Saisir notes" pour acceder a l'ecran de saisie
- Filtres : Par semestre, par classe

#### 3.3.2 Ecran Enseignant : Saisie de Notes pour une Matiere/Classe
- En-tete : Nom de la matiere, Classe, Semestre, Coefficient de la matiere
- Tableau de saisie :
  - Colonnes : N, Nom eleve, Prenom, DS1 (poids 1), DS2 (poids 1), IE1 (poids 0.5), Composition (poids 2), Moyenne (calculee), Appreciation
  - Ligne par eleve avec edition inline
  - Possibilite de marquer "ABS" (absent)
- Zone appreciation : Champ texte par eleve avec suggestions
- Actions :
  - Telecharger template CSV
  - Import CSV
  - Sauvegarder brouillon
  - Soumettre pour validation (si toutes les notes saisies)

#### 3.3.3 Ecran Admin : Validation des Notes
- Liste des matieres/classes avec notes "En attente de validation"
- Pour chaque soumission : Enseignant, Matiere, Classe, Semestre, Nombre d'eleves, Date de soumission
- Previsualisation des notes saisies avec statistiques (moyenne, min, max, taux reussite)
- Actions : Valider / Rejeter (avec commentaire obligatoire si rejet)

#### 3.3.4 Ecran Admin : Publication des Resultats d'un Semestre
- Selection : Classe + Semestre
- Tableau recapitulatif : Toutes les matieres avec statut de validation
- Verification : Toutes les matieres doivent etre validees avant publication
- Bouton "Publier les resultats" avec confirmation
- Recapitulatif post-publication : Moyenne de la classe, nombre d'eleves ayant la moyenne, premier/dernier

#### 3.3.5 Ecran Admin : Tableau Recapitulatif de Classe
- Vue complete d'une classe pour un semestre :
  - Lignes : Eleves (tries par rang)
  - Colonnes : Chaque matiere (moyenne + appreciation), Moyenne Generale, Rang
  - Derniere ligne : Moyenne de la classe par matiere
- Export Excel possible
- Lien vers le conseil de classe

#### 3.3.6 Ecran Eleve : Consultation de ses Notes
- Onglets par semestre (S1, S2)
- Tableau des matieres avec : Matiere, Coefficient, Notes detaillees (DS, IE, Composition), Moyenne de la matiere, Appreciation enseignant
- Resume du semestre :
  - Moyenne generale
  - Rang dans la classe (ex: "12e / 55")
  - Mention obtenue (si applicable)
  - Appreciation generale du conseil de classe
- Onglet "Annuel" : Moyennes annuelles, rang annuel

#### 3.3.7 Ecran Parent : Consultation des Notes de l'Enfant
- Meme interface que l'eleve mais accessible via le compte parent
- Si multi-enfants : selecteur d'enfant en haut de page
- Indicateurs visuels renforces (couleurs, fleches evolution entre S1 et S2)

#### 3.3.8 Ecran Admin : Configuration des Evaluations et Calculs
- Formulaire de configuration par tenant :
  - Mode de calcul de la moyenne par matiere : Simple / Pondere
  - Traitement des absences : Zero / Exclure du calcul
  - Mode de calcul de la moyenne annuelle : Moyenne des generales / Recalcul avec moyennes annuelles par matiere
- Gestion des types d'evaluations :
  - Types disponibles : DS, IE, IO, Composition, TP/Pratique
  - Poids par defaut de chaque type
- Seuils des mentions :
  - Tableau d'honneur : Moyenne >= X (defaut 14)
  - Felicitations : Moyenne >= X (defaut 16)

### 3.4 Accessibility

**WCAG AA** : Le systeme doit respecter les standards d'accessibilite WCAG 2.1 niveau AA :
- Contraste de couleurs suffisant (ratio 4.5:1 minimum)
- Navigation au clavier complete (critique pour la saisie en tableau)
- Labels ARIA pour les elements interactifs
- Messages d'erreur clairs et accessibles aux lecteurs d'ecran

### 3.5 Branding

- Interface professionnelle et epuree
- Couleurs :
  - **Vert (#4CAF50)** : Excellents resultats (>= 14), actions positives, mentions
  - **Bleu (#2196F3)** : Resultats satisfaisants (>= 10), actions primaires
  - **Orange (#FF9800)** : Resultats mediocres (8-10), avertissements
  - **Rouge (#F44336)** : Resultats insuffisants (< 8), erreurs
- Typographie : Police sans-serif moderne et lisible (Roboto, Inter, ou similaire)

### 3.6 Target Device and Platforms

**Web Responsive** :
- Desktop (prioritaire) : Optimise pour la saisie de notes sur grand ecran (enseignants et admins)
- Tablette : Interface adaptee pour consultation et saisie legere
- Mobile : Consultation des notes et appreciations pour les eleves et parents principalement

---

## 4. Technical Assumptions

### 4.1 Repository Structure

**Polyrepo** : Backend (Laravel) et Frontend (Next.js) dans des depots separes (architecture existante).

### 4.2 Service Architecture

**Architecture modulaire (nwidart/laravel-modules)** :
- Module Laravel : `Modules/Notes/`
- Structure standard :
  - `Entities/` : Models Eloquent (Evaluation, Grade, SemesterAverage, ClassRanking, Appreciation)
  - `Http/Controllers/` : Controllers Admin/Frontend
  - `Http/Requests/` : Form Requests pour validation
  - `Http/Resources/` : API Resources
  - `Database/Migrations/tenant/` : Migrations tenant
  - `Database/Factories/` : Factories pour les tests
  - `Routes/` : Routes admin.php, frontend.php
  - `Services/` : GradeCalculationService, RankingService
  - `Tests/` : Tests unitaires et feature

**Frontend Next.js** :
- Module : `src/modules/Notes/`
- Structure en 3 couches : `admin/`, `frontend/`, `types/`
- Services API avec `createApiClient()`
- Hooks React pour gestion de l'etat et calcul temps reel

### 4.3 Base de donnees

**Connexion** : `tenant` (base de donnees tenant dynamique)

**Tables a creer** :

#### Table `evaluations`
Definit les evaluations configurees pour une matiere, une classe, et un semestre.

| Colonne | Type | Description |
|---------|------|-------------|
| id | bigint unsigned PK | Identifiant unique |
| subject_id | bigint unsigned FK | Matiere (table `subjects`) |
| class_id | bigint unsigned FK | Classe (table `classes`) |
| semester_id | bigint unsigned FK | Semestre (table `semesters`) |
| type | enum | 'ds', 'ie', 'io', 'composition', 'tp' |
| name | varchar(100) | Nom affiche (ex: "DS1", "Composition S1") |
| weight | decimal(3,1) | Poids/ponderation de l'evaluation (defaut: 1.0) |
| max_grade | decimal(4,2) | Note maximale (defaut: 20.00) |
| evaluation_date | date nullable | Date de l'evaluation |
| created_by | bigint unsigned FK | Utilisateur ayant cree |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp nullable | SoftDelete |

#### Table `grades`
Stocke chaque note individuelle d'un eleve pour une evaluation.

| Colonne | Type | Description |
|---------|------|-------------|
| id | bigint unsigned PK | Identifiant unique |
| evaluation_id | bigint unsigned FK | Evaluation (table `evaluations`) |
| student_id | bigint unsigned FK | Eleve (table `students`) |
| grade | decimal(4,2) nullable | Note obtenue (0.00 a 20.00, null si ABS) |
| is_absent | boolean | True si l'eleve etait absent (defaut: false) |
| status | enum | 'draft', 'submitted', 'validated', 'published' |
| graded_by | bigint unsigned FK | Enseignant ayant saisi la note |
| graded_at | timestamp nullable | Date/heure de saisie |
| validated_by | bigint unsigned FK nullable | Admin ayant valide |
| validated_at | timestamp nullable | Date/heure de validation |
| rejection_comment | text nullable | Commentaire en cas de rejet |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp nullable | SoftDelete |

#### Table `subject_appreciations`
Stocke les appreciations textuelles par matiere, par eleve, par semestre.

| Colonne | Type | Description |
|---------|------|-------------|
| id | bigint unsigned PK | Identifiant unique |
| student_id | bigint unsigned FK | Eleve |
| subject_id | bigint unsigned FK | Matiere |
| class_id | bigint unsigned FK | Classe |
| semester_id | bigint unsigned FK | Semestre |
| appreciation | text | Texte de l'appreciation |
| created_by | bigint unsigned FK | Enseignant |
| created_at | timestamp | |
| updated_at | timestamp | |

#### Table `semester_averages`
Stocke les moyennes calculees par matiere et la moyenne generale par semestre.

| Colonne | Type | Description |
|---------|------|-------------|
| id | bigint unsigned PK | Identifiant unique |
| student_id | bigint unsigned FK | Eleve |
| class_id | bigint unsigned FK | Classe |
| semester_id | bigint unsigned FK | Semestre |
| subject_id | bigint unsigned FK nullable | Matiere (null = moyenne generale) |
| average | decimal(4,2) | Moyenne calculee |
| coefficient | decimal(3,1) nullable | Coefficient de la matiere (null pour generale) |
| total_points | decimal(6,2) nullable | Total points (moyenne x coefficient) |
| rank | int unsigned nullable | Rang dans la classe (uniquement pour moyenne generale) |
| total_students | int unsigned nullable | Nombre total d'eleves classes |
| class_average | decimal(4,2) nullable | Moyenne de la classe (pour cette matiere ou generale) |
| class_min | decimal(4,2) nullable | Note minimale de la classe |
| class_max | decimal(4,2) nullable | Note maximale de la classe |
| created_at | timestamp | |
| updated_at | timestamp | |

#### Table `class_council_decisions`
Stocke les mentions, appreciations generales et decisions du conseil de classe.

| Colonne | Type | Description |
|---------|------|-------------|
| id | bigint unsigned PK | Identifiant unique |
| student_id | bigint unsigned FK | Eleve |
| class_id | bigint unsigned FK | Classe |
| semester_id | bigint unsigned FK | Semestre |
| mention | enum nullable | 'tableau_honneur', 'encouragements', 'felicitations', null |
| sanction | enum nullable | 'avertissement_travail', 'avertissement_conduite', 'blame', null |
| general_appreciation | text nullable | Appreciation generale du conseil de classe |
| decided_by | bigint unsigned FK | President du conseil (Admin/PP) |
| decided_at | timestamp | Date du conseil |
| created_at | timestamp | |
| updated_at | timestamp | |

#### Table `grade_configs`
Configuration des regles de calcul par tenant.

| Colonne | Type | Description |
|---------|------|-------------|
| id | bigint unsigned PK | Identifiant unique |
| average_calculation_mode | enum | 'simple', 'weighted' (defaut: 'weighted') |
| absence_treatment | enum | 'zero', 'exclude' (defaut: 'zero') |
| annual_average_mode | enum | 'average_of_generals', 'recalculate_subjects' |
| honor_roll_threshold | decimal(4,2) | Seuil Tableau d'honneur (defaut: 14.00) |
| congratulations_threshold | decimal(4,2) | Seuil Felicitations (defaut: 16.00) |
| created_at | timestamp | |
| updated_at | timestamp | |

#### Table `grade_history`
Historique de toutes les modifications de notes pour tracabilite.

| Colonne | Type | Description |
|---------|------|-------------|
| id | bigint unsigned PK | Identifiant unique |
| grade_id | bigint unsigned FK | Note modifiee |
| old_value | decimal(4,2) nullable | Ancienne note |
| new_value | decimal(4,2) nullable | Nouvelle note |
| old_status | varchar(20) | Ancien statut |
| new_status | varchar(20) | Nouveau statut |
| changed_by | bigint unsigned FK | Utilisateur ayant modifie |
| reason | text nullable | Raison de la modification |
| changed_at | timestamp | Date/heure de modification |

**Relations cles** :
- `grades` belongsTo `students`, `evaluations`, `users` (enseignant)
- `evaluations` belongsTo `subjects`, `classes`, `semesters`
- `semester_averages` belongsTo `students`, `classes`, `semesters`, `subjects` (nullable)
- `subject_appreciations` belongsTo `students`, `subjects`, `classes`, `semesters`
- `class_council_decisions` belongsTo `students`, `classes`, `semesters`
- Utiliser **eager loading** pour eviter les N+1 queries

### 4.4 Formules de Calcul Detaillees

#### 4.4.1 Moyenne par Matiere (Mode Pondere)

```
Moyenne Matiere = Somme(note_i x poids_i) / Somme(poids_i)
```

**Exemple** : Mathematiques, eleve Amadou, Semestre 1
| Evaluation | Note | Poids |
|-----------|------|-------|
| DS1 | 14.00 | 1.0 |
| DS2 | 12.00 | 1.0 |
| IE1 | 16.00 | 0.5 |
| IE2 | 10.00 | 0.5 |
| Composition | 11.00 | 2.0 |

```
Moyenne = (14x1 + 12x1 + 16x0.5 + 10x0.5 + 11x2) / (1 + 1 + 0.5 + 0.5 + 2)
        = (14 + 12 + 8 + 5 + 22) / 5
        = 61 / 5
        = 12.20
```

#### 4.4.2 Moyenne par Matiere (Mode Simple)

```
Moyenne Matiere = Somme(notes) / Nombre(notes)
```

**Meme exemple** :
```
Moyenne = (14 + 12 + 16 + 10 + 11) / 5 = 63 / 5 = 12.60
```

#### 4.4.3 Moyenne Generale Semestrielle

```
Moyenne Generale = Somme(Moyenne_Matiere_j x Coefficient_j) / Somme(Coefficient_j)
```

**Exemple** : Eleve Amadou, Classe Tle D, Semestre 1
| Matiere | Moyenne | Coefficient | Points (Moy x Coef) |
|---------|---------|-------------|---------------------|
| Mathematiques | 12.20 | 4 | 48.80 |
| Sciences Physiques | 14.50 | 3 | 43.50 |
| SVT | 11.00 | 3 | 33.00 |
| Francais | 09.50 | 3 | 28.50 |
| Anglais | 13.00 | 2 | 26.00 |
| Philosophie | 10.00 | 2 | 20.00 |
| EPS | 15.00 | 1 | 15.00 |

```
Moyenne Generale = (48.80 + 43.50 + 33.00 + 28.50 + 26.00 + 20.00 + 15.00) / (4 + 3 + 3 + 3 + 2 + 2 + 1)
                 = 214.80 / 18
                 = 11.93
```

#### 4.4.4 Classement

Les eleves d'une classe sont tries par moyenne generale decroissante.
- Rang 1 = meilleure moyenne
- En cas d'egalite : meme rang, le suivant saute (ex: 1er, 2e, 2e, 4e)

#### 4.4.5 Moyenne Annuelle

**Mode 1 - Moyenne des moyennes generales** :
```
Moyenne Annuelle = (Moyenne Generale S1 + Moyenne Generale S2) / 2
```

**Mode 2 - Recalcul par matiere** :
```
Moyenne Annuelle Matiere = (Moyenne Matiere S1 + Moyenne Matiere S2) / 2
Moyenne Generale Annuelle = Somme(Moyenne_Annuelle_Matiere x Coefficient) / Somme(Coefficients)
```

### 4.5 Testing Requirements

**Tests obligatoires** :
- **Tests unitaires** : Formules de calcul (moyenne simple, ponderee, generale, classement, annuelle)
- **Tests unitaires** : Cas limites (ABS traite en zero vs exclu, ex-aequo, un seul eleve, moyennes identiques)
- **Tests d'integration** : Workflow complet (Saisie -> Validation -> Publication -> Consultation)
- **Tests d'integration** : Recalcul automatique a chaque modification
- **Tests de performance** : Calcul moyennes et classement pour classe de 80 eleves en < 3s
- **Tests de non-regression** : Modification d'une note -> recalcul en cascade (moyenne matiere -> generale -> classement)

**Outils** :
- Laravel : PHPUnit (`php artisan test`)
- Frontend : Jest + React Testing Library

### 4.6 API Endpoints

#### Admin Endpoints

| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/grades/config` | Recuperer la configuration de calcul du tenant |
| PUT | `/api/admin/grades/config` | Mettre a jour la configuration de calcul |
| GET | `/api/admin/grades/evaluations` | Lister les evaluations (filtres: class_id, subject_id, semester_id) |
| POST | `/api/admin/grades/evaluations` | Creer une evaluation |
| PUT | `/api/admin/grades/evaluations/{id}` | Modifier une evaluation |
| DELETE | `/api/admin/grades/evaluations/{id}` | Supprimer une evaluation (si aucune note saisie) |
| GET | `/api/admin/grades/submissions` | Lister les soumissions en attente de validation |
| POST | `/api/admin/grades/submissions/{id}/validate` | Valider une soumission |
| POST | `/api/admin/grades/submissions/{id}/reject` | Rejeter une soumission (avec commentaire) |
| POST | `/api/admin/grades/publish` | Publier les resultats d'une classe/semestre |
| GET | `/api/admin/grades/class-summary/{class_id}/{semester_id}` | Recapitulatif complet d'une classe |
| GET | `/api/admin/grades/class-stats/{class_id}/{semester_id}` | Statistiques de classe |
| GET | `/api/admin/grades/student/{student_id}/semester/{semester_id}` | Releve de notes d'un eleve (pour bulletins) |
| GET | `/api/admin/grades/student/{student_id}/annual/{academic_year_id}` | Releve annuel d'un eleve |
| GET | `/api/admin/grades/export/class/{class_id}/{semester_id}` | Export Excel recapitulatif classe |
| GET | `/api/admin/grades/history/{grade_id}` | Historique des modifications d'une note |
| POST | `/api/admin/grades/council-decisions` | Enregistrer les decisions du conseil de classe (mentions, appreciations) |
| PUT | `/api/admin/grades/council-decisions/{id}` | Modifier une decision du conseil |

#### Enseignant Endpoints

| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/grades/my-assignments` | Lister mes matieres/classes assignees avec statut des notes |
| GET | `/api/admin/grades/entry/{class_id}/{subject_id}/{semester_id}` | Recuperer la grille de saisie (eleves + evaluations + notes existantes) |
| POST | `/api/admin/grades/entry` | Sauvegarder des notes (brouillon) |
| PUT | `/api/admin/grades/entry/{grade_id}` | Modifier une note (si brouillon) |
| POST | `/api/admin/grades/entry/import` | Importer des notes via CSV |
| GET | `/api/admin/grades/entry/template/{class_id}/{subject_id}/{semester_id}` | Telecharger le template CSV |
| POST | `/api/admin/grades/entry/submit` | Soumettre les notes pour validation |
| POST | `/api/admin/grades/appreciations` | Sauvegarder les appreciations par matiere |
| GET | `/api/admin/grades/stats/{class_id}/{subject_id}/{semester_id}` | Statistiques d'une matiere/classe |

#### Frontend (Eleve/Parent) Endpoints

| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/frontend/grades/my-grades/{semester_id}` | Notes publiees de l'eleve pour un semestre |
| GET | `/api/frontend/grades/my-summary/{semester_id}` | Resume semestriel (moyenne, rang, mention) |
| GET | `/api/frontend/grades/my-annual/{academic_year_id}` | Resultats annuels |
| GET | `/api/frontend/grades/child/{student_id}/grades/{semester_id}` | Notes d'un enfant (parent) |
| GET | `/api/frontend/grades/child/{student_id}/summary/{semester_id}` | Resume semestriel d'un enfant (parent) |

### 4.7 Additional Technical Assumptions

- **API REST** : Endpoints suivant les conventions existantes
- **Middleware** : `['tenant', 'tenant.auth']` pour toutes les routes
- **Permissions** : Utiliser Spatie Permission pour controle d'acces :
  - `manage-grades` : Admin (validation, publication, configuration)
  - `input-grades` : Enseignant (saisie des notes et appreciations)
  - `view-grades` : Eleve/Parent (consultation des notes publiees)
  - `manage-council` : Admin (decisions du conseil de classe)
- **Validation** : Form Requests pour toutes les saisies (StoreGradeRequest, UpdateGradeRequest, StoreAppreciationRequest, SubmitGradesRequest, PublishGradesRequest, StoreCouncilDecisionRequest)
- **API Resources** : Retourner toujours des Resources, jamais de models bruts (GradeResource, EvaluationResource, SemesterAverageResource, ClassSummaryResource, StudentReportResource)
- **SoftDeletes** : Utiliser sur evaluations et grades
- **Casts Laravel 12** : Utiliser `casts()` method sur les models
- **Format JSON** : Reponses standardisees avec `success`, `message`, `data`
- **Service Layer** : `GradeCalculationService` pour toute la logique de calcul (separation controlleur/logique metier)
- **Events** : Emettre des events Laravel lors de la publication des resultats (pour notifications futures)

---

## 5. Epic List

### Epic 1 : Configuration des Evaluations et Regles de Calcul
**Goal** : Permettre a l'Admin de configurer les regles de calcul des moyennes, les types d'evaluations, et les seuils de mentions, etablissant les fondations parametrables du systeme de notation.

### Epic 2 : Saisie des Notes et Appreciations par les Enseignants
**Goal** : Permettre aux enseignants de saisir les notes et appreciations de leurs eleves pour chaque evaluation definie, avec calcul automatique des moyennes en temps reel.

### Epic 3 : Workflow de Validation et Publication
**Goal** : Implementer le workflow de validation Admin et publication des resultats, incluant le calcul des moyennes generales, classements, et visibilite pour les eleves et parents.

### Epic 4 : Calcul des Moyennes, Classements et Statistiques
**Goal** : Calculer automatiquement les moyennes par matiere, les moyennes generales semestrielles avec coefficients, le classement par classe, et les statistiques de classe.

### Epic 5 : Mentions et Decisions du Conseil de Classe
**Goal** : Permettre l'attribution des mentions (Tableau d'honneur, Encouragements, Felicitations) et sanctions (Avertissements, Blame), ainsi que la saisie de l'appreciation generale du conseil de classe.

### Epic 6 : Consultation et Exports
**Goal** : Fournir des interfaces de consultation pour tous les roles (Enseignant, Admin, Eleve, Parent) et des exports CSV/Excel pour les enseignants et admins, ainsi que l'API pour la generation des bulletins.

### Epic 7 : Moyenne Annuelle et Resultats de Fin d'Annee
**Goal** : Calculer les moyennes annuelles (par matiere et generale), le classement annuel, et fournir les donnees necessaires au bulletin annuel et aux decisions de passage/redoublement.

---

## 6. Epic Details

### Epic 1 : Configuration des Evaluations et Regles de Calcul

**Goal detaille** : Avant toute saisie de notes, l'Admin doit pouvoir configurer les regles de calcul des moyennes (mode simple ou pondere, traitement des absences), definir les types d'evaluations disponibles avec leurs poids, et parametrer les seuils pour l'attribution automatique des mentions. Cette epic pose les fondations du systeme de notation.

#### Story 1.1 : Configuration des Regles de Calcul par Tenant

**As an** Admin,
**I want** configurer les regles de calcul des moyennes pour mon etablissement,
**so that** le systeme applique les bonnes formules et les bons seuils.

**Acceptance Criteria** :
1. Un ecran "Configuration des notes" est accessible uniquement par l'Admin
2. Je peux choisir le mode de calcul de la moyenne par matiere : "Simple" ou "Pondere" (defaut: Pondere)
3. Je peux choisir le traitement des absences : "Compter comme zero" ou "Exclure du calcul" (defaut: Zero)
4. Je peux choisir le mode de calcul de la moyenne annuelle : "Moyenne des generales" ou "Recalcul par matiere"
5. Je peux definir le seuil du Tableau d'honneur (champ numerique, defaut: 14.00)
6. Je peux definir le seuil des Felicitations (champ numerique, defaut: 16.00)
7. Les parametres sont enregistres dans la table `grade_configs`
8. Un message de confirmation s'affiche apres sauvegarde
9. Les parametres sont appliques automatiquement lors de tous les calculs

**Dependencies** : Module UsersGuard (auth Admin), Module Structure Academique

---

#### Story 1.2 : Definition des Types d'Evaluations par Matiere/Classe

**As an** Admin ou Enseignant,
**I want** definir les evaluations pour chaque matiere et classe (DS1, DS2, IE, Composition, etc.) avec leurs poids,
**so that** le systeme calcule correctement la moyenne de la matiere selon les ponderations.

**Acceptance Criteria** :
1. Pour un couple matiere/classe/semestre, je peux acceder a un ecran "Configurer les evaluations"
2. Je peux ajouter des evaluations avec : Type (DS, IE, IO, Composition, TP), Nom (ex: "DS1"), Poids (numerique, defaut: 1.0), Date (optionnelle)
3. Les evaluations sont stockees dans la table `evaluations`
4. Je peux modifier ou supprimer une evaluation tant qu'aucune note n'a ete saisie pour celle-ci
5. Si des notes existent deja, seule la modification du poids est autorisee (pas la suppression)
6. Un recapitulatif affiche la liste des evaluations avec leurs poids
7. L'Admin peut creer des evaluations pour n'importe quelle matiere/classe ; l'Enseignant uniquement pour ses affectations

**Dependencies** : Module Structure Academique (tables `subjects`, `classes`, `semesters`)

---

#### Story 1.3 : Gestion des Appreciations Predefinies

**As an** Admin,
**I want** configurer une liste d'appreciations predefinies pour faciliter la saisie par les enseignants,
**so that** les appreciations soient coherentes et la saisie plus rapide.

**Acceptance Criteria** :
1. Un ecran permet de gerer les appreciations predefinies (CRUD)
2. Par defaut, les appreciations predefinies sont : "Excellent", "Tres bien", "Bien", "Assez bien", "Passable", "Insuffisant", "Tres insuffisant", "Travail serieux", "Peut mieux faire", "Efforts insuffisants", "Doit travailler davantage"
3. L'Admin peut ajouter, modifier, supprimer des appreciations predefinies
4. Les enseignants voient ces appreciations en suggestion lors de la saisie (autocompletion), tout en pouvant saisir un texte libre
5. Les appreciations predefinies sont stockees en base avec scope tenant

**Dependencies** : Story 1.1

---

### Epic 2 : Saisie des Notes et Appreciations par les Enseignants

**Goal detaille** : Les enseignants doivent pouvoir saisir les notes de leurs eleves pour chaque evaluation definie, ainsi que les appreciations par matiere, avec un calcul automatique en temps reel de la moyenne de la matiere. Cette epic garantit zero erreur de calcul et une experience de saisie fluide.

#### Story 2.1 : Liste des Matieres/Classes Assignees a un Enseignant

**As an** Enseignant,
**I want** voir la liste des matieres et classes qui me sont assignees,
**so that** je puisse acceder rapidement a la saisie de notes.

**Acceptance Criteria** :
1. Je vois un tableau avec : Matiere, Classe, Semestre, Nombre d'eleves, Statut des notes (Pas de notes/Brouillon/En attente/Valide/Publie)
2. Seules les matieres et classes ou je suis affecte comme enseignant sont affichees (relation `teacher_assignments`)
3. Un bouton "Saisir notes" est disponible pour chaque ligne
4. Le statut des notes utilise un badge de couleur (Gris=Pas de notes, Jaune=Brouillon, Orange=En attente, Vert=Valide, Bleu=Publie)
5. Le tableau est filtrable par semestre et par classe
6. Le coefficient de la matiere est affiche pour information

**Dependencies** : Module Structure Academique (affectation enseignants <-> matieres <-> classes)

---

#### Story 2.2 : Saisie de Notes en Tableau avec Calcul Temps Reel

**As an** Enseignant,
**I want** saisir les notes de mes eleves dans un tableau avec calcul automatique de la moyenne,
**so that** je vois immediatement les moyennes et puisse verifier mes saisies.

**Acceptance Criteria** :
1. Un tableau de saisie affiche : N, Nom eleve, Prenom, puis 1 colonne par evaluation (DS1, DS2, IE1, Composition...), puis Moyenne (calculee), puis Appreciation
2. Les eleves sont tries par ordre alphabetique (nom, prenom)
3. Chaque cellule de note est editable inline (clic pour activer l'edition)
4. La saisie accepte uniquement des nombres entre 0.00 et 20.00 avec 2 decimales maximum
5. Je peux saisir "ABS" ou laisser vide et cocher "Absent" pour marquer un eleve absent a une evaluation
6. La colonne "Moyenne" se met a jour automatiquement apres chaque saisie selon le mode de calcul configure (simple ou pondere)
7. Si un eleve a "ABS" et que le mode est "zero", la note 0 est utilisee ; si le mode est "exclure", l'evaluation est ignoree
8. Si un eleve est "ABS" a toutes les evaluations et le mode est "exclure", la moyenne affiche "N/A"
9. Les moyennes >= 10 sont affichees en bleu, < 10 en rouge
10. Un bouton "Sauvegarder brouillon" enregistre les notes sans changer le statut (reste modifiable)
11. Un compteur affiche le nombre de notes saisies / total (ex: "45/55 notes saisies")

**Dependencies** : Story 1.2, Story 2.1, Module Inscriptions (liste des eleves inscrits dans la classe)

---

#### Story 2.3 : Saisie des Appreciations par Matiere

**As an** Enseignant,
**I want** saisir une appreciation textuelle pour chaque eleve dans ma matiere,
**so that** l'appreciation apparaisse sur le bulletin semestriel.

**Acceptance Criteria** :
1. Dans le tableau de saisie de notes, une colonne "Appreciation" est presente avec un champ texte par eleve
2. Le champ propose des suggestions (autocompletion) basees sur les appreciations predefinies (Story 1.3)
3. Je peux saisir un texte libre jusqu'a 500 caracteres
4. Les appreciations sont enregistrees avec les notes lors de la sauvegarde (brouillon ou soumission)
5. Les appreciations sont modifiables tant que les notes sont en brouillon
6. Les appreciations sont stockees dans la table `subject_appreciations`

**Dependencies** : Story 2.2, Story 1.3

---

#### Story 2.4 : Import CSV de Notes

**As an** Enseignant,
**I want** importer les notes via un fichier CSV,
**so that** je gagne du temps si j'ai deja saisi les notes dans Excel.

**Acceptance Criteria** :
1. Un bouton "Telecharger le template CSV" genere un fichier avec les colonnes : Matricule, Nom, Prenom, puis 1 colonne par evaluation definie
2. Le template est pre-rempli avec la liste des eleves
3. Un bouton "Importer CSV" ouvre un dialogue de selection de fichier
4. Le systeme valide le fichier : matricules valides (eleves inscrits dans la classe), notes valides (0-20 ou ABS)
5. Le systeme affiche une previsualisation des notes a importer avec les erreurs detectees en rouge
6. Je peux corriger les erreurs dans la previsualisation avant import
7. Un bouton "Confirmer import" enregistre les notes en brouillon
8. Les notes importees remplacent les notes existantes pour les memes eleves/evaluations
9. Un message de confirmation indique le nombre de notes importees avec succes et les erreurs eventuelles

**Dependencies** : Story 2.2

---

#### Story 2.5 : Soumission des Notes pour Validation

**As an** Enseignant,
**I want** soumettre mes notes et appreciations pour validation par l'Admin,
**so that** elles puissent etre verifiees avant publication.

**Acceptance Criteria** :
1. Un bouton "Soumettre pour validation" est disponible si toutes les notes de la matiere/classe/semestre sont saisies (pas de cellule vide, sauf absents marques explicitement)
2. Un message de confirmation demande "Etes-vous sur ? Vous ne pourrez plus modifier les notes apres soumission."
3. Apres confirmation, le statut de toutes les notes de cette matiere/classe/semestre passe a "submitted"
4. Les notes et appreciations ne sont plus editables par l'enseignant
5. Un message de succes s'affiche : "Notes soumises avec succes. En attente de validation par l'administration."
6. Si des notes sont manquantes, un message d'erreur liste les eleves/evaluations sans notes

**Dependencies** : Story 2.2, Story 2.3

---

### Epic 3 : Workflow de Validation et Publication

**Goal detaille** : Les Admins doivent pouvoir valider ou rejeter les notes soumises par les enseignants, puis publier les resultats d'une classe/semestre une fois toutes les matieres validees, declenchant le calcul des moyennes generales et classements.

#### Story 3.1 : Liste des Notes en Attente de Validation

**As an** Admin,
**I want** voir la liste des soumissions de notes en attente de validation,
**so that** je puisse les verifier et les traiter.

**Acceptance Criteria** :
1. Un ecran "Validation des notes" affiche un tableau avec : Matiere, Classe, Semestre, Enseignant, Nombre d'eleves, Date de soumission
2. Seules les soumissions avec statut "submitted" sont affichees
3. Un bouton "Consulter" permet d'acceder a la previsualisation des notes
4. Le tableau est filtrable par semestre, classe, et matiere
5. Un compteur affiche le nombre total de soumissions en attente
6. Un indicateur montre la progression par classe (ex: "4/7 matieres soumises pour la 3e A")

**Dependencies** : Story 2.5

---

#### Story 3.2 : Previsualisation et Validation des Notes

**As an** Admin,
**I want** previsualiser les notes soumises et les valider ou les rejeter,
**so that** je m'assure de leur exactitude avant publication.

**Acceptance Criteria** :
1. La previsualisation affiche le meme tableau que l'enseignant (lecture seule) avec les notes, moyennes et appreciations
2. Des statistiques sont visibles en haut : moyenne de la matiere pour la classe, note min, note max, taux de reussite (% >= 10)
3. Je peux telecharger les notes en CSV/Excel pour verification externe
4. Deux boutons sont disponibles : "Valider" (vert) et "Rejeter" (rouge)
5. Si je clique "Valider", le statut passe a "validated"
6. Si je clique "Rejeter", une modal demande un commentaire obligatoire (raison du rejet)
7. Apres rejet, le statut repasse a "draft", l'enseignant peut modifier, et le commentaire de rejet est affiche a l'enseignant
8. Un message de confirmation s'affiche apres validation ou rejet

**Dependencies** : Story 3.1

---

#### Story 3.3 : Publication des Resultats d'une Classe/Semestre

**As an** Admin,
**I want** publier les resultats d'une classe pour un semestre,
**so that** les moyennes generales et classements soient calcules et les resultats visibles pour les eleves et parents.

**Acceptance Criteria** :
1. Un ecran "Publication des resultats" permet de selectionner une Classe et un Semestre
2. Un tableau recapitulatif affiche toutes les matieres de la classe avec leur statut de validation
3. Le bouton "Publier" n'est actif que si TOUTES les matieres sont au statut "validated"
4. Si des matieres ne sont pas encore validees, un message indique lesquelles manquent
5. Apres confirmation, le systeme :
   a. Calcule les moyennes generales semestrielles pour tous les eleves (FR22)
   b. Calcule le classement (rang) pour tous les eleves (FR26)
   c. Calcule les statistiques de classe (FR29)
   d. Passe toutes les notes au statut "published"
   e. Stocke les resultats dans `semester_averages`
6. Un recapitulatif post-publication affiche :
   - Nombre d'eleves
   - Moyenne de la classe
   - Premier(e) et dernier(e)
   - Nombre d'eleves ayant la moyenne (>= 10)
7. Les resultats publies deviennent visibles pour les eleves et parents

**Dependencies** : Story 3.2, Epic 4

---

#### Story 3.4 : Historique des Modifications de Notes

**As an** Admin,
**I want** consulter l'historique des modifications de notes,
**so that** je puisse tracer qui a modifie quoi et quand.

**Acceptance Criteria** :
1. Pour chaque note, un bouton "Historique" est accessible (Admin uniquement)
2. Une modal affiche l'historique des modifications :
   - Date et heure
   - Utilisateur (nom, role)
   - Ancienne valeur -> Nouvelle valeur
   - Ancien statut -> Nouveau statut
   - Raison (si fournie)
3. L'historique est trie par date decroissante
4. Les modifications sont enregistrees automatiquement via un Observer sur le model Grade

**Dependencies** : Story 2.2, Story 3.2

---

### Epic 4 : Calcul des Moyennes, Classements et Statistiques

**Goal detaille** : Le systeme doit calculer automatiquement les moyennes par matiere (avec ponderation), les moyennes generales semestrielles avec coefficients, le classement par classe, et les statistiques de classe. Ces calculs sont le coeur du systeme et doivent etre 100% fiables.

#### Story 4.1 : Calcul Automatique de la Moyenne par Matiere

**As a** System,
**I want** calculer automatiquement la moyenne de chaque matiere pour chaque eleve,
**so that** les enseignants et eleves voient la moyenne correcte en temps reel.

**Acceptance Criteria** :
1. Le systeme calcule la moyenne selon le mode configure (simple ou pondere) comme defini en FR18
2. Les absences sont traitees selon la configuration (zero ou exclure) comme defini en FR19
3. La moyenne est arrondie a 2 decimales (FR20)
4. La moyenne est recalculee automatiquement a chaque modification de note (FR21)
5. La moyenne par matiere est stockee dans `semester_averages` (avec subject_id renseigne)
6. Le service `GradeCalculationService` encapsule toute la logique de calcul
7. Des tests unitaires couvrent tous les cas : notes normales, absences (zero et exclure), une seule note, toutes absences

**Dependencies** : Story 1.1, Story 2.2

---

#### Story 4.2 : Calcul de la Moyenne Generale Semestrielle

**As a** System,
**I want** calculer la moyenne generale semestrielle de chaque eleve en tenant compte des coefficients,
**so that** le resultat global de l'eleve soit exact.

**Acceptance Criteria** :
1. Le systeme calcule la moyenne generale selon la formule FR22 : `Somme(Moyenne_Matiere x Coefficient) / Somme(Coefficients)`
2. Les coefficients sont lus depuis le Module Structure Academique (table `subjects` ou `subject_class_coefficients`)
3. La moyenne generale est arrondie a 2 decimales
4. Si un eleve n'a pas de moyenne pour une matiere (toutes absences en mode "exclure"), cette matiere est exclue du calcul
5. La moyenne generale est stockee dans `semester_averages` (avec subject_id = null)
6. Des tests unitaires couvrent : calcul normal, coefficients differents, matieres sans notes

**Dependencies** : Story 4.1, Module Structure Academique (coefficients)

---

#### Story 4.3 : Calcul du Classement par Classe

**As a** System,
**I want** calculer le rang de chaque eleve dans sa classe,
**so that** le classement apparaisse sur le bulletin.

**Acceptance Criteria** :
1. Les eleves sont classes par moyenne generale semestrielle decroissante
2. En cas d'egalite : meme rang, le rang suivant saute (FR27)
3. Le rang et le nombre total d'eleves sont stockes dans `semester_averages` (colonnes `rank`, `total_students`)
4. Le classement est recalcule chaque fois que les moyennes generales changent
5. Des tests unitaires couvrent : classement normal, ex-aequo multiples, un seul eleve, tous ex-aequo

**Dependencies** : Story 4.2

---

#### Story 4.4 : Calcul des Statistiques de Classe

**As a** System,
**I want** calculer les statistiques d'une classe pour un semestre,
**so that** l'Admin et les enseignants aient une vue d'ensemble de la performance.

**Acceptance Criteria** :
1. Pour chaque matiere et pour la moyenne generale, le systeme calcule :
   - Moyenne de la classe
   - Note la plus haute (max)
   - Note la plus basse (min)
   - Nombre et pourcentage d'eleves ayant la moyenne (>= 10)
2. Les statistiques par matiere sont stockees dans `semester_averages` (colonnes `class_average`, `class_min`, `class_max`)
3. Des statistiques globales sont aussi disponibles :
   - Repartition par tranche (0-5, 5-8, 8-10, 10-12, 12-14, 14-16, 16-20)
4. Les statistiques sont recalculees lors de la publication

**Dependencies** : Story 4.2, Story 4.3

---

### Epic 5 : Mentions et Decisions du Conseil de Classe

**Goal detaille** : Lors du conseil de classe, l'Admin (ou le professeur principal) doit pouvoir attribuer les mentions positives (Tableau d'honneur, Encouragements, Felicitations) et les sanctions academiques (Avertissement travail, Avertissement conduite, Blame) pour chaque eleve, ainsi que saisir l'appreciation generale.

#### Story 5.1 : Proposition Automatique des Mentions

**As a** System,
**I want** proposer automatiquement les mentions basees sur la moyenne generale,
**so that** le conseil de classe ait une base de travail.

**Acceptance Criteria** :
1. Apres publication des resultats (Story 3.3), le systeme propose :
   - **Felicitations** : Pour les eleves dont la moyenne >= seuil Felicitations (defaut 16.00)
   - **Tableau d'honneur** : Pour les eleves dont la moyenne >= seuil Tableau d'honneur (defaut 14.00) et < seuil Felicitations
   - **Encouragements** : Pas de proposition automatique (decision humaine basee sur les progres)
2. Les propositions sont des suggestions modifiables, pas des decisions finales
3. Le systeme affiche une interface de type checklist avec les propositions pre-cochees

**Dependencies** : Story 3.3, Story 1.1 (seuils)

---

#### Story 5.2 : Attribution des Mentions et Sanctions par le Conseil

**As an** Admin ou Professeur principal,
**I want** attribuer les mentions et sanctions a chaque eleve lors du conseil de classe,
**so that** ces decisions apparaissent sur le bulletin.

**Acceptance Criteria** :
1. Un ecran "Conseil de classe" affiche la liste des eleves avec : Nom, Prenom, Moyenne Generale, Rang, Mention proposee
2. Pour chaque eleve, je peux :
   - Choisir une mention : Aucune, Tableau d'honneur, Encouragements, Felicitations
   - Choisir une sanction : Aucune, Avertissement travail, Avertissement conduite, Blame
   - Un eleve peut avoir a la fois aucune mention et une sanction, ou une mention et aucune sanction
3. Un eleve ne peut PAS avoir a la fois une mention positive et un Blame
4. Les decisions sont stockees dans la table `class_council_decisions`
5. Un bouton "Enregistrer les decisions" sauvegarde toutes les decisions du conseil

**Dependencies** : Story 5.1

---

#### Story 5.3 : Saisie de l'Appreciation Generale du Conseil

**As an** Admin ou Professeur principal,
**I want** saisir l'appreciation generale du conseil de classe pour chaque eleve,
**so that** cette appreciation apparaisse sur le bulletin.

**Acceptance Criteria** :
1. Dans l'ecran du conseil de classe (Story 5.2), un champ "Appreciation generale" est present pour chaque eleve
2. Le champ accepte un texte libre jusqu'a 500 caracteres
3. L'appreciation est saisie par le president du conseil (Admin/PP)
4. L'appreciation est stockee dans `class_council_decisions` (colonne `general_appreciation`)
5. L'appreciation est modifiable tant que les resultats du conseil n'ont pas ete finalises

**Dependencies** : Story 5.2

---

### Epic 6 : Consultation et Exports

**Goal detaille** : Tous les roles doivent pouvoir consulter les resultats selon leurs permissions. Les enseignants et admins doivent pouvoir exporter les donnees en CSV/Excel. Le systeme doit fournir une API pour la generation des bulletins.

#### Story 6.1 : Consultation des Notes par les Eleves

**As an** Eleve,
**I want** consulter mes notes publiees, ma moyenne par matiere, ma moyenne generale, mon rang et les appreciations,
**so that** je connaisse mes resultats et ma position dans la classe.

**Acceptance Criteria** :
1. Un ecran "Mes notes" affiche les semestres sous forme d'onglets (S1, S2, Annuel)
2. Pour chaque semestre, je vois un tableau avec :
   - Matiere, Coefficient, Notes detaillees (DS, IE, Composition...), Moyenne de la matiere, Appreciation de l'enseignant
3. En haut du tableau, je vois un resume :
   - Moyenne generale du semestre
   - Rang dans la classe (ex: "12e sur 55 eleves")
   - Mention obtenue (si applicable)
   - Appreciation generale du conseil de classe
4. Les matieres sont colorees selon la moyenne (bleu >= 10, rouge < 10)
5. Seules les notes publiees sont visibles
6. L'onglet "Annuel" affiche les moyennes annuelles si les deux semestres sont publies

**Dependencies** : Story 3.3, Epic 4, Epic 5

---

#### Story 6.2 : Consultation des Notes par les Parents

**As a** Parent,
**I want** consulter les notes et resultats de mon enfant (ou mes enfants),
**so that** je puisse suivre sa scolarite en temps reel.

**Acceptance Criteria** :
1. Meme interface que l'eleve (Story 6.1) mais accessible via le compte parent
2. Si j'ai plusieurs enfants, un selecteur d'enfant est affiche en haut de la page
3. Des indicateurs d'evolution sont affiches (fleche montante/descendante entre S1 et S2 pour chaque matiere)
4. L'acces est limite aux enfants lies au compte parent (relation `parent_student`)
5. Seules les notes publiees sont visibles

**Dependencies** : Story 6.1, Module Inscriptions (liaison parent-eleve)

---

#### Story 6.3 : Consultation et Statistiques pour les Enseignants

**As an** Enseignant,
**I want** consulter les statistiques de mes matieres/classes,
**so that** j'identifie les eleves en difficulte et evalue la performance de la classe.

**Acceptance Criteria** :
1. Pour chaque matiere/classe, un onglet "Statistiques" est disponible
2. Je vois :
   - Moyenne de la classe pour ma matiere
   - Note minimale / maximale
   - Taux de reussite (% d'eleves avec moyenne >= 10)
   - Distribution des notes (nombre d'eleves par tranche : 0-5, 5-8, 8-10, 10-12, 12-14, 14-16, 16-20)
3. Un graphique en barres illustre la distribution
4. Je peux consulter l'historique de mes saisies (brouillon, validees, publiees)
5. La liste des eleves en difficulte (moyenne < 8) est mise en evidence

**Dependencies** : Story 2.2, Story 3.3, Story 4.4

---

#### Story 6.4 : Tableau Recapitulatif de Classe (Admin)

**As an** Admin,
**I want** consulter un tableau recapitulatif complet d'une classe pour un semestre,
**so that** j'aie une vue d'ensemble pour le conseil de classe.

**Acceptance Criteria** :
1. Un ecran "Recapitulatif de classe" permet de selectionner Classe + Semestre
2. Le tableau affiche :
   - Lignes : Eleves tries par rang
   - Colonnes : Chaque matiere (moyenne), Moyenne Generale, Rang
   - Derniere ligne : Moyenne de la classe par matiere et generale
3. Les moyennes < 10 sont en rouge, >= 14 en vert
4. Un export Excel est disponible (bouton "Exporter en Excel")
5. Ce tableau est la base de travail du conseil de classe

**Dependencies** : Story 4.2, Story 4.3, Story 4.4

---

#### Story 6.5 : Export CSV/Excel des Notes

**As an** Enseignant ou Admin,
**I want** exporter les notes en CSV ou Excel,
**so that** je puisse les analyser dans un tableur externe.

**Acceptance Criteria** :
1. Un bouton "Exporter" est disponible sur les ecrans de consultation des notes
2. L'enseignant peut exporter les notes d'une matiere/classe : colonnes N, Nom, Prenom, Matricule, notes par evaluation, Moyenne, Appreciation
3. L'Admin peut exporter le recapitulatif complet d'une classe : toutes matieres, moyennes, rang
4. Le nom du fichier suit le format : `Notes_[Classe]_[Matiere]_[Semestre]_[Date].xlsx`
5. Le format Excel inclut un formatage basique (en-tetes en gras, bordures, couleurs pour moyennes)
6. Le telechargement demarre automatiquement apres selection du format

**Dependencies** : Story 2.2, Story 6.3, Story 6.4

---

#### Story 6.6 : API pour le Module Documents Officiels (Bulletins)

**As a** System,
**I want** fournir une API interne pour recuperer toutes les donnees necessaires a la generation des bulletins,
**so that** le Module Documents Officiels puisse generer les bulletins semestriels et annuels en PDF.

**Acceptance Criteria** :
1. Un endpoint `/api/admin/grades/student/{student_id}/semester/{semester_id}` retourne :
   - Informations eleve (nom, prenom, matricule, classe, serie)
   - Pour chaque matiere : coefficient, notes detaillees par evaluation, moyenne de la matiere, appreciation de l'enseignant, moyenne de la classe, note min/max de la classe
   - Moyenne generale semestrielle
   - Rang dans la classe (ex: "12e / 55")
   - Mention obtenue
   - Appreciation generale du conseil de classe
   - Statistiques de classe (moyenne, premier, dernier)
2. Un endpoint `/api/admin/grades/student/{student_id}/annual/{academic_year_id}` retourne les memes donnees pour l'annee complete
3. Les endpoints sont securises (middleware `tenant.auth`, permission `manage-grades`)
4. Les donnees sont retournees via des API Resources structurees
5. Les reponses sont optimisees (eager loading, pas de N+1)

**Dependencies** : Epic 4, Epic 5

---

### Epic 7 : Moyenne Annuelle et Resultats de Fin d'Annee

**Goal detaille** : A la fin de l'annee scolaire (apres publication du S2), le systeme doit calculer les moyennes annuelles par matiere et la moyenne generale annuelle, le classement annuel, et fournir les donnees au bulletin annuel et aux decisions de passage/redoublement.

#### Story 7.1 : Calcul de la Moyenne Annuelle par Matiere

**As a** System,
**I want** calculer la moyenne annuelle de chaque matiere pour chaque eleve,
**so that** le bulletin annuel affiche la performance sur l'annee complete.

**Acceptance Criteria** :
1. Le systeme calcule : `Moyenne Annuelle Matiere = (Moyenne S1 + Moyenne S2) / 2`
2. Si un eleve n'a qu'un seul semestre (transfert en cours d'annee), la moyenne du semestre disponible est utilisee
3. La moyenne annuelle par matiere est arrondie a 2 decimales
4. Le calcul est declenche automatiquement quand les resultats du S2 sont publies

**Dependencies** : Story 4.1, Story 3.3 (S2 publie)

---

#### Story 7.2 : Calcul de la Moyenne Generale Annuelle et Classement

**As a** System,
**I want** calculer la moyenne generale annuelle et le classement annuel,
**so that** ces resultats soient disponibles pour le bulletin annuel et les decisions de passage.

**Acceptance Criteria** :
1. Le systeme calcule la moyenne generale annuelle selon le mode configure (FR31) :
   - Mode 1 : `(Moyenne Generale S1 + Moyenne Generale S2) / 2`
   - Mode 2 : `Somme(Moyenne_Annuelle_Matiere x Coefficient) / Somme(Coefficients)`
2. Le classement annuel est calcule de la meme maniere que le semestriel (ex-aequo geres)
3. Les resultats annuels sont stockes dans `semester_averages` avec un flag `is_annual = true` (ou un semester_id special)
4. Des tests unitaires couvrent les deux modes de calcul et les cas limites

**Dependencies** : Story 7.1, Story 4.2, Story 4.3

---

#### Story 7.3 : Consultation des Resultats Annuels

**As an** Eleve, Parent, ou Admin,
**I want** consulter les resultats annuels (moyennes annuelles et classement),
**so that** je connaisse la performance sur l'annee complete.

**Acceptance Criteria** :
1. Un onglet "Annuel" est disponible dans l'ecran de consultation des notes
2. Le tableau affiche pour chaque matiere : Moyenne S1, Moyenne S2, Moyenne Annuelle, Coefficient
3. Le resume affiche : Moyenne Generale Annuelle, Rang Annuel
4. Un comparatif visuel S1 vs S2 montre l'evolution (fleches, couleurs)
5. Les resultats annuels ne sont disponibles que si les deux semestres sont publies

**Dependencies** : Story 7.2, Story 6.1, Story 6.2

---

## 7. Checklist Results Report

**A completer apres implementation** : Executer le checklist PM pour valider :
- [ ] Tous les requirements fonctionnels (FR1-FR54) sont implementes
- [ ] Les formules de calcul sont correctes et testees unitairement
- [ ] Le calcul des moyennes avec coefficients est verifie sur des cas reels
- [ ] Le classement gere correctement les ex-aequo
- [ ] Les appreciations sont correctement stockees et affichees
- [ ] Les mentions et sanctions fonctionnent avec les seuils configurables
- [ ] Le workflow de validation Enseignant -> Admin -> Publication fonctionne
- [ ] Les permissions sont appliquees (Enseignant, Admin, Eleve, Parent)
- [ ] L'API pour les bulletins retourne toutes les donnees necessaires
- [ ] Les exports CSV/Excel fonctionnent pour enseignants et admins
- [ ] Les performances sont acceptables (calcul classe 80 eleves < 3s)
- [ ] L'interface est responsive et accessible (WCAG AA)
- [ ] Les tests couvrent tous les cas limites (absences, ex-aequo, coefficients, etc.)

---

## 8. Next Steps

### 8.1 UX Expert Prompt

> "Creez les maquettes UI pour le Module Notes & Evaluations du systeme de gestion scolaire secondaire (colleges/lycees Niger). Focus sur les ecrans critiques : Saisie de notes en tableau avec calcul temps reel (Enseignant), Validation des notes (Admin), Recapitulatif de classe (Admin/Conseil de classe), Consultation des notes avec rang et appreciations (Eleve/Parent). Assurez l'accessibilite WCAG AA, le responsive design, et une UX optimisee pour la saisie rapide. Les utilisateurs principaux sont des enseignants avec un niveau technique variable, sur des connexions 3G parfois instables."

### 8.2 Architect Prompt

> "Concevez l'architecture technique du Module Notes & Evaluations pour l'enseignement secondaire, en suivant les patterns etablis dans les modules existants (UsersGuard, StructureAcademique). Implementez : les tables de base de donnees (evaluations, grades, semester_averages, class_council_decisions, grade_configs, grade_history, subject_appreciations), les models Eloquent avec relations et casts, le service GradeCalculationService avec les formules de calcul (moyenne simple, ponderee, generale avec coefficients, classement avec ex-aequo), les controllers, les API Resources, les Form Requests, et les tests PHPUnit exhaustifs pour les formules de calcul. La logique metier doit etre dans le service layer, pas dans les controllers."

---

**Document cree par** : John (Product Manager Agent)
**Date** : 2026-03-16
**Version** : v5
**Statut** : Draft pour review
