# PRD - Module Documents Officiels

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Module** : Documents Officiels
> **Version** : v5
> **Date** : 2026-03-16
> **Phase** : Phase 1 - MVP Core
> **Priorite** : CRITIQUE

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 5.0 | Refonte complete - Passage du LMD (superieur) au secondaire (colleges/lycees Niger). Bulletins semestriels/annuels, attestations, certificats, cartes scolaires, recus de paiement, bulletins de paie | John (PM) |
| 2026-01-07 | 1.0 | Creation initiale du PRD Module Documents Officiels (systeme LMD) | John (PM) |

---

## 1. Goals and Background Context

### 1.1 Goals

- **Automatiser la generation des bulletins semestriels** : Generer des bulletins PDF professionnels avec notes, moyennes, rang, appreciations, mentions et decisions -- en masse (toute une classe en un clic) ou individuellement
- **Generer les bulletins annuels** : Produire un recapitulatif des 2 semestres avec la decision finale de passage/redoublement
- **Fournir les documents administratifs** : Attestations de scolarite, attestations d'inscription, certificats de scolarite/exeat pour les transferts
- **Generer les cartes scolaires** : Production de cartes d'identite scolaire avec photo de l'eleve
- **Generer les releves de notes annuels** : Document recapitulatif de toutes les notes de l'annee scolaire
- **Automatiser les documents financiers** : Recus de paiement et bulletins de paie generes automatiquement
- **Garantir la qualite professionnelle** : Templates avec logos, mise en page soignee, signatures, tampons
- **Permettre la generation en masse** : Generer 60+ bulletins d'une classe en une seule operation (< 5 minutes)
- **Assurer la tracabilite** : Historique de tous les documents generes (qui, quand, pour qui)
- **Permettre la reimpression** : Acces aux documents generes precedemment pour reimpression

### 1.2 Background Context

Le **Module Documents Officiels** est le **differenciateur cle** de Gestion Scolaire. C'est la fonctionnalite qui impressionne le plus les etablissements pilotes et resout leur pain point numero un : la production manuelle des bulletins.

Ce module s'inscrit dans la **Phase 1 MVP Core** car il est l'**aboutissement du cycle academique** :
1. La structure academique est configuree (Module Structure Academique)
2. Les eleves sont inscrits et affectes aux classes (Module Inscriptions)
3. Les notes sont saisies et validees (Module Notes & Evaluations)
4. Le conseil de classe a statue (Module Conseil de Classe)
5. **Les bulletins, attestations et documents officiels sont generes automatiquement** (ce module)

**Pain point resolu** : Les colleges et lycees au Niger produisent actuellement les bulletins manuellement, ce qui entraine :
- **Perte de temps massive** : 2 a 3 jours pour generer les bulletins d'une seule classe de 60 eleves (recopie manuelle des notes, calcul des moyennes, classement, redaction des appreciations)
- **Erreurs frequentes** : 10-15% des bulletins contiennent des erreurs (notes mal transcrites, moyennes mal calculees, rang incorrect, coefficients oublies)
- **Mise en page inconsistante** : Chaque bulletin a une mise en page differente selon qui le redige
- **Retards importants** : Bulletins distribues des semaines apres la fin du semestre
- **Difficulte de reimpression** : Si un bulletin est perdu, il faut le refaire entierement a la main

Avec ce module, la generation de tous les bulletins d'une classe prend **< 5 minutes** (vs 2-3 jours manuellement) avec **zero erreur de calcul**.

**Metrique de succes** : Generer 60 bulletins semestriels en < 2 minutes (performance) avec < 0.5% d'erreurs (qualite).

---

## 2. Requirements

### 2.1 Functional Requirements (FR)

#### 2.1.1 Infrastructure de Generation PDF

- **FR1** : Le systeme doit utiliser `barryvdh/laravel-dompdf` pour la generation de PDF
- **FR2** : Le systeme doit permettre de creer des templates PDF au format Blade (HTML vers PDF)
- **FR3** : Le systeme doit supporter les images (logos, photos eleves, signatures, tampons) dans les templates PDF
- **FR4** : Le systeme doit supporter la generation asynchrone via Laravel Queues pour les generations en masse
- **FR5** : Le systeme doit stocker les PDFs generes dans le systeme de fichiers (Laravel Filesystem) avec une URL securisee
- **FR6** : Le systeme doit permettre de telecharger directement un PDF ou de l'envoyer par email (Phase 2)

#### 2.1.2 Template Bulletin Semestriel

- **FR7** : Le systeme doit fournir un template professionnel de bulletin semestriel avec :
  - **En-tete** :
    - Republique du Niger (mention officielle)
    - Ministere de l'Education Nationale
    - Logo de l'etablissement (configurable par tenant)
    - Nom de l'etablissement
    - Adresse, telephone, BP de l'etablissement
    - Titre : "BULLETIN DE NOTES - [SEMESTRE]"
    - Annee scolaire
  - **Informations de l'eleve** :
    - Photo de l'eleve (si disponible)
    - Matricule
    - Nom et Prenom
    - Date et lieu de naissance
    - Sexe
    - Classe (ex: Tle D2)
    - Redoublant (Oui/Non)
    - Effectif de la classe
    - Professeur principal
  - **Tableau des matieres** :
    - Colonnes : Matiere, Coefficient, Note devoir(s), Note composition, Moyenne matiere (/20), Appreciation de l'enseignant
    - Ligne par matiere inscrite au programme de la classe
    - Sous-totaux par groupe de matieres si applicable (matieres scientifiques, litteraires, etc.)
  - **Resume du semestre** :
    - Total des coefficients
    - Total des points (somme des moyennes ponderees)
    - Moyenne generale du semestre (/20)
    - Rang de l'eleve dans la classe (ex: 5e/58)
    - Moyenne la plus haute de la classe
    - Moyenne la plus basse de la classe
    - Moyenne de la classe
    - Mention obtenue : Tableau d'honneur / Encouragements / Felicitations (ou aucune)
  - **Appreciation generale** :
    - Appreciation du conseil de classe ou du professeur principal (texte libre)
  - **Absences** :
    - Nombre d'heures d'absences justifiees
    - Nombre d'heures d'absences non justifiees
    - Total heures d'absences
  - **Avertissements** (si applicable) :
    - Avertissement travail
    - Avertissement conduite
    - Blame
  - **Footer** :
    - Date de generation du document
    - Signature du professeur principal
    - Signature du Proviseur / Principal (image ou texte)
    - Tampon de l'etablissement (image, si disponible)
    - Signature du parent/tuteur (espace reserve)
    - Numero unique du document (pour tracabilite)
- **FR8** : Le template doit etre au format A4 (portrait) avec mise en page optimisee
- **FR9** : Le template doit supporter l'affichage de 12 a 18 matieres par bulletin (pagination automatique si necessaire)

#### 2.1.3 Template Bulletin Annuel

- **FR10** : Le systeme doit fournir un template de bulletin annuel avec :
  - En-tete identique au bulletin semestriel
  - Informations eleve identiques
  - **Tableau recapitulatif** :
    - Colonnes : Matiere, Coefficient, Moyenne S1, Moyenne S2, Moyenne annuelle, Appreciation
    - Ligne par matiere
  - **Resume annuel** :
    - Moyenne generale annuelle (/20)
    - Rang annuel dans la classe
    - Moyenne de la classe (annuelle)
    - Mention annuelle
  - **Decision du conseil de classe** :
    - Passage en classe superieure
    - Redoublement
    - Exclusion
    - Orientation (pour les classes de 3e vers le lycee : serie proposee)
  - Footer avec signatures (Professeur principal, Proviseur/Principal, Parent)

#### 2.1.4 Generation de Bulletins Semestriels

- **FR11** : Le systeme doit permettre de generer un bulletin semestriel pour :
  - **Un eleve specifique** (selection manuelle)
  - **Une classe entiere** (tous les eleves d'une classe pour un semestre donne)
- **FR12** : Avant generation, le systeme doit afficher une previsualisation du bulletin (HTML)
- **FR13** : Le systeme doit permettre de telecharger le PDF genere immediatement
- **FR14** : Le systeme doit enregistrer l'historique de generation dans la table `document_history` :
  - Type de document (Bulletin semestriel)
  - Eleve concerne
  - Classe
  - Semestre
  - Genere par (Admin)
  - Date de generation
  - Chemin du fichier PDF
  - Numero unique du document
- **FR15** : Le systeme doit generer un numero unique pour chaque document (format : BS-2025-001 pour bulletin semestriel, BA-2025-001 pour bulletin annuel)
- **FR16** : Le nom du fichier PDF doit suivre le format : `bulletin_{classe}_{matricule}_{semestre}_{date}.pdf` (ex: `bulletin_TleD2_2025-001_S1_2026-01-15.pdf`)

#### 2.1.5 Generation en Masse (par Classe)

- **FR17** : Le systeme doit permettre de generer les bulletins pour tous les eleves d'une classe en une seule operation
- **FR18** : L'Admin doit pouvoir selectionner :
  - Classe (ex: 6e A, 5e B, Tle D1)
  - Semestre (S1 ou S2)
  - Type de bulletin (Semestriel ou Annuel -- annuel uniquement si les 2 semestres sont clos)
- **FR19** : Le systeme doit afficher une previsualisation de la liste des eleves concernes : "X bulletins seront generes pour la classe Y"
- **FR20** : Le systeme doit generer les bulletins de maniere asynchrone (Laravel Queue) pour eviter le timeout
- **FR21** : Pendant la generation, un indicateur de progression doit s'afficher : "Generation en cours... X/Y bulletins generes"
- **FR22** : Apres generation, le systeme doit afficher un recapitulatif :
  - "X bulletins generes avec succes"
  - Bouton "Telecharger tous les bulletins" (ZIP)
  - Liste des bulletins generes avec lien de telechargement individuel
- **FR23** : Le systeme doit supporter la generation de 60 bulletins en < 2 minutes (performance critique)

#### 2.1.6 Mentions et Decisions

- **FR24** : Le systeme doit calculer et afficher automatiquement les mentions selon les baremes configurables par tenant :
  - **Felicitations** : Moyenne generale >= seuil configurable (defaut : 16/20)
  - **Tableau d'honneur** : Moyenne generale >= seuil configurable (defaut : 14/20)
  - **Encouragements** : Moyenne generale >= seuil configurable (defaut : 12/20) ou progression notable
  - **Aucune mention** : En dessous des seuils
- **FR25** : Le systeme doit afficher les avertissements issus du Module Discipline :
  - Avertissement travail
  - Avertissement conduite
  - Blame
- **FR26** : Pour le bulletin annuel, le systeme doit afficher la decision du conseil de classe :
  - Passage en classe superieure (Admis)
  - Redoublement (Redouble)
  - Exclusion
  - Orientation vers une serie (pour les eleves de 3e)

#### 2.1.7 Attestations et Certificats

- **FR27** : Le systeme doit fournir les templates suivants :
  - **Attestation de scolarite** : Atteste que l'eleve est inscrit pour l'annee scolaire en cours
  - **Attestation d'inscription** : Atteste que l'eleve est inscrit dans une classe specifique
  - **Certificat de scolarite / Exeat** : Document de transfert attestant que l'eleve a ete regulierement inscrit et qu'il est autorise a quitter l'etablissement
- **FR28** : Chaque template d'attestation/certificat doit contenir :
  - **En-tete** : Republique du Niger, Ministere de l'Education Nationale, Logo, Nom etablissement, Adresse
  - **Titre** : "ATTESTATION DE [TYPE]" ou "CERTIFICAT DE [TYPE]"
  - **Corps du texte** avec variables dynamiques :
    - {nom_etablissement}
    - {nom_prenom_eleve}
    - {matricule}
    - {date_naissance}
    - {lieu_naissance}
    - {classe}
    - {annee_scolaire}
    - {date}
  - **Footer** : Date, Lieu, Signature du Proviseur/Principal, Tampon, Numero unique
- **FR29** : Le systeme doit permettre de personnaliser le texte des attestations par tenant
- **FR30** : Le systeme doit permettre la generation individuelle pour un eleve specifique
- **FR31** : Le systeme doit generer un numero unique pour chaque attestation/certificat :
  - Attestation de scolarite : ATT-SCO-2025-001
  - Attestation d'inscription : ATT-INS-2025-001
  - Certificat de scolarite / Exeat : CERT-EXE-2025-001

#### 2.1.8 Cartes Scolaires

- **FR32** : Le systeme doit permettre de generer des cartes scolaires pour les eleves
- **FR33** : Le template de carte scolaire doit contenir :
  - **Recto** :
    - Nom de l'etablissement
    - Logo de l'etablissement
    - Titre : "CARTE SCOLAIRE" ou "CARTE D'IDENTITE SCOLAIRE"
    - Photo de l'eleve
    - Nom et Prenom
    - Matricule
    - Date et lieu de naissance
    - Classe
    - Annee scolaire
  - **Verso** :
    - Nom et contact du parent/tuteur
    - Adresse de l'eleve
    - Groupe sanguin (si renseigne)
    - Personne a contacter en cas d'urgence
    - Tampon et signature du Proviseur/Principal
- **FR34** : Le systeme doit permettre la generation en masse des cartes scolaires (par classe)
- **FR35** : Le format de la carte doit etre au format standard carte d'identite (85.6mm x 54mm), avec possibilite d'imprimer plusieurs cartes par page A4

#### 2.1.9 Releve de Notes Annuel

- **FR36** : Le systeme doit permettre de generer un releve de notes annuel par eleve
- **FR37** : Le template du releve de notes annuel doit contenir :
  - En-tete officiel (Republique du Niger, Ministere, Etablissement)
  - Informations eleve (Matricule, Nom, Prenom, Date naissance, Classe)
  - **Tableau detaille** :
    - Colonnes : Matiere, Coefficient, Notes devoirs S1, Compo S1, Moy S1, Notes devoirs S2, Compo S2, Moy S2, Moyenne annuelle
    - Ligne par matiere
  - Resume : Moyenne annuelle, Rang, Decision du conseil
  - Footer : Date, Signatures, Tampon, Numero unique
- **FR38** : Le releve peut etre genere individuellement ou en masse (par classe)

#### 2.1.10 Recus de Paiement

- **FR39** : Le systeme doit generer automatiquement un recu PDF lors de chaque enregistrement de paiement (integration Module Comptabilite)
- **FR40** : Le template du recu de paiement doit contenir :
  - En-tete de l'etablissement (Logo, Nom, Adresse)
  - Titre : "RECU DE PAIEMENT"
  - Numero du recu (format : REC-2025-001)
  - Date du paiement
  - Informations de l'eleve (Nom, Prenom, Matricule, Classe)
  - Detail du paiement :
    - Type de frais (Inscription, Scolarite, APE, Cantine, Tenue, etc.)
    - Montant paye
    - Mode de paiement (Especes, Virement, Cheque)
    - Montant total du et solde restant
  - Signature du caissier/comptable
  - Mention "Ce recu tient lieu de justificatif de paiement"
- **FR41** : Les recus doivent etre consultables et reimprimes depuis l'historique des documents

#### 2.1.11 Bulletins de Paie

- **FR42** : Le systeme doit generer automatiquement un bulletin de paie PDF pour chaque paiement de salaire (integration Module Paie)
- **FR43** : Le template du bulletin de paie doit contenir :
  - En-tete de l'etablissement
  - Titre : "BULLETIN DE PAIE"
  - Periode de paie (mois/annee)
  - Informations de l'employe :
    - Nom et Prenom
    - Poste / Fonction (Enseignant permanent, Vacataire, Administratif, Personnel d'appui)
    - Matricule employe
    - Date d'entree
  - Detail du calcul :
    - Salaire de base
    - Heures supplementaires (nombre et montant)
    - Primes et indemnites
    - Retenues / Deductions
    - Net a payer
  - Mode de paiement
  - Signature du comptable et du Proviseur/Principal
  - Numero unique (BDP-2025-001)
- **FR44** : Les bulletins de paie doivent etre generes en masse (tous les employes d'un mois) ou individuellement

#### 2.1.12 Historique et Tracabilite

- **FR45** : Le systeme doit conserver un historique de tous les documents generes
- **FR46** : L'historique doit etre consultable par l'Admin avec filtres :
  - Type de document (Bulletin semestriel, Bulletin annuel, Attestation, Certificat/Exeat, Carte scolaire, Releve de notes, Recu de paiement, Bulletin de paie)
  - Eleve / Employe
  - Classe
  - Semestre / Annee scolaire
  - Date de generation (periode)
  - Genere par (Admin/Comptable)
- **FR47** : L'historique doit afficher : Type, Numero unique, Beneficiaire (Matricule + Nom), Classe/Poste, Periode, Date de generation, Genere par, Actions (Telecharger, Reimprimer)
- **FR48** : Le systeme doit permettre de reimprimer un document genere precedemment (acces au fichier PDF stocke)
- **FR49** : Le systeme doit afficher un badge "Reimpression" sur les documents reimprimes (nombre de reimpressions affiche dans l'historique)

#### 2.1.13 Configuration par Tenant

- **FR50** : Le systeme doit permettre a chaque tenant de configurer :
  - Logo de l'etablissement (upload image)
  - Nom de l'etablissement
  - Adresse, telephone, BP de l'etablissement
  - Type d'etablissement (College d'Enseignement General, Lycee, Lycee Technique)
  - Texte de signature (ex: "Le Proviseur", "Le Principal")
  - Image de signature (upload image, optionnel)
  - Tampon de l'etablissement (upload image, optionnel)
  - Texte personnalise pour les attestations (textarea)
  - Baremes des mentions (seuils Felicitations, Tableau d'honneur, Encouragements)
  - Format du bulletin (choix de template si plusieurs disponibles)
- **FR51** : Les configurations sont stockees dans la table `document_settings`
- **FR52** : Le systeme doit previsualiser les templates avec les configurations actuelles avant sauvegarde

---

### 2.2 Non-Functional Requirements (NFR)

- **NFR1** : Le systeme doit generer 60 bulletins semestriels en < 2 minutes (< 30 secondes idealement) (performance)
- **NFR2** : La qualite des PDFs doit etre professionnelle (rendu identique a la previsualisation HTML) (qualite)
- **NFR3** : Les PDFs generes doivent etre legers (< 500 KB par bulletin) pour faciliter le telechargement (UX)
- **NFR4** : Le systeme doit supporter la generation simultanee par 10 admins (concurrence)
- **NFR5** : Les PDFs stockes doivent etre accessibles uniquement par les roles autorises (Admin, Comptable pour les recus, l'eleve/parent concerne) (securite)
- **NFR6** : Le systeme doit conserver les PDFs pendant au moins 10 ans (archivage long terme, conformite Ministere)
- **NFR7** : Le systeme doit fonctionner sur connexion 3G avec optimisation du telechargement PDF (contexte Niger)
- **NFR8** : Les templates doivent etre modifiables facilement par un developpeur (maintenabilite)
- **NFR9** : Le systeme doit generer des PDFs conformes aux standards de l'Education Nationale du Niger (mentions officielles, format)

---

## 3. User Interface Design Goals

### 3.1 Overall UX Vision

L'interface du Module Documents Officiels doit etre **simple, rapide et visuelle**. L'Admin doit pouvoir generer les bulletins de toute une classe en 3 clics maximum : Selection de la classe --> Previsualisation --> Generation en masse.

**Principes cles** :
- **Workflow en 3 etapes** : Selection --> Previsualisation --> Generation
- **Generation en masse par defaut** : L'action la plus courante est de generer tous les bulletins d'une classe
- **Previsualisation obligatoire** : Voir le document avant generation pour eviter les erreurs
- **Feedback immediat** : Indicateur de progression pour les generations en masse
- **Acces rapide a l'historique** : Reimprimer un document en 1 clic

### 3.2 Key Interaction Paradigms

- **Selection guidee** : Formulaire avec filtres pour selectionner la classe/semestre/type de document
- **Previsualisation integree** : Affichage du document dans l'interface (pas de telechargement pour previsualiser)
- **Generation asynchrone** : Barre de progression pour les generations en masse
- **Telechargement direct** : Bouton "Telecharger PDF" immediatement accessible

### 3.3 Core Screens and Views

#### 3.3.1 Ecran Admin : Dashboard Documents

- Vue d'ensemble avec statistiques :
  - Nombre de bulletins generes ce semestre
  - Nombre d'attestations/certificats generes ce mois
  - Nombre de recus de paiement generes ce mois
  - Nombre total de documents generes
  - Taille totale des PDFs stockes
- Boutons d'action rapide :
  - "Generer les bulletins d'une classe"
  - "Generer une attestation"
  - "Generer les cartes scolaires"
  - "Historique des documents"

#### 3.3.2 Ecran Admin : Generation Bulletins Semestriels (par Classe)

- **Etape 1 : Selection** :
  - Classe (select : 6e A, 6e B, ..., Tle D1, Tle D2, etc.)
  - Semestre (select : S1, S2)
  - Previsualisation du nombre : "58 eleves dans cette classe"
  - Bouton "Previsualiser un bulletin" (selectionner un eleve pour apercu)
  - Bouton "Generer tous les bulletins (58)"
- **Etape 2 : Previsualisation** (individuelle) :
  - Selection de l'eleve (autocomplete : recherche par nom, prenom, matricule)
  - Affichage du bulletin en HTML (dans l'interface)
  - Donnees affichees : Photo, Infos eleve, Tableau des matieres avec notes/moyennes/appreciations, Resume (moyenne, rang, mention), Absences, Avertissements, Appreciation generale
  - Boutons : "Retour", "Generer ce bulletin uniquement", "Generer tous les bulletins de la classe"
- **Etape 3 : Generation en masse** :
  - Barre de progression : "Generation en cours... 35/58 bulletins generes (60%)"
  - Indicateur de temps ecoule
  - Message : "Ne fermez pas cette page"
- **Etape 4 : Resultat** :
  - Message de succes : "58 bulletins generes avec succes en 45 secondes"
  - Bouton "Telecharger tous les bulletins (ZIP)"
  - Liste des bulletins generes :
    - Tableau : Matricule, Nom, Prenom, Moyenne, Rang, Mention, Actions (Telecharger PDF)
  - Bouton "Generer les bulletins d'une autre classe"

#### 3.3.3 Ecran Admin : Generation Bulletin Individuel

- **Etape 1 : Selection** :
  - Selection de l'eleve (autocomplete : recherche par nom, prenom, matricule)
  - Semestre (select : S1, S2)
  - Type (select : Bulletin semestriel, Bulletin annuel)
  - Bouton "Previsualiser"
- **Etape 2 : Previsualisation** :
  - Affichage du bulletin en HTML dans l'interface
  - Boutons : "Retour (Modifier)", "Generer PDF"
- **Etape 3 : Telechargement** :
  - Message de succes : "Bulletin genere avec succes pour [Nom Prenom]"
  - Numero unique affiche : "BS-2025-001"
  - Bouton "Telecharger le PDF"
  - Bouton "Generer un autre bulletin"

#### 3.3.4 Ecran Admin : Generation Attestation / Certificat

- **Etape 1 : Selection** :
  - Type de document (select : Attestation de scolarite, Attestation d'inscription, Certificat de scolarite / Exeat)
  - Selection de l'eleve (autocomplete)
  - Annee scolaire (select, pre-rempli avec l'annee en cours)
  - Bouton "Previsualiser"
- **Etape 2 : Previsualisation** :
  - Affichage de l'attestation/certificat en HTML
  - Donnees affichees : En-tete officiel, Titre, Texte avec variables remplacees, Date, Signature, Tampon
  - Boutons : "Retour (Modifier)", "Generer PDF"
- **Etape 3 : Telechargement** :
  - Message de succes : "Attestation generee avec succes pour [Nom Prenom]"
  - Numero unique affiche : "ATT-SCO-2025-001"
  - Bouton "Telecharger le PDF"
  - Bouton "Generer une autre attestation"

#### 3.3.5 Ecran Admin : Generation Cartes Scolaires

- **Etape 1 : Selection** :
  - Classe (select)
  - Previsualisation : "58 eleves dans cette classe (dont 45 avec photo)"
  - Avertissement si des eleves n'ont pas de photo
  - Bouton "Previsualiser" (affiche un exemple de carte)
  - Bouton "Generer les cartes (58)"
- **Etape 2 : Generation** :
  - Barre de progression
  - Resultat : fichier PDF avec toutes les cartes (plusieurs cartes par page A4)
- **Etape 3 : Telechargement** :
  - Bouton "Telecharger le PDF des cartes"

#### 3.3.6 Ecran Admin : Historique des Documents

- Tableau avec colonnes : Type, Numero unique, Beneficiaire (Matricule + Nom), Classe/Poste, Periode, Date de generation, Genere par, Actions (Telecharger, Reimprimer)
- Filtres :
  - Type de document (select : Tous, Bulletin semestriel, Bulletin annuel, Attestation scolarite, Attestation inscription, Certificat/Exeat, Carte scolaire, Releve de notes, Recu de paiement, Bulletin de paie)
  - Beneficiaire (autocomplete : eleve ou employe)
  - Classe (select)
  - Periode (select : S1, S2, Annuel)
  - Date de generation (date range picker)
  - Genere par (select : liste des admins/comptables)
- Recherche : Par numero unique, matricule
- Pagination : 20 documents par page
- Badge "Reimpression" sur les documents reimprimes (nombre de reimpressions affiche)

#### 3.3.7 Ecran Admin : Configuration des Templates

- Onglets :
  - **En-tete et Identite** :
    - Upload logo etablissement (image, max 1 MB)
    - Nom de l'etablissement (texte)
    - Type (select : CEG, Lycee, Lycee Technique)
    - Adresse de l'etablissement (textarea)
    - Telephone, BP
  - **Signature et Tampon** :
    - Texte de signature (ex: "Le Proviseur", "Le Principal")
    - Nom du signataire
    - Upload image de signature (image, max 500 KB, optionnel)
    - Upload tampon de l'etablissement (image, max 500 KB, optionnel)
  - **Baremes des Mentions** :
    - Seuil Felicitations (defaut : 16/20)
    - Seuil Tableau d'honneur (defaut : 14/20)
    - Seuil Encouragements (defaut : 12/20)
  - **Textes des Attestations** :
    - Texte personnalise pour attestation de scolarite (textarea avec variables disponibles)
    - Texte personnalise pour attestation d'inscription (textarea)
    - Texte personnalise pour certificat de scolarite / exeat (textarea)
- Section "Previsualisation" :
  - Affichage d'un bulletin semestriel avec les configurations actuelles (donnees fictives)
  - Affichage d'une attestation avec les configurations actuelles (donnees fictives)
- Boutons : "Sauvegarder", "Reinitialiser par defaut"

### 3.4 Accessibility

**WCAG AA** : Le systeme doit respecter les standards d'accessibilite WCAG 2.1 niveau AA :
- Navigation au clavier complete
- Labels ARIA pour les boutons et formulaires
- Contraste de couleurs suffisant dans les PDFs et l'interface
- Messages de succes accessibles aux lecteurs d'ecran

### 3.5 Branding

- Interface professionnelle et epuree
- Couleurs :
  - **Bleu (#2196F3)** : Actions primaires, liens
  - **Vert (#4CAF50)** : Succes, documents generes
  - **Orange (#FF9800)** : Avertissements, generation en cours
  - **Rouge (#F44336)** : Erreurs
- Les templates PDF doivent avoir un aspect officiel adapte au contexte de l'Education Nationale du Niger

### 3.6 Target Device and Platforms

**Web Responsive** :
- Desktop (prioritaire) : Optimise pour la previsualisation et la generation
- Tablette : Interface adaptee pour consultation et generation legere
- Mobile : Consultation de l'historique, telechargement de documents (pas de generation en masse sur mobile)

---

## 4. Technical Assumptions

### 4.1 Repository Structure

**Polyrepo** : Backend (Laravel) et Frontend (Next.js) dans des depots separes (architecture existante).

### 4.2 Service Architecture

**Architecture modulaire (nwidart/laravel-modules)** :
- Module Laravel : `Modules/Documents/`
- Structure standard :
  - `Entities/` : Models Eloquent (Document, DocumentSetting)
  - `Http/Controllers/` : Controllers Admin
  - `Http/Requests/` : Form Requests pour validation
  - `Http/Resources/` : API Resources
  - `Database/Migrations/tenant/` : Migrations tenant
  - `Routes/` : Routes admin.php
  - `Resources/views/templates/` : Templates Blade pour les PDFs (bulletin_semestriel, bulletin_annuel, attestation_scolarite, attestation_inscription, certificat_exeat, carte_scolaire, releve_notes, recu_paiement, bulletin_paie)
  - `Services/` : Services dedies (PdfGeneratorService, DocumentNumberService, BulletinDataService)

**Frontend Next.js** :
- Module : `src/modules/Documents/`
- Structure en 3 couches : `admin/`, `superadmin/` (vide), `frontend/` (consultation historique)
- Services API avec `createApiClient()`
- Hooks React pour gestion de l'etat

### 4.3 Base de donnees

**Connexion** : `tenant` (base de donnees tenant dynamique)

**Tables a creer** :
- `documents` : Documents generes (id, type, beneficiary_type, beneficiary_id, class_id, semester_id, school_year_id, file_path, document_number, generated_by, generated_at, reprint_count, metadata)
- `document_settings` : Configuration par tenant (id, logo_path, institution_name, institution_type, institution_address, institution_phone, institution_bp, signatory_title, signatory_name, signature_image_path, stamp_image_path, mention_felicitations_threshold, mention_honneur_threshold, mention_encouragements_threshold, attestation_scolarite_text, attestation_inscription_text, certificat_exeat_text)

**Types de documents** (enum) :
- `bulletin_semestriel`
- `bulletin_annuel`
- `attestation_scolarite`
- `attestation_inscription`
- `certificat_exeat`
- `carte_scolaire`
- `releve_notes_annuel`
- `recu_paiement`
- `bulletin_paie`

**Relations cles** :
- `documents` belongsTo `students` (polymorphic via beneficiary), `classes`, `semesters`, `school_years`, `users` (generated_by)
- Utiliser **eager loading** pour eviter les N+1 queries

### 4.4 Testing Requirements

**Tests obligatoires** :
- **Tests unitaires** : Generation numero unique, remplacement variables dans templates, calcul des mentions, formatage des moyennes
- **Tests d'integration** : Generation d'un bulletin semestriel complet (donnees reelles vers PDF), generation d'une attestation, generation d'un recu
- **Tests de performance** : Generation de 60 bulletins en < 2 minutes
- **Tests de cas limites** : Eleve sans photo, matiere sans note, classe avec un seul eleve, eleve redoublant, eleve avec avertissement

**Outils** :
- Laravel : PHPUnit (`php artisan test`)
- Tests de performance : Scripts PHP dedies ou tests fonctionnels avec mesure du temps
- Frontend : Jest + React Testing Library

### 4.5 Additional Technical Assumptions

- **API REST** : Endpoints suivant les conventions existantes (`/api/admin/documents/generate-bulletin`, `/api/admin/documents/generate-attestation`, `/api/admin/documents/generate-cards`, etc.)
- **Middleware** : `['tenant', 'tenant.auth']` pour toutes les routes
- **Permissions** : Utiliser Spatie Permission pour controle d'acces :
  - `generate-bulletins` : Generation de bulletins (Admin)
  - `generate-attestations` : Generation d'attestations/certificats (Admin)
  - `generate-cards` : Generation de cartes scolaires (Admin)
  - `generate-receipts` : Generation/consultation de recus (Admin, Comptable)
  - `generate-payslips` : Generation de bulletins de paie (Admin, Comptable)
  - `view-document-history` : Consultation de l'historique (Admin, Comptable)
- **Validation** : Form Requests pour toutes les saisies
- **API Resources** : Retourner toujours des Resources, jamais de models bruts
- **Format JSON** : Reponses standardisees avec `success`, `message`, `data`
- **Stockage PDF** : Utiliser Laravel Filesystem (local en dev, S3 en production) avec structure : `documents/{tenant_id}/{type}/{year}/{document_number}.pdf`
- **Generation asynchrone** : Utiliser Laravel Queues (Redis ou Database) pour generation en masse
- **Service PdfGeneratorService** : Service dedie pour centraliser la logique de generation
- **Service DocumentNumberService** : Service dedie pour generer les numeros uniques (format configurable)
- **Service BulletinDataService** : Service dedie pour agglomerer les donnees necessaires a un bulletin (notes, moyennes, rang, appreciations, mentions, absences, avertissements) depuis les modules Notes, Conseil de Classe, Presences, Discipline

---

## 5. Epic List

### Epic 1 : Infrastructure de Generation PDF
**Goal** : Mettre en place l'infrastructure technique pour generer des PDFs de qualite professionnelle avec DomPDF.

### Epic 2 : Bulletins Semestriels (Template + Generation Individuelle + Generation en Masse)
**Goal** : Creer le template professionnel de bulletin semestriel et permettre la generation individuelle et en masse (par classe) avec mentions, appreciations et rang.

### Epic 3 : Bulletin Annuel
**Goal** : Creer le template de bulletin annuel recapitulatif des 2 semestres avec decision finale du conseil de classe.

### Epic 4 : Attestations, Certificats et Exeat
**Goal** : Creer les templates d'attestations de scolarite, d'inscription et de certificat de scolarite/exeat, et permettre la generation individuelle.

### Epic 5 : Cartes Scolaires
**Goal** : Creer le template de carte scolaire avec photo et permettre la generation en masse par classe.

### Epic 6 : Releve de Notes Annuel
**Goal** : Creer le template de releve de notes annuel detaille et permettre la generation individuelle et en masse.

### Epic 7 : Documents Financiers (Recus de Paiement + Bulletins de Paie)
**Goal** : Automatiser la generation des recus de paiement et des bulletins de paie au format PDF.

### Epic 8 : Historique, Tracabilite et Reimpression
**Goal** : Conserver l'historique de tous les documents generes et permettre la reimpression.

### Epic 9 : Configuration des Templates par Tenant
**Goal** : Permettre a chaque etablissement de personnaliser les templates (logo, signature, tampon, baremes de mentions, textes des attestations).

---

## 6. Epic Details

### Epic 1 : Infrastructure de Generation PDF

**Goal detaille** : Installer et configurer `barryvdh/laravel-dompdf`, creer les services de generation PDF et de numerotation, et valider la performance avec un test de generation de 60+ PDFs.

#### Story 1.1 : Installation et Configuration de DomPDF

**As a** System,
**I want** installer et configurer DomPDF,
**so that** je puisse generer des PDFs de qualite professionnelle.

**Acceptance Criteria** :
1. Installer `barryvdh/laravel-dompdf` via Composer
2. Publier la configuration : `php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"`
3. Configurer les options DomPDF dans `config/dompdf.php` (format A4, polices UTF-8, support images)
4. Creer un test simple : Generer un PDF "Hello World" a partir d'un template Blade
5. Verifier que le PDF genere supporte les caracteres accentues francais, les images et la mise en page A4

**Dependances** : Aucune

---

#### Story 1.2 : Service de Generation PDF (PdfGeneratorService)

**As a** System,
**I want** creer un service dedie pour centraliser la logique de generation PDF,
**so that** je puisse reutiliser ce service pour tous les types de documents.

**Acceptance Criteria** :
1. Creer un service `PdfGeneratorService` dans `Modules/Documents/Services/`
2. Le service doit avoir une methode `generate(string $templateName, array $data, string $filename): string`
3. La methode `generate()` doit :
   - Charger le template Blade specifie (ex: `documents::templates.bulletin_semestriel`)
   - Injecter les donnees fournies
   - Generer le PDF avec DomPDF
   - Stocker le PDF dans le systeme de fichiers (Laravel Filesystem)
   - Retourner le chemin du fichier PDF genere
4. Le service doit supporter les options : format A4, orientation portrait, marges, format carte (pour les cartes scolaires)
5. Un test unitaire valide que la generation fonctionne correctement

**Dependances** : Story 1.1

---

#### Story 1.3 : Service de Numerotation (DocumentNumberService)

**As a** System,
**I want** creer un service dedie pour generer des numeros uniques de documents,
**so that** chaque document ait un identifiant unique et tracable.

**Acceptance Criteria** :
1. Creer un service `DocumentNumberService` dans `Modules/Documents/Services/`
2. Le service doit generer des numeros uniques par type de document et par annee :
   - Bulletin semestriel : BS-2025-001, BS-2025-002, ...
   - Bulletin annuel : BA-2025-001, BA-2025-002, ...
   - Attestation scolarite : ATT-SCO-2025-001, ...
   - Attestation inscription : ATT-INS-2025-001, ...
   - Certificat/Exeat : CERT-EXE-2025-001, ...
   - Carte scolaire : CS-2025-001, ...
   - Releve de notes : RN-2025-001, ...
   - Recu de paiement : REC-2025-001, ...
   - Bulletin de paie : BDP-2025-001, ...
3. Le compteur doit se reinitialiser chaque annee scolaire
4. Le service doit etre thread-safe (utiliser un lock ou une sequence en base)
5. Un test unitaire valide l'unicite et le format des numeros generes

**Dependances** : Aucune

---

#### Story 1.4 : Tests de Performance (Spike PDF)

**As a** System,
**I want** valider que je peux generer 60 PDFs de bulletins en < 2 minutes,
**so that** je m'assure que la technologie choisie est performante.

**Acceptance Criteria** :
1. Creer un test de performance : Generer 60 bulletins semestriels avec des donnees realistes (12+ matieres, photo, appreciations)
2. Mesurer le temps total de generation
3. Mesurer la consommation memoire
4. **Critere de succes** : 60 PDFs generes en < 2 minutes (idealement < 30 secondes)
5. Si le critere n'est pas atteint, optimiser :
   - Generation asynchrone avec Laravel Queues (parallelisation)
   - Reduction de la taille des images (compression)
   - Simplification des templates
6. Documenter les resultats du test

**Dependances** : Story 1.2

---

### Epic 2 : Bulletins Semestriels

**Goal detaille** : Creer le template Blade professionnel de bulletin semestriel et permettre la generation individuelle et en masse avec calcul automatique des mentions, rang et appreciations.

#### Story 2.1 : Service de Donnees Bulletin (BulletinDataService)

**As a** System,
**I want** creer un service qui agglomere toutes les donnees necessaires a la generation d'un bulletin semestriel,
**so that** les donnees soient centralisees et coherentes.

**Acceptance Criteria** :
1. Creer un service `BulletinDataService` dans `Modules/Documents/Services/`
2. Le service doit avoir une methode `getSemesterData(int $studentId, int $semesterId): array` retournant :
   - **student** : { matricule, nom, prenom, date_naissance, lieu_naissance, sexe, photo_url, classe, est_redoublant }
   - **classe** : { nom, effectif, professeur_principal }
   - **semester** : { nom (S1/S2), annee_scolaire }
   - **matieres** : [ { nom, coefficient, note_devoir, note_composition, moyenne_matiere, appreciation_enseignant } ]
   - **resume** : { total_coefficients, total_points, moyenne_generale, rang, rang_total, moyenne_premier, moyenne_dernier, moyenne_classe }
   - **mention** : { type (Felicitations/Tableau d'honneur/Encouragements/null) }
   - **absences** : { justifiees, non_justifiees, total }
   - **avertissements** : [ { type (travail/conduite/blame) } ]
   - **appreciation_generale** : { texte }
   - **institution** : { nom, type, adresse, telephone, bp, logo_url, signature_titre, signature_nom, signature_url, tampon_url }
3. Le service doit utiliser eager loading pour eviter les N+1 queries
4. Le service doit etre couvert par des tests unitaires

**Dependances** : Module Notes, Module Conseil de Classe, Module Inscriptions, Module Structure Academique

---

#### Story 2.2 : Creation du Template Blade Bulletin Semestriel

**As a** Developer,
**I want** creer un template Blade professionnel pour les bulletins semestriels,
**so that** les PDFs generes soient de qualite professionnelle et conformes aux standards nigeriens.

**Acceptance Criteria** :
1. Creer un template Blade : `Modules/Documents/Resources/views/templates/bulletin_semestriel.blade.php`
2. Le template doit contenir tous les elements decrits dans FR7 :
   - En-tete officiel (Republique du Niger, Ministere, Etablissement)
   - Informations eleve avec photo
   - Tableau des matieres (matiere, coeff, devoirs, composition, moyenne, appreciation)
   - Resume (moyenne, rang, mention)
   - Absences et avertissements
   - Appreciation generale
   - Footer (signatures, tampon, numero unique)
3. Le template doit utiliser CSS inline pour compatibilite DomPDF
4. Le template doit supporter 12 a 18 matieres sans debordement
5. Le template doit etre au format A4 portrait
6. Un test avec des donnees fictives valide que le rendu est correct et professionnel

**Dependances** : Story 1.2

---

#### Story 2.3 : Generation Individuelle de Bulletin Semestriel

**As an** Admin,
**I want** generer un bulletin semestriel pour un eleve avec previsualisation,
**so that** je puisse verifier que tout est correct avant de generer le PDF.

**Acceptance Criteria** :
1. Un endpoint API permet de recuperer les donnees d'un bulletin : `/api/admin/documents/bulletin-data/{student_id}/{semester_id}`
2. Un ecran "Generer un bulletin" affiche un formulaire de selection (eleve + semestre)
3. Le clic sur "Previsualiser" charge les donnees via le BulletinDataService et affiche le bulletin en HTML
4. Le bulletin affiche est visuellement identique au PDF qui sera genere
5. Un bouton "Generer PDF" appelle le PdfGeneratorService
6. Un numero unique est genere (format : BS-2025-001)
7. Le PDF est genere et stocke dans `documents/{tenant_id}/bulletins_semestriels/{year}/BS-2025-001.pdf`
8. L'historique est enregistre dans la table `documents`
9. Un message de succes s'affiche avec le numero unique
10. Un bouton "Telecharger le PDF" permet le telechargement immediat

**Dependances** : Story 2.1, Story 2.2

---

#### Story 2.4 : Generation en Masse des Bulletins Semestriels (par Classe)

**As an** Admin,
**I want** generer les bulletins de toute une classe en une seule operation,
**so that** je gagne du temps lors de la production des bulletins.

**Acceptance Criteria** :
1. Un ecran "Generation par classe" affiche un formulaire : Classe (select), Semestre (select)
2. Un bouton "Afficher les eleves" charge la liste des eleves de la classe avec apercu (nom, moyenne, rang)
3. Un compteur affiche : "X bulletins seront generes"
4. Un bouton "Generer tous les bulletins" dispatch un Job Laravel `GenerateBatchBulletinsJob`
5. Le Job genere chaque bulletin via PdfGeneratorService et met a jour un compteur de progression dans le cache
6. L'interface interroge le cache toutes les 2 secondes pour afficher la progression
7. Apres generation, l'ecran affiche :
   - "X bulletins generes avec succes en Y secondes"
   - Bouton "Telecharger tous les bulletins (ZIP)"
   - Liste des bulletins generes avec telechargement individuel
8. Le systeme doit generer 60 bulletins en < 2 minutes
9. Le fichier ZIP est nomme : `bulletins_{classe}_{semestre}_{date}.zip`

**Dependances** : Story 2.3

---

### Epic 3 : Bulletin Annuel

**Goal detaille** : Creer le template de bulletin annuel recapitulatif des 2 semestres avec decision finale du conseil de classe.

#### Story 3.1 : Creation du Template Blade Bulletin Annuel

**As a** Developer,
**I want** creer un template Blade pour le bulletin annuel,
**so that** les resultats annuels soient presentes de maniere claire et professionnelle.

**Acceptance Criteria** :
1. Creer un template Blade : `Modules/Documents/Resources/views/templates/bulletin_annuel.blade.php`
2. Le template doit contenir tous les elements decrits dans FR10 :
   - En-tete officiel identique au bulletin semestriel
   - Informations eleve
   - Tableau recapitulatif : Matiere, Coeff, Moy S1, Moy S2, Moy Annuelle, Appreciation
   - Resume annuel : Moyenne annuelle, Rang annuel, Moyenne de classe
   - Mention annuelle
   - Decision du conseil de classe (Passage, Redoublement, Exclusion, Orientation)
   - Footer avec signatures
3. Le template ne peut etre genere que si les 2 semestres sont clos et les notes validees
4. Un test avec des donnees fictives valide le rendu

**Dependances** : Story 2.1

---

#### Story 3.2 : Generation du Bulletin Annuel (Individuel et en Masse)

**As an** Admin,
**I want** generer les bulletins annuels pour un eleve ou pour toute une classe,
**so that** les familles recoivent le bilan complet de l'annee.

**Acceptance Criteria** :
1. Le BulletinDataService est etendu avec une methode `getAnnualData(int $studentId, int $schoolYearId): array`
2. La methode retourne les donnees des 2 semestres, les moyennes annuelles et la decision du conseil
3. La generation est possible en individuel ou en masse (meme workflow que les bulletins semestriels)
4. Un garde-fou empeche la generation si le semestre 2 n'est pas clos
5. Le numero unique suit le format BA-2025-001
6. Les bulletins annuels sont stockes dans `documents/{tenant_id}/bulletins_annuels/{year}/`

**Dependances** : Story 3.1, Story 2.4

---

### Epic 4 : Attestations, Certificats et Exeat

**Goal detaille** : Creer les templates d'attestations et de certificats et permettre la generation individuelle.

#### Story 4.1 : Creation des Templates Blade Attestations et Certificats

**As a** Developer,
**I want** creer les templates Blade pour les attestations et certificats,
**so that** l'etablissement puisse delivrer des documents officiels conformes.

**Acceptance Criteria** :
1. Creer 3 templates Blade :
   - `Modules/Documents/Resources/views/templates/attestation_scolarite.blade.php`
   - `Modules/Documents/Resources/views/templates/attestation_inscription.blade.php`
   - `Modules/Documents/Resources/views/templates/certificat_exeat.blade.php`
2. Chaque template contient les elements decrits dans FR28
3. Le texte par defaut est :
   - **Scolarite** : "Le soussigne, {signature_titre} de {nom_etablissement}, atteste que l'eleve {nom_prenom_eleve}, ne(e) le {date_naissance} a {lieu_naissance}, portant le matricule {matricule}, est regulierement inscrit(e) en classe de {classe} au titre de l'annee scolaire {annee_scolaire}. En foi de quoi, la presente attestation lui est delivree pour servir et valoir ce que de droit."
   - **Inscription** : "Le soussigne, {signature_titre} de {nom_etablissement}, atteste que l'eleve {nom_prenom_eleve}, ne(e) le {date_naissance} a {lieu_naissance}, portant le matricule {matricule}, est inscrit(e) en classe de {classe} au titre de l'annee scolaire {annee_scolaire}."
   - **Exeat** : "Le soussigne, {signature_titre} de {nom_etablissement}, certifie que l'eleve {nom_prenom_eleve}, ne(e) le {date_naissance} a {lieu_naissance}, portant le matricule {matricule}, a ete regulierement inscrit(e) dans notre etablissement en classe de {classe} au titre de l'annee scolaire {annee_scolaire}. L'eleve est autorise(e) a quitter l'etablissement. Le present certificat lui est delivre pour servir et valoir ce que de droit."
4. Les templates utilisent le meme style CSS que les bulletins pour la coherence visuelle

**Dependances** : Story 1.2

---

#### Story 4.2 : Generation Individuelle d'Attestation / Certificat

**As an** Admin,
**I want** generer une attestation ou un certificat pour un eleve avec previsualisation,
**so that** je puisse fournir rapidement des documents officiels.

**Acceptance Criteria** :
1. Un ecran "Generer une attestation" affiche un formulaire :
   - Type (select : Attestation de scolarite, Attestation d'inscription, Certificat de scolarite / Exeat)
   - Selection de l'eleve (autocomplete)
   - Annee scolaire (select, pre-rempli)
   - Bouton "Previsualiser"
2. Le clic sur "Previsualiser" charge les donnees et affiche le document en HTML
3. Les variables sont remplacees par les vraies donnees de l'eleve
4. Un bouton "Generer PDF" genere le document avec un numero unique
5. Le PDF est stocke dans `documents/{tenant_id}/attestations/{type}/{year}/{numero}.pdf`
6. L'historique est enregistre
7. Un message de succes s'affiche avec le numero unique

**Dependances** : Story 4.1

---

### Epic 5 : Cartes Scolaires

**Goal detaille** : Creer le template de carte scolaire et permettre la generation individuelle et en masse.

#### Story 5.1 : Creation du Template Blade Carte Scolaire

**As a** Developer,
**I want** creer un template Blade pour les cartes scolaires,
**so that** l'etablissement puisse generer des cartes d'identite scolaire pour ses eleves.

**Acceptance Criteria** :
1. Creer un template Blade : `Modules/Documents/Resources/views/templates/carte_scolaire.blade.php`
2. Le template contient les elements decrits dans FR33 (recto et verso)
3. Le format doit etre au format carte d'identite standard (85.6mm x 54mm)
4. Le template doit supporter l'agencement de plusieurs cartes par page A4 (8 cartes par page : 2 colonnes x 4 lignes)
5. Le template doit gerer les eleves sans photo (afficher un placeholder)
6. Un test valide le rendu avec et sans photo

**Dependances** : Story 1.2

---

#### Story 5.2 : Generation de Cartes Scolaires (Individuelle et en Masse)

**As an** Admin,
**I want** generer les cartes scolaires pour un eleve ou pour toute une classe,
**so that** les eleves recoivent leur carte d'identite scolaire rapidement.

**Acceptance Criteria** :
1. Un ecran "Generer les cartes scolaires" affiche un formulaire : Classe (select)
2. Un compteur affiche le nombre d'eleves et le nombre ayant une photo
3. La generation en masse produit un PDF multi-pages avec plusieurs cartes par page
4. La generation individuelle produit un PDF avec une seule carte
5. Le fichier est stocke et l'historique est enregistre
6. Un avertissement s'affiche si des eleves n'ont pas de photo

**Dependances** : Story 5.1

---

### Epic 6 : Releve de Notes Annuel

**Goal detaille** : Creer le template de releve de notes annuel detaille et permettre la generation.

#### Story 6.1 : Creation du Template et Generation du Releve de Notes Annuel

**As an** Admin,
**I want** generer un releve de notes annuel detaille pour un eleve ou pour une classe,
**so that** les eleves disposent d'un document recapitulatif de toutes leurs notes.

**Acceptance Criteria** :
1. Creer un template Blade : `Modules/Documents/Resources/views/templates/releve_notes_annuel.blade.php`
2. Le template contient les elements decrits dans FR37
3. Le releve presente toutes les notes par matiere et par semestre sur un seul document
4. La generation est possible en individuel ou en masse (par classe)
5. Le numero unique suit le format RN-2025-001
6. Le workflow suit le meme pattern que les autres documents (selection --> previsualisation --> generation)

**Dependances** : Story 2.1, Story 1.2

---

### Epic 7 : Documents Financiers

**Goal detaille** : Automatiser la generation des recus de paiement et des bulletins de paie.

#### Story 7.1 : Template et Generation de Recus de Paiement

**As a** Comptable,
**I want** generer automatiquement un recu de paiement lors de l'enregistrement d'un paiement,
**so that** les parents recoivent un justificatif officiel.

**Acceptance Criteria** :
1. Creer un template Blade : `Modules/Documents/Resources/views/templates/recu_paiement.blade.php`
2. Le template contient les elements decrits dans FR40
3. Le recu est genere automatiquement lors de l'enregistrement d'un paiement dans le Module Comptabilite (via un event listener)
4. Le recu est egalement generable manuellement depuis l'historique (reimpression)
5. Le numero unique suit le format REC-2025-001
6. Le recu est stocke et l'historique est enregistre

**Dependances** : Story 1.2, Module Comptabilite

---

#### Story 7.2 : Template et Generation de Bulletins de Paie

**As a** Comptable/Admin,
**I want** generer les bulletins de paie pour le personnel,
**so that** chaque employe recoive un justificatif detaille de sa remuneration.

**Acceptance Criteria** :
1. Creer un template Blade : `Modules/Documents/Resources/views/templates/bulletin_paie.blade.php`
2. Le template contient les elements decrits dans FR43
3. La generation est possible :
   - Individuellement (pour un employe et un mois donnes)
   - En masse (tous les employes d'un mois donne)
4. Le numero unique suit le format BDP-2025-001
5. La generation en masse utilise le meme pattern asynchrone que les bulletins semestriels
6. Les bulletins de paie sont stockes et l'historique est enregistre

**Dependances** : Story 1.2, Module Paie

---

### Epic 8 : Historique, Tracabilite et Reimpression

**Goal detaille** : Conserver l'historique de tous les documents generes et permettre la reimpression.

#### Story 8.1 : Enregistrement de l'Historique de Generation

**As a** System,
**I want** enregistrer l'historique de chaque document genere,
**so that** je puisse tracer qui a genere quoi et quand.

**Acceptance Criteria** :
1. Chaque generation de document enregistre une ligne dans la table `documents` :
   - type (enum des 9 types de documents)
   - beneficiary_type (student, employee)
   - beneficiary_id
   - class_id (si applicable)
   - semester_id (si applicable)
   - school_year_id
   - file_path (chemin du PDF stocke)
   - document_number (numero unique)
   - generated_by (user_id)
   - generated_at (timestamp)
   - reprint_count (defaut : 0)
   - metadata (JSON : donnees supplementaires specifiques au type)
2. L'historique est enregistre pour chaque generation individuelle et chaque document genere dans un batch

**Dependances** : Toutes les stories de generation (Epics 2 a 7)

---

#### Story 8.2 : Consultation de l'Historique des Documents

**As an** Admin,
**I want** consulter l'historique de tous les documents generes,
**so that** je puisse retrouver rapidement un document genere precedemment.

**Acceptance Criteria** :
1. Un ecran "Historique des documents" affiche un tableau avec les colonnes decrites dans FR47
2. Les filtres decrits dans FR46 sont disponibles
3. Une recherche par numero unique ou matricule est disponible
4. Le tableau est pagine (20 documents par page)
5. Un badge "Reimpression" s'affiche si le document a deja ete reimprime

**Dependances** : Story 8.1

---

#### Story 8.3 : Reimpression d'un Document

**As an** Admin,
**I want** reimprimer un document genere precedemment,
**so that** je puisse fournir une copie si le document original est perdu.

**Acceptance Criteria** :
1. Sur chaque ligne de l'historique, un bouton "Reimprimer" est disponible
2. Le clic sur "Reimprimer" ouvre une modal de confirmation
3. Apres confirmation, le systeme :
   - Incremente le compteur `reprint_count`
   - Genere un nouveau PDF identique (memes donnees, meme numero unique)
   - Ajoute une mention "REIMPRESSION N X" sur le document (watermark ou texte en footer)
4. Un message de succes s'affiche
5. Le bouton "Telecharger le PDF" permet le telechargement immediat
6. Le badge "Reimpression" dans l'historique se met a jour

**Dependances** : Story 8.2

---

### Epic 9 : Configuration des Templates par Tenant

**Goal detaille** : Permettre a chaque etablissement de personnaliser les templates (logo, signature, tampon, baremes de mentions, textes des attestations).

#### Story 9.1 : Configuration de l'En-tete et de l'Identite de l'Etablissement

**As an** Admin,
**I want** configurer les informations de mon etablissement,
**so that** mes documents officiels affichent les bonnes informations.

**Acceptance Criteria** :
1. Un ecran "Configuration des documents" affiche un onglet "En-tete et Identite" :
   - Upload logo etablissement (image, formats : PNG, JPG, max 1 MB)
   - Nom de l'etablissement (texte, obligatoire)
   - Type (select : CEG, Lycee, Lycee Technique, obligatoire)
   - Adresse de l'etablissement (textarea, obligatoire)
   - Telephone (texte)
   - Boite postale (texte)
2. Une previsualisation du logo est affichee apres upload
3. Les configurations sont sauvegardees dans la table `document_settings`
4. Un bouton "Reinitialiser par defaut" restaure les valeurs par defaut

**Dependances** : Aucune

---

#### Story 9.2 : Configuration de la Signature et du Tampon

**As an** Admin,
**I want** configurer la signature et le tampon de mon etablissement,
**so that** mes documents officiels soient authentifies visuellement.

**Acceptance Criteria** :
1. Un onglet "Signature et Tampon" affiche :
   - Titre du signataire (texte, ex: "Le Proviseur", "Le Principal", obligatoire)
   - Nom du signataire (texte, obligatoire)
   - Upload image de signature (image, max 500 KB, optionnel)
   - Upload tampon de l'etablissement (image, max 500 KB, optionnel)
2. Des previsualisations sont affichees apres upload
3. Si aucune image de signature n'est uploadee, seul le texte est affiche sur les documents
4. Si aucun tampon n'est uploade, aucun tampon n'est affiche

**Dependances** : Story 9.1

---

#### Story 9.3 : Configuration des Baremes de Mentions

**As an** Admin,
**I want** configurer les seuils de mentions pour mon etablissement,
**so that** les mentions soient attribuees selon nos criteres propres.

**Acceptance Criteria** :
1. Un onglet "Baremes des Mentions" affiche :
   - Seuil Felicitations (input numerique, defaut : 16/20)
   - Seuil Tableau d'honneur (input numerique, defaut : 14/20)
   - Seuil Encouragements (input numerique, defaut : 12/20)
2. Le systeme valide que Felicitations > Tableau d'honneur > Encouragements
3. Les seuils sont utilises automatiquement lors de la generation des bulletins
4. Un bouton "Valeurs par defaut" restaure les seuils standards

**Dependances** : Story 9.1

---

#### Story 9.4 : Personnalisation des Textes d'Attestations

**As an** Admin,
**I want** personnaliser le texte des attestations et certificats pour mon etablissement,
**so that** je puisse adapter le contenu selon mes besoins.

**Acceptance Criteria** :
1. Un onglet "Textes des Attestations" affiche :
   - Texte attestation de scolarite (textarea avec compteur de caracteres)
   - Texte attestation d'inscription (textarea)
   - Texte certificat de scolarite / exeat (textarea)
2. Une aide affiche les variables disponibles : {nom_etablissement}, {nom_prenom_eleve}, {matricule}, {date_naissance}, {lieu_naissance}, {classe}, {annee_scolaire}, {date}, {signature_titre}
3. Un bouton "Texte par defaut" restaure le texte standard pour chaque type
4. Les textes personnalises sont utilises lors de la generation

**Dependances** : Story 9.1

---

#### Story 9.5 : Previsualisation des Documents avec Configurations

**As an** Admin,
**I want** previsualiser les documents avec mes configurations actuelles,
**so that** je puisse verifier le rendu avant de generer des documents officiels.

**Acceptance Criteria** :
1. Sur l'ecran de configuration, une section "Previsualisation" affiche :
   - Un bulletin semestriel avec donnees fictives et configurations actuelles (logo, signature, tampon, baremes)
   - Une attestation de scolarite avec donnees fictives et texte personnalise
2. La previsualisation se met a jour apres clic sur "Actualiser la previsualisation"
3. Un bouton "Telecharger PDF de test" genere un PDF de test pour verifier le rendu final

**Dependances** : Story 9.1, Story 9.2, Story 9.3, Story 9.4

---

## 7. Checklist Results Report

**A completer apres implementation** : Executer le checklist PM pour valider :
- [ ] Tous les requirements fonctionnels sont implementes (FR1 a FR52)
- [ ] Generation de 60 bulletins semestriels en < 2 minutes (performance validee)
- [ ] Qualite professionnelle des PDFs (rendu conforme aux standards nigeriens)
- [ ] Bulletin semestriel avec notes, moyennes, rang, appreciations, mentions, absences, avertissements
- [ ] Bulletin annuel avec recapitulatif S1/S2 et decision du conseil de classe
- [ ] Attestations et certificats personnalisables par tenant
- [ ] Cartes scolaires avec photo et generation en masse
- [ ] Releve de notes annuel detaille
- [ ] Recus de paiement generes automatiquement
- [ ] Bulletins de paie generes individuellement et en masse
- [ ] Templates configurables par tenant (logo, signature, tampon, baremes, textes)
- [ ] Historique complet avec tracabilite et reimpression
- [ ] Generation en masse asynchrone avec progression
- [ ] Telechargement ZIP fonctionnel
- [ ] Permissions appliquees (Admin, Comptable)
- [ ] Interface responsive et accessible (WCAG AA)
- [ ] Tests unitaires, d'integration et de performance valides

---

## 8. Next Steps

### 8.1 UX Expert Prompt

> "Creez les maquettes UI pour le Module Documents Officiels en vous basant sur ce PRD. Focus sur les ecrans critiques : Generation bulletins semestriels par classe (Selection classe --> Previsualisation --> Generation en masse avec barre de progression --> Telechargement ZIP), Generation bulletin individuel, Generation attestation/certificat, Historique des documents avec filtres, Configuration des templates (logo, signature, baremes mentions). Creez egalement des maquettes des templates PDF : bulletin semestriel (avec toutes les matieres, moyennes, rang, appreciations, mentions, absences, avertissements), bulletin annuel (recapitulatif S1/S2 avec decision), attestation de scolarite, carte scolaire (recto/verso), recu de paiement. Les templates doivent avoir un aspect officiel conforme au contexte de l'Education Nationale du Niger."

### 8.2 Architect Prompt

> "Concevez l'architecture technique du Module Documents Officiels en suivant les patterns etablis dans le module UsersGuard. Definissez les tables de base de donnees (documents, document_settings), les models Eloquent avec relations, le service PdfGeneratorService avec DomPDF, le service DocumentNumberService pour generation de numeros uniques, le service BulletinDataService pour agglomerer les donnees des bulletins depuis les modules Notes/ConseilDeClasse/Presences/Discipline, le Job asynchrone GenerateBatchBulletinsJob avec Laravel Queues, l'integration avec le Module Comptabilite (event listener pour generation automatique des recus de paiement), l'integration avec le Module Paie (generation des bulletins de paie), et les tests de performance pour valider la generation de 60 bulletins en < 2 minutes. Les 9 types de documents a supporter sont : bulletin semestriel, bulletin annuel, attestation de scolarite, attestation d'inscription, certificat/exeat, carte scolaire, releve de notes annuel, recu de paiement, bulletin de paie."

---

**Document cree par** : John (Product Manager Agent)
**Date** : 2026-03-16
**Version** : v5
**Statut** : Draft pour review
