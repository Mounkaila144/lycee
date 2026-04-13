# PRD - Module Structure Academique

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Module** : Structure Academique (Academic Structure)
> **Version** : 2.0
> **Date** : 2026-03-16
> **Phase** : MVP Core (Phase 1)
> **Priorite** : 🔴 CRITIQUE (Fondation de tous les modules)

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-01-07 | 1.0 | Creation initiale du PRD Module Structure Academique (systeme LMD) | John (PM) |
| 2026-03-16 | 2.0 | Refonte complete - Adaptation enseignement secondaire (colleges/lycees Niger) | John (PM) |

---

## 1. Goals and Background Context

### 1.1 Goals

- **Definir la structure academique secondaire** : Permettre a chaque etablissement (college ou lycee) de modeliser sa structure (Cycles, Classes, Series, Matieres)
- **Gerer les matieres avec coefficients** : Creer les matieres d'enseignement avec coefficients variables selon la serie et la classe (ex: Maths coeff 5 en Tle C, coeff 2 en Tle A)
- **Organiser les eleves par classes** : Definir les classes (6e, 5e, 4e, 3e pour le college ; 2nde, 1ere, Tle pour le lycee) avec effectifs et professeur principal
- **Affecter les enseignants aux matieres et classes** : Lier chaque enseignant a une ou plusieurs matieres dans une ou plusieurs classes pour une annee scolaire donnee
- **Configurer les baremes** : Permettre a chaque etablissement de definir ses seuils de passage, mentions, tableau d'honneur et encouragements
- **Garantir la flexibilite** : Permettre a chaque etablissement d'adapter la structure a ses specificites (college seul, lycee seul, ou les deux)
- **Preparer les modules suivants** : Fournir les donnees necessaires aux modules Inscriptions, Notes, Emplois du Temps, Bulletins

### 1.2 Background Context

Le **Module Structure Academique** est la **fondation de tout le systeme** Gestion Scolaire pour l'enseignement secondaire au Niger. Sans une structure academique bien definie, il est impossible d'inscrire des eleves, de saisir des notes, ou de generer des bulletins.

Ce module s'inscrit dans la **Phase 1 MVP Core** car il est le **premier prerequis** :
1. Les eleves doivent etre inscrits dans une classe (Module Inscriptions depend de ce module)
2. Les notes sont saisies par matiere et par classe (Module Notes depend de ce module)
3. Les bulletins mentionnent les matieres, coefficients et moyennes (Module Bulletins depend de ce module)
4. Les emplois du temps sont organises par classe et matiere (Module Emplois du Temps depend de ce module)

Le module doit gerer les specificites du **systeme d'enseignement secondaire nigerien** :
- **Deux cycles** :
  - **College** (1er cycle) : 6e, 5e, 4e, 3e - Tronc commun, pas de series
  - **Lycee** (2nd cycle) : 2nde (tronc commun), 1ere et Tle avec series (A, C, D)
- **Series au lycee** :
  - **Serie A** : Litteraire (Philosophie, Langues, Histoire-Geographie)
  - **Serie C** : Mathematiques-Physique (Maths, Physique-Chimie)
  - **Serie D** : Sciences Naturelles (SVT, Physique-Chimie, Maths)
- **Coefficients variables** : Une meme matiere peut avoir un coefficient different selon la serie (ex: Maths coeff 5 en Tle C, coeff 4 en Tle D, coeff 2 en Tle A)
- **Deux semestres** par annee scolaire : S1 (octobre-fevrier) et S2 (mars-juin)
- **Professeur principal** : Chaque classe a un professeur principal responsable du suivi des eleves
- **Baremes configurables** : Seuils de passage (10/20), mentions (Passable, Assez Bien, Bien, Tres Bien), tableau d'honneur, encouragements

**Pain point resolu** : Les etablissements secondaires au Niger gerent actuellement leur structure dans des fichiers Excel disperses, des registres papier, ou des logiciels non adaptes au contexte nigerien. Cela entraine des incoherences dans les coefficients, des erreurs dans le calcul des moyennes, et une difficulte a produire des bulletins conformes.

---

## 2. Requirements

### 2.1 Functional Requirements (FR)

#### 2.1.1 Gestion des Annees Scolaires

- **FR1** : Le systeme doit permettre a l'Admin de creer des annees scolaires avec : Nom (ex: 2025-2026), Date de debut, Date de fin, Statut (En cours, Terminee, Planifiee)
- **FR2** : Une annee scolaire contient exactement 2 semestres (S1 et S2)
- **FR3** : Lors de la creation d'une annee scolaire, les 2 semestres sont automatiquement crees :
  - S1 : date de debut = debut annee, date de fin configurable (generalement fin fevrier)
  - S2 : date de debut = fin S1 + 1 jour, date de fin = fin annee
- **FR4** : Le systeme doit permettre de definir l'annee scolaire active (une seule active a la fois)
- **FR5** : L'annee active est utilisee par defaut dans tous les formulaires (inscriptions, affectations, saisie de notes)
- **FR6** : Le systeme doit permettre de modifier les dates des semestres apres creation
- **FR7** : Le systeme doit permettre de consulter les annees scolaires precedentes sans les modifier (lecture seule)
- **FR8** : La suppression d'une annee scolaire doit etre bloquee si des inscriptions ou des notes existent pour cette annee

#### 2.1.2 Gestion des Cycles

- **FR9** : Le systeme doit gerer deux cycles predefinissables :
  - **College** (1er cycle) : Classes 6e, 5e, 4e, 3e
  - **Lycee** (2nd cycle) : Classes 2nde, 1ere, Tle
- **FR10** : Le systeme doit permettre a l'Admin de configurer les cycles disponibles pour son etablissement (college uniquement, lycee uniquement, ou les deux)
- **FR11** : Chaque cycle a : Nom, Code (unique), Description, Ordre d'affichage
- **FR12** : Le systeme doit permettre de modifier la description d'un cycle mais pas son code ni ses classes associees (structure fixe du systeme nigerien)

#### 2.1.3 Gestion des Classes

- **FR13** : Le systeme doit permettre de creer des classes avec :
  - Nom complet (ex: "6eme A", "Terminale C1")
  - Niveau (select : 6e, 5e, 4e, 3e, 2nde, 1ere, Tle)
  - Cycle (auto-determine selon le niveau)
  - Serie (obligatoire uniquement pour 1ere et Tle : A, C, D ; non applicable pour les autres niveaux)
  - Section/Lettre (ex: A, B, C, 1, 2 pour distinguer les classes paralleles)
  - Capacite maximale (nombre d'eleves)
  - Salle attitrée (optionnel, texte libre, ex: "Salle 12")
  - Professeur principal (select : liste des enseignants disponibles)
  - Annee scolaire (select, defaut : annee active)
- **FR14** : Le systeme doit generer automatiquement le nom complet de la classe a partir du niveau, de la serie et de la section (ex: Niveau=Tle, Serie=C, Section=1 => "Tle C1")
- **FR15** : Le systeme doit permettre de modifier ou supprimer une classe
- **FR16** : La suppression d'une classe doit etre bloquee si des eleves y sont inscrits
- **FR17** : Les classes doivent etre listees avec filtres par : Cycle, Niveau, Serie, Annee scolaire
- **FR18** : Le systeme doit afficher le nombre d'eleves inscrits dans chaque classe
- **FR19** : Le systeme doit alerter si l'effectif d'une classe depasse la capacite maximale configuree
- **FR20** : Un professeur principal ne peut etre affecte qu'a une seule classe par annee scolaire

#### 2.1.4 Gestion des Series

- **FR21** : Le systeme doit gerer les series predefinies du systeme nigerien :
  - **Serie A** : Litteraire - Code "A", Description "Lettres et Sciences Humaines"
  - **Serie C** : Maths-Physique - Code "C", Description "Mathematiques et Sciences Physiques"
  - **Serie D** : Sciences Naturelles - Code "D", Description "Sciences de la Vie et de la Terre"
- **FR22** : Les series ne s'appliquent qu'aux classes de 1ere et Terminale
- **FR23** : La classe de 2nde est un tronc commun (pas de serie)
- **FR24** : Le systeme doit permettre a l'Admin d'ajouter de nouvelles series si le systeme educatif evolue (ex: Serie G pour la Gestion dans certains lycees techniques)
- **FR25** : Chaque serie a : Code (unique, majuscules), Nom complet, Description, Statut (Active/Inactive)
- **FR26** : Le systeme doit permettre de desactiver une serie sans la supprimer (pour garder l'historique)

#### 2.1.5 Gestion des Matieres (Subjects)

- **FR27** : Le systeme doit permettre de creer des matieres avec :
  - Code matiere (unique, ex: MATH, FRAN, PHYS, SVT, HG, PHIL, ANG, EPS)
  - Nom complet (ex: "Mathematiques", "Francais", "Physique-Chimie")
  - Nom abrege (ex: "Maths", "PC", "SVT")
  - Categorie (select : Sciences, Lettres, Langues, Sciences Humaines, Education Physique, Arts, Autres)
  - Description (optionnelle)
  - Statut (Active/Inactive)
- **FR28** : Le systeme doit permettre de modifier ou supprimer une matiere
- **FR29** : La suppression d'une matiere doit etre bloquee si des coefficients, des notes ou des affectations enseignants existent pour cette matiere
- **FR30** : Les matieres doivent etre listees avec filtres par categorie et statut
- **FR31** : Le systeme doit permettre de rechercher une matiere par code ou nom

#### 2.1.6 Gestion des Coefficients par Matiere/Classe/Serie

- **FR32** : Le systeme doit permettre de definir le coefficient d'une matiere pour un couple (niveau, serie) :
  - Matiere (select)
  - Niveau (select : 6e, 5e, 4e, 3e, 2nde, 1ere, Tle)
  - Serie (select : A, C, D ou "Tronc commun" pour 6e-2nde)
  - Coefficient (numerique, 1 a 8)
  - Nombre d'heures hebdomadaires (numerique, 1 a 10, optionnel pour planification EDT)
- **FR33** : Un meme matiere peut avoir des coefficients differents selon le niveau et la serie :
  - Exemple : Mathematiques → coeff 5 en Tle C, coeff 4 en Tle D, coeff 2 en Tle A, coeff 4 en 3e
- **FR34** : Le systeme doit permettre de modifier un coefficient existant
- **FR35** : Le systeme doit permettre de supprimer un coefficient (retirer une matiere d'un niveau/serie)
- **FR36** : Le systeme doit afficher un recapitulatif des matieres et coefficients par niveau et serie sous forme de tableau :
  - Colonnes : Matiere, Coefficient, Heures hebdomadaires
  - Ligne de total : Somme des coefficients
- **FR37** : Le systeme doit permettre de dupliquer la configuration des coefficients d'un niveau/serie vers un autre (ex: copier la config de 4e vers 3e puis ajuster)
- **FR38** : Le systeme doit empêcher la creation de doublons (meme matiere pour le meme couple niveau/serie)

#### 2.1.7 Affectation Enseignants ↔ Matieres ↔ Classes

- **FR39** : Le systeme doit permettre d'affecter un enseignant a une matiere dans une ou plusieurs classes pour une annee scolaire donnee :
  - Enseignant (select : liste des utilisateurs avec role "Enseignant")
  - Matiere (select : liste des matieres actives)
  - Classe(s) (multi-select : liste des classes de l'annee active)
  - Annee scolaire (select, defaut : annee active)
- **FR40** : Un enseignant peut etre affecte a plusieurs matieres (rare mais possible, ex: un prof de Maths-Physique)
- **FR41** : Un enseignant peut etre affecte a la meme matiere dans plusieurs classes (cas le plus courant)
- **FR42** : Une matiere dans une classe donnee ne peut avoir qu'un seul enseignant affecte (pas de doublons matiere-classe)
- **FR43** : Le systeme doit afficher la liste des affectations avec filtres par : Enseignant, Matiere, Classe, Niveau, Annee scolaire
- **FR44** : Le systeme doit afficher la charge horaire totale d'un enseignant (somme des heures hebdomadaires de ses affectations)
- **FR45** : Le systeme doit permettre de retirer une affectation
- **FR46** : La suppression d'une affectation doit etre bloquee si l'enseignant a deja saisi des notes pour cette matiere/classe
- **FR47** : Le systeme doit permettre de dupliquer les affectations d'une annee scolaire vers une nouvelle annee (reconduction)

#### 2.1.8 Configuration des Baremes

- **FR48** : Le systeme doit permettre a l'Admin de configurer les baremes de l'etablissement :
  - **Seuil de passage** : Moyenne minimale pour passer en classe superieure (defaut : 10/20)
  - **Seuil de rachat** : Moyenne minimale pour etre eligible au rachat/deliberation (defaut : 9/20, optionnel)
  - **Mentions** : Configuration des seuils de mention :
    - Passable : 10 a 11.99 (configurable)
    - Assez Bien : 12 a 13.99 (configurable)
    - Bien : 14 a 15.99 (configurable)
    - Tres Bien : 16 et plus (configurable)
  - **Tableau d'honneur** : Moyenne minimale pour le tableau d'honneur (defaut : 14/20)
  - **Encouragements** : Moyenne minimale pour les encouragements (defaut : 12/20)
  - **Felicitations** : Moyenne minimale pour les felicitations (defaut : 16/20)
  - **Avertissement travail** : Moyenne maximale declenchant un avertissement (defaut : 7/20)
  - **Blame** : Moyenne maximale declenchant un blame (defaut : 5/20)
- **FR49** : Les baremes sont configurables par etablissement (tenant) et sont appliques globalement
- **FR50** : Le systeme doit permettre de configurer des baremes specifiques par cycle (college vs lycee) si necessaire
- **FR51** : Les baremes par defaut doivent etre pre-remplis selon les standards nigeriens lors de la premiere configuration
- **FR52** : Le systeme doit permettre de modifier les baremes a tout moment, avec un avertissement si des bulletins ont deja ete generes avec les anciens baremes

#### 2.1.9 Validation et Coherence des Donnees

- **FR53** : Le systeme doit verifier qu'au moins une matiere avec coefficient est definie pour chaque couple (niveau, serie) actif
- **FR54** : Le systeme doit empecher la creation de matieres avec le meme code
- **FR55** : Le systeme doit empecher la creation de classes avec le meme nom complet pour la meme annee scolaire
- **FR56** : Le systeme doit afficher un recapitulatif visuel de la structure academique (vue arborescente par cycle)
- **FR57** : Le systeme doit verifier que chaque classe a un professeur principal affecte
- **FR58** : Le systeme doit verifier que toutes les matieres d'une classe ont un enseignant affecte et alerter si ce n'est pas le cas

---

### 2.2 Non-Functional Requirements (NFR)

- **NFR1** : Le systeme doit supporter jusqu'a 50 classes, 30 matieres, 500 affectations enseignants, et 100 configurations de coefficients par tenant sans degradation de performance (scalabilite)
- **NFR2** : L'affichage de la liste des classes (avec filtres) doit se faire en moins de 500ms pour 50 classes (performance)
- **NFR3** : Le systeme doit empecher la suppression accidentelle de donnees critiques (classes, matieres, annees scolaires) avec confirmation obligatoire (securite)
- **NFR4** : Les codes (matiere, serie) doivent etre en majuscules et sans espaces (coherence des donnees)
- **NFR5** : Le systeme doit supporter la modification de la structure academique en cours d'annee sans impacter les notes deja saisies (flexibilite)
- **NFR6** : Le temps de creation d'une classe avec toutes ses relations doit etre < 500ms (UX)
- **NFR7** : La configuration des coefficients doit etre intuitive et ne pas necessiter plus de 3 clics pour modifier un coefficient (ergonomie)
- **NFR8** : Le systeme doit garantir l'integrite referentielle : aucune note ne peut exister pour une matiere/classe sans affectation enseignant correspondante (integrite)

---

## 3. User Interface Design Goals

### 3.1 Overall UX Vision

L'interface du Module Structure Academique doit etre **claire, structuree et adaptee au contexte scolaire nigerien**. L'Admin doit pouvoir configurer rapidement la structure de son etablissement (cycles, classes, matieres, coefficients) et visualiser les relations entre les entites.

**Principes cles** :
- **Navigation par cycle** : Separation claire entre College et Lycee
- **Configuration guidee** : Assistant de configuration initiale pour les nouveaux etablissements
- **Tableaux recapitulatifs** : Vue synthetique des coefficients par niveau/serie
- **Actions contextuelles** : Boutons d'action (Creer, Modifier, Supprimer) a cote de chaque entite
- **Validation en temps reel** : Verification des codes uniques, coherence des coefficients, completude des affectations

### 3.2 Key Interaction Paradigms

- **Navigation par onglets** : Onglets "College" et "Lycee" pour separer les vues
- **Configuration par grille** : Tableau editable pour configurer les coefficients (lignes = matieres, colonnes = series)
- **Filtres multi-criteres** : Filtres par cycle, niveau, serie, annee scolaire pour afficher les entites pertinentes
- **Affectation en masse** : Possibilite d'affecter un enseignant a plusieurs classes en une seule operation
- **Duplication intelligente** : Copier la configuration d'une annee scolaire vers la suivante

### 3.3 Core Screens and Views

#### 3.3.1 Ecran Admin : Dashboard Structure Academique
- Vue d'ensemble avec statistiques :
  - Nombre de classes par cycle (College / Lycee)
  - Nombre total d'eleves inscrits
  - Nombre de matieres configurees
  - Nombre d'enseignants affectes
  - Pourcentage de classes avec professeur principal affecte
  - Pourcentage de matieres avec enseignant affecte
- Alertes :
  - Classes sans professeur principal
  - Matieres sans enseignant affecte
  - Classes depassant la capacite maximale
- Boutons : "Gerer Classes", "Gerer Matieres", "Coefficients", "Affectations Enseignants", "Baremes"

#### 3.3.2 Ecran Admin : Gestion des Annees Scolaires
- Tableau avec colonnes : Nom (ex: 2025-2026), Date debut, Date fin, Semestre 1 (dates), Semestre 2 (dates), Statut (badge : Active/Terminee/Planifiee), Actions
- Bouton : "Creer Annee Scolaire"
- Badge vert sur l'annee active
- Formulaire de creation :
  - Nom (texte, ex: 2025-2026)
  - Date de debut (date picker)
  - Date de fin (date picker)
  - Date de fin S1 (date picker, auto-calculee mais modifiable)

#### 3.3.3 Ecran Admin : Liste des Classes
- Tableau avec colonnes : Nom complet (ex: 6eme A, Tle C1), Cycle (badge), Niveau, Serie (si applicable), Section, Professeur principal, Effectif / Capacite max, Actions
- Bouton : "Creer Classe"
- Filtres : Cycle (College/Lycee), Niveau, Serie, Annee scolaire
- Badge vert si effectif < 80% capacite, orange si 80-100%, rouge si depasse
- Tri par : Cycle, Niveau, Serie, Nom

#### 3.3.4 Ecran Admin : Formulaire de Creation de Classe
- Champs :
  - Niveau (select : 6e, 5e, 4e, 3e, 2nde, 1ere, Tle)
  - Serie (select dynamique : apparait uniquement si Niveau = 1ere ou Tle ; options : A, C, D)
  - Section/Lettre (texte, ex: A, B, 1, 2)
  - Nom complet (auto-genere, lecture seule, ex: "Tle C1")
  - Capacite maximale (numerique)
  - Salle attitree (texte, optionnel)
  - Professeur principal (select : liste des enseignants disponibles, filtres sur ceux non deja affectes comme PP)
  - Annee scolaire (select, defaut : annee active)
- Boutons : "Sauvegarder", "Annuler"

#### 3.3.5 Ecran Admin : Liste des Matieres
- Tableau avec colonnes : Code, Nom complet, Nom abrege, Categorie (badge), Statut (badge Active/Inactive), Nombre de niveaux ou elle est enseignee, Actions
- Bouton : "Creer Matiere"
- Filtres : Categorie, Statut
- Recherche par code ou nom

#### 3.3.6 Ecran Admin : Configuration des Coefficients
- **Vue principale** : Selection du niveau et de la serie (ou "Tronc commun" pour 6e-2nde)
- **Grille de coefficients** :
  - Lignes : Matieres
  - Colonnes : Coefficient, Heures hebdomadaires, Actions (Modifier, Supprimer)
  - Ligne de total en bas : Somme des coefficients, Somme des heures
- Bouton : "Ajouter une matiere a ce niveau"
- Bouton : "Dupliquer cette configuration vers un autre niveau/serie"
- **Vue comparative** (pour le lycee) :
  - Tableau croise : Lignes = Matieres, Colonnes = Series (A, C, D)
  - Cellules : Coefficient pour chaque matiere/serie
  - Permet de voir rapidement les differences de coefficients entre series

#### 3.3.7 Ecran Admin : Affectation Enseignants
- Tableau avec colonnes : Enseignant (nom), Matiere, Classe(s), Charge horaire (heures/semaine), Annee scolaire, Actions (Retirer)
- Bouton : "Affecter un enseignant"
- Formulaire d'affectation :
  - Enseignant (select avec recherche)
  - Matiere (select)
  - Classe(s) (multi-select, filtre par niveau et serie)
  - Annee scolaire (select, defaut : annee active)
- Filtres : Par enseignant, par matiere, par classe, par niveau
- Recapitulatif : "X enseignants affectes, Y affectations totales, Z matieres/classes non couvertes"

#### 3.3.8 Ecran Admin : Configuration des Baremes
- Formulaire organise en sections :
  - **Section "Passage et Rachat"** :
    - Seuil de passage (numerique, defaut 10)
    - Seuil de rachat (numerique, defaut 9, activable/desactivable)
  - **Section "Mentions"** :
    - Tableau configurable : Mention, Seuil minimum, Seuil maximum
    - Lignes par defaut : Passable (10-11.99), AB (12-13.99), Bien (14-15.99), TB (16+)
  - **Section "Distinctions et Sanctions"** :
    - Tableau d'honneur : seuil minimum (defaut 14)
    - Encouragements : seuil minimum (defaut 12)
    - Felicitations : seuil minimum (defaut 16)
    - Avertissement travail : seuil maximum (defaut 7)
    - Blame : seuil maximum (defaut 5)
  - **Section "Configuration par cycle"** : Toggle pour activer des baremes differents College vs Lycee
- Bouton : "Sauvegarder", "Reinitialiser aux valeurs par defaut"

#### 3.3.9 Ecran Admin : Vue Arborescente de la Structure
- Arbre hierarchique interactif :
  ```
  ├─ College (1er cycle)
  │  ├─ 6eme [3 classes, 145 eleves]
  │  │  ├─ 6eme A (PP: M. Moussa) [48/50 eleves]
  │  │  ├─ 6eme B (PP: Mme Fatima) [49/50 eleves]
  │  │  └─ 6eme C (PP: M. Ibrahim) [48/50 eleves]
  │  ├─ 5eme [3 classes, 140 eleves]
  │  │  └─ ...
  │  ├─ 4eme [2 classes, 95 eleves]
  │  │  └─ ...
  │  └─ 3eme [2 classes, 88 eleves]
  │     └─ ...
  ├─ Lycee (2nd cycle)
  │  ├─ 2nde [3 classes, 130 eleves] (Tronc commun)
  │  │  ├─ 2nde A (PP: M. Ali) [44/50 eleves]
  │  │  ├─ 2nde B (PP: Mme Aissa) [43/50 eleves]
  │  │  └─ 2nde C (PP: M. Hamidou) [43/50 eleves]
  │  ├─ 1ere [4 classes, 160 eleves]
  │  │  ├─ 1ere A (PP: Mme Mariama) [42/50 eleves]
  │  │  ├─ 1ere C (PP: M. Abdou) [40/45 eleves]
  │  │  ├─ 1ere D1 (PP: M. Soumana) [39/45 eleves]
  │  │  └─ 1ere D2 (PP: Mme Hawa) [39/45 eleves]
  │  └─ Tle [4 classes, 155 eleves]
  │     ├─ Tle A (PP: M. Garba) [40/50 eleves]
  │     ├─ Tle C (PP: Mme Zara) [38/45 eleves]
  │     ├─ Tle D1 (PP: M. Boubacar) [39/45 eleves]
  │     └─ Tle D2 (PP: M. Issoufou) [38/45 eleves]
  ```
- Actions : Clic sur un noeud pour voir les details, bouton "Modifier" sur chaque noeud
- Indicateurs visuels : Badge couleur pour l'effectif (vert/orange/rouge)

#### 3.3.10 Ecran Enseignant : Mes Classes et Matieres

- Liste des affectations de l'enseignant connecte pour l'annee active :
  - Matiere, Classe, Nombre d'eleves, Coefficient dans cette classe
- Filtre par semestre
- Liens vers : Saisie des notes, Liste des eleves de la classe

### 3.4 Accessibility

**WCAG AA** : Le systeme doit respecter les standards d'accessibilite WCAG 2.1 niveau AA :
- Navigation au clavier complete (tabs, enter, espace)
- Labels ARIA pour les elements interactifs (selects, checkboxes, boutons)
- Contraste de couleurs suffisant (ratio 4.5:1 minimum)
- Messages d'erreur clairs et accessibles aux lecteurs d'ecran
- Tableaux avec en-tetes de colonnes correctement balises (`<th>`)

### 3.5 Branding

- Interface professionnelle et epuree, adaptee au contexte scolaire
- Couleurs :
  - **Bleu (#2196F3)** : Actions primaires, liens, cycle College
  - **Vert (#4CAF50)** : Validation, succes, effectifs normaux
  - **Orange (#FF9800)** : Avertissements (effectif proche du max, matieres non couvertes)
  - **Rouge (#F44336)** : Erreurs, effectif depasse, alertes critiques
  - **Violet (#9C27B0)** : Cycle Lycee (distinction visuelle avec College)
- Icones :
  - 🏫 Etablissement
  - 📅 Annee scolaire
  - 🎓 Cycle
  - 📝 Classe
  - 📖 Matiere
  - 👨‍🏫 Enseignant
  - ⚖️ Coefficient
  - 📊 Bareme

### 3.6 Target Device and Platforms

**Web Responsive** :
- Desktop (prioritaire) : Optimise pour la configuration et la gestion (tableaux de coefficients, affectations)
- Tablette : Interface adaptee pour consultation et modification legere
- Mobile : Consultation uniquement (pas de creation/modification sur mobile, sauf consultation des affectations par les enseignants)

---

## 4. Technical Assumptions

### 4.1 Repository Structure

**Polyrepo** : Backend (Laravel) et Frontend (Next.js) dans des depots separes (architecture existante).

### 4.2 Service Architecture

**Architecture modulaire (nwidart/laravel-modules)** :
- Nouveau module Laravel : `Modules/AcademicStructure/`
- Structure standard :
  - `Entities/` : Models Eloquent (AcademicYear, Semester, Cycle, SchoolClass, Series, Subject, SubjectCoefficient, TeacherAssignment, ClassSetting)
  - `Http/Controllers/` : Controllers Admin
  - `Http/Requests/` : Form Requests pour validation
  - `Http/Resources/` : API Resources
  - `Database/Migrations/tenant/` : Migrations tenant
  - `Database/Factories/` : Factories pour les tests
  - `Database/Seeders/` : Seeders pour donnees initiales (cycles, series par defaut)
  - `Routes/` : Routes admin.php

**Frontend Next.js** :
- Nouveau module : `src/modules/AcademicStructure/`
- Structure en 3 couches : `admin/`, `superadmin/`, `frontend/` (superadmin et frontend vides pour MVP)
- Services API avec `createApiClient()`
- Hooks React pour gestion de l'etat

### 4.3 Base de donnees

**Connexion** : `tenant` (base de donnees tenant dynamique)

**Tables a creer** :

#### Table `academic_years` - Annees scolaires
```
- id (bigint, PK, auto-increment)
- name (string, ex: "2025-2026")
- start_date (date)
- end_date (date)
- is_active (boolean, default false)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, nullable - SoftDeletes)
```

#### Table `semesters` - Semestres
```
- id (bigint, PK, auto-increment)
- academic_year_id (bigint, FK -> academic_years.id)
- name (string, "S1" ou "S2")
- order (tinyint, 1 ou 2)
- start_date (date)
- end_date (date)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, nullable - SoftDeletes)
```

#### Table `cycles` - Cycles (College, Lycee)
```
- id (bigint, PK, auto-increment)
- code (string, unique, ex: "COL", "LYC")
- name (string, ex: "College", "Lycee")
- description (text, nullable)
- display_order (tinyint)
- is_active (boolean, default true)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, nullable - SoftDeletes)
```

#### Table `series` - Series (A, C, D)
```
- id (bigint, PK, auto-increment)
- code (string, unique, ex: "A", "C", "D")
- name (string, ex: "Litteraire", "Mathematiques-Physique", "Sciences Naturelles")
- description (text, nullable)
- is_active (boolean, default true)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, nullable - SoftDeletes)
```

#### Table `classes` - Classes scolaires
```
- id (bigint, PK, auto-increment)
- academic_year_id (bigint, FK -> academic_years.id)
- cycle_id (bigint, FK -> cycles.id)
- level (string, enum: "6e", "5e", "4e", "3e", "2nde", "1ere", "Tle")
- series_id (bigint, FK -> series.id, nullable - null pour 6e-2nde)
- section (string, nullable, ex: "A", "B", "1", "2")
- full_name (string, ex: "6eme A", "Tle C1" - auto-genere)
- max_capacity (integer, default 50)
- classroom (string, nullable, ex: "Salle 12")
- head_teacher_id (bigint, FK -> users.id, nullable)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, nullable - SoftDeletes)

Index unique : (academic_year_id, full_name)
Index unique : (academic_year_id, head_teacher_id) WHERE head_teacher_id IS NOT NULL
```

#### Table `subjects` - Matieres
```
- id (bigint, PK, auto-increment)
- code (string, unique, ex: "MATH", "FRAN", "PHYS")
- name (string, ex: "Mathematiques")
- short_name (string, ex: "Maths")
- category (string, enum: "sciences", "lettres", "langues", "sciences_humaines", "education_physique", "arts", "autres")
- description (text, nullable)
- is_active (boolean, default true)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, nullable - SoftDeletes)
```

#### Table `subject_coefficients` - Coefficients par matiere/niveau/serie
```
- id (bigint, PK, auto-increment)
- subject_id (bigint, FK -> subjects.id)
- level (string, enum: "6e", "5e", "4e", "3e", "2nde", "1ere", "Tle")
- series_id (bigint, FK -> series.id, nullable - null pour tronc commun 6e-2nde)
- coefficient (decimal 3,1, ex: 5.0, 2.5)
- weekly_hours (decimal 3,1, nullable, ex: 4.0, 2.5)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, nullable - SoftDeletes)

Index unique : (subject_id, level, series_id) - Pas de doublon matiere/niveau/serie
```

#### Table `teacher_assignments` - Affectations enseignants
```
- id (bigint, PK, auto-increment)
- teacher_id (bigint, FK -> users.id)
- subject_id (bigint, FK -> subjects.id)
- class_id (bigint, FK -> classes.id)
- academic_year_id (bigint, FK -> academic_years.id)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, nullable - SoftDeletes)

Index unique : (subject_id, class_id, academic_year_id) - Une seule affectation par matiere/classe/annee
```

#### Table `class_settings` - Baremes et reglages
```
- id (bigint, PK, auto-increment)
- cycle_id (bigint, FK -> cycles.id, nullable - null = global pour tout l'etablissement)
- academic_year_id (bigint, FK -> academic_years.id, nullable - null = configuration par defaut)
- passing_threshold (decimal 4,2, default 10.00)
- redemption_threshold (decimal 4,2, nullable, default 9.00)
- mention_passable_min (decimal 4,2, default 10.00)
- mention_assez_bien_min (decimal 4,2, default 12.00)
- mention_bien_min (decimal 4,2, default 14.00)
- mention_tres_bien_min (decimal 4,2, default 16.00)
- honor_roll_threshold (decimal 4,2, default 14.00)
- encouragement_threshold (decimal 4,2, default 12.00)
- congratulations_threshold (decimal 4,2, default 16.00)
- work_warning_threshold (decimal 4,2, default 7.00)
- blame_threshold (decimal 4,2, default 5.00)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, nullable - SoftDeletes)

Index unique : (cycle_id, academic_year_id) - Un seul bareme par cycle/annee
```

**Relations cles** :
- `semesters` belongsTo `academic_years`
- `classes` belongsTo `academic_years`, `cycles`, `series` (nullable)
- `classes` belongsTo `users` (head_teacher via head_teacher_id)
- `subject_coefficients` belongsTo `subjects`, `series` (nullable)
- `teacher_assignments` belongsTo `users` (teacher), `subjects`, `classes`, `academic_years`
- `class_settings` belongsTo `cycles` (nullable), `academic_years` (nullable)
- Utiliser **eager loading** pour eviter les N+1 queries

### 4.4 Donnees Initiales (Seeders)

Lors de la creation d'un nouveau tenant, les donnees suivantes doivent etre inserees automatiquement :

**Cycles** :
| Code | Nom | Ordre |
|------|-----|-------|
| COL | College (1er cycle) | 1 |
| LYC | Lycee (2nd cycle) | 2 |

**Series** :
| Code | Nom | Description |
|------|-----|-------------|
| A | Litteraire | Lettres et Sciences Humaines |
| C | Maths-Physique | Mathematiques et Sciences Physiques |
| D | Sciences Naturelles | Sciences de la Vie et de la Terre |

**Matieres par defaut** :
| Code | Nom | Nom abrege | Categorie |
|------|-----|------------|-----------|
| MATH | Mathematiques | Maths | sciences |
| FRAN | Francais | Francais | lettres |
| PHYS | Physique-Chimie | PC | sciences |
| SVT | Sciences de la Vie et de la Terre | SVT | sciences |
| HG | Histoire-Geographie | HG | sciences_humaines |
| ANG | Anglais | Anglais | langues |
| PHIL | Philosophie | Philo | lettres |
| EPS | Education Physique et Sportive | EPS | education_physique |
| EC | Education Civique | EC | sciences_humaines |
| INF | Informatique | Info | sciences |
| ARAB | Arabe | Arabe | langues |
| ALL | Allemand | Allemand | langues |
| ESP | Espagnol | Espagnol | langues |

**Coefficients par defaut (exemples pour le systeme nigerien)** :

*Classe de 6e/5e (Tronc commun College)* :
| Matiere | Coefficient | Heures/semaine |
|---------|-------------|----------------|
| Francais | 4 | 6 |
| Mathematiques | 4 | 5 |
| Anglais | 2 | 3 |
| Histoire-Geographie | 2 | 3 |
| SVT | 2 | 2 |
| Education Civique | 1 | 1 |
| EPS | 1 | 2 |

*Classe de 4e/3e (Tronc commun College)* :
| Matiere | Coefficient | Heures/semaine |
|---------|-------------|----------------|
| Francais | 4 | 5 |
| Mathematiques | 4 | 5 |
| Physique-Chimie | 3 | 3 |
| SVT | 2 | 2 |
| Anglais | 2 | 3 |
| Histoire-Geographie | 2 | 3 |
| Education Civique | 1 | 1 |
| EPS | 1 | 2 |

*Classe de Terminale C (exemple Lycee)* :
| Matiere | Coefficient | Heures/semaine |
|---------|-------------|----------------|
| Mathematiques | 5 | 6 |
| Physique-Chimie | 5 | 5 |
| SVT | 2 | 2 |
| Francais | 2 | 3 |
| Philosophie | 2 | 3 |
| Anglais | 2 | 3 |
| Histoire-Geographie | 2 | 2 |
| EPS | 1 | 2 |

*Classe de Terminale A (exemple Lycee)* :
| Matiere | Coefficient | Heures/semaine |
|---------|-------------|----------------|
| Philosophie | 5 | 6 |
| Francais | 4 | 5 |
| Histoire-Geographie | 4 | 4 |
| Anglais | 3 | 3 |
| Mathematiques | 2 | 3 |
| 2e Langue vivante | 2 | 2 |
| EPS | 1 | 2 |

*Classe de Terminale D (exemple Lycee)* :
| Matiere | Coefficient | Heures/semaine |
|---------|-------------|----------------|
| SVT | 5 | 5 |
| Mathematiques | 4 | 5 |
| Physique-Chimie | 4 | 4 |
| Francais | 2 | 3 |
| Philosophie | 2 | 3 |
| Anglais | 2 | 3 |
| Histoire-Geographie | 2 | 2 |
| EPS | 1 | 2 |

**Baremes par defaut** :
| Parametre | Valeur |
|-----------|--------|
| Seuil de passage | 10.00 |
| Seuil de rachat | 9.00 |
| Mention Passable | 10.00 |
| Mention Assez Bien | 12.00 |
| Mention Bien | 14.00 |
| Mention Tres Bien | 16.00 |
| Tableau d'honneur | 14.00 |
| Encouragements | 12.00 |
| Felicitations | 16.00 |
| Avertissement travail | 7.00 |
| Blame | 5.00 |

### 4.5 Testing Requirements

**Tests obligatoires** :
- **Tests unitaires** : Validation codes uniques matieres, contraintes coefficients (pas de doublons), generation automatique du nom de classe, validation baremes (seuils coherents)
- **Tests d'integration** : Creation d'une structure complete (Annee → Classes → Matieres → Coefficients → Affectations), verification de la cascade des relations
- **Tests de cas limites** : Suppression avec dependances, codes dupliques, affectation d'un PP a 2 classes, affectation d'un enseignant a une matiere/classe deja occupee, modification de baremes apres generation de bulletins

**Outils** :
- Laravel : PHPUnit (`php artisan test`)
- Frontend : Jest + React Testing Library

### 4.6 Additional Technical Assumptions

- **API REST** : Endpoints suivant les conventions existantes :
  - `/api/admin/academic-years/*`
  - `/api/admin/semesters/*`
  - `/api/admin/cycles/*`
  - `/api/admin/classes/*`
  - `/api/admin/series/*`
  - `/api/admin/subjects/*`
  - `/api/admin/subject-coefficients/*`
  - `/api/admin/teacher-assignments/*`
  - `/api/admin/class-settings/*`
- **Middleware** : `['tenant', 'tenant.auth']` pour toutes les routes
- **Permissions** : Utiliser Spatie Permission pour controle d'acces (permission `manage-academic-structure` pour Admin)
- **Validation** : Form Requests pour toutes les saisies (StoreClassRequest, StoreSubjectRequest, StoreSubjectCoefficientRequest, StoreTeacherAssignmentRequest, UpdateClassSettingsRequest, etc.)
- **API Resources** : Retourner toujours des Resources, jamais de models bruts
- **SoftDeletes** : Utiliser sur toutes les tables (historique)
- **Casts Laravel 12** : Utiliser `casts()` method sur les models
- **Format JSON** : Reponses standardisees avec `success`, `message`, `data`
- **Codes uniques** : Index unique sur les colonnes `code` de chaque table
- **Enums PHP** : Utiliser des enums pour les niveaux (`Level`), les categories de matieres (`SubjectCategory`), les statuts d'annee scolaire (`AcademicYearStatus`)

---

## 5. Epic List

### Epic 1 : Gestion des Annees Scolaires et Semestres
**Goal** : Permettre a l'Admin de creer et gerer les annees scolaires avec leurs deux semestres, fondation temporelle de toute la structure academique.

### Epic 2 : Gestion des Cycles, Series et Classes
**Goal** : Permettre de configurer les cycles (College/Lycee), les series (A, C, D) et de creer les classes scolaires avec leurs caracteristiques (niveau, serie, section, capacite, professeur principal).

### Epic 3 : Gestion des Matieres et Coefficients
**Goal** : Permettre de creer les matieres d'enseignement et de configurer les coefficients par niveau et serie, fondation du systeme de notation et de calcul des moyennes.

### Epic 4 : Affectation Enseignants ↔ Matieres ↔ Classes
**Goal** : Permettre d'affecter les enseignants a leurs matieres dans les classes correspondantes, prerequis a la saisie des notes et a la generation des emplois du temps.

### Epic 5 : Configuration des Baremes
**Goal** : Permettre a chaque etablissement de configurer ses seuils de passage, mentions, tableau d'honneur, encouragements et sanctions selon les standards nigeriens.

### Epic 6 : Validation et Vue d'Ensemble
**Goal** : Fournir des outils de validation (completude des affectations, coherence des coefficients) et une vue arborescente de la structure academique complete.

---

## 6. Epic Details

### Epic 1 : Gestion des Annees Scolaires et Semestres

**Goal detaille** : L'Admin doit pouvoir creer les annees scolaires qui structurent le calendrier de l'etablissement. Chaque annee scolaire est composee de deux semestres (S1 et S2). Cette epic est la premiere a implementer car toutes les autres entites (classes, affectations, notes) sont rattachees a une annee scolaire.

#### Story 1.1 : Creation et Gestion des Annees Scolaires

**As an** Admin,
**I want** creer et gerer les annees scolaires de mon etablissement,
**so that** je puisse organiser le calendrier scolaire et structurer les inscriptions par annee.

**Acceptance Criteria** :
1. Un ecran "Gestion des Annees Scolaires" affiche la liste des annees scolaires existantes
2. Je peux creer une annee scolaire avec : Nom (ex: 2025-2026), Date de debut, Date de fin
3. Le nom doit etre unique au niveau du tenant (validation backend + frontend)
4. La date de fin doit etre posterieure a la date de debut
5. Lors de la creation, 2 semestres sont automatiquement crees :
   - S1 : debut = debut annee, fin = date configurable (par defaut milieu de l'annee)
   - S2 : debut = fin S1 + 1 jour, fin = fin annee
6. Je peux modifier une annee scolaire existante (nom, dates) et ajuster les dates des semestres
7. Je peux supprimer une annee scolaire uniquement si aucune classe, inscription ou note n'y est rattachee
8. La liste des annees est triable par : Nom, Date de debut, Statut
9. Un badge visuel distingue l'annee active (badge vert "Active")

**Dependances** : Module UsersGuard (auth Admin)

---

#### Story 1.2 : Activation d'une Annee Scolaire

**As an** Admin,
**I want** definir une annee scolaire comme "active",
**so that** tous les formulaires utilisent cette annee par defaut et que le systeme sache quelle est l'annee en cours.

**Acceptance Criteria** :
1. Un bouton "Activer" est disponible sur chaque annee scolaire non active
2. Le clic sur "Activer" desactive l'annee precedemment active et active la nouvelle
3. Une seule annee scolaire peut etre active a la fois
4. Un message de confirmation s'affiche : "Voulez-vous activer l'annee scolaire 2025-2026 ? L'annee 2024-2025 sera desactivee."
5. Apres activation, tous les formulaires (classes, inscriptions, affectations) utilisent cette annee par defaut
6. L'annee active est identifiable visuellement dans la liste (badge vert, mise en evidence)
7. Il n'est pas possible de desactiver une annee sans en activer une autre

**Dependances** : Story 1.1

---

#### Story 1.3 : Gestion des Semestres

**As an** Admin,
**I want** consulter et modifier les dates des semestres d'une annee scolaire,
**so that** je puisse ajuster le calendrier si necessaire (ex: greves, vacances prolongees).

**Acceptance Criteria** :
1. Les semestres sont affiches dans le detail d'une annee scolaire
2. Je peux modifier les dates de debut et de fin de chaque semestre
3. Le systeme valide que :
   - La date de debut de S1 >= date de debut de l'annee
   - La date de fin de S2 <= date de fin de l'annee
   - La date de debut de S2 > date de fin de S1
   - Aucun chevauchement entre S1 et S2
4. Le systeme affiche un avertissement si les dates sont modifiees apres que des notes ont ete saisies pour ce semestre
5. Le semestre actuel est automatiquement detecte selon la date du jour

**Dependances** : Story 1.1

---

### Epic 2 : Gestion des Cycles, Series et Classes

**Goal detaille** : L'Admin doit pouvoir configurer les cycles disponibles dans son etablissement (College et/ou Lycee), gerer les series pour le lycee, et creer les classes scolaires. Cette epic definit l'organisation physique de l'etablissement : quelles classes existent, avec quel effectif, quel professeur principal.

#### Story 2.1 : Configuration des Cycles

**As an** Admin,
**I want** configurer les cycles disponibles dans mon etablissement,
**so that** le systeme sache si je gere un college, un lycee, ou les deux.

**Acceptance Criteria** :
1. Un ecran "Configuration des Cycles" affiche les cycles disponibles (College, Lycee)
2. Les cycles sont pre-crees lors de l'initialisation du tenant (seeder)
3. Je peux activer/desactiver un cycle selon le type de mon etablissement :
   - College uniquement : seul le cycle "College" est actif
   - Lycee uniquement : seul le cycle "Lycee" est actif
   - College + Lycee : les deux cycles sont actifs
4. La desactivation d'un cycle est bloquee si des classes existent pour ce cycle dans l'annee active
5. Les niveaux associes a chaque cycle sont affiches (College : 6e, 5e, 4e, 3e ; Lycee : 2nde, 1ere, Tle)
6. Je peux modifier la description d'un cycle mais pas son code ni ses niveaux

**Dependances** : Story 1.1

---

#### Story 2.2 : Gestion des Series

**As an** Admin,
**I want** consulter et gerer les series du lycee,
**so that** je puisse adapter la structure aux series enseignees dans mon etablissement.

**Acceptance Criteria** :
1. Un ecran "Gestion des Series" affiche les series disponibles (A, C, D)
2. Les series sont pre-creees lors de l'initialisation du tenant (seeder)
3. Je peux activer/desactiver une serie (ex: desactiver la Serie C si mon lycee ne la propose pas)
4. La desactivation d'une serie est bloquee si des classes de cette serie existent dans l'annee active
5. Je peux ajouter une nouvelle serie avec : Code (unique, majuscules), Nom, Description
6. Je peux modifier le nom et la description d'une serie existante
7. Les series ne s'appliquent qu'aux niveaux 1ere et Terminale (le systeme le rappelle visuellement)
8. La suppression d'une serie n'est pas autorisee (uniquement desactivation pour conserver l'historique)

**Dependances** : Story 2.1 (cycles actifs pour determiner si le lycee est disponible)

---

#### Story 2.3 : Creation et Gestion des Classes

**As an** Admin,
**I want** creer et gerer les classes scolaires de mon etablissement,
**so that** je puisse definir les groupes d'eleves pour chaque niveau.

**Acceptance Criteria** :
1. Un ecran "Gestion des Classes" affiche la liste des classes de l'annee active
2. Je peux creer une classe avec :
   - Niveau (select dynamique : uniquement les niveaux des cycles actifs)
   - Serie (select dynamique : apparait uniquement si Niveau = 1ere ou Tle ; liste les series actives)
   - Section/Lettre (texte libre, ex: A, B, 1, 2)
   - Capacite maximale (numerique, defaut 50)
   - Salle attitree (texte, optionnel)
   - Professeur principal (select : enseignants disponibles)
3. Le nom complet est auto-genere : [Niveau + Serie + Section] => "Tle C1", "6eme A", "2nde B"
4. Le nom complet doit etre unique pour une meme annee scolaire
5. Le cycle est auto-determine selon le niveau choisi
6. Je peux modifier une classe existante (tous les champs modifiables sauf le niveau et la serie si des eleves sont inscrits)
7. Je peux supprimer une classe si aucun eleve n'y est inscrit
8. Si je tente de supprimer une classe avec eleves, un message d'erreur s'affiche : "Impossible de supprimer la classe [Nom] car elle contient X eleves inscrits"
9. La liste des classes affiche : Nom complet, Cycle (badge couleur), Niveau, Serie, PP, Effectif/Capacite, Salle, Actions
10. Le tableau est trie par defaut : Cycle (College avant Lycee), puis Niveau (6e → Tle), puis Serie, puis Section

**Dependances** : Story 2.1 (Cycles), Story 2.2 (Series)

---

#### Story 2.4 : Affectation du Professeur Principal

**As an** Admin,
**I want** affecter un professeur principal a chaque classe,
**so that** chaque classe ait un referent responsable du suivi des eleves.

**Acceptance Criteria** :
1. Lors de la creation/modification d'une classe, je peux selectionner un professeur principal
2. La liste des enseignants disponibles ne montre que les enseignants qui ne sont pas deja professeur principal d'une autre classe pour cette annee scolaire
3. Si un enseignant est deja PP d'une autre classe, il n'apparait pas dans la liste (ou apparait grise avec indication "Deja PP de [Classe]")
4. Le changement de professeur principal est possible a tout moment de l'annee
5. Un recapitulatif affiche les classes sans professeur principal (alerte orange)
6. Le professeur principal est affiche dans le detail de la classe et dans la liste des classes

**Dependances** : Story 2.3, Module UsersGuard (enseignants)

---

#### Story 2.5 : Filtrage et Navigation des Classes

**As an** Admin,
**I want** filtrer et naviguer dans la liste des classes selon differents criteres,
**so that** je puisse retrouver rapidement une classe specifique.

**Acceptance Criteria** :
1. Des filtres sont disponibles au-dessus de la liste des classes :
   - Cycle (select : Tous, College, Lycee)
   - Niveau (select : Tous, 6e, 5e, ..., Tle)
   - Serie (select : Toutes, A, C, D - visible uniquement si Lycee selectionne)
   - Annee scolaire (select, defaut : annee active)
2. Les filtres sont cumulables (AND logic)
3. Un bouton "Reinitialiser filtres" efface tous les filtres
4. Le nombre de classes affichees est visible (ex: "12 classes trouvees")
5. Un compteur global affiche : "Total eleves : X dans Y classes"

**Dependances** : Story 2.3

---

### Epic 3 : Gestion des Matieres et Coefficients

**Goal detaille** : L'Admin doit pouvoir creer les matieres d'enseignement et configurer les coefficients par niveau et serie. Les coefficients sont essentiels pour le calcul des moyennes ponderees dans le Module Notes. Une matiere comme Mathematiques peut avoir un coefficient de 5 en Tle C mais seulement 2 en Tle A, refletant l'importance relative de la matiere selon la serie.

#### Story 3.1 : Creation et Gestion des Matieres

**As an** Admin,
**I want** creer et gerer les matieres d'enseignement,
**so that** je puisse definir le catalogue de matieres de mon etablissement.

**Acceptance Criteria** :
1. Un ecran "Gestion des Matieres" affiche la liste des matieres existantes
2. Je peux creer une matiere avec :
   - Code matiere (majuscules, unique, ex: MATH, FRAN, PHYS)
   - Nom complet (ex: "Mathematiques")
   - Nom abrege (ex: "Maths")
   - Categorie (select : Sciences, Lettres, Langues, Sciences Humaines, Education Physique, Arts, Autres)
   - Description (textarea, optionnelle)
3. Le code matiere doit etre unique au niveau du tenant (validation backend + frontend)
4. Des matieres par defaut sont pre-creees lors de l'initialisation du tenant (seeder avec matieres standard nigeriennes)
5. Je peux modifier une matiere existante (tous les champs modifiables sauf le code si des coefficients ou notes existent)
6. Je peux desactiver une matiere (au lieu de la supprimer) pour la masquer des formulaires de creation de coefficients/affectations
7. La suppression d'une matiere est bloquee si des coefficients, notes ou affectations enseignants existent
8. La liste des matieres affiche : Code, Nom, Nom abrege, Categorie (badge), Statut (badge), Actions
9. Une recherche par code ou nom est disponible
10. Des filtres sont disponibles : Categorie, Statut (Active/Inactive)

**Dependances** : Module UsersGuard (auth Admin)

---

#### Story 3.2 : Configuration des Coefficients par Niveau et Serie

**As an** Admin,
**I want** definir le coefficient de chaque matiere pour chaque niveau et serie,
**so that** le systeme puisse calculer correctement les moyennes ponderees.

**Acceptance Criteria** :
1. Un ecran "Configuration des Coefficients" affiche un selecteur de niveau et de serie
2. Apres selection d'un niveau/serie, un tableau affiche les matieres configurees avec leurs coefficients :
   - Colonnes : Matiere (code + nom), Coefficient, Heures hebdomadaires, Actions (Modifier, Supprimer)
   - Ligne de total : Somme des coefficients, Somme des heures hebdomadaires
3. Je peux ajouter une matiere a ce niveau/serie via un bouton "Ajouter une matiere" :
   - Select matiere (uniquement les matieres actives non encore ajoutees a ce niveau/serie)
   - Coefficient (numerique, 1 a 8)
   - Heures hebdomadaires (numerique, optionnel)
4. Je peux modifier le coefficient ou les heures d'une matiere existante (edition inline ou modale)
5. Je peux supprimer une matiere de ce niveau/serie (retirer le coefficient)
6. La suppression est bloquee si des notes existent pour cette matiere/niveau/serie
7. Le systeme empeche la creation de doublons (meme matiere pour le meme niveau/serie)
8. Pour les niveaux 6e a 2nde, la serie est "Tronc commun" (pas de selection de serie)
9. Pour les niveaux 1ere et Tle, la serie est obligatoire (A, C, ou D)
10. Des coefficients par defaut sont pre-configures lors de l'initialisation du tenant (seeder)

**Dependances** : Story 3.1 (Matieres), Story 2.2 (Series)

---

#### Story 3.3 : Vue Comparative des Coefficients entre Series

**As an** Admin,
**I want** voir un tableau comparatif des coefficients entre les differentes series pour un meme niveau,
**so that** je puisse verifier la coherence de la configuration et voir rapidement les differences.

**Acceptance Criteria** :
1. Un ecran "Comparaison des Coefficients" affiche un tableau croise :
   - Lignes : Matieres
   - Colonnes : Series (A, C, D) - pour le niveau selectionne
   - Cellules : Coefficient (ou "-" si la matiere n'est pas enseignee dans cette serie)
2. Le tableau est disponible uniquement pour les niveaux 1ere et Tle
3. La somme des coefficients est affichee en bas de chaque colonne
4. Les differences significatives sont mises en evidence (ex: Maths coeff 5 en C mais 2 en A → couleur differente)
5. Un bouton "Exporter en PDF" permet d'imprimer le tableau comparatif

**Dependances** : Story 3.2

---

#### Story 3.4 : Duplication de la Configuration des Coefficients

**As an** Admin,
**I want** dupliquer la configuration des coefficients d'un niveau/serie vers un autre,
**so that** je puisse gagner du temps lors de la configuration initiale ou de la creation de nouvelles series.

**Acceptance Criteria** :
1. Un bouton "Dupliquer cette configuration" est disponible sur l'ecran de configuration des coefficients
2. Le clic ouvre un formulaire avec :
   - Niveau cible (select)
   - Serie cible (select, si applicable)
3. Le systeme copie tous les coefficients du niveau/serie source vers la cible
4. Si des coefficients existent deja dans la cible, un message d'avertissement s'affiche : "Des coefficients existent deja pour [cible]. Voulez-vous les remplacer ou les fusionner ?"
5. Options : "Remplacer tout" (supprime les anciens et copie), "Fusionner" (ajoute les manquants sans modifier les existants), "Annuler"
6. Apres duplication, l'Admin est redirige vers la configuration cible pour ajuster si necessaire

**Dependances** : Story 3.2

---

### Epic 4 : Affectation Enseignants ↔ Matieres ↔ Classes

**Goal detaille** : L'Admin doit pouvoir affecter les enseignants a leurs matieres dans les classes correspondantes. Cette epic est essentielle car un enseignant ne peut saisir des notes que pour les matieres/classes qui lui sont affectees. L'affectation permet aussi de calculer la charge horaire de chaque enseignant.

#### Story 4.1 : Affectation d'un Enseignant a une Matiere et des Classes

**As an** Admin,
**I want** affecter un enseignant a une matiere dans une ou plusieurs classes,
**so that** l'enseignant puisse saisir les notes de ses eleves dans ces classes.

**Acceptance Criteria** :
1. Un ecran "Affectations Enseignants" affiche la liste des affectations existantes pour l'annee active
2. Un bouton "Affecter un enseignant" ouvre un formulaire avec :
   - Enseignant (select avec recherche : liste des utilisateurs avec role "Enseignant")
   - Matiere (select : liste des matieres actives)
   - Classe(s) (multi-select : liste des classes de l'annee active, filtrees par les classes ou cette matiere a un coefficient configure)
   - Annee scolaire (select, defaut : annee active)
3. La selection de la matiere filtre automatiquement les classes disponibles (uniquement les classes ou un coefficient existe pour cette matiere)
4. Le multi-select de classes permet d'affecter l'enseignant a plusieurs classes en une seule operation
5. Le systeme verifie qu'aucune autre affectation n'existe deja pour la meme matiere/classe (une matiere par classe a un seul enseignant)
6. Si une affectation existe deja pour matiere/classe, un message d'erreur s'affiche : "La matiere [Matiere] est deja affectee a [Enseignant] dans la classe [Classe]"
7. Un message de succes s'affiche apres creation : "Enseignant [Nom] affecte a [Matiere] dans X classe(s) avec succes"
8. La charge horaire resultante est affichee (somme des heures hebdomadaires des affectations)

**Dependances** : Story 3.2 (Coefficients/heures), Story 2.3 (Classes), Module UsersGuard (enseignants)

---

#### Story 4.2 : Liste et Filtrage des Affectations

**As an** Admin,
**I want** consulter la liste de toutes les affectations enseignants et les filtrer,
**so that** je puisse verifier que toutes les matieres sont couvertes dans toutes les classes.

**Acceptance Criteria** :
1. La liste des affectations affiche : Enseignant (nom), Matiere (code + nom), Classe, Charge horaire, Annee scolaire, Actions (Retirer)
2. Des filtres sont disponibles : Enseignant, Matiere, Classe, Niveau, Cycle, Annee scolaire
3. Un regroupement par enseignant est disponible (voir toutes les classes d'un enseignant)
4. Un recapitulatif affiche :
   - "X enseignants affectes a Y matieres dans Z classes"
   - "W matieres/classes non couvertes" (avec lien pour voir lesquelles)
5. Un bouton "Retirer" permet de supprimer une affectation
6. Si je tente de retirer une affectation pour laquelle des notes ont deja ete saisies, un message d'avertissement s'affiche : "Attention : Des notes ont deja ete saisies pour cette matiere/classe. Voulez-vous vraiment retirer cette affectation ?"
7. La confirmation de suppression avec notes existantes necessite une double confirmation

**Dependances** : Story 4.1

---

#### Story 4.3 : Verification de la Couverture des Matieres

**As an** Admin,
**I want** voir quelles matieres ne sont pas encore affectees a un enseignant dans chaque classe,
**so that** je puisse m'assurer que toutes les matieres sont couvertes avant le debut du semestre.

**Acceptance Criteria** :
1. Un ecran "Verification de couverture" affiche pour chaque classe :
   - Liste des matieres avec coefficient configure pour cette classe
   - Pour chaque matiere : Enseignant affecte (nom) ou badge rouge "Non affecte"
2. Le tableau est filtre par cycle, niveau, serie
3. Un pourcentage de couverture est affiche par classe (ex: "8/10 matieres couvertes - 80%")
4. Un pourcentage de couverture global est affiche (ex: "Couverture globale : 95%")
5. Un bouton "Affecter" est disponible directement sur les lignes "Non affecte" pour creer l'affectation rapidement
6. Les classes avec couverture < 100% sont mises en evidence (badge orange ou rouge)

**Dependances** : Story 4.1, Story 3.2

---

#### Story 4.4 : Reconduction des Affectations

**As an** Admin,
**I want** dupliquer les affectations d'une annee scolaire vers une nouvelle annee,
**so that** je puisse reconduire les affectations existantes sans tout ressaisir.

**Acceptance Criteria** :
1. Un bouton "Reconduire les affectations" est disponible sur l'ecran d'affectation
2. Le clic ouvre un formulaire :
   - Annee source (select : annees scolaires avec affectations)
   - Annee cible (select : annees scolaires sans affectation ou partiellement configurees)
3. Le systeme copie toutes les affectations enseignant/matiere et essaie de les mapper sur les classes de l'annee cible (meme niveau/serie/section)
4. Un rapport de reconduction est affiche :
   - "X affectations reconduites avec succes"
   - "Y affectations non reconduites (classe non trouvee dans l'annee cible)"
   - Liste des affectations non reconduites avec raison
5. L'Admin peut ensuite ajuster manuellement les affectations non reconduites
6. La reconduction ne cree pas de doublons (ignore les affectations deja existantes dans la cible)

**Dependances** : Story 4.1

---

#### Story 4.5 : Vue Enseignant : Mes Matieres et Classes

**As an** Enseignant,
**I want** voir la liste des matieres et classes qui me sont affectees pour l'annee en cours,
**so that** je sache pour quelles classes et matieres je dois saisir des notes.

**Acceptance Criteria** :
1. Un ecran "Mes matieres et classes" affiche la liste des affectations pour l'annee active
2. La liste affiche : Matiere (code + nom), Classe (nom complet), Nombre d'eleves, Coefficient, Heures/semaine
3. Un filtre par semestre est disponible (pour anticiper ou consulter)
4. Chaque ligne a un lien "Voir les eleves" pour acceder a la liste des eleves de la classe
5. Cette liste sera utilisee par le Module Notes pour afficher les matieres/classes ou l'enseignant peut saisir des notes
6. La charge horaire totale est affichee en haut de l'ecran

**Dependances** : Story 4.1, Module UsersGuard (auth Enseignant)

---

### Epic 5 : Configuration des Baremes

**Goal detaille** : L'Admin doit pouvoir configurer les baremes de son etablissement : seuils de passage, mentions, tableau d'honneur, encouragements, felicitations et sanctions. Ces baremes sont utilises par le Module Notes et le Module Bulletins pour determiner les resultats des eleves.

#### Story 5.1 : Configuration des Seuils de Passage et Mentions

**As an** Admin,
**I want** configurer les seuils de passage et de mention de mon etablissement,
**so that** le systeme applique les bons criteres lors du calcul des resultats.

**Acceptance Criteria** :
1. Un ecran "Configuration des Baremes" affiche les parametres actuels
2. Je peux configurer :
   - Seuil de passage : moyenne minimale pour passer (defaut 10/20)
   - Seuil de rachat : moyenne minimale pour etre eligible au rachat (defaut 9/20, activable/desactivable)
   - Mentions :
     - Passable : seuil minimum (defaut 10)
     - Assez Bien : seuil minimum (defaut 12)
     - Bien : seuil minimum (defaut 14)
     - Tres Bien : seuil minimum (defaut 16)
3. Le systeme valide la coherence des seuils :
   - Seuil de rachat < Seuil de passage
   - Passable <= Assez Bien <= Bien <= Tres Bien
   - Tous les seuils entre 0 et 20
4. Les valeurs par defaut sont pre-remplies selon les standards nigeriens
5. Un bouton "Reinitialiser aux valeurs par defaut" est disponible
6. Les modifications sont enregistrees immediatement
7. Un avertissement s'affiche si des bulletins ont deja ete generes avec les anciens baremes

**Dependances** : Story 2.1 (Cycles pour configuration par cycle)

---

#### Story 5.2 : Configuration du Tableau d'Honneur et Distinctions

**As an** Admin,
**I want** configurer les seuils pour le tableau d'honneur, les encouragements et les felicitations,
**so that** les distinctions soient correctement attribuees sur les bulletins.

**Acceptance Criteria** :
1. Dans l'ecran "Configuration des Baremes", une section "Distinctions" permet de configurer :
   - Tableau d'honneur : seuil minimum (defaut 14/20)
   - Encouragements : seuil minimum (defaut 12/20)
   - Felicitations : seuil minimum (defaut 16/20)
2. Le systeme valide la coherence :
   - Encouragements <= Tableau d'honneur <= Felicitations
3. Je peux activer/desactiver chaque distinction individuellement
4. Les distinctions activees seront affichees sur les bulletins des eleves correspondants

**Dependances** : Story 5.1

---

#### Story 5.3 : Configuration des Sanctions (Avertissement, Blame)

**As an** Admin,
**I want** configurer les seuils declenchant des avertissements de travail ou des blames,
**so that** les sanctions soient correctement appliquees sur les bulletins.

**Acceptance Criteria** :
1. Dans l'ecran "Configuration des Baremes", une section "Sanctions" permet de configurer :
   - Avertissement travail : seuil maximum (defaut 7/20 - si moyenne <= seuil)
   - Blame : seuil maximum (defaut 5/20 - si moyenne <= seuil)
2. Le systeme valide que : Blame < Avertissement travail
3. Je peux activer/desactiver chaque sanction individuellement
4. Les sanctions activees seront affichees sur les bulletins des eleves correspondants

**Dependances** : Story 5.1

---

#### Story 5.4 : Baremes par Cycle (optionnel)

**As an** Admin,
**I want** configurer des baremes differents pour le college et le lycee,
**so that** je puisse appliquer des criteres adaptes a chaque cycle si necessaire.

**Acceptance Criteria** :
1. Un toggle "Baremes differencies par cycle" est disponible dans la configuration
2. Si active, deux onglets "College" et "Lycee" apparaissent, chacun avec ses propres parametres
3. Si desactive, un seul jeu de parametres s'applique a tout l'etablissement (comportement par defaut)
4. Les baremes par cycle sont enregistres dans la table `class_settings` avec le `cycle_id` correspondant
5. Les baremes sans `cycle_id` (null) s'appliquent globalement quand la differenciation n'est pas activee

**Dependances** : Story 5.1, Story 2.1

---

### Epic 6 : Validation et Vue d'Ensemble

**Goal detaille** : Fournir des outils de validation (completude des affectations, coherence des coefficients, verification que toutes les classes ont un PP) et une vue arborescente de la structure academique complete pour avoir une vision d'ensemble de l'etablissement.

#### Story 6.1 : Vue Arborescente de la Structure Academique

**As an** Admin,
**I want** visualiser la structure academique sous forme d'arbre hierarchique,
**so that** j'aie une vue d'ensemble de mon etablissement.

**Acceptance Criteria** :
1. Un ecran "Vue d'ensemble" affiche la structure academique sous forme d'arbre interactif :
   ```
   ├─ College (1er cycle) [10 classes, 468 eleves]
   │  ├─ 6eme [3 classes, 145 eleves]
   │  │  ├─ 6eme A (PP: M. Moussa) [48/50] - 7 matieres
   │  │  ├─ 6eme B (PP: Mme Fatima) [49/50] - 7 matieres
   │  │  └─ 6eme C (PP: M. Ibrahim) [48/50] - 7 matieres
   │  ├─ 5eme [3 classes, 140 eleves]
   │  ├─ 4eme [2 classes, 95 eleves]
   │  └─ 3eme [2 classes, 88 eleves]
   ├─ Lycee (2nd cycle) [11 classes, 445 eleves]
   │  ├─ 2nde (Tronc commun) [3 classes, 130 eleves]
   │  ├─ 1ere [4 classes, 160 eleves]
   │  │  ├─ 1ere A (PP: Mme Mariama) [42/50] - 8 matieres
   │  │  ├─ 1ere C (PP: M. Abdou) [40/45] - 8 matieres
   │  │  └─ ...
   │  └─ Tle [4 classes, 155 eleves]
   │     ├─ Tle A (PP: M. Garba) [40/50] - 7 matieres
   │     ├─ Tle C (PP: Mme Zara) [38/45] - 8 matieres
   │     └─ ...
   ```
2. Chaque noeud de l'arbre est cliquable pour afficher les details
3. Un bouton "Modifier" est disponible sur chaque noeud pour editer l'entite
4. Les noeuds sont expansibles/collapsibles
5. L'arbre affiche le nombre d'entites enfants et les effectifs entre crochets
6. Les indicateurs visuels montrent l'etat (vert = OK, orange = attention, rouge = probleme)
7. L'arbre est filtre par annee scolaire (defaut : annee active)

**Dependances** : Stories 2.1, 2.2, 2.3, 3.2, 4.1

---

#### Story 6.2 : Dashboard Structure Academique avec Statistiques

**As an** Admin,
**I want** voir un dashboard avec des statistiques sur la structure academique,
**so that** j'aie une vue quantitative de mon etablissement.

**Acceptance Criteria** :
1. Un dashboard affiche :
   - Nombre total de classes (avec repartition College/Lycee)
   - Nombre total d'eleves inscrits
   - Nombre de matieres configurees
   - Nombre d'enseignants affectes
   - Taux de couverture des affectations (pourcentage de matieres/classes avec enseignant)
   - Nombre de classes sans professeur principal
2. Des graphiques sont affiches :
   - Repartition des eleves par cycle (College/Lycee) - Pie chart
   - Repartition des eleves par niveau (6e, 5e, ..., Tle) - Bar chart
   - Repartition des classes par serie pour le lycee - Bar chart
   - Taux de remplissage des classes (effectif vs capacite) - Bar chart horizontal
3. Des liens rapides sont disponibles : "Gerer Classes", "Gerer Matieres", "Coefficients", "Affectations", "Baremes"
4. Des alertes sont affichees en haut du dashboard :
   - Classes sans PP (liste)
   - Matieres non couvertes (nombre)
   - Classes en sureffectif (liste)

**Dependances** : Stories 2.3, 3.1, 3.2, 4.1, 5.1

---

#### Story 6.3 : Rapport de Validation de la Structure

**As an** Admin,
**I want** generer un rapport de validation de la structure academique,
**so that** je puisse identifier les incoherences avant le debut du semestre.

**Acceptance Criteria** :
1. Un bouton "Generer rapport de validation" est disponible sur le dashboard
2. Le rapport affiche les categories suivantes avec indicateurs (vert/orange/rouge) :
   - **Classes sans professeur principal** : Liste des classes concernees avec lien "Affecter"
   - **Niveaux/Series sans coefficients** : Liste des (niveau, serie) actifs qui n'ont aucun coefficient configure
   - **Matieres sans enseignant affecte** : Liste des (matiere, classe) sans affectation pour l'annee active
   - **Classes en sureffectif** : Liste des classes ou le nombre d'eleves > capacite max
   - **Classes vides** : Liste des classes sans aucun eleve inscrit
   - **Coherence des baremes** : Verification que les seuils sont configures et coherents
   - **Enseignants sans affectation** : Liste des enseignants actifs qui n'ont aucune affectation pour l'annee active
3. Chaque section affiche un compteur (ex: "3 classes sans PP")
4. Un score global de completude est affiche (ex: "Structure complete a 87%")
5. Le rapport est exportable en PDF
6. Chaque ligne du rapport a un lien "Corriger" qui redirige vers l'ecran de modification

**Dependances** : Stories 2.3, 2.4, 3.2, 4.1, 4.3, 5.1

---

#### Story 6.4 : Export de la Structure Academique

**As an** Admin,
**I want** exporter la structure academique en format imprimable,
**so that** je puisse archiver ou partager la structure avec les autorites academiques.

**Acceptance Criteria** :
1. Un bouton "Exporter" est disponible sur la vue d'ensemble
2. Options d'export :
   - **PDF** : Document formate avec la structure complete (cycles, classes, matieres, coefficients, enseignants)
   - **Excel** : Tableur avec onglets (Classes, Matieres, Coefficients, Affectations)
3. L'export inclut :
   - Liste des classes avec PP et effectifs
   - Tableau des coefficients par niveau/serie
   - Liste des affectations enseignants
   - Baremes configures
4. L'export est filtre par annee scolaire
5. Le document genere porte l'en-tete de l'etablissement (nom, logo si configure)

**Dependances** : Stories 2.3, 3.2, 4.1, 5.1

---

## 7. Checklist Results Report

**A completer apres implementation** : Executer le checklist PM pour valider :
- [ ] Tous les requirements fonctionnels sont implementes (FR1-FR58)
- [ ] Les annees scolaires sont creees avec 2 semestres automatiques
- [ ] Les cycles (College/Lycee) sont configurables et fonctionnels
- [ ] Les series (A, C, D) sont gerees et s'appliquent uniquement a 1ere et Tle
- [ ] Les classes sont creees avec nom auto-genere, PP et capacite
- [ ] Les matieres sont creees avec code unique et categories
- [ ] Les coefficients sont configurables par niveau/serie avec validation anti-doublons
- [ ] Les affectations enseignant ↔ matiere ↔ classe sont operationnelles
- [ ] La verification de couverture des matieres fonctionne
- [ ] Les baremes sont configurables (seuils, mentions, distinctions, sanctions)
- [ ] La vue arborescente affiche la structure complete
- [ ] Le rapport de validation identifie toutes les incoherences
- [ ] Les contraintes de suppression fonctionnent correctement
- [ ] Les permissions sont appliquees (Admin uniquement pour configuration, Enseignant pour consultation)
- [ ] L'interface est responsive et accessible (WCAG AA)
- [ ] Les donnees initiales sont inserees correctement (seeder)
- [ ] Les tests unitaires et d'integration couvrent tous les cas

---

## 8. Next Steps

### 8.1 UX Expert Prompt

> "Creez les maquettes UI pour le Module Structure Academique adapte a l'enseignement secondaire nigerien. Focus sur les ecrans critiques : Configuration des coefficients par niveau/serie (grille editable avec vue comparative entre series), Gestion des classes (avec indicateurs d'effectifs et PP), Affectation enseignants (multi-select de classes), Configuration des baremes (formulaire organise en sections). Assurez l'accessibilite WCAG AA, le responsive design, et une navigation intuitive entre les cycles College et Lycee."

### 8.2 Architect Prompt

> "Concevez l'architecture technique du Module Structure Academique pour l'enseignement secondaire en suivant les patterns etablis dans le module UsersGuard. Definissez les tables de base de donnees (academic_years, semesters, cycles, classes, series, subjects, subject_coefficients, teacher_assignments, class_settings) avec relations Eloquent et eager loading. Implementez les enums PHP pour les niveaux et categories. Creez les controllers avec Form Requests pour la validation des contraintes (unicite codes, coherence baremes, anti-doublons coefficients). Creez les API Resources, les factories et seeders pour les donnees initiales nigeriennes. Ecrivez les tests PHPUnit pour les cas critiques : creation de classe avec nom auto-genere, coefficients differencies par serie, affectation enseignant avec verification couverture, validation coherence baremes."

---

**Document cree par** : John (Product Manager Agent)
**Date de creation** : 2026-01-07
**Date de mise a jour** : 2026-03-16
**Version** : 2.0
**Statut** : Draft pour review - Refonte secondaire
