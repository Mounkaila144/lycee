# PRD - Module Conseil de Classe

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Module** : Conseil de Classe (Class Council)
> **Version** : 1.0
> **Date** : 2026-03-16
> **Phase** : Phase 1 - MVP Core
> **Priorite** : CRITIQUE

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 1.0 | Creation initiale du PRD Module Conseil de Classe | John (PM) |

---

## 1. Goals and Background Context

### 1.1 Goals

- **Fournir un tableau recapitulatif complet** : Consolider toutes les notes et moyennes d'une classe pour un semestre donne dans une vue unique exploitable lors du conseil de classe
- **Automatiser les statistiques de classe** : Calculer automatiquement la moyenne generale de la classe, le taux de reussite, la repartition par tranches de moyennes, et identifier les matieres les plus fortes/faibles
- **Gerer les decisions de fin d'annee** : Permettre la saisie des decisions individuelles (Passage, Redoublement, Exclusion) avec proposition automatique basee sur les moyennes et conditions configurables
- **Generer automatiquement le proces-verbal (PV)** : Produire un PV officiel du conseil de classe avec statistiques, decisions, observations et signatures
- **Faciliter la saisie des appreciations generales** : Permettre au president du conseil de saisir une appreciation generale par eleve, distincte des appreciations par matiere
- **Piloter le workflow du conseil** : Gerer le cycle de vie complet du conseil de classe (convocation, seance, decisions, cloture, PV)

### 1.2 Background Context

Le **Module Conseil de Classe** est un element central du cycle academique dans l'enseignement secondaire au Niger. Il constitue l'aboutissement du processus de notation semestriel et le lieu de prise de decisions majeures pour le parcours des eleves (passage, redoublement, exclusion).

Ce module s'inscrit dans la **Phase 1 MVP Core** car il est **indispensable** pour :
1. Fournir aux directeurs et enseignants les donnees consolidees necessaires pour prendre des decisions eclairees
2. Generer le PV officiel du conseil de classe, document reglementaire obligatoire
3. Alimenter le Module Documents Officiels avec les decisions et appreciations pour les bulletins semestriels et annuels
4. Enregistrer les decisions de fin d'annee qui determinent le parcours scolaire de chaque eleve

Le conseil de classe est un moment cle dans le fonctionnement des colleges et lycees au Niger :
- Il se tient **deux fois par an** (fin de chaque semestre : S1 et S2)
- Le conseil de S1 est consultif (appreciations, mise en garde)
- Le conseil de S2 est **decisif** (passage, redoublement, exclusion)
- Il reunit la direction (president), les enseignants de la classe, les delegues des eleves et les delegues des parents
- Les decisions prises sont consignees dans un **proces-verbal officiel** signe par le president et le secretaire de seance

**Pain points resolus** :
- **Compilation manuelle des resultats** : Actuellement, le censeur passe des heures a compiler les moyennes de chaque eleve dans chaque matiere sur des feuilles de calcul manuelles
- **Statistiques absentes ou imprecises** : Le taux de reussite, les moyennes par tranche et les comparaisons sont rarement disponibles faute de temps
- **Decisions non tracees** : Les decisions de passage/redoublement sont souvent inscrites sur des cahiers sans suivi formel
- **PV fastidieux** : La redaction du PV se fait manuellement, avec des risques d'oublis et d'incoherences
- **Appreciations perdues** : Les appreciations generales sont ecrites sur des feuilles volantes, parfois perdues avant la generation des bulletins

**Dependances critiques** :
- **Module Structure Academique** : Classes, matieres, coefficients, semestres, annees scolaires
- **Module Inscriptions** : Liste des eleves inscrits par classe
- **Module Notes & Evaluations** : Moyennes par matiere, moyennes generales, classements
- **Module Documents Officiels** : Generation des bulletins integrant les decisions et appreciations du conseil

---

## 2. Requirements

### 2.1 Functional Requirements (FR)

#### 2.1.1 Tableau Recapitulatif de Classe

- **FR1** : Le systeme doit generer un tableau recapitulatif consolidant toutes les notes et moyennes d'une classe pour un semestre donne
- **FR2** : Le tableau doit afficher en colonnes : Rang, Nom et Prenom de l'eleve, Moyenne par matiere (une colonne par matiere), Moyenne Generale, Mention/Decision
- **FR3** : Le tableau doit etre trie par rang (du meilleur au moins bon) base sur la moyenne generale
- **FR4** : Le systeme doit mettre en surbrillance (highlighting) les eleves dont la moyenne est inferieure a un seuil configurable (par defaut : 10/20)
- **FR5** : Le systeme doit afficher le coefficient de chaque matiere dans l'en-tete de colonne
- **FR6** : Le systeme doit permettre l'export du tableau recapitulatif en Excel et en PDF
- **FR7** : Le systeme doit calculer et afficher pour chaque matiere : la moyenne de la classe, la note la plus haute, la note la plus basse
- **FR8** : Le systeme doit permettre de filtrer le tableau par sexe (Garcons/Filles/Tous) pour des statistiques genrees

#### 2.1.2 Statistiques de Classe

- **FR9** : Le systeme doit calculer automatiquement la moyenne generale de la classe pour le semestre
- **FR10** : Le systeme doit calculer le taux de reussite : pourcentage d'eleves ayant une moyenne >= 10/20
- **FR11** : Le systeme doit calculer la repartition des eleves par tranches de moyennes : [0-5[, [5-8[, [8-10[, [10-12[, [12-14[, [14-16[, [16-20]
- **FR12** : Le systeme doit identifier et afficher les 3 matieres ou la classe a les meilleures moyennes (matieres fortes)
- **FR13** : Le systeme doit identifier et afficher les 3 matieres ou la classe a les plus faibles moyennes (matieres faibles)
- **FR14** : Le systeme doit permettre la comparaison des statistiques avec le semestre precedent (si disponible) : evolution de la moyenne, evolution du taux de reussite
- **FR15** : Le systeme doit afficher les statistiques sous forme de graphiques : histogramme pour la repartition par tranches, evolution de la moyenne de classe entre semestres
- **FR16** : Le systeme doit calculer des statistiques complementaires : ecart-type des moyennes, taux de mentions (Tableau d'honneur, Encouragements, Felicitations)

#### 2.1.3 Decisions de Fin d'Annee

- **FR17** : Le systeme doit permettre la saisie de decisions individuelles par eleve avec les types suivants : Passage en classe superieure, Redoublement, Exclusion
- **FR18** : Les decisions ne doivent etre saisissables qu'apres le conseil de classe du semestre 2 (S2)
- **FR19** : Le systeme doit permettre a l'Admin/Directeur de configurer les conditions de passage automatique (ex : moyenne generale >= 10/20)
- **FR20** : Le systeme doit proposer automatiquement une decision basee sur les moyennes annuelles et les conditions configurees (proposition modifiable par le conseil)
- **FR21** : La proposition automatique doit appliquer les regles suivantes :
  - Moyenne annuelle >= seuil de passage (configurable, defaut 10/20) : **Proposition = Passage**
  - Moyenne annuelle < seuil de passage : **Proposition = Redoublement**
  - Cas speciaux (exclusions disciplinaires, etc.) : **Pas de proposition automatique, decision manuelle obligatoire**
- **FR22** : Le systeme doit permettre de saisir des mentions speciales pour chaque eleve : Tableau d'honneur, Encouragements, Felicitations
- **FR23** : Les conditions d'attribution des mentions doivent etre configurables par l'Admin (ex : Felicitations si moyenne >= 16, Tableau d'honneur si moyenne >= 14, Encouragements si moyenne >= 12)
- **FR24** : Le systeme doit proposer automatiquement les mentions basees sur les seuils configures (proposition modifiable par le conseil)
- **FR25** : Le systeme doit empecher la modification des decisions apres cloture du conseil (sauf par l'Admin avec justification tracee dans l'historique)
- **FR26** : Le systeme doit permettre l'ajout d'une observation textuelle pour chaque decision (ex : "Redoublement sur avis du conseil malgre moyenne suffisante")

#### 2.1.4 Proces-Verbal (PV) du Conseil de Classe

- **FR27** : Le systeme doit generer automatiquement un PV du conseil de classe au format PDF
- **FR28** : Le PV doit contenir les informations suivantes :
  - **En-tete** : Nom de l'etablissement, Logo, Annee scolaire, Semestre
  - **Informations du conseil** : Date et heure, Classe concernee, Nom du president (Directeur/Censeur), Nom du secretaire de seance
  - **Membres presents** : Liste des enseignants presents, delegues des eleves, delegues des parents, autres membres
  - **Statistiques de classe** : Effectif total (inscrits/presents), Moyenne generale de la classe, Taux de reussite, Repartition par tranches
  - **Observations generales** : Appreciation globale sur la classe (saisie libre par le president)
  - **Decisions individuelles** (uniquement pour S2) : Tableau avec Nom eleve, Moyenne annuelle, Decision, Mention, Observation
  - **Signatures** : Espace signature du president et du secretaire
- **FR29** : Le systeme doit permettre la personnalisation du template de PV (logo, informations etablissement) via la configuration du tenant
- **FR30** : Le PV doit etre numerote de maniere unique et sequentielle par annee scolaire (ex : PV-2025-2026-001)
- **FR31** : Le systeme doit stocker le PV genere et permettre sa consultation et reimpresiion ulterieure
- **FR32** : Le systeme doit permettre de regenerer le PV si des modifications sont apportees aux decisions avant cloture definitive

#### 2.1.5 Appreciations Generales

- **FR33** : Le systeme doit permettre au president du conseil (Admin/Directeur/Censeur) de saisir une appreciation generale par eleve
- **FR34** : L'appreciation generale est distincte des appreciations par matiere (saisies par les enseignants dans le Module Notes)
- **FR35** : Le systeme doit proposer des templates d'appreciations suggerees basees sur la moyenne et le comportement de l'eleve (optionnel, modifiable) :
  - Moyenne >= 16 : "Excellent travail. Continuez ainsi."
  - Moyenne >= 14 : "Tres bon travail. Eleve serieux(se) et applique(e)."
  - Moyenne >= 12 : "Bon travail dans l'ensemble. Peut mieux faire."
  - Moyenne >= 10 : "Travail passable. Des efforts supplementaires sont necessaires."
  - Moyenne < 10 : "Travail insuffisant. Des efforts importants sont attendus."
- **FR36** : Le systeme doit permettre la saisie en masse des appreciations (navigation rapide d'un eleve a l'autre sans rechargement de page)
- **FR37** : L'appreciation generale doit etre integree au bulletin semestriel genere par le Module Documents Officiels
- **FR38** : Le systeme doit permettre de modifier une appreciation tant que le conseil n'est pas cloture

#### 2.1.6 Workflow du Conseil de Classe

- **FR39** : Le systeme doit gerer le cycle de vie du conseil de classe avec les statuts suivants :
  - **Planifie** : Le conseil est programme avec une date, heure, et salle
  - **En cours** : Le conseil est en session (ouvert par le president)
  - **Deliberations terminees** : Les decisions et appreciations sont saisies
  - **Cloture** : Le PV est genere et signe, aucune modification possible sauf par Admin
- **FR40** : Le systeme doit permettre de planifier un conseil de classe avec : Classe, Date, Heure, Salle, President (Directeur/Censeur), Secretaire de seance
- **FR41** : Le systeme doit permettre de generer une liste de convocation des membres du conseil :
  - Tous les enseignants affectes a la classe
  - Le professeur principal de la classe
  - Les delegues des eleves (noms saisis manuellement ou selectionnes parmi les eleves inscrits)
  - Les delegues des parents
  - La direction (president + secretaire)
- **FR42** : Le systeme doit permettre de cocher les membres presents lors du conseil (registre de presence)
- **FR43** : Le systeme doit permettre l'ouverture du conseil (passage au statut "En cours") uniquement par l'Admin/Directeur
- **FR44** : Le systeme doit permettre de projeter les donnees du conseil (tableau recapitulatif, statistiques) en mode "presentation" (plein ecran, grandes polices)
- **FR45** : Le systeme doit permettre la saisie des decisions et appreciations en temps reel pendant la seance
- **FR46** : Le systeme doit permettre la cloture du conseil par le president, declenchant la generation automatique du PV
- **FR47** : Le systeme doit enregistrer un historique complet des actions effectuees sur chaque conseil (date/heure, utilisateur, action)

---

### 2.2 Non-Functional Requirements (NFR)

- **NFR1** : Le tableau recapitulatif d'une classe de 60 eleves avec 12 matieres doit s'afficher en moins de 2 secondes (performance)
- **NFR2** : Le calcul des statistiques de classe (moyennes, taux, repartitions) doit s'effectuer en moins de 1 seconde pour une classe de 60 eleves (performance)
- **NFR3** : La generation du PV en PDF doit s'effectuer en moins de 10 secondes (performance)
- **NFR4** : Le systeme doit supporter la tenue de 20 conseils de classe simultanement (differentes classes) sans degradation de performance (scalabilite)
- **NFR5** : Les decisions de fin d'annee doivent etre protegees par un systeme de permissions strictes : seuls les utilisateurs avec le role Admin/Directeur peuvent valider les decisions (securite)
- **NFR6** : L'historique des modifications de decisions doit etre immutable et consultable par l'Admin (tracabilite)
- **NFR7** : Le systeme doit fonctionner sur une connexion 3G pour la consultation du tableau recapitulatif (optimisation bande passante)
- **NFR8** : Tous les calculs de moyennes et statistiques doivent etre testes unitairement avec des cas limites (fiabilite)
- **NFR9** : Le PV genere doit etre conforme au format A4 avec une mise en page professionnelle adaptee a l'impression (qualite)
- **NFR10** : Le mode presentation (projectable) doit s'adapter aux resolutions courantes (1024x768, 1920x1080) sans deformation (UX)

---

## 3. User Interface Design Goals

### 3.1 Overall UX Vision

L'interface du Module Conseil de Classe doit etre **claire, structuree, et orientee vers la prise de decision**. Le conseil de classe est un moment intense ou les participants doivent acceder rapidement aux donnees de chaque eleve et prendre des decisions eclairees. L'interface doit donc :

- **Centraliser l'information** : Toutes les donnees d'un eleve (notes, moyennes, absences, appreciations) visibles en un seul ecran
- **Faciliter la saisie rapide** : Navigation fluide entre les eleves pour saisir decisions et appreciations
- **Offrir un mode projection** : Interface adaptee a la projection sur grand ecran pendant la seance
- **Visualiser les tendances** : Graphiques et indicateurs visuels pour identifier rapidement les points forts/faibles

### 3.2 Key Interaction Paradigms

- **Dashboard du conseil** : Vue d'ensemble avec statistiques cles et acces rapide aux differentes fonctionnalites
- **Navigation par eleve** : Clic sur un eleve dans le tableau recapitulatif pour voir le detail et saisir decisions/appreciations
- **Saisie en lot** : Possibilite de saisir les decisions et appreciations en mode "defilement" (precedent/suivant)
- **Mode double ecran** : Statistiques de classe en haut, detail d'un eleve en bas (split view)
- **Export contextuel** : Boutons d'export (PDF, Excel) disponibles sur chaque vue

### 3.3 Core Screens and Views

#### 3.3.1 Ecran Admin : Liste des Conseils de Classe

- Tableau avec colonnes : Classe, Semestre, Date planifiee, President, Statut (Planifie/En cours/Cloture), Actions
- Bouton : "Planifier un conseil"
- Filtres : Par semestre, par cycle (College/Lycee), par statut
- Indicateur : Nombre de conseils restants a planifier

#### 3.3.2 Ecran Admin : Planification du Conseil

- Formulaire avec :
  - Classe (select : toutes les classes de l'etablissement)
  - Semestre (select : S1 ou S2 de l'annee active)
  - Date et heure (date picker + time picker)
  - Salle (texte libre)
  - President (select : Admin/Directeur/Censeur)
  - Secretaire de seance (select : enseignants de la classe ou personnel administratif)
- Bouton : "Planifier" / "Annuler"

#### 3.3.3 Ecran Admin : Tableau de Bord du Conseil (vue principale pendant la seance)

- **Zone superieure** : Informations du conseil (Classe, Semestre, Date, President, Statut)
- **Zone gauche** : Tableau recapitulatif avec navigation par eleve (clic pour selectionner)
- **Zone droite** : Detail de l'eleve selectionne (notes par matiere, appreciation, decision)
- **Zone inferieure** : Statistiques de classe (graphiques, indicateurs cles)
- Boutons d'action : "Ouvrir le conseil", "Cloturer le conseil", "Generer PV"

#### 3.3.4 Ecran Admin : Tableau Recapitulatif de Classe

- Tableau scrollable horizontalement (beaucoup de colonnes = beaucoup de matieres)
- Colonnes fixes : Rang, Nom, Prenom, Moyenne Generale
- Colonnes scrollables : Moyennes par matiere
- Ligne de resume en bas : Moyenne de la classe par matiere, Note max, Note min
- Code couleur :
  - Cellule rouge si moyenne matiere < 10
  - Cellule verte si moyenne matiere >= 14
  - Cellule orange si moyenne matiere entre 10 et 12
- Boutons : "Exporter Excel", "Exporter PDF", "Mode Presentation"

#### 3.3.5 Ecran Admin : Statistiques de Classe

- **Carte 1** : Moyenne generale de la classe (grand chiffre) + evolution vs semestre precedent (fleche haut/bas)
- **Carte 2** : Taux de reussite (%) + evolution vs semestre precedent
- **Carte 3** : Effectif (nombre d'eleves)
- **Carte 4** : Eleve premier de la classe (nom + moyenne)
- **Graphique 1** : Histogramme de repartition par tranches de moyennes
- **Graphique 2** : Barres horizontales des matieres (classees par moyenne de classe, de la meilleure a la plus faible)
- **Tableau** : Comparaison S1 vs S2 (si conseil de S2) avec colonnes : Indicateur, S1, S2, Evolution

#### 3.3.6 Ecran Admin : Saisie des Decisions (S2 uniquement)

- Tableau avec colonnes : Rang, Nom, Prenom, Moyenne Annuelle, Proposition Auto, Decision Finale, Mention, Observation
- Colonne "Proposition Auto" : Affichee automatiquement basee sur les regles configurees (non editable)
- Colonne "Decision Finale" : Select (Passage / Redoublement / Exclusion) - editable par le conseil
- Colonne "Mention" : Select (Aucune / Encouragements / Tableau d'honneur / Felicitations) - editable
- Colonne "Observation" : Champ texte libre (optionnel)
- Bouton "Valider toutes les decisions" + bouton "Sauvegarder brouillon"
- Resume en bas : X passages, Y redoublements, Z exclusions

#### 3.3.7 Ecran Admin : Saisie des Appreciations Generales

- Liste des eleves avec :
  - Nom, Prenom, Photo (si disponible)
  - Moyenne du semestre
  - Zone de texte pour l'appreciation generale
  - Bouton "Suggestion" : Genere un texte suggere base sur la moyenne
- Navigation : Boutons "Precedent" / "Suivant" pour passer d'un eleve a l'autre
- Progression : Indicateur "12/55 appreciations saisies"
- Bouton "Sauvegarder" (sauvegarde auto toutes les 30 secondes)

#### 3.3.8 Ecran Admin : Registre de Presence du Conseil

- Liste des membres convoques avec :
  - Nom, Role (Enseignant de [Matiere], Delegue eleves, Delegue parents, President, Secretaire)
  - Checkbox "Present"
  - Champ "Motif d'absence" (si non present)
- Bouton "Enregistrer les presences"

#### 3.3.9 Ecran Admin : Visualisation et Telechargement du PV

- Apercu du PV genere (iframe ou viewer PDF)
- Bouton "Telecharger PDF"
- Bouton "Regenerer" (si le conseil n'est pas encore cloture definitivement)
- Metadonnees : Numero du PV, Date de generation, Genere par

#### 3.3.10 Ecran Enseignant : Consultation du Conseil de Classe

- Vue en lecture seule du tableau recapitulatif de sa classe
- Vue des statistiques de classe
- Possibilite de voir ses moyennes de matiere par rapport a la moyenne de classe
- Pas d'acces a la saisie de decisions ou d'appreciations generales

#### 3.3.11 Ecran Parent/Eleve : Consultation des Resultats du Conseil

- Apres cloture du conseil :
  - Appreciation generale de l'eleve
  - Decision de fin d'annee (si S2) : Passage / Redoublement
  - Mention obtenue (si applicable)
- Ces informations sont egalement integrees dans le bulletin semestriel

### 3.4 Accessibility

**WCAG AA** : Le systeme doit respecter les standards d'accessibilite WCAG 2.1 niveau AA :
- Navigation au clavier complete (tabs, enter, espace) dans le tableau recapitulatif et les formulaires de saisie
- Labels ARIA pour tous les elements interactifs (selects de decisions, checkboxes de presence)
- Contraste de couleurs suffisant (ratio 4.5:1 minimum) y compris pour les codes couleur dans le tableau
- Messages d'erreur clairs et accessibles aux lecteurs d'ecran
- Le mode presentation doit etre lisible a distance (taille de police minimum 18px)

### 3.5 Branding

- Interface professionnelle et institutionnelle
- Couleurs :
  - **Vert (#4CAF50)** : Passage, moyennes >= 14, validation
  - **Orange (#FF9800)** : Avertissements, moyennes entre 10 et 12, decisions en attente
  - **Rouge (#F44336)** : Redoublement, exclusion, moyennes < 10, erreurs
  - **Bleu (#2196F3)** : Actions primaires, liens, statuts en cours
  - **Violet (#9C27B0)** : Felicitations, mentions speciales
- Icones :
  - Conseil de classe (icone de reunion/groupe)
  - PV (icone de document officiel)
  - Decision (icone de verdict/marteau)
  - Statistiques (icone de graphique)

### 3.6 Target Device and Platforms

**Web Responsive** :
- Desktop (prioritaire) : Optimise pour la projection en salle de conseil et la saisie sur grand ecran
- Tablette : Interface adaptee pour la consultation et la saisie legere pendant la seance
- Mobile : Consultation des resultats du conseil pour parents et eleves uniquement (pas de saisie)

---

## 4. Technical Assumptions

### 4.1 Repository Structure

**Polyrepo** : Backend (Laravel) et Frontend (Next.js) dans des depots separes (architecture existante).

### 4.2 Service Architecture

**Architecture modulaire (nwidart/laravel-modules)** :
- Nouveau module Laravel : `Modules/ConseilDeClasse/`
- Structure standard :
  - `Entities/` : Models Eloquent (ClassCouncil, CouncilDecision, CouncilAppreciation, CouncilAttendance, CouncilMinutes, DecisionConfig)
  - `Http/Controllers/` : Controllers Admin, Frontend
  - `Http/Requests/` : Form Requests pour validation
  - `Http/Resources/` : API Resources
  - `Database/Migrations/tenant/` : Migrations tenant
  - `Database/Factories/` : Factories pour tests
  - `Routes/` : Routes admin.php, frontend.php
  - `Services/` : Services metier (StatisticsService, DecisionProposalService, MinutesGenerationService)

**Frontend Next.js** :
- Nouveau module : `src/modules/ConseilDeClasse/`
- Structure en 3 couches : `admin/`, `frontend/`, `types/`
- Services API avec `createApiClient()`
- Hooks React pour gestion de l'etat
- Composants specifiques : RecapTable, StatisticsCharts, DecisionForm, AppreciationForm, PresentationMode

### 4.3 Base de donnees

**Connexion** : `tenant` (base de donnees tenant dynamique)

**Tables a creer** :

```
class_councils
├── id (bigint, PK)
├── class_id (bigint, FK -> classes)
├── semester_id (bigint, FK -> semesters)
├── academic_year_id (bigint, FK -> academic_years)
├── scheduled_date (datetime)
├── scheduled_room (varchar, nullable)
├── president_id (bigint, FK -> users)
├── secretary_id (bigint, FK -> users, nullable)
├── status (enum: 'planned', 'in_progress', 'deliberations_done', 'closed')
├── general_observation (text, nullable) -- appreciation globale sur la classe
├── closed_at (datetime, nullable)
├── closed_by (bigint, FK -> users, nullable)
├── minutes_number (varchar, unique, nullable) -- ex: PV-2025-2026-001
├── created_at (timestamp)
├── updated_at (timestamp)
├── deleted_at (timestamp, nullable)
└── UNIQUE(class_id, semester_id, academic_year_id)

council_attendances
├── id (bigint, PK)
├── class_council_id (bigint, FK -> class_councils)
├── user_id (bigint, FK -> users, nullable) -- null si membre externe
├── member_name (varchar) -- nom affiche (utile pour delegues parents externes)
├── member_role (varchar) -- ex: 'Enseignant (Maths)', 'Delegue eleves', 'Delegue parents', 'President', 'Secretaire'
├── is_present (boolean, default false)
├── absence_reason (varchar, nullable)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

council_decisions
├── id (bigint, PK)
├── class_council_id (bigint, FK -> class_councils)
├── student_id (bigint, FK -> students/users)
├── annual_average (decimal 5,2) -- moyenne annuelle au moment de la decision
├── auto_proposal (enum: 'passage', 'redoublement', 'exclusion', nullable) -- proposition automatique
├── final_decision (enum: 'passage', 'redoublement', 'exclusion', nullable) -- decision du conseil
├── mention (enum: 'aucune', 'encouragements', 'tableau_honneur', 'felicitations', nullable)
├── auto_mention (enum: 'aucune', 'encouragements', 'tableau_honneur', 'felicitations', nullable)
├── observation (text, nullable) -- justification ou commentaire
├── decided_by (bigint, FK -> users, nullable)
├── decided_at (datetime, nullable)
├── created_at (timestamp)
├── updated_at (timestamp)
├── deleted_at (timestamp, nullable)
└── UNIQUE(class_council_id, student_id)

council_appreciations
├── id (bigint, PK)
├── class_council_id (bigint, FK -> class_councils)
├── student_id (bigint, FK -> students/users)
├── appreciation (text) -- appreciation generale par le president
├── appreciated_by (bigint, FK -> users)
├── created_at (timestamp)
├── updated_at (timestamp)
├── deleted_at (timestamp, nullable)
└── UNIQUE(class_council_id, student_id)

council_minutes
├── id (bigint, PK)
├── class_council_id (bigint, FK -> class_councils)
├── minutes_number (varchar, unique) -- numero unique du PV
├── file_path (varchar) -- chemin du fichier PDF genere
├── generated_by (bigint, FK -> users)
├── generated_at (datetime)
├── metadata (json, nullable) -- donnees snapshot au moment de la generation
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

decision_configs
├── id (bigint, PK)
├── academic_year_id (bigint, FK -> academic_years)
├── passing_threshold (decimal 4,2, default 10.00) -- seuil de passage
├── honors_threshold (decimal 4,2, default 14.00) -- seuil tableau d'honneur
├── encouragement_threshold (decimal 4,2, default 12.00) -- seuil encouragements
├── congratulations_threshold (decimal 4,2, default 16.00) -- seuil felicitations
├── auto_proposal_enabled (boolean, default true) -- activer proposition auto
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

council_history
├── id (bigint, PK)
├── class_council_id (bigint, FK -> class_councils)
├── action (varchar) -- ex: 'council_opened', 'decision_changed', 'appreciation_added', 'council_closed'
├── details (json, nullable) -- donnees specifiques de l'action (ancien/nouveau)
├── performed_by (bigint, FK -> users)
├── performed_at (datetime)
├── created_at (timestamp)
└── updated_at (timestamp)
```

**Relations cles** :
- `class_councils` belongsTo `classes`, `semesters`, `academic_years`, `users` (president, secretary)
- `class_councils` hasMany `council_attendances`, `council_decisions`, `council_appreciations`, `council_minutes`, `council_history`
- `council_decisions` belongsTo `class_councils`, `users` (student)
- `council_appreciations` belongsTo `class_councils`, `users` (student, appreciated_by)
- Utiliser **eager loading** pour eviter les N+1 queries (critique pour le tableau recapitulatif)

### 4.4 API Endpoints

#### Routes Admin (prefix: `/api/admin/`)

**Conseils de Classe (CRUD + Workflow)**
```
GET    /class-councils                          -- Liste des conseils (filtres: semester, class, status)
POST   /class-councils                          -- Planifier un conseil
GET    /class-councils/{id}                     -- Detail d'un conseil
PUT    /class-councils/{id}                     -- Modifier un conseil planifie
DELETE /class-councils/{id}                     -- Annuler un conseil planifie
POST   /class-councils/{id}/open                -- Ouvrir le conseil (statut -> in_progress)
POST   /class-councils/{id}/close               -- Cloturer le conseil (statut -> closed)
```

**Tableau Recapitulatif et Statistiques**
```
GET    /class-councils/{id}/recap               -- Tableau recapitulatif de la classe
GET    /class-councils/{id}/statistics           -- Statistiques de la classe
GET    /class-councils/{id}/recap/export/excel   -- Export Excel du tableau recapitulatif
GET    /class-councils/{id}/recap/export/pdf     -- Export PDF du tableau recapitulatif
```

**Presences**
```
GET    /class-councils/{id}/attendances          -- Liste des membres convoques et presences
POST   /class-councils/{id}/attendances          -- Enregistrer/Mettre a jour les presences
```

**Decisions**
```
GET    /class-councils/{id}/decisions            -- Liste des decisions (avec propositions auto)
POST   /class-councils/{id}/decisions            -- Sauvegarder les decisions (batch)
PUT    /class-councils/{id}/decisions/{student_id} -- Modifier une decision individuelle
```

**Appreciations**
```
GET    /class-councils/{id}/appreciations        -- Liste des appreciations
POST   /class-councils/{id}/appreciations        -- Sauvegarder les appreciations (batch)
PUT    /class-councils/{id}/appreciations/{student_id} -- Modifier une appreciation individuelle
GET    /class-councils/{id}/appreciations/suggest/{student_id} -- Suggestion d'appreciation
```

**PV (Proces-Verbal)**
```
POST   /class-councils/{id}/minutes/generate     -- Generer le PV (PDF)
GET    /class-councils/{id}/minutes               -- Consulter le PV genere
GET    /class-councils/{id}/minutes/download      -- Telecharger le PV (PDF)
```

**Configuration**
```
GET    /decision-configs                          -- Configuration des seuils de decision
POST   /decision-configs                          -- Creer/Mettre a jour la configuration
```

**Historique**
```
GET    /class-councils/{id}/history               -- Historique des actions sur un conseil
```

#### Routes Frontend (prefix: `/api/frontend/`)

```
GET    /class-councils/my-results/{semester_id}   -- Resultats du conseil pour l'eleve connecte
GET    /class-councils/my-children-results/{semester_id} -- Resultats pour les enfants d'un parent
```

### 4.5 Testing Requirements

**Tests obligatoires** :

- **Tests unitaires** :
  - Calcul des statistiques de classe (moyenne, taux de reussite, repartition par tranches)
  - Proposition automatique de decisions (passage/redoublement basee sur seuils)
  - Proposition automatique de mentions (Felicitations/Tableau d'honneur/Encouragements basee sur seuils)
  - Numerotation sequentielle des PV
  - Validation des transitions de statut du workflow

- **Tests d'integration** :
  - Workflow complet : Planification -> Ouverture -> Saisie decisions -> Cloture -> Generation PV
  - Verifier que les decisions ne peuvent pas etre modifiees apres cloture
  - Verifier que les decisions ne sont saisissables que pour le S2
  - Export Excel/PDF du tableau recapitulatif
  - Integration avec le Module Notes (recuperation des moyennes)

- **Tests de cas limites** :
  - Classe avec un seul eleve
  - Classe ou tous les eleves ont la meme moyenne (rang ex-aequo)
  - Moyenne exactement egale au seuil de passage (10.00)
  - Eleve sans aucune note (absent a toutes les evaluations)
  - Conseil sans aucune decision enregistree (S1 uniquement)

- **Tests de performance** :
  - Tableau recapitulatif avec 60 eleves et 12 matieres en < 2 secondes
  - Generation du PV en < 10 secondes

**Outils** :
- Laravel : PHPUnit (`php artisan test`)
- Frontend : Jest + React Testing Library

### 4.6 Additional Technical Assumptions

- **API REST** : Endpoints suivant les conventions existantes (`/api/admin/class-councils/*`, `/api/frontend/class-councils/*`)
- **Middleware** : `['tenant', 'tenant.auth']` pour toutes les routes
- **Permissions Spatie** :
  - `manage-class-councils` : Planifier, ouvrir, cloturer un conseil (Admin/Directeur/Censeur)
  - `manage-council-decisions` : Saisir et modifier les decisions (Admin/Directeur)
  - `manage-council-appreciations` : Saisir les appreciations generales (Admin/Directeur/Censeur)
  - `view-class-councils` : Consulter les donnees du conseil (Enseignant)
  - `view-council-results` : Consulter les resultats apres cloture (Eleve, Parent)
- **Validation** : Form Requests pour toutes les saisies (StoreClassCouncilRequest, StoreDecisionRequest, StoreAppreciationRequest, etc.)
- **API Resources** : Retourner toujours des Resources, jamais de models bruts (ClassCouncilResource, CouncilDecisionResource, CouncilStatisticsResource, etc.)
- **SoftDeletes** : Utiliser sur toutes les tables (historique et tracabilite)
- **Casts Laravel 12** : Utiliser `casts()` method sur les models (statut en enum, metadata en array/json)
- **Format JSON** : Reponses standardisees avec `success`, `message`, `data`
- **PDF Generation** : Utiliser `barryvdh/laravel-snappy` ou `barryvdh/laravel-dompdf` pour la generation du PV (meme approche que le Module Documents Officiels)
- **Queues** : La generation du PV doit etre effectuee de maniere asynchrone via Laravel Queues pour ne pas bloquer l'interface
- **Cache** : Mettre en cache les statistiques de classe calculees (invalidation au changement de notes ou decisions)

---

## 5. Epic List

### Epic 1 : Planification et Workflow du Conseil de Classe
**Goal** : Permettre a l'Admin de planifier les conseils de classe, gerer les convocations et piloter le cycle de vie (planification -> ouverture -> deliberations -> cloture).

### Epic 2 : Tableau Recapitulatif et Statistiques de Classe
**Goal** : Generer automatiquement le tableau recapitulatif consolidant toutes les notes et moyennes, accompagne des statistiques cles (taux de reussite, repartition par tranches, matieres fortes/faibles).

### Epic 3 : Decisions de Fin d'Annee et Mentions
**Goal** : Permettre la saisie des decisions individuelles (Passage, Redoublement, Exclusion) avec propositions automatiques basees sur les moyennes, et l'attribution des mentions (Felicitations, Tableau d'honneur, Encouragements).

### Epic 4 : Appreciations Generales
**Goal** : Permettre au president du conseil de saisir une appreciation generale par eleve avec suggestions automatiques et saisie en masse.

### Epic 5 : Generation du Proces-Verbal (PV)
**Goal** : Generer automatiquement le PV officiel du conseil de classe en PDF avec toutes les informations reglementaires, numerotation unique, et archivage.

### Epic 6 : Consultation des Resultats et Integration
**Goal** : Permettre aux enseignants, eleves et parents de consulter les resultats du conseil, et assurer l'integration avec le Module Documents Officiels pour les bulletins.

---

## 6. Epic Details

### Epic 1 : Planification et Workflow du Conseil de Classe

**Goal detaille** : L'Admin/Directeur doit pouvoir planifier les conseils de classe pour chaque classe et chaque semestre, generer les convocations, gerer le registre de presence, et piloter le cycle de vie du conseil. Cette epic etablit le cadre organisationnel dans lequel s'inscrivent toutes les autres fonctionnalites.

#### Story 1.1 : Planification d'un Conseil de Classe

**As an** Admin/Directeur,
**I want** planifier un conseil de classe avec une date, une heure, une salle et les responsables,
**so that** tous les participants sachent quand et ou le conseil aura lieu.

**Acceptance Criteria** :
1. Un ecran "Conseils de Classe" affiche la liste de tous les conseils planifies ou passes
2. Un bouton "Planifier un conseil" ouvre un formulaire avec : Classe (select), Semestre (select : S1 ou S2), Date et heure, Salle (texte), President (select parmi Admin/Directeur/Censeur), Secretaire (select parmi enseignants et personnel)
3. Le systeme empeche la creation de deux conseils pour la meme classe et le meme semestre
4. Le systeme verifie que toutes les notes du semestre sont publiees avant de permettre la planification (avertissement si des notes sont manquantes)
5. Le conseil est cree avec le statut "Planifie"
6. Un message de succes s'affiche : "Conseil de classe pour [Classe] - [Semestre] planifie le [Date]"
7. La liste des conseils est filtrable par semestre, classe, cycle (College/Lycee), et statut

**Dependances** : Module Structure Academique (classes, semestres), Module Notes (verification notes publiees)

---

#### Story 1.2 : Generation de la Liste de Convocation

**As an** Admin/Directeur,
**I want** generer automatiquement la liste des membres a convoquer au conseil,
**so that** je puisse m'assurer que tous les participants sont informes.

**Acceptance Criteria** :
1. Lors de la creation du conseil, le systeme genere automatiquement la liste des membres a convoquer :
   - President (selectionne dans le formulaire)
   - Secretaire (selectionne dans le formulaire)
   - Tous les enseignants affectes a des matieres de cette classe (recuperes depuis les affectations enseignant-matiere-classe)
   - Le professeur principal de la classe (identifie dans la structure academique)
2. Le systeme permet d'ajouter manuellement des membres supplementaires : Delegues des eleves (selection parmi les eleves inscrits ou saisie libre du nom), Delegues des parents (saisie libre du nom)
3. La liste de convocation est consultable et modifiable depuis le detail du conseil
4. Un bouton "Exporter la convocation" permet de generer un PDF de convocation avec la liste des membres, la date, l'heure et la salle
5. Le total des membres convoques est affiche

**Dependances** : Story 1.1, Module Structure Academique (affectations enseignants-classes)

---

#### Story 1.3 : Registre de Presence du Conseil

**As an** Admin/Directeur,
**I want** enregistrer la presence des membres lors du conseil,
**so that** le PV mentionne correctement les membres presents et absents.

**Acceptance Criteria** :
1. Un ecran "Registre de Presence" affiche la liste de tous les membres convoques
2. Chaque membre a une checkbox "Present" (defaut : non coche)
3. Si un membre est absent, un champ "Motif d'absence" apparait (optionnel)
4. Le nombre de presents / total est affiche en temps reel (ex : "8/12 presents")
5. Les presences sont sauvegardees et utilisees dans la generation du PV
6. Le systeme empeche l'ouverture du conseil si le president n'est pas marque present

**Dependances** : Story 1.2

---

#### Story 1.4 : Ouverture et Cloture du Conseil

**As an** Admin/Directeur (President),
**I want** ouvrir officiellement le conseil et le cloturer apres les deliberations,
**so that** le workflow du conseil soit formalise et les donnees protegees.

**Acceptance Criteria** :
1. Un bouton "Ouvrir le conseil" est disponible lorsque le conseil est en statut "Planifie"
2. L'ouverture change le statut en "En cours" et enregistre la date/heure reelle d'ouverture
3. Seul le president (ou un Admin) peut ouvrir le conseil
4. Pendant le statut "En cours", les decisions et appreciations sont editables
5. Un bouton "Cloturer le conseil" est disponible lorsque le statut est "En cours" ou "Deliberations terminees"
6. Avant la cloture, le systeme verifie :
   - Pour S1 : Toutes les appreciations sont saisies (avertissement si manquantes)
   - Pour S2 : Toutes les decisions et appreciations sont saisies (avertissement si manquantes)
7. La cloture change le statut en "Cloture" et declenche automatiquement la generation du PV
8. Apres cloture, les decisions et appreciations ne sont plus modifiables (sauf par Admin avec justification)
9. Un historique de l'action est enregistre dans `council_history`

**Dependances** : Story 1.3

---

### Epic 2 : Tableau Recapitulatif et Statistiques de Classe

**Goal detaille** : Le systeme doit generer automatiquement un tableau recapitulatif complet de toutes les notes et moyennes de la classe, accompagne de statistiques detaillees (moyenne de classe, taux de reussite, repartition par tranches, matieres fortes/faibles). Ces donnees sont la base des deliberations du conseil.

#### Story 2.1 : Generation du Tableau Recapitulatif

**As an** Admin/Directeur,
**I want** visualiser un tableau recapitulatif consolidant toutes les notes de la classe pour le semestre,
**so that** je puisse avoir une vue d'ensemble rapide des resultats lors du conseil.

**Acceptance Criteria** :
1. Le tableau recapitulatif affiche en colonnes : Rang, Nom, Prenom, une colonne par matiere (avec coefficient affiche en en-tete), Moyenne Generale
2. Les donnees sont recuperees depuis le Module Notes (moyennes par matiere, moyenne generale semestrielle)
3. Le tableau est trie par rang (meilleur au moins bon, base sur la moyenne generale)
4. Les rangs ex-aequo sont geres correctement (ex : 2 eleves avec la meme moyenne = meme rang, rang suivant = rang + 2)
5. Les cellules de moyenne sont colorees selon les seuils :
   - Rouge si < 10/20
   - Orange si >= 10 et < 12
   - Vert si >= 14
6. Les eleves dont la moyenne generale est inferieure au seuil configurable (defaut 10/20) sont mis en surbrillance (ligne rouge clair)
7. Le tableau est scrollable horizontalement si le nombre de matieres depasse la largeur de l'ecran
8. Les colonnes Rang, Nom, Prenom sont fixees a gauche lors du scroll horizontal

**Dependances** : Story 1.4 (conseil ouvert), Module Notes (moyennes)

---

#### Story 2.2 : Ligne de Resume par Matiere

**As an** Admin/Directeur,
**I want** voir en bas du tableau recapitulatif un resume par matiere (moyenne classe, note max, note min),
**so that** je puisse identifier rapidement les matieres problematiques.

**Acceptance Criteria** :
1. En bas du tableau recapitulatif, une ligne "Moyenne de la classe" affiche la moyenne de la classe pour chaque matiere
2. Une ligne "Note la plus haute" affiche la meilleure note par matiere
3. Une ligne "Note la plus basse" affiche la plus mauvaise note par matiere
4. Les matieres dont la moyenne de classe est < 10 sont mises en evidence (fond rouge clair)
5. Les matieres dont la moyenne de classe est >= 14 sont mises en evidence (fond vert clair)

**Dependances** : Story 2.1

---

#### Story 2.3 : Export du Tableau Recapitulatif

**As an** Admin/Directeur,
**I want** exporter le tableau recapitulatif en Excel et en PDF,
**so that** je puisse l'imprimer ou le partager.

**Acceptance Criteria** :
1. Un bouton "Exporter Excel" genere un fichier .xlsx avec le meme contenu que le tableau a l'ecran
2. Un bouton "Exporter PDF" genere un fichier .pdf en format paysage (A4 landscape) pour accommoder les nombreuses colonnes
3. Le fichier exporte inclut : en-tete (nom etablissement, classe, semestre, annee scolaire), le tableau complet, les lignes de resume
4. Le nom du fichier suit le format : `Recap_[Classe]_[Semestre]_[AnneesScolaire].[ext]` (ex : `Recap_3eA_S1_2025-2026.xlsx`)
5. Le telechargement demarre automatiquement apres clic

**Dependances** : Story 2.1

---

#### Story 2.4 : Statistiques de Classe

**As an** Admin/Directeur,
**I want** consulter les statistiques detaillees de la classe pour le semestre,
**so that** je puisse evaluer la performance globale de la classe.

**Acceptance Criteria** :
1. Les statistiques suivantes sont calculees et affichees :
   - **Moyenne generale de la classe** : Moyenne des moyennes generales de tous les eleves
   - **Taux de reussite** : Pourcentage d'eleves ayant une moyenne >= 10/20
   - **Ecart-type** : Dispersion des moyennes autour de la moyenne de classe
   - **Moyenne la plus haute** : Meilleure moyenne generale (avec nom de l'eleve)
   - **Moyenne la plus basse** : Plus faible moyenne generale (avec nom de l'eleve)
2. La repartition par tranches de moyennes est affichee sous forme d'histogramme :
   - [0-5[ : X eleves (Y%)
   - [5-8[ : X eleves (Y%)
   - [8-10[ : X eleves (Y%)
   - [10-12[ : X eleves (Y%)
   - [12-14[ : X eleves (Y%)
   - [14-16[ : X eleves (Y%)
   - [16-20] : X eleves (Y%)
3. Les 3 matieres les plus fortes (meilleures moyennes de classe) sont affichees
4. Les 3 matieres les plus faibles (plus faibles moyennes de classe) sont affichees
5. Si le semestre precedent est disponible, une comparaison est affichee : evolution de la moyenne de classe, evolution du taux de reussite (avec fleche haut/bas et pourcentage de variation)

**Dependances** : Story 2.1, Module Notes (donnees de moyennes)

---

#### Story 2.5 : Mode Presentation

**As an** Admin/Directeur,
**I want** projeter le tableau recapitulatif et les statistiques en mode plein ecran,
**so that** tous les participants du conseil puissent voir les donnees sur grand ecran.

**Acceptance Criteria** :
1. Un bouton "Mode Presentation" bascule l'ecran en mode plein ecran
2. En mode presentation, les polices sont agrandies (minimum 18px pour le texte, 24px pour les titres)
3. Les elements de navigation (menu, sidebar) sont masques pour maximiser l'espace d'affichage
4. Le mode presentation permet de basculer entre : Tableau Recapitulatif, Statistiques, Detail d'un eleve
5. La navigation se fait par touches fleches (gauche/droite) ou boutons gros (precedent/suivant)
6. Un bouton "Quitter la presentation" (ou touche Echap) ramene a l'interface normale
7. Le mode presentation est optimise pour les resolutions 1024x768 et 1920x1080

**Dependances** : Story 2.1, Story 2.4

---

### Epic 3 : Decisions de Fin d'Annee et Mentions

**Goal detaille** : Le systeme doit permettre la saisie des decisions de fin d'annee (Passage, Redoublement, Exclusion) pour chaque eleve lors du conseil de S2, avec des propositions automatiques basees sur les moyennes annuelles et des conditions configurables. Le systeme doit egalement gerer l'attribution des mentions (Felicitations, Tableau d'honneur, Encouragements).

#### Story 3.1 : Configuration des Seuils de Decision et Mentions

**As an** Admin/Directeur,
**I want** configurer les seuils de passage et d'attribution des mentions pour mon etablissement,
**so that** le systeme puisse generer des propositions automatiques coherentes.

**Acceptance Criteria** :
1. Un ecran de configuration "Seuils de Decision" est accessible via les parametres du conseil de classe
2. Je peux definir :
   - Seuil de passage (defaut : 10.00/20)
   - Seuil pour Encouragements (defaut : 12.00/20)
   - Seuil pour Tableau d'honneur (defaut : 14.00/20)
   - Seuil pour Felicitations (defaut : 16.00/20)
   - Activer/desactiver les propositions automatiques (defaut : active)
3. Les seuils sont enregistres par annee scolaire dans la table `decision_configs`
4. Un message de validation empeche les incoherences (ex : seuil Felicitations < seuil Tableau d'honneur)
5. Les seuils sont utilises par le systeme pour generer les propositions automatiques

**Dependances** : Module Structure Academique (annees scolaires)

---

#### Story 3.2 : Proposition Automatique de Decisions

**As a** System,
**I want** calculer automatiquement une proposition de decision pour chaque eleve basee sur sa moyenne annuelle,
**so that** le conseil dispose d'une base de travail pour ses deliberations.

**Acceptance Criteria** :
1. Lors de l'ouverture du conseil de S2, le systeme calcule pour chaque eleve :
   - La moyenne annuelle = (Moyenne S1 + Moyenne S2) / 2
   - La proposition automatique :
     - Si moyenne annuelle >= seuil de passage : Proposition = "Passage"
     - Si moyenne annuelle < seuil de passage : Proposition = "Redoublement"
2. Le systeme calcule egalement la proposition automatique de mention :
   - Si moyenne annuelle >= seuil Felicitations : Mention = "Felicitations"
   - Si moyenne annuelle >= seuil Tableau d'honneur : Mention = "Tableau d'honneur"
   - Si moyenne annuelle >= seuil Encouragements : Mention = "Encouragements"
   - Sinon : Mention = "Aucune"
3. Les propositions sont stockees dans les colonnes `auto_proposal` et `auto_mention` de `council_decisions`
4. Les propositions sont affichees dans une colonne distincte de la decision finale (lecture seule)
5. Le conseil peut adopter ou modifier chaque proposition
6. Les propositions ne sont pas generees pour le conseil de S1 (S1 est consultif, pas de decisions)

**Dependances** : Story 3.1, Module Notes (moyennes S1 et S2)

---

#### Story 3.3 : Saisie des Decisions Individuelles

**As an** Admin/Directeur (President du conseil),
**I want** saisir ou modifier la decision finale pour chaque eleve,
**so that** le conseil puisse officialiser ses deliberations.

**Acceptance Criteria** :
1. L'ecran de saisie des decisions affiche un tableau avec : Rang, Nom, Prenom, Moyenne Annuelle, Proposition Auto, Decision Finale (select), Mention (select), Observation (texte)
2. La colonne "Decision Finale" est un select avec les options : Passage, Redoublement, Exclusion
3. La colonne "Mention" est un select avec les options : Aucune, Encouragements, Tableau d'honneur, Felicitations
4. Par defaut, la Decision Finale est pre-remplie avec la proposition automatique (modifiable)
5. Par defaut, la Mention est pre-remplie avec la proposition automatique de mention (modifiable)
6. Le champ "Observation" permet de justifier un ecart entre la proposition et la decision finale (ex : "Passage accorde sur avis favorable du conseil malgre moyenne insuffisante")
7. Un resume en bas du tableau affiche : X passages, Y redoublements, Z exclusions
8. Un bouton "Sauvegarder les decisions" enregistre toutes les decisions en une seule operation (batch save)
9. Les decisions ne sont saisissables que si le conseil est en statut "En cours" et que c'est un conseil de S2
10. Un historique des modifications est enregistre dans `council_history` pour chaque changement de decision

**Dependances** : Story 3.2, Story 1.4 (conseil en cours)

---

#### Story 3.4 : Protection des Decisions apres Cloture

**As an** Admin/Directeur,
**I want** que les decisions soient protegees contre toute modification apres la cloture du conseil,
**so that** l'integrite des deliberations soit preservee.

**Acceptance Criteria** :
1. Apres cloture du conseil, les champs Decision Finale, Mention et Observation sont en lecture seule
2. Seul un utilisateur avec le role Admin peut modifier une decision apres cloture
3. En cas de modification post-cloture, le systeme exige une justification obligatoire (champ texte)
4. La modification post-cloture est enregistree dans `council_history` avec : ancienne valeur, nouvelle valeur, justification, utilisateur, date/heure
5. Un badge "Modifie apres cloture" est affiche sur les decisions qui ont ete modifiees apres la cloture

**Dependances** : Story 3.3, Story 1.4

---

### Epic 4 : Appreciations Generales

**Goal detaille** : Le president du conseil doit pouvoir saisir une appreciation generale par eleve, distincte des appreciations par matiere (qui sont saisies par les enseignants dans le Module Notes). Le systeme propose des suggestions basees sur les performances de l'eleve pour accelerer la saisie.

#### Story 4.1 : Saisie des Appreciations Generales par Eleve

**As an** Admin/Directeur (President du conseil),
**I want** saisir une appreciation generale pour chaque eleve,
**so that** le bulletin semestriel inclue un commentaire global du conseil de classe.

**Acceptance Criteria** :
1. Un ecran "Appreciations Generales" affiche la liste des eleves avec : Photo (si disponible), Nom, Prenom, Moyenne du semestre, Zone de texte pour l'appreciation
2. L'appreciation est un champ texte libre (maximum 500 caracteres)
3. Je peux saisir les appreciations en mode "defilement" : boutons "Precedent" / "Suivant" pour naviguer entre les eleves sans rechargement
4. Un indicateur de progression affiche le nombre d'appreciations saisies (ex : "12/55 saisies")
5. La sauvegarde est automatique toutes les 30 secondes (ou au clic "Sauvegarder")
6. L'appreciation est stockee dans la table `council_appreciations`
7. L'appreciation generale est distincte des appreciations par matiere (Module Notes) et sera integree au bulletin
8. Les appreciations ne sont saisissables que si le conseil est en statut "En cours"

**Dependances** : Story 1.4 (conseil en cours), Module Notes (moyennes pour affichage)

---

#### Story 4.2 : Suggestions d'Appreciations

**As an** Admin/Directeur (President du conseil),
**I want** recevoir des suggestions d'appreciations basees sur les resultats de l'eleve,
**so that** je puisse gagner du temps tout en personnalisant chaque appreciation.

**Acceptance Criteria** :
1. Un bouton "Suggerer" est disponible a cote de chaque zone de texte d'appreciation
2. Le clic sur "Suggerer" genere un texte suggere base sur la moyenne de l'eleve :
   - Moyenne >= 16 : "Excellent travail. Continuez ainsi."
   - Moyenne >= 14 : "Tres bon travail. Eleve serieux(se) et applique(e)."
   - Moyenne >= 12 : "Bon travail dans l'ensemble. Peut mieux faire."
   - Moyenne >= 10 : "Travail passable. Des efforts supplementaires sont necessaires."
   - Moyenne < 10 : "Travail insuffisant. Des efforts importants sont attendus."
3. Le texte suggere est insere dans la zone de texte (remplace le contenu existant, avec confirmation si non vide)
4. Le president peut modifier le texte suggere avant de sauvegarder
5. Les templates de suggestion sont configurables par l'Admin (Phase 2 - pour le MVP, templates fixes)
6. Un bouton "Appliquer la suggestion a tous les eleves sans appreciation" permet de pre-remplir toutes les appreciations vides en un clic

**Dependances** : Story 4.1

---

### Epic 5 : Generation du Proces-Verbal (PV)

**Goal detaille** : Le systeme doit generer automatiquement un PV officiel du conseil de classe au format PDF, contenant toutes les informations reglementaires (membres presents, statistiques, decisions, observations). Le PV est numerote de maniere unique et archive pour consultation ulterieure.

#### Story 5.1 : Generation Automatique du PV

**As an** Admin/Directeur,
**I want** generer automatiquement le PV du conseil de classe en PDF,
**so that** le document officiel soit cree sans effort de redaction manuelle.

**Acceptance Criteria** :
1. La generation du PV est declenchee automatiquement lors de la cloture du conseil (ou manuellement via un bouton "Generer PV")
2. Le PV contient les sections suivantes :
   - **En-tete** : Logo etablissement, Nom etablissement, "PROCES-VERBAL DU CONSEIL DE CLASSE", Annee scolaire, Semestre
   - **Informations** : Classe, Date et heure du conseil, Salle
   - **Membres presents** : Liste avec nom et role
   - **Membres absents** : Liste avec nom, role et motif d'absence (si renseigne)
   - **Statistiques** : Effectif, Moyenne de classe, Taux de reussite, Repartition par tranches
   - **Observations generales** : Texte saisi par le president sur la classe
   - **Decisions individuelles** (S2 uniquement) : Tableau Nom, Moyenne annuelle, Decision, Mention, Observation
   - **Pied de page** : Date de generation, Signature du President, Signature du Secretaire, Numero du PV
3. Le PV est numerote sequentiellement par annee scolaire (ex : PV-2025-2026-001, PV-2025-2026-002, ...)
4. Le PDF est genere en format A4 portrait avec pagination automatique
5. Le PV est stocke dans le systeme de fichiers et reference dans la table `council_minutes`
6. La generation est asynchrone (via Laravel Queue) pour ne pas bloquer l'interface
7. Un message de succes s'affiche : "PV genere avec succes. Numero : PV-2025-2026-001"

**Dependances** : Story 1.4 (cloture), Story 2.4 (statistiques), Story 3.3 (decisions), Module Documents Officiels (infrastructure PDF)

---

#### Story 5.2 : Consultation et Telechargement du PV

**As an** Admin/Directeur,
**I want** consulter le PV genere et le telecharger,
**so that** je puisse l'imprimer et le faire signer.

**Acceptance Criteria** :
1. Un onglet "PV" est disponible dans le detail du conseil de classe
2. L'onglet affiche un apercu du PV (viewer PDF integre dans la page)
3. Un bouton "Telecharger PDF" permet de telecharger le fichier
4. Le nom du fichier telecharge suit le format : `PV_[Classe]_[Semestre]_[AnneeScolaire].pdf` (ex : `PV_3eA_S2_2025-2026.pdf`)
5. Les metadonnees du PV sont affichees : Numero du PV, Date de generation, Genere par
6. Si le conseil a ete rouvert et re-cloture, le PV est regenere et l'ancienne version est conservee dans l'historique

**Dependances** : Story 5.1

---

#### Story 5.3 : Regeneration du PV

**As an** Admin/Directeur,
**I want** regenerer le PV si des modifications ont ete apportees apres la premiere generation,
**so that** le PV reflette les deliberations finales.

**Acceptance Criteria** :
1. Un bouton "Regenerer le PV" est disponible si des modifications post-cloture ont ete effectuees sur les decisions
2. La regeneration cree une nouvelle version du PV avec un suffixe (ex : PV-2025-2026-001-v2)
3. L'ancienne version du PV est conservee et reste accessible dans l'historique
4. Le numero de PV principal reste le meme (seul le suffixe de version change)
5. Un historique des versions du PV est affiche avec : version, date de generation, raison de la regeneration

**Dependances** : Story 5.2, Story 3.4

---

### Epic 6 : Consultation des Resultats et Integration

**Goal detaille** : Apres la cloture du conseil, les enseignants, eleves et parents doivent pouvoir consulter les resultats. Le module doit egalement fournir les donnees necessaires au Module Documents Officiels pour integrer les decisions et appreciations dans les bulletins semestriels.

#### Story 6.1 : Consultation par les Enseignants

**As an** Enseignant,
**I want** consulter le tableau recapitulatif et les statistiques des classes ou j'enseigne,
**so that** je puisse evaluer la performance de mes eleves dans le contexte global de la classe.

**Acceptance Criteria** :
1. Un ecran "Conseils de Classe" affiche la liste des conseils clotures pour les classes ou j'enseigne
2. Pour chaque conseil, je peux voir le tableau recapitulatif en lecture seule
3. Je peux voir les statistiques de classe
4. Je peux voir la moyenne de ma matiere comparee a la moyenne de classe
5. Je ne peux pas voir les decisions ni les appreciations generales pendant la seance (uniquement apres cloture)
6. Apres cloture, je peux voir les decisions et appreciations en lecture seule

**Dependances** : Story 1.4, Story 2.1, Story 2.4, Module Structure Academique (affectations enseignant-classe)

---

#### Story 6.2 : Consultation des Resultats par les Eleves

**As an** Eleve,
**I want** consulter les resultats du conseil de classe me concernant,
**so that** je connaisse mon appreciation generale et ma decision de fin d'annee.

**Acceptance Criteria** :
1. Apres cloture du conseil, un ecran "Resultats du Conseil" est disponible dans le portail de l'eleve
2. L'ecran affiche :
   - Mon rang dans la classe
   - Ma moyenne generale du semestre
   - L'appreciation generale du conseil
   - La decision de fin d'annee (si S2) : Passage / Redoublement
   - La mention obtenue (si applicable)
3. L'eleve ne peut voir que ses propres resultats (pas ceux des autres eleves)
4. Les resultats ne sont visibles qu'apres la cloture du conseil
5. Ces informations sont egalement presentes dans le bulletin semestriel

**Dependances** : Story 1.4 (cloture), Story 3.3 (decisions), Story 4.1 (appreciations)

---

#### Story 6.3 : Consultation des Resultats par les Parents

**As a** Parent/Tuteur,
**I want** consulter les resultats du conseil de classe de mon enfant,
**so that** je sois informe de son appreciation generale et de sa decision de fin d'annee.

**Acceptance Criteria** :
1. Apres cloture du conseil, les resultats sont visibles dans le portail parent
2. Le parent voit les memes informations que l'eleve (Story 6.2) pour chacun de ses enfants
3. Si le parent a plusieurs enfants, il peut basculer entre les enfants
4. Les resultats ne sont visibles qu'apres la cloture du conseil
5. Le parent peut telecharger le bulletin semestriel incluant les donnees du conseil

**Dependances** : Story 6.2, Module Inscriptions (liaison parent-eleve)

---

#### Story 6.4 : API d'Integration avec le Module Documents Officiels

**As a** System (Module Documents Officiels),
**I want** recuperer les donnees du conseil de classe via une API,
**so that** les bulletins semestriels incluent les decisions, appreciations et mentions du conseil.

**Acceptance Criteria** :
1. Un endpoint `/api/admin/class-councils/student-results/{student_id}/{semester_id}` retourne :
   - Appreciation generale du conseil
   - Decision de fin d'annee (si S2) : Passage / Redoublement / Exclusion
   - Mention : Aucune / Encouragements / Tableau d'honneur / Felicitations
   - Rang dans la classe
   - Moyenne generale de la classe (pour comparaison sur le bulletin)
   - Effectif de la classe
2. L'endpoint retourne `null` pour les champs non disponibles (ex : decision = null pour S1)
3. L'endpoint est securise avec middleware `tenant.auth` et permission `view-council-results`
4. Les donnees sont retournees au format JSON standardise avec `success`, `message`, `data`
5. L'endpoint est performant (< 200ms) car appele pour chaque eleve lors de la generation en masse des bulletins

**Dependances** : Story 3.3 (decisions), Story 4.1 (appreciations), Module Documents Officiels

---

## 7. Out of Scope (Hors Perimetre)

Les fonctionnalites suivantes sont explicitement exclues du perimetre de ce module pour le MVP :

1. **Notifications SMS/Email** des convocations aux membres du conseil (Phase 2)
2. **Vote electronique** pour les decisions (les decisions sont prises par deliberation et saisies par le president)
3. **Signature electronique** sur le PV (le PV est imprime et signe manuellement pour le MVP)
4. **Templates d'appreciation configurables** par l'Admin (templates fixes pour le MVP, configurables en Phase 2)
5. **Statistiques multi-classes** comparatives (comparaison entre classes d'un meme niveau - Phase 2)
6. **Integration avec le Module Discipline** pour afficher les sanctions dans le conseil (Phase 2, apres creation du Module Discipline)
7. **Integration avec le Module Presences** pour afficher le total d'absences par eleve dans le tableau recapitulatif (Phase 2)
8. **Conseil de discipline** (fonctionnalite separee dans le Module Discipline)
9. **Archivage longue duree** des PV (archivage standard dans le systeme de fichiers pour le MVP)
10. **Mode hors-ligne** pour la saisie des decisions (PWA offline en Phase 2)

---

## 8. Technical Considerations

### 8.1 Performance

- **Tableau recapitulatif** : La requete SQL doit etre optimisee avec eager loading (classes -> eleves -> notes -> matieres). Utiliser une requete agrregee plutot que des boucles N+1
- **Statistiques** : Les statistiques doivent etre calculees cote serveur (pas cote frontend) pour les classes avec beaucoup d'eleves. Mettre en cache les resultats avec invalidation lors de la modification des notes
- **Generation PDF** : Utiliser les Queues Laravel pour la generation asynchrone du PV. Prevoir un mecanisme de notification lorsque le PV est pret

### 8.2 Securite

- Les decisions de fin d'annee sont des donnees sensibles : acces strictement controle par permissions Spatie
- L'historique des modifications (`council_history`) est immutable : aucune suppression possible
- Le PV genere ne doit pas etre modifiable (fichier PDF en lecture seule)

### 8.3 Integration

- **Module Notes** : Dependance critique pour les moyennes. Le Module Conseil de Classe lit les donnees du Module Notes mais ne les modifie jamais
- **Module Structure Academique** : Dependance pour les classes, matieres, coefficients, semestres
- **Module Inscriptions** : Dependance pour la liste des eleves et la liaison parent-eleve
- **Module Documents Officiels** : Le Module Documents consomme les donnees du conseil pour generer les bulletins

### 8.4 Scalabilite

- Le systeme doit supporter la tenue de 20+ conseils simultanes (un par classe, en periode de fin de semestre)
- La base de donnees des decisions doit supporter des index sur `(class_council_id, student_id)` pour des requetes performantes

---

## 9. Success Metrics

### 9.1 Metriques d'Efficacite

| Metrique | Objectif | Mesure |
|----------|----------|--------|
| Temps de preparation du conseil | < 5 minutes (vs 2-3 heures manuellement) | Temps entre l'ouverture et la disponibilite des donnees |
| Temps de saisie des decisions | < 30 minutes pour 60 eleves | Temps entre premiere et derniere decision saisie |
| Temps de generation du PV | < 10 secondes | Chrono generation PDF |
| Erreurs dans les statistiques | 0% | Comparaison avec calcul manuel |

### 9.2 Metriques d'Adoption

| Metrique | Objectif | Mesure |
|----------|----------|--------|
| Conseils tenus via le systeme | 100% apres 1 semestre | Nombre de conseils clotures / Nombre de classes |
| PV generes automatiquement | 100% apres 1 semestre | Nombre de PV generes / Nombre de conseils clotures |
| Appreciations saisies dans le systeme | > 90% | Nombre d'appreciations / Nombre total d'eleves |

### 9.3 Metriques de Satisfaction

| Metrique | Objectif | Mesure |
|----------|----------|--------|
| NPS Directeurs/Censeurs | > 50 | Enquete satisfaction |
| NPS Enseignants | > 40 | Enquete satisfaction |
| Reduction du temps administratif | > 80% | Comparaison avant/apres |

---

## 10. Open Questions

### 10.1 Questions Produit

1. **Regles specifiques par etablissement** : Existe-t-il des regles de passage specifiques par classe (ex : conditions differentes pour le passage en Seconde vs le passage en Terminale) ?
2. **Mentionss regionales** : Le systeme de mentions (Tableau d'honneur, Encouragements, Felicitations) est-il standardise au niveau national ou propre a chaque etablissement ?
3. **Avertissements sur le bulletin** : Faut-il gerer des "Avertissements Travail" et "Avertissements Conduite" au niveau du conseil de classe, ou uniquement dans le Module Discipline ?
4. **Delegues de classe** : Comment sont designes les delegues des eleves et des parents ? Le systeme doit-il gerer l'election des delegues ?
5. **Decisions S1** : Certains etablissements prennent-ils des decisions intermediaires en S1 (ex : mise en garde, avertissement) en plus des appreciations ?
6. **Format PV** : Le Ministere de l'Education impose-t-il un format specifique pour le PV du conseil de classe ?

### 10.2 Questions Techniques

1. **Volume de donnees** : Quel est le nombre moyen de matieres par classe (pour dimensionner le tableau recapitulatif) ? 8-12 pour le college, 10-15 pour le lycee ?
2. **Stockage PDF** : Les PV doivent-ils etre stockes localement ou sur un service cloud (S3) ? Quelle est la politique de retention ?
3. **Concurrence** : Comment gerer le cas ou deux utilisateurs modifient les decisions simultanement pendant le conseil ? Verrouillage optimiste ou dernier ecrivain gagne ?
4. **Mode projection** : Le mode presentation necessite-t-il un support WebSocket pour des mises a jour en temps reel (decisions saisies visibles immediatement sur l'ecran projete) ?

---

## 11. Checklist Results Report

**A completer apres implementation** : Executer le checklist PM pour valider :
- [ ] Tous les requirements fonctionnels sont implementes
- [ ] Le tableau recapitulatif est complet et performant (< 2 secondes pour 60 eleves)
- [ ] Les statistiques sont correctement calculees (taux de reussite, repartition par tranches)
- [ ] Les propositions automatiques de decisions sont fiables
- [ ] Le workflow du conseil fonctionne correctement (Planifie -> En cours -> Cloture)
- [ ] Le PV est genere correctement en PDF avec toutes les informations requises
- [ ] Les appreciations generales sont saisissables et integrees aux bulletins
- [ ] Les permissions sont appliquees (Admin/Directeur, Enseignant, Eleve, Parent)
- [ ] Les decisions sont protegees apres cloture
- [ ] L'historique des modifications est immutable et consultable
- [ ] L'interface est responsive et accessible (WCAG AA)
- [ ] L'API d'integration avec le Module Documents Officiels est fonctionnelle
- [ ] Les tests unitaires et d'integration passent tous

---

## 12. Next Steps

### 12.1 UX Expert Prompt

> "Creez les maquettes UI pour le Module Conseil de Classe en vous basant sur ce PRD. Focus sur les ecrans critiques : Tableau Recapitulatif (avec scroll horizontal et codes couleur), Mode Presentation (plein ecran pour projection), Saisie des Decisions (tableau editable avec propositions auto), Saisie des Appreciations (navigation rapide entre eleves). Assurez l'accessibilite WCAG AA et le responsive design. Prevoir un design professionnel et institutionnel adapte au contexte scolaire."

### 12.2 Architect Prompt

> "Concevez l'architecture technique du Module Conseil de Classe en suivant les patterns etablis dans les modules existants (UsersGuard, StructureAcademique). Definissez les tables de base de donnees avec relations (ClassCouncil -> Decisions/Appreciations/Attendances/Minutes), les models Eloquent avec eager loading optimise pour le tableau recapitulatif, les Services metier (StatisticsService, DecisionProposalService, MinutesGenerationService), les controllers avec validation des contraintes de workflow, les API Resources, et les tests unitaires pour les calculs de statistiques et les propositions automatiques de decisions."

---

**Document cree par** : John (Product Manager Agent)
**Date** : 2026-03-16
**Version** : 1.0
**Statut** : Draft pour review
