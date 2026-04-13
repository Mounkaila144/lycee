# PRD - Module Inscriptions (Enrollment)

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Module** : Inscriptions (Enrollment)
> **Version** : v5
> **Date** : 2026-03-16
> **Phase** : Phase 1 - MVP Core
> **Priorite** : CRITIQUE 🔴 (Prerequis au Module Notes)

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 2.0 | Refonte complete - Adaptation pour l'enseignement secondaire (colleges/lycees). Remplacement filieres/niveaux LMD par classes (6e-Tle), series, matieres a coefficients. Ajout role Parent/Tuteur, creation automatique compte parent, exeat/certificat de scolarite, passage en classe superieure. | John (PM) |
| 2026-01-07 | 1.0 | Creation initiale du PRD Module Inscriptions (systeme LMD) | John (PM) |

---

## 1. Goals and Background Context

### 1.1 Goals

- **Numeriser l'inscription administrative** : Remplacer les registres papier par une saisie en ligne des donnees personnelles des eleves et de leurs parents/tuteurs
- **Automatiser la generation des matricules** : Generer automatiquement des numeros de matricule uniques pour chaque eleve inscrit
- **Gerer l'affectation en classe** : Inscrire les eleves dans une classe precise (6e A, 3e B, Tle D1, etc.) avec gestion des series pour le lycee
- **Creer automatiquement les comptes parents** : Lors de l'inscription d'un eleve, creer automatiquement un compte Parent/Tuteur lie a l'eleve
- **Permettre l'import en masse** : Importer des centaines d'eleves via CSV/Excel pour gagner du temps en debut d'annee scolaire
- **Gerer les statuts des eleves** : Suivre le cycle de vie (Actif, Transfere, Exclu, Diplome)
- **Gerer le passage en classe superieure** : Permettre la promotion des eleves d'une classe a la suivante (reinscription annuelle)
- **Generer les documents administratifs** : Exeat, certificat de scolarite, attestation d'inscription
- **Preparer la saisie de notes** : Fournir les listes d'eleves inscrits par classe pour le Module Notes

### 1.2 Background Context

Le **Module Inscriptions** est essentiel pour faire fonctionner le systeme academique. Sans eleves inscrits et affectes a des classes, il est impossible de saisir des notes, de generer des bulletins, ou de gerer les presences.

Ce module s'inscrit dans la **Phase 1 MVP Core** car il est le **deuxieme prerequis critique** (apres Structure Academique) :
1. Les eleves doivent etre inscrits administrativement (donnees personnelles, matricule, photo)
2. Les informations du/des parent(s)/tuteur(s) doivent etre enregistrees avec creation automatique de leur compte
3. Les eleves doivent etre affectes a une classe (6e A, 3e B, Tle C1, etc.)
4. Les eleves du lycee doivent etre rattaches a une serie (A, C, D)

Le module gere deux dimensions de l'inscription :
- **Inscription administrative** : Enregistrement des donnees personnelles de l'eleve (nom, prenom, date de naissance, etc.), des informations parentales, et generation du matricule
- **Affectation pedagogique** : Placement de l'eleve dans une classe, avec rattachement automatique aux matieres et coefficients de cette classe/serie

**Pain point resolu** : Les etablissements gerent actuellement les inscriptions sur des cahiers ou des fichiers Excel disperses, ce qui entraine :
- Des erreurs de saisie frequentes (noms mal orthographies, dates erronees)
- Des doublons (meme eleve inscrit 2 fois avec des matricules differents)
- Une perte de temps considerable (15-20 minutes par eleve manuellement)
- Aucune liaison formalisee entre l'eleve et son parent/tuteur dans le systeme
- Des difficultates a produire rapidement les listes de classe, certificats de scolarite et exeats
- Une gestion chaotique des transferts et passages en classe superieure

Avec ce module, l'inscription administrative + affectation en classe prend **< 5 minutes** (vs 15-20 minutes manuellement), et le compte parent est cree automatiquement.

---

## 2. Requirements

### 2.1 Functional Requirements (FR)

#### 2.1.1 Inscription Administrative de l'Eleve

- **FR1** : Le systeme doit permettre a l'Admin/Directeur de creer un eleve avec les donnees personnelles suivantes :
  - Nom (obligatoire)
  - Prenom (obligatoire)
  - Date de naissance (obligatoire)
  - Lieu de naissance (optionnel)
  - Sexe (select : M, F)
  - Nationalite (optionnel, defaut : Nigerienne)
  - Telephone (optionnel - pour les eleves du lycee)
  - Adresse (optionnel)
  - Ville (optionnel, defaut : Niamey)
  - Quartier (optionnel)
  - Photo (upload image, optionnelle)
  - Groupe sanguin (optionnel)
  - Maladie ou allergie connue (optionnel - champ texte libre)
- **FR2** : Le systeme doit generer automatiquement un numero de matricule unique lors de la creation d'un eleve
- **FR3** : Le format du matricule doit etre configurable par tenant (ex: 2025-001, LYCEE-2025-001, MAT-001-2025, etc.)
- **FR4** : Le systeme doit verifier l'unicite du matricule au niveau du tenant
- **FR5** : Le systeme doit permettre de modifier les donnees personnelles d'un eleve existant
- **FR6** : Le systeme doit permettre d'uploader une photo de l'eleve (formats : JPG, PNG, max 2 MB)
- **FR7** : Le systeme doit permettre de supprimer un eleve uniquement si aucune note n'a ete saisie pour lui (soft delete)

#### 2.1.2 Informations Parent/Tuteur et Creation de Compte

- **FR8** : Le systeme doit permettre de saisir les informations d'au moins un parent/tuteur lors de l'inscription de l'eleve :
  - Nom du pere (optionnel)
  - Prenom du pere (optionnel)
  - Telephone du pere (optionnel)
  - Profession du pere (optionnel)
  - Nom de la mere (optionnel)
  - Prenom de la mere (optionnel)
  - Telephone de la mere (optionnel)
  - Profession de la mere (optionnel)
  - Nom du tuteur legal (optionnel, si different des parents)
  - Prenom du tuteur legal (optionnel)
  - Telephone du tuteur legal (obligatoire - au moins un contact est requis)
  - Email du tuteur/parent (optionnel mais necessaire pour la creation du compte portail)
  - Adresse du parent/tuteur (optionnelle)
  - Lien de parente (select : Pere, Mere, Tuteur, Oncle, Tante, Grand-parent, Autre)
- **FR9** : Si un email est fourni pour le parent/tuteur, le systeme doit creer automatiquement un compte utilisateur avec le role "Parent" lie a l'eleve
- **FR10** : Si le parent/tuteur existe deja dans le systeme (meme email ou meme telephone), le systeme doit proposer de lier l'eleve au compte existant (cas : fratrie dans le meme etablissement)
- **FR11** : Le systeme doit permettre de lier plusieurs eleves au meme parent/tuteur
- **FR12** : Le mot de passe initial du compte parent doit etre genere automatiquement et communique (affiche a l'ecran + option email)
- **FR13** : Le systeme doit permettre de modifier les informations du parent/tuteur depuis la fiche de l'eleve

#### 2.1.3 Affectation en Classe

- **FR14** : Le systeme doit permettre d'affecter un eleve a une classe lors de l'inscription :
  - Annee scolaire (select, obligatoire - ex: 2025-2026)
  - Classe (select, obligatoire - ex: 6e A, 5e B, Tle C1)
  - Serie (select, obligatoire pour le cycle lycee : A, C, D, etc. - masque pour le college)
- **FR15** : La selection de la classe doit filtrer automatiquement les series disponibles (les series ne s'appliquent qu'a partir de la 2nde)
- **FR16** : Le systeme doit empecher l'inscription d'un eleve dans une classe qui a atteint sa capacite maximale (configurable) sauf derogation explicite de l'Admin
- **FR17** : Le systeme doit rattacher automatiquement l'eleve aux matieres et coefficients correspondant a sa classe/serie, tels que definis dans le Module Structure Academique
- **FR18** : Le systeme doit permettre de changer un eleve de classe en cours d'annee (transfert interne) avec conservation de l'historique
- **FR19** : Le systeme doit afficher la liste des eleves par classe avec effectifs

#### 2.1.4 Import en Masse (CSV/Excel)

- **FR20** : Le systeme doit permettre d'importer des eleves via un fichier CSV ou Excel avec les colonnes suivantes (minimum) :
  - Nom, Prenom, Date de naissance, Sexe, Lieu de naissance, Classe, Telephone parent, Nom parent, Prenom parent, Email parent
- **FR21** : Le systeme doit fournir un template CSV/Excel telechargeable avec les colonnes attendues et un exemple de ligne
- **FR22** : Le systeme doit afficher une previsualisation des donnees importees avant validation
- **FR23** : Le systeme doit valider les donnees importees :
  - Classe existante (code valide dans la structure academique)
  - Date de naissance valide
  - Sexe valide (M ou F)
  - Telephone parent au bon format
  - Detection de doublons (meme nom + prenom + date de naissance)
- **FR24** : Le systeme doit afficher les erreurs de validation ligne par ligne avec un message clair
- **FR25** : L'Admin doit pouvoir corriger les erreurs detectees directement dans l'interface de previsualisation
- **FR26** : Apres correction, l'Admin peut confirmer l'import
- **FR27** : Le systeme doit generer automatiquement les matricules pour tous les eleves importes
- **FR28** : Le systeme doit creer automatiquement les comptes parents (si email fourni) pour tous les eleves importes
- **FR29** : Le systeme doit affecter automatiquement les eleves a leur classe avec rattachement aux matieres/coefficients
- **FR30** : Le systeme doit afficher un recapitulatif de l'import : X eleves importes avec succes, Y comptes parents crees, Z erreurs

#### 2.1.5 Gestion des Statuts

- **FR31** : Le systeme doit permettre de definir le statut d'un eleve parmi :
  - **Actif** (par defaut a la creation)
  - **Transfere** (quitte l'etablissement pour un autre - necessite un exeat)
  - **Exclu** (definitivement inactif suite a un conseil de discipline)
  - **Diplome** (a obtenu le BEPC ou le Baccalaureat)
- **FR32** : Le systeme doit permettre de changer le statut d'un eleve avec confirmation et motif obligatoire
- **FR33** : Le systeme doit conserver l'historique des changements de statut (qui, quand, nouveau statut, motif)
- **FR34** : Le systeme doit filtrer les eleves par statut dans la liste
- **FR35** : Les eleves "Transferes", "Exclus", ou "Diplomes" ne doivent pas apparaitre dans les listes de saisie de notes ou de presences (sauf si filtre explicite)
- **FR36** : Lors d'un changement de statut vers "Transfere", le systeme doit proposer la generation automatique d'un Exeat

#### 2.1.6 Passage en Classe Superieure (Reinscription)

- **FR37** : Le systeme doit permettre la promotion en masse des eleves d'une classe a la classe superieure pour une nouvelle annee scolaire :
  - 6e → 5e, 5e → 4e, 4e → 3e (college)
  - 3e → 2nde (passage au lycee, choix de serie necessaire)
  - 2nde → 1ere, 1ere → Tle (lycee)
- **FR38** : L'Admin doit pouvoir selectionner les eleves a promouvoir (checkbox par eleve) apres le conseil de classe
- **FR39** : Le systeme doit permettre de specifier la classe cible pour chaque eleve (ex: un eleve de 6e A peut aller en 5e B)
- **FR40** : Les eleves non promus (redoublants) doivent etre re-inscrits dans la meme classe pour la nouvelle annee scolaire
- **FR41** : Le passage de 3e a 2nde doit obliger la selection d'une serie (A, C, D, etc.)
- **FR42** : Le systeme doit conserver l'historique complet du parcours de l'eleve (classes frequentees par annee scolaire)
- **FR43** : Le systeme doit generer un recapitulatif : X eleves promus, Y redoublants, Z transferes/exclus

#### 2.1.7 Documents Administratifs

- **FR44** : Le systeme doit permettre de generer un **Certificat de scolarite** pour un eleve (PDF) attestant de son inscription
- **FR45** : Le systeme doit permettre de generer un **Exeat** pour un eleve transfere (PDF) - document obligatoire pour l'inscription dans un autre etablissement
- **FR46** : Le systeme doit permettre de generer une **Attestation d'inscription** pour un eleve (PDF)
- **FR47** : Chaque document genere doit porter un numero unique, la date de generation, et etre archivable

#### 2.1.8 Consultation et Recherche

- **FR48** : Le systeme doit afficher une liste de tous les eleves avec pagination (20 par page par defaut)
- **FR49** : Le systeme doit permettre de rechercher un eleve par :
  - Nom ou Prenom (recherche partielle)
  - Matricule (recherche exacte)
  - Nom du parent/tuteur
- **FR50** : Le systeme doit permettre de filtrer les eleves par :
  - Annee scolaire
  - Cycle (College, Lycee)
  - Classe
  - Serie (lycee uniquement)
  - Statut (Actif, Transfere, Exclu, Diplome)
  - Sexe
- **FR51** : Le systeme doit permettre de trier la liste par : Nom, Prenom, Matricule, Classe, Date d'inscription
- **FR52** : Le systeme doit afficher une fiche detaillee d'un eleve avec :
  - Donnees personnelles et photo
  - Informations du/des parent(s)/tuteur(s)
  - Classe actuelle et serie (si lycee)
  - Historique du parcours scolaire (classes precedentes)
  - Statut actuel et historique des changements
  - Liens rapides vers les notes, bulletins et absences de l'eleve

#### 2.1.9 Export et Rapports

- **FR53** : Le systeme doit permettre d'exporter la liste des eleves en CSV ou Excel
- **FR54** : L'export doit inclure : Matricule, Nom, Prenom, Date de naissance, Sexe, Classe, Serie, Nom parent, Telephone parent, Statut
- **FR55** : Le systeme doit permettre de generer un rapport d'effectifs par classe (combien d'eleves inscrits) avec repartition garcons/filles
- **FR56** : Le systeme doit permettre de generer la liste de classe officielle (PDF) pour affichage ou usage administratif
- **FR57** : Le systeme doit permettre d'exporter un listing des comptes parents crees avec identifiants

---

### 2.2 Non-Functional Requirements (NFR)

- **NFR1** : Le systeme doit supporter jusqu'a 3 000 eleves par tenant sans degradation de performance
- **NFR2** : L'affichage de la liste des eleves (avec filtres et pagination) doit se faire en moins de 1 seconde
- **NFR3** : L'import de 500 eleves via CSV/Excel doit se faire en moins de 60 secondes (incluant creation des comptes parents)
- **NFR4** : Le systeme doit empecher la creation de doublons (meme nom + prenom + date de naissance) avec validation backend + frontend
- **NFR5** : Les donnees personnelles des eleves (mineurs) doivent etre protegees avec un niveau de securite renforce (acces Admin et Parent uniquement)
- **NFR6** : Le systeme doit supporter l'upload de photos jusqu'a 2 MB avec redimensionnement automatique si > 500 KB
- **NFR7** : Le temps de creation d'un eleve (admin + parent + classe) doit etre < 5 minutes (vs 15-20 minutes manuellement)
- **NFR8** : La generation d'un document administratif (exeat, certificat) doit prendre < 5 secondes
- **NFR9** : Le systeme doit etre optimise pour fonctionner sur des connexions a bande passante limitee (3G)

---

## 3. User Personas

### 3.1 Persona 1 : SuperAdmin (Gestionnaire de Plateforme)

**Role dans le module Inscriptions** : N'intervient pas directement dans les inscriptions. Son role est de creer les tenants et les comptes Admin pour chaque etablissement. Il peut consulter les statistiques globales d'inscription par tenant.

### 3.2 Persona 2 : Admin / Directeur (Gestionnaire d'Etablissement)

**Profil** : Proviseur (lycee), Principal (college), Directeur des etudes, Censeur. Niveau technique intermediaire.

**Role dans le module Inscriptions** : Acteur principal. Il inscrit les eleves, saisit les informations parentales, affecte les eleves aux classes, gere les imports en masse, les statuts, et les passages en classe superieure. Il genere les documents administratifs (exeat, certificats).

**Objectifs** :
- Inscrire rapidement les eleves en debut d'annee (objectif : 500 eleves en 1 journee via import CSV)
- Avoir une vue claire des effectifs par classe
- Gerer les mouvements en cours d'annee (transferts, exclusions)
- Produire les documents administratifs a la demande
- S'assurer que chaque eleve a un parent/tuteur identifie

### 3.3 Persona 3 : Enseignant

**Profil** : Professeur permanent, vacataire, contractuel. Niveau technique variable.

**Role dans le module Inscriptions** : Consultation uniquement. L'enseignant peut consulter la liste des eleves de ses classes et leurs informations de base. Il ne peut ni inscrire, ni modifier les donnees des eleves.

**Objectifs** :
- Voir la liste des eleves de chaque classe ou il enseigne
- Acceder aux informations de contact du parent/tuteur en cas de besoin
- Verifier l'effectif de ses classes

### 3.4 Persona 4 : Eleve

**Profil** : Eleve inscrit (6e a Terminale), age 11-20 ans. Acces principalement via smartphone.

**Role dans le module Inscriptions** : Consultation de ses propres donnees uniquement. L'eleve peut voir ses informations personnelles, sa classe, son matricule, mais ne peut rien modifier.

**Objectifs** :
- Consulter ses informations personnelles
- Connaitre son numero de matricule
- Telecharger son attestation d'inscription

### 3.5 Persona 5 : Parent / Tuteur

**Profil** : Parent d'eleve, tuteur legal. Niveau technique basique (smartphone). Tres implique dans le suivi scolaire.

**Role dans le module Inscriptions** : Consultation des informations de son/ses enfant(s). Le compte parent est cree automatiquement lors de l'inscription de l'eleve. Un parent peut etre lie a plusieurs enfants.

**Objectifs** :
- Verifier que les informations d'inscription de son enfant sont correctes
- Acceder aux informations de scolarite de son/ses enfant(s)
- Recevoir les documents administratifs (certificat de scolarite, bulletins)

### 3.6 Persona 6 : Comptable / Intendant

**Profil** : Intendant, econome, caissier. Niveau technique intermediaire.

**Role dans le module Inscriptions** : Consultation des listes d'eleves inscrits pour le suivi des frais de scolarite. Ne peut pas modifier les inscriptions. A besoin de la liste des eleves inscrits par classe pour la facturation.

**Objectifs** :
- Acceder aux listes d'eleves inscrits pour generer les factures
- Verifier le statut d'un eleve (actif vs transfere/exclu) pour le suivi financier

### 3.7 Persona 7 : Surveillant General

**Profil** : Responsable de la discipline et du suivi des absences. Niveau technique intermediaire.

**Role dans le module Inscriptions** : Consultation des listes d'eleves et des informations parentales (pour contacter les parents en cas de probleme de discipline ou d'absences repetees).

**Objectifs** :
- Acceder rapidement aux coordonnees du parent/tuteur d'un eleve
- Consulter les listes de classe pour la gestion des absences
- Verifier le statut d'un eleve

---

## 4. User Interface Design Goals

### 4.1 Overall UX Vision

L'interface du Module Inscriptions doit etre **simple, rapide, et guidee**. L'Admin doit pouvoir inscrire un eleve en quelques clics, avec un workflow en 3 etapes (Eleve → Parent → Classe).

**Principes cles** :
- **Workflow en etapes** : Inscription eleve (Etape 1) → Informations parent/tuteur (Etape 2) → Affectation classe (Etape 3)
- **Validation en temps reel** : Verification doublons, format date de naissance, detection parent existant
- **Import en masse facilite** : Previsualisation avec correction inline des erreurs
- **Recherche performante** : Filtres multiples pour retrouver rapidement un eleve
- **Creation automatique du compte parent** : Transparente pour l'Admin

### 4.2 Key Interaction Paradigms

- **Formulaire en 3 etapes** : Stepper (Etape 1 : Eleve → Etape 2 : Parent/Tuteur → Etape 3 : Classe)
- **Import CSV/Excel avec previsualisation** : Upload → Previsualisation → Correction erreurs → Confirmation
- **Passage en classe superieure** : Selection classe source → Selection eleves a promouvoir → Classe cible → Confirmation
- **Filtrage multi-criteres** : Filtres empilables avec compteur de resultats

### 4.3 Core Screens and Views

#### 4.3.1 Ecran Admin : Liste des Eleves

- Tableau avec colonnes : Photo (miniature), Matricule, Nom, Prenom, Classe, Serie, Sexe, Telephone parent, Statut (badge), Actions (Voir, Modifier, Supprimer)
- Boutons : "Inscrire un eleve", "Importer CSV/Excel", "Passage en classe superieure", "Exporter"
- Filtres : Annee scolaire, Cycle (College/Lycee), Classe, Serie, Statut, Sexe
- Recherche : Par nom, prenom, matricule, nom du parent
- Pagination : 20 eleves par page (configurable)
- Compteur : "350 eleves trouves (185 garcons, 165 filles)"

#### 4.3.2 Ecran Admin : Inscription d'un Eleve (Etape 1 : Donnees de l'Eleve)

- Formulaire avec sections :
  - **Informations Personnelles** :
    - Nom (texte, obligatoire)
    - Prenom (texte, obligatoire)
    - Date de naissance (datepicker, obligatoire)
    - Lieu de naissance (texte, optionnel)
    - Sexe (radio : M, F)
    - Nationalite (select, defaut : Nigerienne)
  - **Informations Complementaires** :
    - Telephone (texte, optionnel)
    - Adresse (textarea, optionnelle)
    - Ville (texte, defaut : Niamey)
    - Quartier (texte, optionnel)
  - **Sante** :
    - Groupe sanguin (select, optionnel)
    - Maladie ou allergie connue (textarea, optionnel)
  - **Photo** :
    - Upload photo (drag & drop ou clic pour selectionner)
    - Previsualisation de la photo uploadee
- Boutons : "Suivant (Etape 2 : Parent/Tuteur)", "Annuler"
- Message : "Le matricule sera genere automatiquement a la fin de l'inscription"
- Alerte doublon : Si nom + prenom + date de naissance correspondent a un eleve existant, avertissement affiche

#### 4.3.3 Ecran Admin : Inscription d'un Eleve (Etape 2 : Parent/Tuteur)

- Barre de recherche : "Rechercher un parent/tuteur existant (par telephone ou email)"
  - Si parent trouve : Affichage des informations existantes avec bouton "Lier cet eleve a ce parent"
  - Si parent non trouve : Formulaire de creation
- Formulaire :
  - **Pere** :
    - Nom (texte, optionnel)
    - Prenom (texte, optionnel)
    - Telephone (texte, optionnel)
    - Profession (texte, optionnel)
  - **Mere** :
    - Nom (texte, optionnel)
    - Prenom (texte, optionnel)
    - Telephone (texte, optionnel)
    - Profession (texte, optionnel)
  - **Tuteur Legal** (si different des parents) :
    - Nom (texte, optionnel)
    - Prenom (texte, optionnel)
    - Telephone (texte, obligatoire - au moins un contact requis)
    - Lien de parente (select : Pere, Mere, Tuteur, Oncle, Tante, Grand-parent, Autre)
  - **Compte Portail Parent** :
    - Email (email, optionnel - requis pour la creation du compte portail)
    - Message : "Un compte portail sera cree automatiquement pour le parent/tuteur si un email est fourni"
- Boutons : "Retour (Etape 1)", "Suivant (Etape 3 : Classe)", "Annuler"

#### 4.3.4 Ecran Admin : Inscription d'un Eleve (Etape 3 : Affectation en Classe)

- Recapitulatif des etapes precedentes :
  - Photo de l'eleve, Nom, Prenom, Date de naissance
  - Nom et telephone du parent/tuteur principal
- Formulaire :
  - Annee scolaire (select, pre-selectionne sur l'annee en cours, obligatoire)
  - Cycle (radio : College, Lycee - optionnel, filtre les classes)
  - Classe (select, obligatoire - ex: 6e A, 5e B, Tle C1)
  - Serie (select, obligatoire pour le lycee - masque pour le college)
- Information classe :
  - Effectif actuel / Capacite maximale (ex: "42/50 places occupees")
  - Professeur principal de la classe
  - Nombre de matieres et coefficients totaux
- Boutons : "Retour (Etape 2)", "Sauvegarder et Inscrire", "Annuler"
- Apres sauvegarde :
  - Matricule genere et affiche
  - Identifiants du compte parent affiches (si email fourni)
  - Bouton "Imprimer la fiche d'inscription"
  - Bouton "Inscrire un autre eleve"

#### 4.3.5 Ecran Admin : Import CSV/Excel

- **Etape 1 : Upload fichier** :
  - Zone de drag & drop pour uploader le fichier CSV ou Excel
  - Bouton "Telecharger le template CSV" et "Telecharger le template Excel"
  - Message : "Le fichier doit contenir les colonnes : Nom, Prenom, Date de naissance, Sexe, Lieu de naissance, Classe, Telephone parent, Nom parent, Prenom parent, Email parent"
- **Etape 2 : Previsualisation et validation** :
  - Tableau avec les donnees importees ligne par ligne
  - Colonnes : Nom, Prenom, Date naissance, Sexe, Classe, Parent, Statut validation (icone valide/invalide)
  - Lignes avec erreurs affichees en rouge avec message d'erreur
  - Possibilite de corriger inline (clic sur une cellule pour editer)
  - Compteur : "X lignes valides, Y lignes avec erreurs"
- **Etape 3 : Confirmation** :
  - Recapitulatif : "X eleves seront inscrits, Y comptes parents seront crees, Z erreurs"
  - Bouton "Importer X eleves" (desactive si erreurs critiques)
  - Bouton "Annuler"
- **Etape 4 : Resultat** :
  - Message de succes : "X eleves inscrits, Y comptes parents crees"
  - Bouton "Telecharger la liste des identifiants parents" (CSV)
  - Bouton "Voir les eleves importes"

#### 4.3.6 Ecran Admin : Passage en Classe Superieure

- Selection de l'annee scolaire source et de l'annee scolaire cible
- Selection de la classe source (ex: 6e A - Annee 2024-2025)
- Tableau des eleves de la classe avec colonnes :
  - Checkbox (selection)
  - Matricule, Nom, Prenom
  - Moyenne generale annuelle (si disponible depuis le Module Notes)
  - Decision conseil de classe (Admis, Redouble, Exclu) - si disponible
  - Classe cible (select : ex: 5e A, 5e B, 5e C)
- Actions groupees :
  - "Selectionner tous les Admis"
  - "Promouvoir les selectionnes" → Modal de confirmation
  - Pour le passage 3e → 2nde : Selection de serie obligatoire (A, C, D)
- Recapitulatif :
  - X eleves promus vers [classe cible]
  - Y eleves redoublants (restent dans la meme classe niveau)
  - Z eleves non traites

#### 4.3.7 Ecran Admin : Fiche Detaillee Eleve

- Sections :
  - **Donnees Personnelles** :
    - Photo, Matricule, Nom, Prenom, Date de naissance, Lieu de naissance, Sexe, Nationalite
    - Telephone, Adresse, Ville, Quartier
    - Groupe sanguin, Maladies/Allergies
  - **Parent / Tuteur** :
    - Informations pere (nom, prenom, telephone, profession)
    - Informations mere (nom, prenom, telephone, profession)
    - Informations tuteur (nom, prenom, telephone, lien de parente)
    - Lien vers le compte portail parent (si existe)
  - **Scolarite Actuelle** :
    - Annee scolaire, Classe, Serie (si lycee)
    - Professeur principal
    - Date d'inscription
  - **Parcours Scolaire** :
    - Tableau historique : Annee scolaire, Classe, Serie, Decision (Admis/Redouble/-)
  - **Statut** :
    - Statut actuel (badge de couleur)
    - Bouton "Changer le statut" (modal de confirmation)
  - **Historique des Statuts** :
    - Tableau : Date, Ancien statut, Nouveau statut, Motif, Modifie par
  - **Liens Rapides** :
    - Voir les notes de l'eleve (Module Notes)
    - Voir les absences de l'eleve (Module Presences)
    - Voir les bulletins de l'eleve (Module Documents)
- Boutons : "Modifier", "Generer Certificat de Scolarite", "Generer Exeat" (si transfere), "Supprimer" (avec confirmation)

#### 4.3.8 Ecran Admin : Gestion des Statuts (Modal)

- Modal "Changer le statut de [Nom Eleve]" :
  - Statut actuel affiche (badge couleur)
  - Selection du nouveau statut (select : Actif, Transfere, Exclu, Diplome)
  - Motif (textarea, obligatoire) : Raison du changement
  - Si statut = "Transfere" :
    - Checkbox : "Generer un Exeat automatiquement"
    - Etablissement de destination (texte, optionnel)
  - Bouton "Confirmer", "Annuler"
- Confirmation : "Le statut de [Nom Eleve] a ete change de [Ancien] a [Nouveau]"

#### 4.3.9 Ecran Enseignant : Liste des Eleves (Consultation)

- Selection de la classe (parmi les classes ou l'enseignant enseigne)
- Tableau en lecture seule : Matricule, Nom, Prenom, Sexe, Telephone parent
- Compteur : "45 eleves (24 garcons, 21 filles)"
- Pas d'actions de modification possibles

#### 4.3.10 Ecran Parent : Informations de l'Enfant

- Tableau de bord avec les informations de chaque enfant lie au compte :
  - Photo, Nom, Prenom, Matricule
  - Classe actuelle, Serie
  - Statut
- Si plusieurs enfants : Navigation par onglets ou liste
- Bouton "Telecharger attestation d'inscription"

### 4.4 Accessibility

**WCAG AA** : Le systeme doit respecter les standards d'accessibilite WCAG 2.1 niveau AA :
- Navigation au clavier complete dans le formulaire d'inscription (tabs, enter)
- Labels ARIA pour les champs de formulaire
- Messages d'erreur clairs et accessibles aux lecteurs d'ecran
- Contraste de couleurs suffisant (ratio 4.5:1 minimum)

### 4.5 Branding

- Interface professionnelle et epuree
- Couleurs des statuts :
  - **Vert (#4CAF50)** : Statut "Actif", validation, succes
  - **Bleu (#2196F3)** : Actions primaires, liens, informations
  - **Orange (#FF9800)** : Statut "Transfere", avertissements
  - **Rouge (#F44336)** : Statut "Exclu", erreurs, suppression
  - **Gris (#9E9E9E)** : Statut "Diplome"

### 4.6 Target Device and Platforms

**Web Responsive** :
- Desktop (prioritaire) : Optimise pour la saisie de donnees et l'import CSV
- Tablette : Interface adaptee pour consultation et modification legere
- Mobile : Consultation uniquement (pas d'inscription/modification sur mobile) - sauf portail parent (consultation)

---

## 5. Technical Assumptions

### 5.1 Repository Structure

**Polyrepo** : Backend (Laravel) et Frontend (Next.js) dans des depots separes (architecture existante).

### 5.2 Service Architecture

**Architecture modulaire (nwidart/laravel-modules)** :
- Module Laravel : `Modules/Inscriptions/`
- Structure standard :
  - `Entities/` : Models Eloquent (Student, StudentParent, StudentEnrollment, StudentStatusHistory, StudentDocument)
  - `Http/Controllers/` : Controllers Admin, Enseignant, Parent
  - `Http/Requests/` : Form Requests pour validation
  - `Http/Resources/` : API Resources
  - `Database/Migrations/tenant/` : Migrations tenant
  - `Database/Factories/` : Factories pour tests
  - `Database/Seeders/` : Seeders
  - `Services/` : MatriculeGeneratorService, ParentAccountService, StudentImportService, DocumentGeneratorService
  - `Routes/` : admin.php, api.php

**Frontend Next.js** :
- Module : `src/modules/Inscriptions/`
- Structure en 3 couches : `admin/`, `teacher/`, `parent/`
- Services API avec `createApiClient()`
- Hooks React pour gestion de l'etat

### 5.3 Base de donnees

**Connexion** : `tenant` (base de donnees tenant dynamique)

**Tables a creer** :

```
students
├── id (bigint, PK)
├── matricule (string, unique par tenant)
├── firstname (string)
├── lastname (string)
├── birthdate (date)
├── birthplace (string, nullable)
├── sex (enum: M, F)
├── nationality (string, default: Nigerienne)
├── phone (string, nullable)
├── address (text, nullable)
├── city (string, nullable)
├── quarter (string, nullable)
├── photo (string, nullable - chemin fichier)
├── blood_group (string, nullable)
├── health_notes (text, nullable)
├── status (enum: actif, transfere, exclu, diplome, default: actif)
├── created_at, updated_at, deleted_at (soft deletes)

student_parents
├── id (bigint, PK)
├── student_id (FK → students)
├── user_id (FK → users, nullable - lien avec le compte portail parent)
├── father_lastname (string, nullable)
├── father_firstname (string, nullable)
├── father_phone (string, nullable)
├── father_profession (string, nullable)
├── mother_lastname (string, nullable)
├── mother_firstname (string, nullable)
├── mother_phone (string, nullable)
├── mother_profession (string, nullable)
├── guardian_lastname (string, nullable)
├── guardian_firstname (string, nullable)
├── guardian_phone (string, nullable)
├── guardian_relation (enum: pere, mere, tuteur, oncle, tante, grand_parent, autre, nullable)
├── guardian_address (text, nullable)
├── email (string, nullable)
├── created_at, updated_at

student_enrollments
├── id (bigint, PK)
├── student_id (FK → students)
├── school_year_id (FK → school_years)
├── class_id (FK → classes)
├── serie_id (FK → series, nullable - pour le lycee)
├── enrollment_date (date)
├── decision (enum: admis, redouble, exclu, nullable - rempli apres conseil de classe)
├── created_at, updated_at

student_status_history
├── id (bigint, PK)
├── student_id (FK → students)
├── old_status (enum: actif, transfere, exclu, diplome)
├── new_status (enum: actif, transfere, exclu, diplome)
├── reason (text)
├── destination_school (string, nullable - si transfere)
├── changed_by (FK → users)
├── changed_at (datetime)
├── created_at, updated_at

student_documents
├── id (bigint, PK)
├── student_id (FK → students)
├── type (enum: certificat_scolarite, exeat, attestation_inscription)
├── document_number (string, unique)
├── file_path (string)
├── generated_by (FK → users)
├── generated_at (datetime)
├── created_at, updated_at
```

**Relations cles** :
- `students` : table principale
- `student_parents` belongsTo `students` et optionnellement belongsTo `users` (compte portail)
- `student_enrollments` belongsTo `students`, `school_years`, `classes`, optionnellement `series`
- `student_status_history` belongsTo `students`, `users` (changed_by)
- `student_documents` belongsTo `students`, `users` (generated_by)
- Un `user` (role Parent) hasMany `students` (via student_parents)
- Utiliser **eager loading** pour eviter les N+1 queries

### 5.4 API Endpoints

#### Eleves (Admin)

```
GET    /api/admin/students                        → Liste paginee des eleves (filtres, recherche, tri)
POST   /api/admin/students                        → Creer un eleve (etape 1+2+3 en une requete ou par etapes)
GET    /api/admin/students/{id}                    → Fiche detaillee d'un eleve
PUT    /api/admin/students/{id}                    → Modifier les donnees d'un eleve
DELETE /api/admin/students/{id}                    → Supprimer un eleve (soft delete, si aucune note)
PATCH  /api/admin/students/{id}/status             → Changer le statut d'un eleve
GET    /api/admin/students/{id}/status-history     → Historique des statuts d'un eleve
GET    /api/admin/students/{id}/enrollment-history → Parcours scolaire (classes precedentes)
POST   /api/admin/students/{id}/photo              → Uploader la photo d'un eleve
DELETE /api/admin/students/{id}/photo              → Supprimer la photo d'un eleve
```

#### Parent/Tuteur (Admin)

```
GET    /api/admin/students/{id}/parents            → Informations parent/tuteur d'un eleve
POST   /api/admin/students/{id}/parents            → Creer/mettre a jour les informations parent
PUT    /api/admin/students/{studentId}/parents/{parentId} → Modifier les informations parent
GET    /api/admin/parents/search?phone=X&email=Y   → Rechercher un parent existant
POST   /api/admin/parents/{id}/link-student        → Lier un eleve supplementaire a un parent existant
```

#### Affectation en Classe (Admin)

```
POST   /api/admin/enrollments                      → Affecter un eleve a une classe
PUT    /api/admin/enrollments/{id}                  → Modifier l'affectation (changer de classe)
GET    /api/admin/classes/{classId}/students        → Liste des eleves d'une classe
```

#### Import en Masse (Admin)

```
GET    /api/admin/students/import/template          → Telecharger le template CSV/Excel
POST   /api/admin/students/import/preview           → Previsualiser les donnees du fichier
POST   /api/admin/students/import/validate          → Valider les donnees (apres corrections)
POST   /api/admin/students/import/execute           → Executer l'import
```

#### Passage en Classe Superieure (Admin)

```
GET    /api/admin/promotions/classes/{classId}      → Liste des eleves d'une classe avec decisions
POST   /api/admin/promotions                        → Promouvoir les eleves selectionnes
GET    /api/admin/promotions/summary                → Recapitulatif des promotions
```

#### Documents Administratifs (Admin)

```
POST   /api/admin/students/{id}/documents/certificat     → Generer un certificat de scolarite
POST   /api/admin/students/{id}/documents/exeat           → Generer un exeat
POST   /api/admin/students/{id}/documents/attestation     → Generer une attestation d'inscription
GET    /api/admin/students/{id}/documents                 → Liste des documents generes
GET    /api/admin/students/{id}/documents/{docId}/download → Telecharger un document
```

#### Export et Rapports (Admin)

```
GET    /api/admin/students/export                  → Exporter la liste des eleves (CSV/Excel)
GET    /api/admin/students/report/effectifs         → Rapport des effectifs par classe
GET    /api/admin/classes/{classId}/list-pdf         → Liste de classe officielle (PDF)
GET    /api/admin/parents/credentials-export        → Export identifiants parents (CSV)
```

#### Enseignant (Consultation)

```
GET    /api/teacher/classes/{classId}/students      → Liste des eleves d'une classe (lecture seule)
GET    /api/teacher/students/{id}                   → Fiche resumee d'un eleve (lecture seule)
```

#### Parent (Consultation)

```
GET    /api/parent/children                         → Liste des enfants lies au compte parent
GET    /api/parent/children/{id}                    → Fiche de l'enfant (donnees de base, classe, statut)
GET    /api/parent/children/{id}/attestation         → Telecharger attestation d'inscription
```

### 5.5 Testing Requirements

**Tests obligatoires** :
- **Tests unitaires** : Generation matricule unique, detection doublons, changement de statut, validation donnees import
- **Tests d'integration** : Workflow complet (Creation eleve → Parent → Affectation classe → Compte parent auto)
- **Tests d'import CSV** : Import avec erreurs, correction, validation, creation comptes parents
- **Tests de passage en classe superieure** : Promotion en masse, passage 3e → 2nde avec serie, redoublants
- **Tests de documents** : Generation certificat, exeat, attestation
- **Tests de cas limites** : Import 500 eleves, doublons, classes pleines, parent existant (fratrie)
- **Tests de permissions** : Verification que les enseignants ne peuvent pas modifier, que les parents n'accedent qu'a leurs enfants

**Outils** :
- Laravel : PHPUnit (`php artisan test`)
- Frontend : Jest + React Testing Library

### 5.6 Additional Technical Assumptions

- **API REST** : Endpoints suivant les conventions existantes
- **Middleware** : `['tenant', 'tenant.auth']` pour toutes les routes
- **Permissions** : Utiliser Spatie Permission pour controle d'acces :
  - `manage-students` : CRUD complet (Admin)
  - `view-students` : Lecture seule (Enseignant, Comptable, Surveillant General)
  - `view-own-children` : Parent ne voit que ses enfants (Parent)
  - `manage-promotions` : Passage en classe superieure (Admin)
  - `generate-documents` : Generation de documents (Admin)
- **Validation** : Form Requests pour toutes les saisies (StoreStudentRequest, UpdateStudentRequest, ImportStudentsRequest, PromoteStudentsRequest, ChangeStatusRequest)
- **API Resources** : Retourner toujours des Resources, jamais de models bruts (StudentResource, StudentDetailResource, ParentResource, EnrollmentResource)
- **SoftDeletes** : Utiliser sur la table `students` (historique)
- **Casts Laravel 12** : Utiliser `casts()` method sur les models
- **Format JSON** : Reponses standardisees avec `success`, `message`, `data`
- **Upload photo** : Utiliser Laravel Filesystem (local en dev, S3 en production)
- **Generation matricule** : Service dedie `MatriculeGeneratorService` avec format configurable par tenant (table `tenant_settings`)
- **Compte parent** : Service dedie `ParentAccountService` pour la creation/liaison automatique des comptes parents
- **Import** : Service dedie `StudentImportService` avec job queue pour les imports volumineux (> 100 eleves)
- **Documents** : Service dedie `DocumentGeneratorService` utilisant `barryvdh/laravel-dompdf` pour la generation PDF

---

## 6. Epic List

### Epic 1 : Inscription Administrative des Eleves
**Goal** : Permettre a l'Admin d'enregistrer les donnees personnelles des eleves avec generation automatique du matricule.

### Epic 2 : Gestion des Parents/Tuteurs et Creation de Comptes
**Goal** : Enregistrer les informations parentales et creer automatiquement les comptes portail parent.

### Epic 3 : Affectation en Classe
**Goal** : Permettre d'affecter les eleves dans les classes (6e-Tle) avec rattachement automatique aux matieres et coefficients.

### Epic 4 : Import en Masse via CSV/Excel
**Goal** : Permettre l'import de centaines d'eleves via CSV/Excel avec previsualisation, validation, et creation automatique des comptes parents.

### Epic 5 : Gestion des Statuts des Eleves
**Goal** : Permettre de gerer le cycle de vie des eleves (Actif, Transfere, Exclu, Diplome) avec historique et generation d'exeat.

### Epic 6 : Passage en Classe Superieure
**Goal** : Permettre la promotion en masse des eleves vers la classe superieure pour la nouvelle annee scolaire.

### Epic 7 : Documents Administratifs
**Goal** : Generer les documents officiels (certificat de scolarite, exeat, attestation d'inscription) au format PDF.

### Epic 8 : Consultation, Recherche, et Exports
**Goal** : Fournir des outils de recherche performants, des exports CSV/Excel, et des rapports d'effectifs.

---

## 7. Epic Details

### Epic 1 : Inscription Administrative des Eleves

**Goal detaille** : L'Admin doit pouvoir enregistrer les donnees personnelles d'un eleve (nom, prenom, date de naissance, etc.) et generer automatiquement un matricule unique. Cette epic etablit l'identite de l'eleve dans le systeme.

#### Story 1.1 : Creation d'un Eleve (Donnees Personnelles)

**As an** Admin/Directeur,
**I want** creer un eleve avec ses donnees personnelles,
**so that** je puisse l'inscrire ensuite dans une classe.

**Acceptance Criteria** :
1. Un formulaire "Inscription Eleve - Etape 1" affiche les champs :
   - Nom (texte, obligatoire)
   - Prenom (texte, obligatoire)
   - Date de naissance (datepicker, obligatoire)
   - Lieu de naissance (texte, optionnel)
   - Sexe (radio : M, F)
   - Nationalite (select, defaut : Nigerienne)
   - Telephone (texte, optionnel)
   - Adresse (textarea, optionnelle)
   - Ville (texte, defaut : Niamey)
   - Quartier (texte, optionnel)
   - Groupe sanguin (select, optionnel)
   - Maladie ou allergie connue (textarea, optionnel)
2. Si un eleve avec les memes nom + prenom + date de naissance existe deja, un avertissement s'affiche : "Un eleve avec les memes nom, prenom et date de naissance existe deja (Matricule : XXX). Voulez-vous continuer ?"
3. Je peux uploader une photo (formats : JPG, PNG, max 2 MB)
4. La photo est previsualisee apres upload
5. Un bouton "Suivant (Etape 2 : Parent/Tuteur)" me redirige vers la saisie des informations parentales
6. Un bouton "Annuler" abandonne la creation

**Dependances** : Module UsersGuard (auth Admin)

---

#### Story 1.2 : Generation Automatique du Matricule

**As a** System,
**I want** generer automatiquement un matricule unique pour chaque nouvel eleve,
**so that** chaque eleve ait un identifiant unique dans l'etablissement.

**Acceptance Criteria** :
1. Lors de la sauvegarde de l'eleve (fin Etape 3), un matricule est genere automatiquement
2. Le format du matricule est configurable par tenant (table `tenant_settings`, champ `matricule_format`)
3. Format par defaut : `YYYY-XXX` (ex: 2025-001, 2025-002, etc.)
4. Le matricule est stocke dans la table `students` (colonne `matricule`, unique par tenant)
5. Le matricule est affiche a la fin de l'inscription en lecture seule
6. Le systeme garantit l'unicite du matricule au niveau du tenant (contrainte DB + validation)
7. Le compteur de matricule s'incremente automatiquement et ne reutilise jamais un numero supprime

**Dependances** : Story 1.1

---

#### Story 1.3 : Modification des Donnees Personnelles

**As an** Admin/Directeur,
**I want** modifier les donnees personnelles d'un eleve existant,
**so that** je puisse corriger des erreurs de saisie ou mettre a jour ses informations.

**Acceptance Criteria** :
1. Sur la liste des eleves, un bouton "Modifier" est disponible sur chaque ligne
2. Le clic sur "Modifier" ouvre le formulaire de modification pre-rempli avec les donnees actuelles
3. Le matricule est affiche en lecture seule (non modifiable)
4. Je peux modifier tous les autres champs (nom, prenom, photo, etc.)
5. Le systeme verifie les doublons potentiels si le nom, prenom ou date de naissance est modifie
6. Un bouton "Sauvegarder" enregistre les modifications
7. Un message de succes s'affiche : "Les donnees de [Nom Prenom] ont ete mises a jour"

**Dependances** : Story 1.1

---

#### Story 1.4 : Upload et Gestion de la Photo Eleve

**As an** Admin/Directeur,
**I want** uploader et modifier la photo d'un eleve,
**so that** je puisse l'identifier visuellement dans les listes et documents (carte scolaire, bulletins).

**Acceptance Criteria** :
1. Une zone de drag & drop est disponible pour uploader la photo
2. Les formats acceptes : JPG, PNG (max 2 MB)
3. Si le fichier depasse 2 MB, un message d'erreur s'affiche : "La photo ne doit pas depasser 2 MB"
4. Si le fichier > 500 KB, le systeme le redimensionne automatiquement a 500x500 px
5. La photo est previsualisee apres upload
6. Un bouton "Supprimer la photo" permet de retirer la photo uploadee
7. La photo est stockee dans le systeme de fichiers (Laravel Filesystem) avec le chemin : `students/{student_id}/photo.jpg`
8. Le chemin de la photo est stocke dans la table `students` (colonne `photo`)

**Dependances** : Story 1.1

---

#### Story 1.5 : Suppression d'un Eleve

**As an** Admin/Directeur,
**I want** supprimer un eleve qui a ete inscrit par erreur,
**so that** je puisse corriger les erreurs d'inscription.

**Acceptance Criteria** :
1. Un bouton "Supprimer" est disponible sur la fiche detaillee de l'eleve
2. Si l'eleve a des notes saisies, le bouton est desactive avec un tooltip : "Impossible de supprimer cet eleve car des notes ont ete saisies"
3. Si l'eleve n'a aucune note, une modal de confirmation s'affiche : "Voulez-vous vraiment supprimer l'eleve [Nom Prenom] ? Cette action est irreversible."
4. Apres confirmation, l'eleve est supprime en soft delete
5. Le matricule n'est pas reutilise (le compteur continue)
6. Si un compte parent a ete cree et qu'aucun autre enfant n'est lie, un avertissement s'affiche

**Dependances** : Story 1.1

---

### Epic 2 : Gestion des Parents/Tuteurs et Creation de Comptes

**Goal detaille** : L'Admin doit pouvoir saisir les informations du/des parent(s)/tuteur(s) de l'eleve. Si un email est fourni, un compte portail parent est cree automatiquement. Si le parent existe deja (fratrie), le systeme propose de lier l'eleve au compte existant.

#### Story 2.1 : Saisie des Informations Parent/Tuteur

**As an** Admin/Directeur,
**I want** saisir les informations du pere, de la mere et/ou du tuteur legal d'un eleve,
**so that** l'etablissement dispose d'un contact responsable pour chaque eleve.

**Acceptance Criteria** :
1. Le formulaire "Inscription Eleve - Etape 2 : Parent/Tuteur" affiche les sections Pere, Mere, et Tuteur
2. Au moins un numero de telephone (pere, mere ou tuteur) est obligatoire
3. Chaque section contient : Nom, Prenom, Telephone, Profession
4. La section Tuteur contient en plus : Lien de parente (select)
5. Un champ Email est disponible pour la creation du compte portail parent
6. Un message informe : "Un compte portail sera cree automatiquement pour le parent/tuteur si un email est fourni"
7. Les donnees sont enregistrees dans la table `student_parents`

**Dependances** : Story 1.1

---

#### Story 2.2 : Detection de Parent Existant (Fratrie)

**As an** Admin/Directeur,
**I want** que le systeme detecte si le parent/tuteur existe deja dans le systeme,
**so that** je puisse lier le nouvel eleve au parent existant sans creer de doublon.

**Acceptance Criteria** :
1. Une barre de recherche "Rechercher un parent/tuteur existant" est disponible en haut de l'Etape 2
2. La recherche se fait par telephone ou par email
3. Si un parent est trouve, ses informations sont affichees avec un bouton "Lier cet eleve a ce parent"
4. Le clic sur "Lier" pre-remplit automatiquement le formulaire avec les informations du parent existant
5. L'eleve est lie au meme compte portail parent (user_id dans student_parents)
6. Un message confirme : "Ce parent est deja enregistre avec X autre(s) enfant(s) : [Liste noms]"

**Dependances** : Story 2.1

---

#### Story 2.3 : Creation Automatique du Compte Portail Parent

**As a** System,
**I want** creer automatiquement un compte utilisateur avec le role "Parent" lorsqu'un email est fourni,
**so that** le parent puisse se connecter au portail pour suivre la scolarite de son enfant.

**Acceptance Criteria** :
1. Si un email est fourni dans le formulaire parent et qu'aucun compte n'existe pour cet email, le systeme cree automatiquement un utilisateur dans la table `users` avec le role "Parent"
2. Le mot de passe est genere automatiquement (8 caracteres, mix alphanumerique)
3. A la fin de l'inscription (Etape 3), les identifiants sont affiches a l'ecran :
   - Email : parent@email.com
   - Mot de passe : XXXXXXXX
   - Message : "Veuillez communiquer ces identifiants au parent. Ils ne seront plus affiches."
4. Un bouton "Imprimer les identifiants" permet d'imprimer un recepisse
5. Si un email est fourni et qu'un compte existe deja, l'eleve est lie au compte existant (pas de nouveau compte)
6. Le lien parent ↔ eleve est enregistre dans la table `student_parents` (colonne `user_id`)
7. Si aucun email n'est fourni, aucun compte n'est cree (mais les informations parent sont quand meme enregistrees)

**Dependances** : Story 2.1, Module UsersGuard (creation user + role Parent)

---

#### Story 2.4 : Modification des Informations Parent/Tuteur

**As an** Admin/Directeur,
**I want** modifier les informations du parent/tuteur d'un eleve,
**so that** je puisse corriger des erreurs ou mettre a jour les coordonnees.

**Acceptance Criteria** :
1. Sur la fiche detaillee d'un eleve, une section "Parent / Tuteur" affiche les informations enregistrees
2. Un bouton "Modifier" ouvre le formulaire de modification pre-rempli
3. Je peux modifier tous les champs (telephone, adresse, etc.)
4. Si je modifie l'email et qu'un compte portail existait, le systeme met a jour l'email du compte utilisateur
5. Si j'ajoute un email alors qu'il n'y en avait pas, le systeme propose de creer un compte portail parent
6. Un bouton "Sauvegarder" enregistre les modifications

**Dependances** : Story 2.1

---

### Epic 3 : Affectation en Classe

**Goal detaille** : L'Admin doit pouvoir affecter un eleve a une classe (6e A, 3e B, Tle C1, etc.) avec rattachement automatique aux matieres et coefficients definis dans le Module Structure Academique. Pour le lycee, une serie (A, C, D) est obligatoire.

#### Story 3.1 : Affectation d'un Eleve a une Classe

**As an** Admin/Directeur,
**I want** affecter un eleve a une classe pour l'annee scolaire en cours,
**so that** il soit inclus dans les listes de classe et puisse recevoir des notes.

**Acceptance Criteria** :
1. Le formulaire "Inscription Eleve - Etape 3 : Classe" affiche :
   - Recapitulatif des Etapes 1 et 2 (nom, prenom, photo, parent)
   - Annee scolaire (select, pre-selectionne sur l'annee en cours)
   - Cycle (radio : College, Lycee - filtre les classes)
   - Classe (select : 6e A, 6e B, 5e A, etc. - filtree par cycle)
   - Serie (select : A, C, D - obligatoire si classe de lycee, masque sinon)
2. Chaque classe affiche l'effectif actuel / capacite maximale (ex: "42/50")
3. Si la classe est pleine, un avertissement s'affiche mais l'Admin peut quand meme inscrire (derogation)
4. Un bouton "Sauvegarder et Inscrire" finalise l'inscription :
   - Cree l'eleve dans la table `students`
   - Genere le matricule
   - Enregistre l'affectation dans `student_enrollments`
   - Cree le compte parent (si email fourni)
5. L'eleve est automatiquement rattache aux matieres et coefficients de sa classe/serie (via Module Structure Academique)
6. Un ecran de confirmation affiche le matricule, les identifiants parent, et les boutons d'action

**Dependances** : Story 1.1, Story 2.1, Module Structure Academique (Classes, Series, Matieres)

---

#### Story 3.2 : Changement de Classe en Cours d'Annee

**As an** Admin/Directeur,
**I want** changer un eleve de classe en cours d'annee,
**so that** je puisse gerer les reorganisations de classes ou les demandes de changement.

**Acceptance Criteria** :
1. Sur la fiche detaillee d'un eleve, un bouton "Changer de classe" est disponible
2. Une modal affiche :
   - Classe actuelle : 6e A (effectif : 45)
   - Nouvelle classe (select : liste des classes du meme cycle)
   - Motif du changement (textarea, obligatoire)
3. Si le changement implique un changement de serie (lycee), la serie cible est selectionnable
4. Un bouton "Confirmer" enregistre le changement
5. L'historique de l'ancienne affectation est conserve (mise a jour de `student_enrollments` avec nouvelle entree)
6. Les matieres et coefficients sont mis a jour selon la nouvelle classe/serie
7. Un message de succes s'affiche : "[Nom Prenom] a ete transfere de [Ancienne classe] a [Nouvelle classe]"

**Dependances** : Story 3.1

---

#### Story 3.3 : Liste des Eleves par Classe

**As an** Admin/Directeur,
**I want** voir la liste des eleves affectes a une classe,
**so that** je puisse verifier les effectifs et la composition des classes.

**Acceptance Criteria** :
1. Un ecran "Eleves par classe" affiche un select pour choisir la classe
2. Apres selection, un tableau affiche les eleves de la classe avec :
   - Matricule, Nom, Prenom, Sexe, Date de naissance, Statut
3. Un compteur affiche : "45 eleves (24 garcons, 21 filles)"
4. Le professeur principal de la classe est affiche
5. Un bouton "Exporter la liste de classe (PDF)" genere une liste officielle
6. Un bouton "Exporter (Excel)" exporte les donnees

**Dependances** : Story 3.1

---

### Epic 4 : Import en Masse via CSV/Excel

**Goal detaille** : L'Admin doit pouvoir importer des centaines d'eleves via un fichier CSV ou Excel avec previsualisation, validation, correction des erreurs, et creation automatique des comptes parents. Cette epic est essentielle pour les etablissements qui doivent inscrire 500+ eleves au debut de l'annee scolaire.

#### Story 4.1 : Telechargement du Template CSV/Excel

**As an** Admin/Directeur,
**I want** telecharger un template CSV ou Excel avec un exemple,
**so that** je sache quel format utiliser pour l'import.

**Acceptance Criteria** :
1. Deux boutons sont disponibles : "Telecharger le template CSV" et "Telecharger le template Excel"
2. Le clic telecharge un fichier avec :
   - En-tetes : Nom, Prenom, Date de naissance (JJ/MM/AAAA), Sexe (M/F), Lieu de naissance, Classe (code), Telephone parent, Nom parent, Prenom parent, Email parent
   - 2 lignes d'exemple :
     - Adamou, Moussa, 15/03/2010, M, Niamey, 6A, 90123456, Adamou, Ibrahim, ibrahim@email.com
     - Boubacar, Fatima, 22/08/2011, F, Maradi, 5B, 96789012, Boubacar, Ali, (vide)
3. Un message explique les colonnes obligatoires et optionnelles

**Dependances** : Module UsersGuard (auth Admin)

---

#### Story 4.2 : Upload et Previsualisation du Fichier

**As an** Admin/Directeur,
**I want** uploader un fichier CSV/Excel et previsualiser les donnees avant l'import,
**so that** je puisse verifier que tout est correct avant de creer les eleves.

**Acceptance Criteria** :
1. Une zone de drag & drop permet d'uploader un fichier CSV ou Excel (.csv, .xlsx, .xls)
2. Apres upload, le systeme affiche un tableau de previsualisation avec les donnees importees ligne par ligne
3. Les lignes valides affichent une icone de validation (vert)
4. Les lignes avec erreurs affichent une icone d'erreur (rouge) avec un message
5. Un compteur affiche : "X lignes valides, Y lignes avec erreurs"
6. Un bouton "Suivant (Corriger les erreurs)" est disponible si Y > 0
7. Un bouton "Importer X eleves" est disponible si Y = 0

**Dependances** : Story 4.1

---

#### Story 4.3 : Validation des Donnees Importees

**As a** System,
**I want** valider les donnees importees avant l'import,
**so that** je detecte les erreurs et les affiche a l'Admin.

**Acceptance Criteria** :
1. Le systeme valide chaque ligne du fichier :
   - **Nom et Prenom** : Non vides
   - **Classe existante** : Verifie que le code classe existe dans la structure academique
   - **Date de naissance valide** : Verifie le format (JJ/MM/AAAA) et que la date est realiste (entre 2000 et aujourd'hui)
   - **Sexe valide** : M ou F
   - **Telephone parent** : Format valide (8 chiffres pour Niger)
   - **Doublon interne** : Meme nom + prenom + date de naissance dans le fichier
   - **Doublon en base** : Meme nom + prenom + date de naissance deja existant dans la base
   - **Email parent** : Format valide si fourni
2. Les erreurs detectees sont affichees ligne par ligne avec un message clair
3. Si plusieurs erreurs sur la meme ligne, toutes sont affichees
4. Les doublons potentiels sont signales comme avertissements (pas erreurs bloquantes)

**Dependances** : Story 4.2

---

#### Story 4.4 : Correction Inline des Erreurs

**As an** Admin/Directeur,
**I want** corriger les erreurs detectees directement dans l'interface de previsualisation,
**so that** je n'aie pas a refaire l'upload apres correction dans Excel.

**Acceptance Criteria** :
1. Les cellules avec erreurs sont affichees en rouge et sont editables
2. Je peux modifier le contenu d'une cellule erronee
3. Apres modification, le systeme revalide la ligne en temps reel
4. Si la ligne devient valide, l'icone d'erreur devient une icone de validation
5. Le compteur se met a jour en temps reel
6. Un bouton "Reinitialiser" permet de recharger le fichier original

**Dependances** : Story 4.3

---

#### Story 4.5 : Import Final des Eleves

**As an** Admin/Directeur,
**I want** confirmer l'import des eleves apres correction des erreurs,
**so that** les eleves soient crees dans le systeme avec leurs affectations et comptes parents.

**Acceptance Criteria** :
1. Si toutes les lignes sont valides, un bouton "Importer X eleves" est active
2. Le clic affiche une modal de confirmation : "Vous allez importer X eleves et creer Y comptes parents. Confirmer ?"
3. Apres confirmation, le systeme cree les eleves avec :
   - Donnees personnelles
   - Generation automatique des matricules
   - Enregistrement des informations parent/tuteur
   - Creation automatique des comptes parents (si email fourni)
   - Affectation a la classe
   - Rattachement aux matieres/coefficients
4. Un message de succes affiche : "X eleves inscrits, Y comptes parents crees"
5. Un bouton "Telecharger la liste des identifiants parents" genere un CSV avec email/mot de passe
6. Un bouton "Voir les eleves importes" affiche la liste filtree
7. L'import utilise une queue job si > 100 eleves (execution asynchrone avec barre de progression)

**Dependances** : Story 4.4, Story 2.3

---

### Epic 5 : Gestion des Statuts des Eleves

**Goal detaille** : L'Admin doit pouvoir gerer le cycle de vie des eleves en changeant leur statut (Actif, Transfere, Exclu, Diplome) avec historique des changements et generation automatique d'exeat pour les transferts.

#### Story 5.1 : Changement de Statut d'un Eleve

**As an** Admin/Directeur,
**I want** changer le statut d'un eleve,
**so that** je puisse gerer les transferts, exclusions, et diplomes.

**Acceptance Criteria** :
1. Sur la fiche detaillee d'un eleve, le statut actuel est affiche (badge de couleur)
2. Un bouton "Changer le statut" ouvre une modal
3. La modal affiche :
   - Statut actuel : Actif (badge vert)
   - Selection du nouveau statut (select : Actif, Transfere, Exclu, Diplome)
   - Motif (textarea, obligatoire) : "Raison du changement"
   - Si statut = "Transfere" :
     - Etablissement de destination (texte, optionnel)
     - Checkbox : "Generer un Exeat automatiquement" (cochee par defaut)
4. Un bouton "Confirmer" enregistre le changement
5. Le systeme enregistre l'historique dans la table `student_status_history`
6. Un message de succes s'affiche : "Le statut de [Nom Prenom] a ete change de [Ancien] a [Nouveau]"
7. Si "Generer un Exeat" est coche, un PDF est genere et stocke dans `student_documents`

**Dependances** : Story 1.1

---

#### Story 5.2 : Filtrage par Statut

**As an** Admin/Directeur,
**I want** filtrer les eleves par statut,
**so that** je puisse voir uniquement les eleves actifs, transferes, exclus, ou diplomes.

**Acceptance Criteria** :
1. Un filtre "Statut" est disponible sur la liste des eleves (multi-select : Actif, Transfere, Exclu, Diplome)
2. Par defaut, seuls les eleves "Actifs" sont affiches
3. Je peux cocher/decocher les statuts pour afficher plusieurs categories simultanement
4. Un badge de couleur affiche le statut de chaque eleve dans la liste :
   - Actif : Vert
   - Transfere : Orange
   - Exclu : Rouge
   - Diplome : Gris
5. Le compteur affiche : "X eleves trouves (Y actifs, Z transferes, ...)"

**Dependances** : Story 5.1

---

#### Story 5.3 : Historique des Changements de Statut

**As an** Admin/Directeur,
**I want** consulter l'historique des changements de statut d'un eleve,
**so that** je puisse tracer les modifications et comprendre le parcours de l'eleve.

**Acceptance Criteria** :
1. Sur la fiche detaillee d'un eleve, une section "Historique des Statuts" affiche un tableau :
   - Colonnes : Date, Ancien statut, Nouveau statut, Motif, Etablissement destination (si transfere), Modifie par
2. Les lignes sont triees par date decroissante (plus recent en haut)
3. Si aucun changement de statut, un message s'affiche : "Aucun changement de statut enregistre"

**Dependances** : Story 5.1

---

### Epic 6 : Passage en Classe Superieure

**Goal detaille** : En fin d'annee scolaire, apres les conseils de classe, l'Admin doit pouvoir promouvoir en masse les eleves vers la classe superieure pour la nouvelle annee scolaire. Les redoublants sont re-inscrits au meme niveau. Le passage de 3e a 2nde necessite le choix d'une serie.

#### Story 6.1 : Selection des Eleves a Promouvoir

**As an** Admin/Directeur,
**I want** voir la liste des eleves d'une classe avec les decisions du conseil de classe,
**so that** je puisse selectionner ceux qui passent en classe superieure.

**Acceptance Criteria** :
1. Un ecran "Passage en classe superieure" affiche :
   - Annee scolaire source (select) et annee scolaire cible (select)
   - Classe source (select : ex: 6e A)
2. Un tableau affiche les eleves de la classe avec :
   - Checkbox (selection)
   - Matricule, Nom, Prenom
   - Moyenne generale annuelle (si disponible)
   - Decision conseil de classe (si disponible)
3. Des boutons d'action groupee :
   - "Selectionner tous les Admis"
   - "Tout selectionner"
   - "Tout deselectionner"
4. Un select "Classe cible" par defaut (ex: 5e A pour les 6e A)
5. Possibilite de changer la classe cible individuellement (select par ligne)

**Dependances** : Module Structure Academique (Annees scolaires, Classes)

---

#### Story 6.2 : Promotion en Masse

**As an** Admin/Directeur,
**I want** promouvoir les eleves selectionnes vers la classe cible,
**so that** ils soient inscrits pour la nouvelle annee scolaire.

**Acceptance Criteria** :
1. Un bouton "Promouvoir les X eleves selectionnes" affiche un recapitulatif :
   - X eleves promus vers [classe cible]
   - Annee scolaire cible : 2026-2027
2. Un bouton "Confirmer" cree les nouvelles inscriptions (`student_enrollments`) pour chaque eleve promu
3. Les anciennes inscriptions sont conservees (historique du parcours)
4. Un message de succes affiche : "X eleves promus avec succes de [Classe source] vers [Classe cible]"
5. Les eleves non selectionnes restent dans leur classe actuelle (redoublants possibles)

**Dependances** : Story 6.1

---

#### Story 6.3 : Passage 3e → 2nde avec Selection de Serie

**As an** Admin/Directeur,
**I want** affecter une serie aux eleves de 3e qui passent en 2nde,
**so that** ils soient inscrits dans la bonne serie au lycee.

**Acceptance Criteria** :
1. Lorsque la classe source est une classe de 3e, un champ "Serie" (select : A, C, D, etc.) apparait pour chaque eleve
2. La serie est obligatoire pour la promotion en 2nde
3. Le systeme cree les inscriptions avec la serie selectionnee
4. Si des eleves ont des series differentes, ils peuvent etre repartis dans des classes cibles differentes (ex: 2nde A1, 2nde C1)

**Dependances** : Story 6.2, Module Structure Academique (Series)

---

#### Story 6.4 : Gestion des Redoublants

**As an** Admin/Directeur,
**I want** re-inscrire les redoublants dans une classe du meme niveau pour la nouvelle annee scolaire,
**so that** ils apparaissent dans les listes de classe de la nouvelle annee.

**Acceptance Criteria** :
1. Les eleves non promus (non selectionnes dans la promotion) sont identifies comme "non traites"
2. Un ecran affiche les eleves non traites avec un select "Classe cible" (meme niveau)
3. Un bouton "Reinscrire les redoublants" cree les inscriptions pour la nouvelle annee
4. Le parcours scolaire de l'eleve reflete le redoublement (deux inscriptions dans le meme niveau sur des annees differentes)

**Dependances** : Story 6.2

---

#### Story 6.5 : Recapitulatif des Promotions

**As an** Admin/Directeur,
**I want** voir un recapitulatif global des promotions pour toutes les classes,
**so that** je puisse verifier que tous les eleves ont ete traites.

**Acceptance Criteria** :
1. Un ecran "Recapitulatif des promotions" affiche pour chaque classe :
   - Classe source : 6e A
   - Effectif total : 45
   - Promus : 38
   - Redoublants : 5
   - Transferes/Exclus : 2
   - Non traites : 0
2. Une alerte s'affiche si des eleves sont "non traites"
3. Un bouton "Exporter le recapitulatif (PDF)" genere un rapport

**Dependances** : Story 6.2, Story 6.4

---

### Epic 7 : Documents Administratifs

**Goal detaille** : L'Admin doit pouvoir generer des documents administratifs officiels (certificat de scolarite, exeat, attestation d'inscription) au format PDF pour chaque eleve.

#### Story 7.1 : Generation du Certificat de Scolarite

**As an** Admin/Directeur,
**I want** generer un certificat de scolarite pour un eleve,
**so that** l'eleve puisse justifier de son inscription aupres de tiers.

**Acceptance Criteria** :
1. Sur la fiche detaillee d'un eleve, un bouton "Generer Certificat de Scolarite" est disponible
2. Le clic genere un PDF avec :
   - En-tete de l'etablissement (nom, adresse, logo si configure)
   - Numero du document (auto-genere, unique)
   - "Certifie que l'eleve [Nom Prenom], ne(e) le [Date] a [Lieu], de matricule [Matricule], est regulierement inscrit(e) en classe de [Classe] pour l'annee scolaire [Annee]"
   - Date de generation
   - Espace pour signature et cachet
3. Le document est stocke dans `student_documents` avec son numero unique
4. Le PDF est telecharge automatiquement

**Dependances** : Story 1.1, Module Structure Academique

---

#### Story 7.2 : Generation de l'Exeat

**As an** Admin/Directeur,
**I want** generer un exeat pour un eleve transfere,
**so that** l'eleve puisse s'inscrire dans un autre etablissement.

**Acceptance Criteria** :
1. L'exeat peut etre genere de deux facons :
   - Automatiquement lors d'un changement de statut vers "Transfere" (si checkbox cochee)
   - Manuellement via le bouton "Generer Exeat" sur la fiche de l'eleve
2. Le PDF contient :
   - En-tete de l'etablissement
   - Numero du document (unique)
   - "Certifie que l'eleve [Nom Prenom], ne(e) le [Date] a [Lieu], de matricule [Matricule], inscrit(e) en classe de [Classe], quitte l'etablissement a compter du [Date] pour [Motif]."
   - Etablissement de destination (si renseigne)
   - Date de generation
   - Espace pour signature et cachet
3. Le document est archive dans `student_documents`

**Dependances** : Story 5.1

---

#### Story 7.3 : Generation de l'Attestation d'Inscription

**As an** Admin/Directeur,
**I want** generer une attestation d'inscription pour un eleve,
**so that** l'eleve ou son parent puisse justifier de l'inscription en cours d'annee.

**Acceptance Criteria** :
1. Un bouton "Generer Attestation d'Inscription" est disponible sur la fiche de l'eleve
2. Le PDF contient :
   - En-tete de l'etablissement
   - Numero du document (unique)
   - "Atteste que l'eleve [Nom Prenom], ne(e) le [Date] a [Lieu], de matricule [Matricule], est inscrit(e) en classe de [Classe] [Serie] pour l'annee scolaire [Annee]."
   - Date de generation
   - Espace pour signature et cachet
3. Aussi accessible depuis le portail parent (consultation)
4. Le document est archive dans `student_documents`

**Dependances** : Story 1.1

---

### Epic 8 : Consultation, Recherche, et Exports

**Goal detaille** : Fournir des outils de recherche performants, des exports CSV/Excel, et des rapports d'effectifs pour faciliter la consultation et la gestion administrative.

#### Story 8.1 : Liste des Eleves avec Filtres Multiples

**As an** Admin/Directeur,
**I want** filtrer et rechercher les eleves selon plusieurs criteres,
**so that** je puisse retrouver rapidement un eleve ou un groupe d'eleves.

**Acceptance Criteria** :
1. Des filtres sont disponibles au-dessus de la liste des eleves :
   - Annee scolaire (select)
   - Cycle (select : College, Lycee, Tous)
   - Classe (select, filtree par cycle)
   - Serie (select, filtree par classe - lycee uniquement)
   - Statut (multi-select : Actif, Transfere, Exclu, Diplome)
   - Sexe (select : M, F, Tous)
2. Une recherche par texte est disponible (recherche dans : Nom, Prenom, Matricule, Nom parent)
3. Les filtres sont cumulables (AND logic)
4. Un bouton "Reinitialiser filtres" efface tous les filtres et la recherche
5. Le nombre d'eleves affiches est visible : "350 eleves trouves (185 garcons, 165 filles)"
6. La liste est paginee (20 eleves par page par defaut, configurable)

**Dependances** : Story 1.1

---

#### Story 8.2 : Tri de la Liste des Eleves

**As an** Admin/Directeur,
**I want** trier la liste des eleves par differents criteres,
**so that** je puisse organiser l'affichage selon mes besoins.

**Acceptance Criteria** :
1. Les colonnes du tableau sont triables par clic sur l'en-tete :
   - Matricule (croissant/decroissant)
   - Nom (alphabetique A-Z ou Z-A)
   - Prenom (alphabetique A-Z ou Z-A)
   - Classe (ordre logique : 6e, 5e, 4e, 3e, 2nde, 1ere, Tle)
   - Date d'inscription (plus recent ou plus ancien)
2. Une fleche indique le sens du tri actif
3. Le tri est conserve lors de la pagination

**Dependances** : Story 8.1

---

#### Story 8.3 : Fiche Detaillee d'un Eleve

**As an** Admin/Directeur,
**I want** consulter la fiche detaillee d'un eleve,
**so that** j'aie acces a toutes ses informations en un seul endroit.

**Acceptance Criteria** :
1. Le clic sur une ligne d'eleve dans la liste ouvre sa fiche detaillee
2. La fiche affiche toutes les sections decrites dans l'ecran 4.3.7 :
   - Donnees personnelles et photo
   - Informations parent/tuteur
   - Scolarite actuelle (classe, serie, annee)
   - Parcours scolaire (historique)
   - Statut et historique des statuts
   - Liens rapides vers notes, absences, bulletins
3. Des boutons d'action sont disponibles : "Modifier", "Changer de classe", "Changer le statut", "Generer certificat", "Generer exeat", "Supprimer"

**Dependances** : Story 1.1, Story 2.1, Story 3.1, Story 5.1

---

#### Story 8.4 : Export CSV/Excel de la Liste des Eleves

**As an** Admin/Directeur,
**I want** exporter la liste des eleves en CSV ou Excel,
**so that** je puisse analyser les donnees dans un tableur ou produire des rapports.

**Acceptance Criteria** :
1. Un bouton "Exporter" est disponible au-dessus de la liste des eleves
2. Une modal propose le choix du format : CSV ou Excel
3. L'export prend en compte les filtres actifs (seuls les eleves filtres sont exportes)
4. Le fichier exporte contient les colonnes : Matricule, Nom, Prenom, Date de naissance, Sexe, Classe, Serie, Nom parent, Telephone parent, Statut
5. Le nom du fichier suit le format : `eleves_[classe]_[date].csv` (ex: `eleves_6eA_2026-03-16.csv`)
6. Le telechargement demarre automatiquement apres selection du format

**Dependances** : Story 8.1

---

#### Story 8.5 : Rapport des Effectifs par Classe

**As an** Admin/Directeur,
**I want** generer un rapport des effectifs par classe,
**so that** je puisse voir la repartition des eleves par classe et par sexe.

**Acceptance Criteria** :
1. Un ecran "Rapport des Effectifs" affiche un tableau recapitulatif :
   - Lignes : Classes (groupees par cycle)
   - Colonnes : Garcons, Filles, Total, Capacite maximale, Taux de remplissage
2. Exemple :
   ```
   COLLEGE
   Classe  | Garcons | Filles | Total | Capacite | Taux
   --------+---------+--------+-------+----------+-----
   6e A    | 24      | 21     | 45    | 50       | 90%
   6e B    | 22      | 23     | 45    | 50       | 90%
   5e A    | 25      | 20     | 45    | 50       | 90%
   ...

   LYCEE
   Classe  | Serie | Garcons | Filles | Total | Capacite | Taux
   --------+-------+---------+--------+-------+----------+-----
   2nde A1 | A     | 15      | 30     | 45    | 50       | 90%
   2nde C1 | C     | 28      | 12     | 40    | 45       | 89%
   Tle D1  | D     | 20      | 18     | 38    | 45       | 84%
   ...

   TOTAL GENERAL : 650 eleves (340 garcons, 310 filles)
   ```
3. Un filtre "Annee scolaire" permet de voir les effectifs d'annees precedentes
4. Un bouton "Exporter en PDF" genere un rapport officiel
5. Un bouton "Exporter en Excel" exporte les donnees

**Dependances** : Story 8.1

---

#### Story 8.6 : Liste de Classe Officielle (PDF)

**As an** Admin/Directeur,
**I want** generer la liste de classe officielle au format PDF,
**so that** je puisse l'imprimer pour l'affichage ou la distribuer aux enseignants.

**Acceptance Criteria** :
1. Un bouton "Imprimer la liste de classe" est disponible sur l'ecran des eleves par classe
2. Le PDF genere contient :
   - En-tete de l'etablissement
   - Annee scolaire, Classe, Serie (si lycee), Professeur principal
   - Tableau : N (rang), Matricule, Nom, Prenom, Date de naissance, Sexe
   - Eleves tries par ordre alphabetique (Nom, Prenom)
   - Pied de page : Effectif total, Garcons, Filles
3. Le format est adapte pour l'impression A4

**Dependances** : Story 3.3

---

#### Story 8.7 : Export des Identifiants Parents

**As an** Admin/Directeur,
**I want** exporter la liste des comptes parents crees avec leurs identifiants,
**so that** je puisse distribuer les codes d'acces aux parents lors des reunions APE.

**Acceptance Criteria** :
1. Un bouton "Exporter les identifiants parents" est disponible
2. Le fichier CSV contient : Nom eleve, Prenom eleve, Classe, Nom parent, Email parent, Mot de passe initial
3. Un avertissement s'affiche : "Ce fichier contient des informations sensibles. Veuillez le traiter avec confidentialite."
4. L'export peut etre filtre par classe

**Dependances** : Story 2.3

---

## 8. Checklist Results Report

**A completer apres implementation** : Executer le checklist PM pour valider :
- [ ] Tous les requirements fonctionnels sont implementes
- [ ] Inscription administrative + parent + classe < 5 minutes
- [ ] Import CSV/Excel de 500 eleves < 60 secondes
- [ ] Generation matricule automatique et unique
- [ ] Creation automatique du compte parent fonctionnelle
- [ ] Detection parent existant (fratrie) operationnelle
- [ ] Gestion des statuts avec historique fonctionne
- [ ] Generation exeat automatique lors d'un transfert
- [ ] Passage en classe superieure en masse operationnel
- [ ] Passage 3e → 2nde avec selection de serie fonctionnel
- [ ] Generation documents PDF (certificat, exeat, attestation) fonctionnelle
- [ ] Filtres et recherche performants (< 1 seconde)
- [ ] Export CSV/Excel fonctionnel
- [ ] Rapport des effectifs par classe avec repartition garcons/filles
- [ ] Liste de classe officielle en PDF
- [ ] Permissions appliquees (Admin : CRUD, Enseignant : lecture seule, Parent : ses enfants uniquement)
- [ ] Protection des donnees de mineurs
- [ ] Interface responsive et accessible (WCAG AA)
- [ ] Tests unitaires et d'integration passes

---

## 9. Next Steps

### 9.1 UX Expert Prompt

> "Creez les maquettes UI pour le Module Inscriptions en vous basant sur ce PRD. Focus sur les ecrans critiques : Formulaire d'inscription en 3 etapes (Eleve → Parent/Tuteur → Classe), Import CSV/Excel avec previsualisation et correction inline, Passage en classe superieure avec selection d'eleves, Liste des eleves avec filtres multiples, Fiche detaillee eleve. Assurez l'accessibilite WCAG AA et le responsive design. Contexte : colleges et lycees au Niger."

### 9.2 Architect Prompt

> "Concevez l'architecture technique du Module Inscriptions en suivant les patterns etablis dans le module UsersGuard et StructureAcademique. Definissez les tables de base de donnees (students, student_parents, student_enrollments, student_status_history, student_documents), les models Eloquent avec relations (eager loading), les controllers avec validation des doublons, les services dedies (MatriculeGeneratorService, ParentAccountService, StudentImportService, DocumentGeneratorService), l'import CSV/Excel avec validation et queue job, et les tests unitaires/integration. L'architecture doit supporter la creation automatique des comptes parents, la detection de fratrie, le passage en classe superieure en masse, et la generation de documents PDF (exeat, certificat, attestation)."

---

**Document cree par** : John (Product Manager Agent)
**Date** : 2026-03-16
**Version** : v5
**Statut** : Draft pour review
