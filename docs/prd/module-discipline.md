# PRD - Module Discipline

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Module** : Discipline (Gestion Disciplinaire)
> **Version** : 1.0
> **Date** : 2026-03-16
> **Phase** : Phase 2 - Vie Scolaire & Operations
> **Priorite** : HAUTE 🟠

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 1.0 | Creation initiale du PRD Module Discipline | John (PM) |

---

## 1. Goals and Background Context

### 1.1 Goals

- **Numeriser le suivi disciplinaire** : Remplacer les registres papier de discipline par un systeme centralise permettant l'enregistrement, le suivi et l'analyse de tous les incidents disciplinaires
- **Hierarchiser les sanctions** : Implementer la hierarchie officielle des sanctions (observation verbale, avertissement ecrit, blame, exclusion temporaire, exclusion definitive) avec des workflows de validation adaptes
- **Centraliser le dossier disciplinaire par eleve** : Fournir une vue complete de l'historique disciplinaire de chaque eleve, accessible au Surveillant General, a la direction et aux enseignants concernes
- **Automatiser la notification des parents** : Alerter automatiquement les parents par email lors de sanctions ecrites, avec detail de l'incident et possibilite de demande de rendez-vous
- **Outiller le Conseil de Discipline** : Fournir les outils pour convoquer, constituer le dossier, tenir le conseil de discipline et notifier la decision
- **Produire des statistiques disciplinaires** : Generer des rapports par classe, par periode, par type d'incident pour piloter la politique disciplinaire de l'etablissement
- **Impacter le bulletin scolaire** : Permettre l'integration du statut disciplinaire dans le bulletin semestriel (mention comportement)

### 1.2 Background Context

Le **Module Discipline** s'inscrit dans la **Phase 2 - Vie Scolaire & Operations** du projet Gestion Scolaire. Il est essentiel pour les etablissements d'enseignement secondaire au Niger (colleges et lycees) ou la gestion de la discipline est une responsabilite quotidienne du **Surveillant General**.

**Situation actuelle et problemes** :
- Les incidents disciplinaires sont enregistres dans des **registres papier** tenus par le Surveillant General
- L'**historique disciplinaire** d'un eleve est difficile a reconstituer (registres multiples, annees differentes)
- Les **parents sont informes tardivement**, souvent via des convocations papier transmises par l'eleve (frequemment perdues ou non remises)
- Les **statistiques disciplinaires** sont quasi inexistantes, empechant un pilotage eclaire de la politique disciplinaire
- Le **Conseil de Discipline** est organise de maniere informelle, sans outils numeriques pour la constitution du dossier ou la generation du proces-verbal
- Le lien entre **discipline et bulletin scolaire** est manuel et sujet a erreurs

**Dependances avec d'autres modules** :
- **Module Inscriptions** : Acces aux donnees des eleves (identite, classe, parents/tuteurs)
- **Module Structure Academique** : Acces aux classes, annees scolaires, semestres
- **Module UsersGuard** : Authentification, roles (Surveillant General, Enseignant, Admin/Directeur, Parent)
- **Module Documents Officiels** : Integration de la mention comportement dans les bulletins semestriels
- **Module Portail Parent** : Affichage des notifications de sanctions et historique disciplinaire

**Pain points resolus** :
1. Elimination des registres papier de discipline
2. Notification instantanee des parents en cas de sanction
3. Historique disciplinaire complet et accessible en un clic
4. Statistiques disciplinaires automatiques pour pilotage
5. Conseil de discipline dematerialise avec dossier et PV numeriques

---

## 2. Requirements

### 2.1 Functional Requirements (FR)

#### 2.1.1 Types de Sanctions (Hierarchie)

- **FR1** : Le systeme doit implementer la hierarchie officielle des sanctions suivante (par ordre de gravite croissante) :
  1. Observation verbale (note au dossier uniquement)
  2. Avertissement ecrit
  3. Blame
  4. Exclusion temporaire (1 a 8 jours, duree configurable)
  5. Exclusion definitive (uniquement decidee par le Conseil de Discipline)
- **FR2** : Chaque sanction doit comporter obligatoirement : date, description de l'incident, type de sanction, rapporteur (enseignant ou surveillant), et optionnellement une duree (pour les exclusions temporaires)
- **FR3** : Le systeme doit permettre a l'Admin de configurer la duree maximale d'exclusion temporaire (defaut : 8 jours)
- **FR4** : L'exclusion definitive ne doit pouvoir etre enregistree que via le workflow du Conseil de Discipline (pas de saisie directe)
- **FR5** : Le systeme doit permettre de definir des types de sanctions supplementaires propres a l'etablissement (configurable par tenant)

#### 2.1.2 Types d'Incidents

- **FR6** : Le systeme doit proposer une liste predefinies de types d'incidents :
  - Bagarre / violence physique
  - Insolence / irrespect envers un enseignant ou le personnel
  - Tricherie / fraude lors d'une evaluation
  - Retards repetes
  - Absence injustifiee repetee
  - Degradation de materiel
  - Vol
  - Comportement perturbateur en classe
  - Non-respect du reglement interieur
  - Usage de telephone portable
  - Autre (avec champ de precision obligatoire)
- **FR7** : Le systeme doit permettre a l'Admin de configurer la liste des types d'incidents (ajout, modification, desactivation)
- **FR8** : Chaque type d'incident peut avoir un niveau de gravite associe (Mineur, Moyen, Grave, Tres grave) pour aider a la categorisation

#### 2.1.3 Enregistrement des Incidents

- **FR9** : Le systeme doit permettre aux Enseignants et au Surveillant General d'enregistrer un incident disciplinaire via un formulaire comprenant :
  - Date et heure de l'incident (obligatoire)
  - Lieu de l'incident (en classe, dans la cour, a la cantine, etc.)
  - Description detaillee de l'incident (texte libre, obligatoire)
  - Eleve(s) implique(s) (selection multiple, obligatoire, au moins 1 eleve)
  - Type d'incident (selection dans la liste predefinies, obligatoire)
  - Sanction decidee (selection dans la hierarchie, obligatoire)
  - Rapporteur (pre-rempli avec l'utilisateur connecte, modifiable pour le Surveillant General)
  - Duree d'exclusion (si sanction = exclusion temporaire, en jours, 1-8)
  - Pieces jointes optionnelles (photos, documents, max 3 fichiers, 5 MB chacun)
  - Notes/commentaires internes (visibles uniquement par le personnel)
- **FR10** : Si plusieurs eleves sont impliques dans le meme incident, un enregistrement d'incident unique doit etre cree avec un lien vers chaque eleve concerne, mais la sanction peut differer par eleve
- **FR11** : Un incident enregistre par un enseignant doit avoir le statut "En attente de validation" jusqu'a validation par le Surveillant General ou la direction
- **FR12** : Le Surveillant General et la direction peuvent enregistrer un incident avec le statut "Valide" directement
- **FR13** : Le systeme doit permettre de modifier un incident non valide (par le rapporteur original) ou un incident valide (par le Surveillant General ou la direction uniquement)
- **FR14** : Le systeme doit empecher la suppression d'un incident une fois valide (soft delete uniquement pour les incidents non valides, avec autorisation direction)

#### 2.1.4 Validation des Incidents

- **FR15** : Le Surveillant General doit avoir acces a une file d'attente des incidents "En attente de validation"
- **FR16** : Lors de la validation, le Surveillant General peut : approuver tel quel, modifier la sanction proposee, ou rejeter l'incident (avec motif obligatoire)
- **FR17** : La validation d'un incident declenche automatiquement les actions suivantes :
  - Mise a jour du dossier disciplinaire de l'eleve
  - Notification aux parents (si sanction ecrite ou superieure)
  - Mise a jour du statut disciplinaire de l'eleve

#### 2.1.5 Dossier Disciplinaire par Eleve

- **FR18** : Le systeme doit fournir un dossier disciplinaire complet par eleve comprenant :
  - Informations de l'eleve (nom, classe, photo, numero matricule)
  - Historique complet de tous les incidents et sanctions (ordre chronologique inverse)
  - Resume quantitatif : nombre de sanctions par type (observations, avertissements, blames, exclusions)
  - Statut disciplinaire actuel (calcule automatiquement)
  - Impact sur le bulletin (mention comportement)
- **FR19** : Le statut disciplinaire d'un eleve doit etre calcule automatiquement selon les regles suivantes (configurables par l'Admin) :
  - **Bon** : 0 avertissement ecrit, 0 blame, 0 exclusion
  - **Avertissement conduite** : 1-2 avertissements ecrits ou 1 blame
  - **Blame conduite** : 3+ avertissements ecrits ou 2+ blames ou 1+ exclusion temporaire
  - **Exclusion** : Exclusion definitive prononcee par le Conseil de Discipline
- **FR20** : Le statut disciplinaire doit pouvoir etre reinitialise au debut de chaque annee scolaire (configurable : reinitialisation automatique ou manuelle)
- **FR21** : Le dossier disciplinaire doit etre exportable en PDF (pour les conseils de discipline, les transferts, etc.)
- **FR22** : Le dossier disciplinaire doit etre accessible au Surveillant General, a la direction, et aux enseignants de la classe de l'eleve (en lecture seule pour les enseignants)

#### 2.1.6 Notification des Parents

- **FR23** : Le systeme doit envoyer automatiquement un email de notification aux parents/tuteurs lors de toute sanction ecrite (avertissement ecrit, blame, exclusion temporaire)
- **FR24** : L'email de notification doit contenir :
  - Nom de l'eleve et classe
  - Date et description de l'incident
  - Type de sanction prononcee
  - Duree d'exclusion (si applicable)
  - Nom de l'etablissement
  - Coordonnees de contact du Surveillant General
  - Lien vers le portail parent pour consulter le dossier complet
- **FR25** : Le systeme doit permettre de joindre une demande de rendez-vous dans la notification (checkbox activable par le Surveillant General lors de la validation)
- **FR26** : Le systeme doit tracer l'envoi de la notification (date d'envoi, statut : envoye, echoue, lu)
- **FR27** : Le systeme doit permettre de renvoyer manuellement une notification en cas d'echec d'envoi
- **FR28** : Le Surveillant General doit pouvoir desactiver la notification automatique pour un incident specifique (cas particuliers)

#### 2.1.7 Conseil de Discipline

- **FR29** : Le systeme doit permettre de convoquer un Conseil de Discipline avec les informations suivantes :
  - Eleve concerne
  - Date et heure de la seance
  - Lieu de la seance
  - Motif de convocation (resume)
  - Membres convies (liste configurable : Directeur/Proviseur, Censeur, Surveillant General, enseignants rapporteurs, representant des parents APE, representant des eleves)
- **FR30** : Le systeme doit generer automatiquement les convocations formelles (PDF) pour :
  - L'eleve concerne
  - Les parents/tuteurs de l'eleve
  - Chaque membre du conseil
- **FR31** : Le systeme doit permettre de constituer le dossier du Conseil de Discipline comprenant :
  - Historique disciplinaire complet de l'eleve
  - Detail des incidents ayant motive la convocation
  - Rapports des enseignants concernes
  - Bulletin scolaire de l'eleve (lien vers Module Documents)
  - Toute piece justificative (photos, documents)
- **FR32** : Le systeme doit permettre de saisir le proces-verbal (PV) de la seance :
  - Date et heure effective de la seance
  - Membres presents / absents
  - Resume des debats
  - Decision prise : acquittement, sanction intermediaire (blame, exclusion temporaire), ou exclusion definitive
  - Duree d'exclusion (si applicable)
  - Vote (nombre de voix pour, contre, abstentions)
  - Signatures (champ texte pour les noms des signataires)
- **FR33** : Le systeme doit generer le PV en format PDF avec mise en page officielle
- **FR34** : Le systeme doit enregistrer la decision du Conseil de Discipline comme sanction dans le dossier de l'eleve
- **FR35** : Apres decision, le systeme doit generer une notification officielle de la decision aux parents (PDF + email)
- **FR36** : Le systeme doit gerer le workflow complet du Conseil de Discipline avec les statuts suivants : Planifie, Convocations envoyees, En seance, Decision rendue, Cloture

#### 2.1.8 Rapports et Statistiques

- **FR37** : Le systeme doit generer les rapports statistiques suivants :
  - Nombre d'incidents par classe, par periode (mois, semestre, annee)
  - Types d'incidents les plus frequents (top 10 avec graphique)
  - Eleves les plus sanctionnes (top N, configurable)
  - Evolution temporelle des incidents (courbe mensuelle)
  - Repartition des sanctions par type (graphique circulaire)
  - Comparaison entre classes (taux d'incidents par effectif)
  - Taux de recidive (eleves avec plus de N sanctions)
- **FR38** : Les rapports doivent etre filtrables par : annee scolaire, semestre, classe, type d'incident, type de sanction, periode personnalisee
- **FR39** : Les rapports doivent etre exportables en Excel et PDF
- **FR40** : Le dashboard disciplinaire doit afficher les indicateurs cles en temps reel :
  - Nombre d'incidents ce mois-ci (avec comparaison mois precedent)
  - Nombre d'incidents en attente de validation
  - Nombre de conseils de discipline planifies
  - Top 3 types d'incidents
  - Classes les plus concernees

---

### 2.2 Non-Functional Requirements (NFR)

- **NFR1** : L'enregistrement d'un incident (avec pieces jointes) doit se faire en moins de 2 minutes par l'utilisateur
- **NFR2** : L'affichage du dossier disciplinaire d'un eleve (avec historique complet) doit se faire en moins de 1 seconde
- **NFR3** : La notification email aux parents doit etre envoyee dans les 5 minutes suivant la validation de l'incident
- **NFR4** : La generation du PV de Conseil de Discipline en PDF doit se faire en moins de 5 secondes
- **NFR5** : Le systeme doit supporter jusqu'a 10 000 incidents par annee scolaire par tenant sans degradation de performance
- **NFR6** : Les pieces jointes doivent etre limitees a 3 fichiers par incident, 5 MB par fichier, formats PDF/JPG/PNG
- **NFR7** : L'historique disciplinaire doit etre conserve pendant toute la scolarite de l'eleve dans l'etablissement (minimum 7 ans)
- **NFR8** : Les donnees disciplinaires doivent etre isolees par tenant (multi-tenant) et protegees (donnees sensibles de mineurs)
- **NFR9** : Un journal d'audit doit tracer toutes les actions sur les incidents et sanctions (creation, modification, validation, suppression)
- **NFR10** : Le module doit etre accessible en mode responsive (tablette et desktop) pour le Surveillant General en mobilite dans l'etablissement

---

## 3. User Interface Design Goals

### 3.1 Overall UX Vision

L'interface du Module Discipline doit etre **rapide, claire et professionnelle**. Le Surveillant General, utilisateur principal, doit pouvoir enregistrer un incident en moins de 2 minutes et acceder au dossier d'un eleve en un clic. L'interface doit vehiculer une impression de rigueur et de tracabilite, essentielle pour des donnees a caractere sensible.

**Principes cles** :
- **Rapidite de saisie** : Formulaires optimises avec champs pre-remplis, listes deroulantes, auto-completion
- **Visibilite du statut** : Codes couleur pour les niveaux de gravite et les statuts de validation
- **Acces contextuel** : Depuis n'importe quel ecran, acces rapide au dossier disciplinaire d'un eleve
- **Tracabilite** : Chaque action est horodatee et attribuee a son auteur

### 3.2 Key Interaction Paradigms

- **Saisie rapide d'incident** : Formulaire en etapes (1. Selectionner l'eleve, 2. Decrire l'incident, 3. Choisir la sanction, 4. Valider)
- **File d'attente de validation** : Liste triee par date avec actions rapides (Valider / Modifier / Rejeter)
- **Dossier eleve en un clic** : Recherche par nom, classe, ou matricule avec acces direct au dossier
- **Dashboard en temps reel** : Indicateurs cles actualises automatiquement

### 3.3 Core Screens and Views

#### 3.3.1 Ecran : Dashboard Discipline (Surveillant General / Admin)
- Indicateurs cles :
  - Nombre d'incidents du mois (avec tendance vs mois precedent)
  - Incidents en attente de validation (badge rouge)
  - Conseils de discipline planifies
  - Top 3 types d'incidents (barres horizontales)
- Graphique : Evolution mensuelle des incidents (courbe sur 12 mois)
- Raccourcis : "Enregistrer un incident", "Incidents en attente", "Dossiers eleves", "Rapports"
- Liste des 5 derniers incidents enregistres

#### 3.3.2 Ecran : Enregistrement d'un Incident
- Formulaire structure en sections :
  - **Section 1 - Eleve(s)** : Champ de recherche avec auto-completion (nom, matricule), affichage des eleves selectionnes avec photo et classe
  - **Section 2 - Incident** : Date/heure (pre-rempli avec maintenant), lieu (select), type d'incident (select), description (textarea)
  - **Section 3 - Sanction** : Type de sanction (select hierarchique avec indicateur de gravite), duree si exclusion (nombre de jours), notes internes (textarea)
  - **Section 4 - Pieces jointes** : Zone de drag & drop pour fichiers (max 3, 5 MB chacun)
- Boutons : "Enregistrer" (sauvegarde en attente de validation), "Enregistrer et valider" (Surveillant General uniquement)

#### 3.3.3 Ecran : File d'Attente de Validation
- Tableau avec colonnes : Date, Eleve(s), Type d'incident, Sanction proposee, Rapporteur, Actions
- Actions par ligne : "Valider", "Modifier et valider", "Rejeter"
- Filtres : Periode, classe, type d'incident
- Badge rouge dans la navigation indiquant le nombre d'incidents en attente

#### 3.3.4 Ecran : Liste des Incidents
- Tableau avec colonnes : Date, Eleve(s), Classe, Type d'incident, Sanction, Rapporteur, Statut (Valide/En attente/Rejete), Actions
- Filtres : Annee scolaire, semestre, classe, type d'incident, type de sanction, statut, rapporteur
- Recherche par nom d'eleve ou description
- Tri par date (decroissant par defaut)
- Export Excel et PDF

#### 3.3.5 Ecran : Dossier Disciplinaire d'un Eleve
- En-tete : Photo, Nom complet, Classe, Matricule, Statut disciplinaire (badge colore)
- Resume :
  - Nombre de sanctions par type (compteurs avec icones)
  - Graphique radar de repartition des types d'incidents
- Historique : Timeline verticale des incidents (du plus recent au plus ancien)
  - Chaque entree affiche : date, type d'incident, sanction, rapporteur, description resumee
  - Clic pour voir le detail complet
- Actions : "Exporter le dossier (PDF)", "Enregistrer un nouvel incident"

#### 3.3.6 Ecran : Conseil de Discipline
- Sous-ecrans :
  1. **Planification** : Formulaire de creation avec eleve, date, lieu, membres
  2. **Dossier** : Vue du dossier constitue (historique + rapports + pieces)
  3. **Convocations** : Liste des convocations avec statut (generee, envoyee) et boutons de telechargement PDF
  4. **Seance** : Formulaire du PV (presents/absents, resume debats, decision, vote)
  5. **Decision** : Resume de la decision avec bouton de notification aux parents

#### 3.3.7 Ecran : Rapports et Statistiques
- Filtres en haut : Annee scolaire, semestre, classe, periode personnalisee
- Graphiques :
  - Evolution mensuelle des incidents (line chart)
  - Repartition par type d'incident (pie chart)
  - Repartition par type de sanction (bar chart)
  - Comparaison entre classes (bar chart horizontal)
- Tableaux detailles avec export Excel/PDF

#### 3.3.8 Ecran Enseignant : Signaler un Incident
- Formulaire simplifie : Eleve(s), date/heure, type d'incident, description, sanction proposee
- Liste de "Mes signalements" avec statuts (En attente, Valide, Rejete)

#### 3.3.9 Ecran Parent (Portail Parent) : Discipline
- Vue en lecture seule du dossier disciplinaire de l'enfant
- Notifications recues (liste)
- Detail de chaque incident et sanction

### 3.4 Accessibility

**WCAG AA** : Le systeme doit respecter les standards d'accessibilite WCAG 2.1 niveau AA :
- Navigation au clavier complete (formulaires, tableaux, actions)
- Labels ARIA pour les statuts de gravite et les badges colores
- Contraste de couleurs suffisant (ratio 4.5:1 minimum)
- Messages d'erreur clairs et accessibles aux lecteurs d'ecran
- Icones accompagnees de texte pour les indicateurs de gravite

### 3.5 Branding

- Interface professionnelle et sobre, adaptee au caractere sensible des donnees
- Couleurs par niveau de gravite des sanctions :
  - **Gris (#9E9E9E)** : Observation verbale
  - **Jaune (#FFC107)** : Avertissement ecrit
  - **Orange (#FF9800)** : Blame
  - **Rouge (#F44336)** : Exclusion temporaire
  - **Rouge fonce (#B71C1C)** : Exclusion definitive
- Couleurs par statut de validation :
  - **Vert (#4CAF50)** : Valide
  - **Orange (#FF9800)** : En attente
  - **Rouge (#F44336)** : Rejete
- Couleurs par statut disciplinaire eleve :
  - **Vert (#4CAF50)** : Bon
  - **Jaune (#FFC107)** : Avertissement conduite
  - **Orange (#FF9800)** : Blame conduite
  - **Rouge (#F44336)** : Exclusion

### 3.6 Target Device and Platforms

**Web Responsive** :
- Desktop (prioritaire) : Gestion complete, rapports, conseil de discipline
- Tablette : Enregistrement d'incidents (Surveillant General en mobilite), consultation de dossiers
- Mobile : Consultation uniquement (enseignants signalant un incident, parents consultant les notifications)

---

## 4. Technical Assumptions

### 4.1 Repository Structure

**Polyrepo** : Backend (Laravel) et Frontend (Next.js) dans des depots separes (architecture existante).

### 4.2 Service Architecture

**Architecture modulaire (nwidart/laravel-modules)** :
- Nouveau module Laravel : `Modules/Discipline/`
- Structure standard :
  - `Entities/` : Models Eloquent (Incident, Sanction, DisciplinaryCouncil, CouncilConvocation, CouncilMinutes, IncidentAttachment, IncidentType, SanctionType, ParentNotification)
  - `Http/Controllers/Admin/` : Controllers pour Surveillant General et Admin
  - `Http/Controllers/Teacher/` : Controllers pour Enseignants
  - `Http/Requests/` : Form Requests pour validation
  - `Http/Resources/` : API Resources
  - `Database/Migrations/tenant/` : Migrations tenant
  - `Database/Factories/` : Factories pour tests
  - `Database/Seeders/` : Seeders (types d'incidents, types de sanctions)
  - `Services/` : Services metier (DisciplinaryStatusService, NotificationService, StatisticsService, CouncilService)
  - `Enums/` : Enums (SanctionLevel, IncidentSeverity, ValidationStatus, CouncilStatus)
  - `Notifications/` : Notifications Laravel (ParentSanctionNotification, CouncilConvocationNotification, CouncilDecisionNotification)
  - `Exports/` : Classes d'export Excel (IncidentsExport, DisciplinaryReportExport)
  - `Routes/` : Routes admin.php, teacher.php

**Frontend Next.js** :
- Nouveau module : `src/modules/Discipline/`
- Pages Admin : `/admin/discipline/dashboard`, `/admin/discipline/incidents`, `/admin/discipline/dossiers`, `/admin/discipline/conseil`, `/admin/discipline/rapports`
- Pages Enseignant : `/teacher/discipline/signaler`, `/teacher/discipline/mes-signalements`
- Pages Parent (Portail) : `/parent/discipline`

### 4.3 Base de Donnees

**Connexion** : `tenant` (base de donnees tenant dynamique)

**Tables a creer** :

#### Table `incident_types`
Configuration des types d'incidents.
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
name                VARCHAR(255) NOT NULL         -- Ex: "Bagarre", "Tricherie"
slug                VARCHAR(255) UNIQUE NOT NULL   -- Ex: "bagarre", "tricherie"
severity            ENUM('minor','medium','serious','very_serious') NOT NULL
description         TEXT NULLABLE
is_active           BOOLEAN DEFAULT TRUE
sort_order          INTEGER DEFAULT 0
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE             -- SoftDeletes
```

#### Table `sanction_types`
Configuration des types de sanctions (hierarchie).
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
name                VARCHAR(255) NOT NULL          -- Ex: "Observation verbale", "Avertissement ecrit"
slug                VARCHAR(255) UNIQUE NOT NULL
level               TINYINT UNSIGNED NOT NULL       -- 1=Observation, 2=Avertissement, 3=Blame, 4=Exclusion temp, 5=Exclusion def
requires_council    BOOLEAN DEFAULT FALSE           -- true pour exclusion definitive
requires_notification BOOLEAN DEFAULT FALSE         -- true pour avert ecrit, blame, exclusions
min_duration_days   INTEGER NULLABLE                -- Pour exclusions temporaires
max_duration_days   INTEGER NULLABLE                -- Pour exclusions temporaires (defaut 8)
description         TEXT NULLABLE
is_active           BOOLEAN DEFAULT TRUE
sort_order          INTEGER DEFAULT 0
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE
```

#### Table `incidents`
Enregistrement des incidents disciplinaires.
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
reference           VARCHAR(50) UNIQUE NOT NULL     -- Ex: "INC-2026-0001"
incident_type_id    BIGINT UNSIGNED, FK -> incident_types(id)
incident_date       DATETIME NOT NULL
location            VARCHAR(255) NULLABLE           -- Lieu de l'incident
description         TEXT NOT NULL
reported_by         BIGINT UNSIGNED, FK -> users(id) -- Enseignant/Surveillant rapporteur
validated_by        BIGINT UNSIGNED NULLABLE, FK -> users(id)
validated_at        DATETIME NULLABLE
validation_status   ENUM('pending','validated','rejected') DEFAULT 'pending'
rejection_reason    TEXT NULLABLE
internal_notes      TEXT NULLABLE                   -- Notes visibles uniquement par le personnel
academic_year_id    BIGINT UNSIGNED, FK -> academic_years(id)
semester_id         BIGINT UNSIGNED NULLABLE, FK -> semesters(id)
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE
```

#### Table `incident_students`
Table pivot : eleves impliques dans un incident, avec sanction individualisee.
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
incident_id         BIGINT UNSIGNED, FK -> incidents(id) ON DELETE CASCADE
student_id          BIGINT UNSIGNED, FK -> students(id)
sanction_type_id    BIGINT UNSIGNED, FK -> sanction_types(id)
exclusion_days      INTEGER NULLABLE                -- Nombre de jours si exclusion temporaire
exclusion_start     DATE NULLABLE
exclusion_end       DATE NULLABLE
notes               TEXT NULLABLE                   -- Notes specifiques a cet eleve
created_at          TIMESTAMP
updated_at          TIMESTAMP

UNIQUE INDEX idx_incident_student (incident_id, student_id)
```

#### Table `incident_attachments`
Pieces jointes associees a un incident.
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
incident_id         BIGINT UNSIGNED, FK -> incidents(id) ON DELETE CASCADE
file_path           VARCHAR(500) NOT NULL
file_name           VARCHAR(255) NOT NULL
file_type           VARCHAR(50) NOT NULL            -- mime type
file_size           INTEGER UNSIGNED NOT NULL        -- en octets
uploaded_by         BIGINT UNSIGNED, FK -> users(id)
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### Table `parent_notifications`
Suivi des notifications envoyees aux parents.
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
incident_id         BIGINT UNSIGNED, FK -> incidents(id)
incident_student_id BIGINT UNSIGNED, FK -> incident_students(id)
parent_id           BIGINT UNSIGNED, FK -> users(id) -- Parent/tuteur
notification_type   ENUM('sanction','council_convocation','council_decision') NOT NULL
channel             ENUM('email','sms','portal') DEFAULT 'email'
subject             VARCHAR(500) NOT NULL
body                TEXT NOT NULL
sent_at             DATETIME NULLABLE
read_at             DATETIME NULLABLE
status              ENUM('pending','sent','failed','read') DEFAULT 'pending'
failure_reason      TEXT NULLABLE
meeting_requested   BOOLEAN DEFAULT FALSE           -- Demande de RDV
meeting_date        DATETIME NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### Table `disciplinary_councils`
Gestion des Conseils de Discipline.
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
reference           VARCHAR(50) UNIQUE NOT NULL     -- Ex: "CD-2026-0001"
student_id          BIGINT UNSIGNED, FK -> students(id)
scheduled_date      DATETIME NOT NULL
location            VARCHAR(255) NOT NULL
reason              TEXT NOT NULL                    -- Motif de convocation
status              ENUM('planned','convocations_sent','in_session','decision_rendered','closed') DEFAULT 'planned'
convened_by         BIGINT UNSIGNED, FK -> users(id) -- Directeur/Admin
academic_year_id    BIGINT UNSIGNED, FK -> academic_years(id)
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP NULLABLE
```

#### Table `council_members`
Membres du Conseil de Discipline.
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
council_id          BIGINT UNSIGNED, FK -> disciplinary_councils(id) ON DELETE CASCADE
user_id             BIGINT UNSIGNED NULLABLE, FK -> users(id) -- null si membre externe
member_name         VARCHAR(255) NOT NULL
member_role         VARCHAR(255) NOT NULL            -- Ex: "Proviseur", "Representant APE"
is_present          BOOLEAN NULLABLE                 -- null = pas encore determine
convocation_sent    BOOLEAN DEFAULT FALSE
convocation_sent_at DATETIME NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### Table `council_minutes`
Proces-verbal du Conseil de Discipline.
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
council_id          BIGINT UNSIGNED, FK -> disciplinary_councils(id) UNIQUE
session_date        DATETIME NOT NULL                -- Date/heure effective
summary             TEXT NOT NULL                     -- Resume des debats
decision            ENUM('acquittal','intermediate_sanction','definitive_exclusion') NOT NULL
sanction_type_id    BIGINT UNSIGNED NULLABLE, FK -> sanction_types(id) -- Si sanction intermediaire
exclusion_days      INTEGER NULLABLE                  -- Si exclusion temporaire
votes_for           INTEGER UNSIGNED DEFAULT 0
votes_against       INTEGER UNSIGNED DEFAULT 0
votes_abstention    INTEGER UNSIGNED DEFAULT 0
signatories         TEXT NULLABLE                     -- Noms des signataires (JSON ou texte)
additional_notes    TEXT NULLABLE
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### Table `council_incidents`
Incidents lies a un Conseil de Discipline.
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
council_id          BIGINT UNSIGNED, FK -> disciplinary_councils(id) ON DELETE CASCADE
incident_id         BIGINT UNSIGNED, FK -> incidents(id)
created_at          TIMESTAMP

UNIQUE INDEX idx_council_incident (council_id, incident_id)
```

#### Table `discipline_settings`
Parametres de configuration du module par tenant.
```
id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
key                 VARCHAR(100) UNIQUE NOT NULL
value               TEXT NOT NULL
created_at          TIMESTAMP
updated_at          TIMESTAMP
```
Cles de configuration :
- `max_exclusion_days` : Duree max exclusion temporaire (defaut: 8)
- `auto_reset_yearly` : Reinitialisation annuelle du statut (defaut: true)
- `good_status_max_warnings` : Seuil avertissements pour statut "Bon" (defaut: 0)
- `warning_status_max_warnings` : Seuil pour statut "Avertissement conduite" (defaut: 2)
- `blame_status_max_warnings` : Seuil pour statut "Blame conduite" (defaut: 3)

**Relations cles** :
- `incidents` belongsTo `incident_types`
- `incidents` belongsToMany `students` (pivot `incident_students` avec sanction)
- `incidents` hasMany `incident_attachments`
- `incidents` hasMany `parent_notifications`
- `incident_students` belongsTo `sanction_types`
- `disciplinary_councils` belongsTo `students`
- `disciplinary_councils` hasMany `council_members`
- `disciplinary_councils` hasOne `council_minutes`
- `disciplinary_councils` belongsToMany `incidents` (pivot `council_incidents`)
- Utiliser **eager loading** systematiquement pour eviter les N+1 queries

**Index recommandes** :
- `incidents` : INDEX sur (`incident_date`), (`validation_status`), (`academic_year_id`), (`reported_by`)
- `incident_students` : INDEX sur (`student_id`), UNIQUE sur (`incident_id`, `student_id`)
- `parent_notifications` : INDEX sur (`parent_id`), (`status`)
- `disciplinary_councils` : INDEX sur (`student_id`), (`status`), (`scheduled_date`)

### 4.4 API Endpoints

#### Routes Admin (Surveillant General / Direction)

**Incidents**
```
GET    /api/admin/discipline/incidents                    -- Liste des incidents (filtres, pagination)
POST   /api/admin/discipline/incidents                    -- Creer un incident (avec validation directe)
GET    /api/admin/discipline/incidents/{id}                -- Detail d'un incident
PUT    /api/admin/discipline/incidents/{id}                -- Modifier un incident
DELETE /api/admin/discipline/incidents/{id}                -- Supprimer un incident (soft delete, non valides uniquement)
GET    /api/admin/discipline/incidents/pending             -- Incidents en attente de validation
PUT    /api/admin/discipline/incidents/{id}/validate       -- Valider un incident
PUT    /api/admin/discipline/incidents/{id}/reject         -- Rejeter un incident
POST   /api/admin/discipline/incidents/{id}/attachments    -- Ajouter des pieces jointes
DELETE /api/admin/discipline/incidents/{id}/attachments/{attachmentId} -- Supprimer une piece jointe
```

**Dossier disciplinaire eleve**
```
GET    /api/admin/discipline/students/{studentId}/record   -- Dossier disciplinaire complet d'un eleve
GET    /api/admin/discipline/students/{studentId}/record/export -- Export PDF du dossier
GET    /api/admin/discipline/students/{studentId}/status   -- Statut disciplinaire actuel
```

**Conseil de Discipline**
```
GET    /api/admin/discipline/councils                      -- Liste des conseils de discipline
POST   /api/admin/discipline/councils                      -- Planifier un conseil de discipline
GET    /api/admin/discipline/councils/{id}                  -- Detail d'un conseil
PUT    /api/admin/discipline/councils/{id}                  -- Modifier un conseil
DELETE /api/admin/discipline/councils/{id}                  -- Annuler un conseil (uniquement si statut = planned)
POST   /api/admin/discipline/councils/{id}/convocations    -- Generer et envoyer les convocations
GET    /api/admin/discipline/councils/{id}/convocations/pdf -- Telecharger les convocations en PDF
POST   /api/admin/discipline/councils/{id}/minutes         -- Enregistrer le PV
PUT    /api/admin/discipline/councils/{id}/minutes         -- Modifier le PV
GET    /api/admin/discipline/councils/{id}/minutes/pdf     -- Telecharger le PV en PDF
PUT    /api/admin/discipline/councils/{id}/status           -- Mettre a jour le statut du conseil
POST   /api/admin/discipline/councils/{id}/decision        -- Enregistrer la decision et notifier
GET    /api/admin/discipline/councils/{id}/dossier         -- Dossier constitue pour le conseil
```

**Notifications**
```
GET    /api/admin/discipline/notifications                 -- Liste des notifications envoyees
POST   /api/admin/discipline/notifications/{id}/resend     -- Renvoyer une notification echouee
GET    /api/admin/discipline/notifications/{id}             -- Detail d'une notification
```

**Rapports et Statistiques**
```
GET    /api/admin/discipline/statistics/dashboard           -- Indicateurs du dashboard
GET    /api/admin/discipline/statistics/incidents-by-class  -- Incidents par classe
GET    /api/admin/discipline/statistics/incidents-by-type   -- Incidents par type
GET    /api/admin/discipline/statistics/incidents-by-period -- Evolution temporelle
GET    /api/admin/discipline/statistics/top-sanctioned      -- Eleves les plus sanctionnes
GET    /api/admin/discipline/statistics/recidivism          -- Taux de recidive
GET    /api/admin/discipline/reports/export                 -- Export Excel du rapport
GET    /api/admin/discipline/reports/export-pdf             -- Export PDF du rapport
```

**Configuration**
```
GET    /api/admin/discipline/settings                      -- Parametres du module
PUT    /api/admin/discipline/settings                      -- Modifier les parametres
GET    /api/admin/discipline/incident-types                -- Types d'incidents configurables
POST   /api/admin/discipline/incident-types                -- Creer un type d'incident
PUT    /api/admin/discipline/incident-types/{id}            -- Modifier un type d'incident
DELETE /api/admin/discipline/incident-types/{id}            -- Desactiver un type d'incident
GET    /api/admin/discipline/sanction-types                -- Types de sanctions
```

#### Routes Enseignant

```
POST   /api/teacher/discipline/incidents                   -- Signaler un incident (statut "pending")
GET    /api/teacher/discipline/incidents                   -- Mes signalements
GET    /api/teacher/discipline/incidents/{id}               -- Detail d'un signalement
PUT    /api/teacher/discipline/incidents/{id}               -- Modifier un signalement (si non valide)
GET    /api/teacher/discipline/students/{studentId}/record  -- Dossier disciplinaire (lecture seule, eleves de ses classes uniquement)
```

#### Routes Parent (Portail)

```
GET    /api/parent/discipline/children/{studentId}/record   -- Dossier disciplinaire de l'enfant
GET    /api/parent/discipline/notifications                 -- Mes notifications disciplinaires
GET    /api/parent/discipline/notifications/{id}            -- Detail d'une notification
PUT    /api/parent/discipline/notifications/{id}/read       -- Marquer comme lu
```

### 4.5 Middleware et Permissions

**Middleware** : `['tenant', 'tenant.auth']` pour toutes les routes

**Permissions Spatie** :
- `manage-discipline` : Acces complet au module (Surveillant General, Direction)
- `report-discipline-incident` : Signaler un incident (Enseignant)
- `view-discipline-record` : Consulter les dossiers (Enseignant, en lecture seule)
- `manage-disciplinary-council` : Gerer les conseils de discipline (Direction)
- `view-discipline-notifications` : Consulter les notifications (Parent)
- `manage-discipline-settings` : Configurer le module (Admin/Direction)
- `export-discipline-reports` : Exporter les rapports (Surveillant General, Direction)

### 4.6 Testing Requirements

**Tests obligatoires** :

- **Tests unitaires** :
  - Calcul du statut disciplinaire (DisciplinaryStatusService)
  - Generation des references d'incident (INC-2026-XXXX)
  - Regles de validation des sanctions (hierarchie, conseil de discipline obligatoire pour exclusion definitive)
  - Calcul des statistiques disciplinaires

- **Tests d'integration (Feature)** :
  - CRUD incidents avec validation des permissions
  - Workflow de validation des incidents (pending -> validated, pending -> rejected)
  - Workflow complet du Conseil de Discipline (planification -> convocations -> seance -> decision)
  - Notification automatique des parents apres validation
  - Export PDF du dossier disciplinaire
  - Export Excel des rapports

- **Tests de cas limites** :
  - Incident avec plusieurs eleves et sanctions differentes
  - Tentative d'enregistrement d'exclusion definitive sans Conseil de Discipline
  - Modification d'un incident deja valide (interdit pour enseignant)
  - Notification parente echouee et renvoi
  - Suppression d'un incident valide (interdite)

**Outils** :
- Backend : PHPUnit (`php artisan test`)
- Frontend : Jest + React Testing Library

### 4.7 Additional Technical Assumptions

- **API REST** : Endpoints suivant les conventions existantes du projet
- **Validation** : Form Requests pour toutes les saisies (StoreIncidentRequest, ValidateIncidentRequest, StoreDisciplinaryCouncilRequest, StoreCouncilMinutesRequest, etc.)
- **API Resources** : Retourner toujours des Resources, jamais de models bruts (IncidentResource, DisciplinaryRecordResource, DisciplinaryCouncilResource, etc.)
- **SoftDeletes** : Utiliser sur les tables incidents, incident_types, sanction_types, disciplinary_councils
- **Casts Laravel 12** : Utiliser `casts()` method sur les models (enums, dates, booleans)
- **Format JSON** : Reponses standardisees avec `success`, `message`, `data`
- **Files Queue** : Utiliser les queues Laravel pour l'envoi des notifications email et la generation PDF asynchrone
- **Stockage fichiers** : Pieces jointes stockees dans `storage/app/public/discipline/{tenant_id}/{incident_id}/` avec liens symboliques
- **PDF Generation** : Utiliser `barryvdh/laravel-dompdf` pour les convocations, PV, et dossiers disciplinaires
- **Excel Export** : Utiliser `maatwebsite/excel` pour les exports de rapports

---

## 5. Epic List

### Epic 1 : Fondations et Configuration du Module
**Goal** : Creer les entites de base (types d'incidents, types de sanctions, parametres), les migrations, les models Eloquent et l'infrastructure API pour le module Discipline.

### Epic 2 : Enregistrement et Validation des Incidents
**Goal** : Permettre aux enseignants de signaler des incidents et au Surveillant General de les valider, modifier ou rejeter, avec gestion des pieces jointes.

### Epic 3 : Dossier Disciplinaire par Eleve
**Goal** : Fournir une vue complete et centralisee du dossier disciplinaire de chaque eleve avec historique, statut, et export PDF.

### Epic 4 : Notification Automatique des Parents
**Goal** : Envoyer automatiquement des notifications email aux parents lors de sanctions, avec suivi de livraison et accusee de reception.

### Epic 5 : Conseil de Discipline
**Goal** : Gerer le workflow complet du Conseil de Discipline, de la planification a la decision finale, avec generation des convocations et du PV.

### Epic 6 : Rapports et Statistiques
**Goal** : Generer des rapports statistiques detailles sur la discipline, avec graphiques, filtres avances et exports Excel/PDF.

---

## 6. Epic Details

### Epic 1 : Fondations et Configuration du Module

**Goal detaille** : Etablir les fondations du module Discipline avec creation des tables de configuration (types d'incidents, types de sanctions, parametres), des models Eloquent, des enums, des factories et des seeders. Cette epic pose les bases pour toutes les epics suivantes.

#### Story 1.1 : Creer les Migrations, Models et Enums

**En tant qu'** architecte technique,
**Je veux** creer les tables, models et enums pour le module Discipline,
**Afin de** stocker et structurer les donnees disciplinaires.

**Acceptance Criteria** :
1. Migration `create_incident_types_table` creee avec les colonnes definies dans la section 4.3
2. Migration `create_sanction_types_table` creee avec la hierarchie des sanctions
3. Migration `create_incidents_table` creee avec reference auto-incrementee, relations et statuts
4. Migration `create_incident_students_table` creee avec pivot eleve-incident-sanction
5. Migration `create_incident_attachments_table` creee pour les pieces jointes
6. Migration `create_parent_notifications_table` creee pour le suivi des notifications
7. Migration `create_disciplinary_councils_table` creee avec workflow de statuts
8. Migration `create_council_members_table` creee
9. Migration `create_council_minutes_table` creee
10. Migration `create_council_incidents_table` creee (pivot conseil-incidents)
11. Migration `create_discipline_settings_table` creee
12. Enum `IncidentSeverity` cree : Minor, Medium, Serious, VerySerous
13. Enum `ValidationStatus` cree : Pending, Validated, Rejected
14. Enum `SanctionLevel` cree : VerbalObservation, WrittenWarning, Blame, TemporaryExclusion, DefinitiveExclusion
15. Enum `CouncilStatus` cree : Planned, ConvocationsSent, InSession, DecisionRendered, Closed
16. Enum `NotificationStatus` cree : Pending, Sent, Failed, Read
17. Enum `CouncilDecision` cree : Acquittal, IntermediateSanction, DefinitiveExclusion
18. Models Eloquent crees avec relations, SoftDeletes, et `casts()` method
19. Factories creees pour tous les models (IncidentFactory, DisciplinaryCouncilFactory, etc.)
20. Tests unitaires verifiant les relations entre models

**Dependances** : Module UsersGuard, Module StructureAcademique (academic_years, semesters), Module Inscriptions (students)

---

#### Story 1.2 : Creer les Seeders de Configuration

**En tant qu'** administrateur systeme,
**Je veux** que le module soit pre-configure avec les types d'incidents et de sanctions standard,
**Afin de** pouvoir utiliser le module immediatement apres installation.

**Acceptance Criteria** :
1. Seeder `IncidentTypeSeeder` creant les 11 types d'incidents predefinis (cf. FR6) avec niveaux de gravite
2. Seeder `SanctionTypeSeeder` creant les 5 types de sanctions hierarchiques (cf. FR1)
3. Seeder `DisciplineSettingsSeeder` creant les parametres par defaut
4. Les seeders doivent etre idempotents (pas de doublons si executes plusieurs fois)
5. Les seeders doivent etre appeles dans le seeder principal du module `DisciplineDatabaseSeeder`
6. Tests verifiant que les seeders creent le nombre correct d'enregistrements

**Dependances** : Story 1.1

---

#### Story 1.3 : Creer les API de Configuration des Types d'Incidents

**En tant qu'** administrateur,
**Je veux** pouvoir configurer les types d'incidents disciplinaires de mon etablissement,
**Afin d'** adapter la liste aux specificites de mon etablissement.

**Acceptance Criteria** :
1. Route `GET /api/admin/discipline/incident-types` retournant la liste des types d'incidents actifs
2. Route `POST /api/admin/discipline/incident-types` creant un nouveau type d'incident
3. Route `PUT /api/admin/discipline/incident-types/{id}` modifiant un type existant
4. Route `DELETE /api/admin/discipline/incident-types/{id}` desactivant un type (soft delete)
5. `IncidentTypeResource` incluant : id, name, slug, severity, description, is_active
6. `StoreIncidentTypeRequest` validant : name (required, max:255), severity (required, enum), description (nullable)
7. Validation : slug genere automatiquement a partir du nom (unique)
8. Permission `manage-discipline-settings` requise
9. Tests feature couvrant tous les endpoints avec verification des permissions

**Dependances** : Story 1.1

---

#### Story 1.4 : Creer les API de Parametres du Module

**En tant qu'** administrateur,
**Je veux** configurer les parametres du module Discipline (durees max, seuils de statut, etc.),
**Afin d'** adapter les regles disciplinaires aux politiques de mon etablissement.

**Acceptance Criteria** :
1. Route `GET /api/admin/discipline/settings` retournant tous les parametres du module
2. Route `PUT /api/admin/discipline/settings` mettant a jour un ou plusieurs parametres
3. `DisciplineSettingsResource` incluant : key, value, description
4. Validation : `max_exclusion_days` (integer, 1-30), `auto_reset_yearly` (boolean), seuils de statut (integer, >= 0)
5. Service `DisciplineSettingsService` pour recuperer facilement une valeur de parametre avec fallback sur defaut
6. Permission `manage-discipline-settings` requise
7. Tests feature verifiant la sauvegarde et la recuperation des parametres

**Dependances** : Story 1.2

---

### Epic 2 : Enregistrement et Validation des Incidents

**Goal detaille** : Permettre aux enseignants de signaler des incidents disciplinaires (avec statut "en attente") et au Surveillant General/Direction de les valider, modifier ou rejeter. Gestion des pieces jointes et des incidents impliquant plusieurs eleves.

#### Story 2.1 : Creer l'API d'Enregistrement des Incidents

**En tant qu'** enseignant ou Surveillant General,
**Je veux** enregistrer un incident disciplinaire via l'API,
**Afin de** documenter les evenements et les sanctions dans le systeme.

**Acceptance Criteria** :
1. Route `POST /api/admin/discipline/incidents` creant un incident (Surveillant General/Admin, statut "validated" direct)
2. Route `POST /api/teacher/discipline/incidents` creant un incident (Enseignant, statut "pending")
3. `StoreIncidentRequest` validant :
   - `incident_type_id` (required, exists:incident_types,id)
   - `incident_date` (required, date, before_or_equal:now)
   - `location` (nullable, max:255)
   - `description` (required, min:10, max:5000)
   - `students` (required, array, min:1) contenant pour chaque eleve :
     - `student_id` (required, exists:students,id)
     - `sanction_type_id` (required, exists:sanction_types,id)
     - `exclusion_days` (required_if sanction = exclusion temporaire, integer, 1-max_config)
     - `notes` (nullable, max:1000)
   - `internal_notes` (nullable, max:2000)
4. Reference auto-generee : format "INC-{annee}-{numero sequentiel 4 chiffres}"
5. Validation : si la sanction est "exclusion definitive", bloquer avec message d'erreur "L'exclusion definitive ne peut etre prononcee que par le Conseil de Discipline"
6. Le systeme calcule automatiquement `exclusion_start` et `exclusion_end` si exclusion temporaire
7. `IncidentResource` incluant : reference, type, date, description, eleves avec sanctions, rapporteur, statut, pieces jointes
8. Tests feature verifiant la creation avec differentes configurations (1 eleve, plusieurs eleves, differentes sanctions)

**Dependances** : Story 1.1, Story 1.3

---

#### Story 2.2 : Creer le Workflow de Validation des Incidents

**En tant que** Surveillant General,
**Je veux** valider, modifier ou rejeter les incidents signales par les enseignants,
**Afin de** m'assurer de la pertinence et de la justesse des sanctions avant enregistrement officiel.

**Acceptance Criteria** :
1. Route `GET /api/admin/discipline/incidents/pending` retournant les incidents en attente de validation (tries par date)
2. Route `PUT /api/admin/discipline/incidents/{id}/validate` validant un incident
3. Route `PUT /api/admin/discipline/incidents/{id}/reject` rejetant un incident avec `rejection_reason` obligatoire
4. Lors de la validation :
   - Le statut passe de "pending" a "validated"
   - Le champ `validated_by` est rempli avec l'utilisateur courant
   - Le champ `validated_at` est rempli avec la date/heure courante
   - Le dossier disciplinaire de l'eleve est mis a jour (via DisciplinaryStatusService)
   - Si la sanction necessite une notification parent (`requires_notification`), un job de notification est dispatche
5. Possibilite de modifier la sanction proposee avant validation (PUT avec nouvelles donnees de sanction)
6. Lors du rejet : le statut passe a "rejected", le rapporteur est notifie (notification in-app)
7. Permission `manage-discipline` requise pour valider/rejeter
8. Tests feature couvrant les 3 scenarios (validation directe, modification+validation, rejet)

**Dependances** : Story 2.1

---

#### Story 2.3 : Gerer les Pieces Jointes des Incidents

**En tant que** rapporteur d'un incident,
**Je veux** joindre des photos ou documents a un incident,
**Afin de** fournir des preuves ou elements complementaires.

**Acceptance Criteria** :
1. Route `POST /api/admin/discipline/incidents/{id}/attachments` pour ajouter des pieces jointes
2. Route `DELETE /api/admin/discipline/incidents/{id}/attachments/{attachmentId}` pour supprimer une piece jointe
3. Validation : max 3 fichiers par incident, max 5 MB par fichier, formats PDF/JPG/PNG uniquement
4. Stockage dans `storage/app/public/discipline/{tenant_id}/{incident_id}/`
5. `IncidentAttachmentResource` incluant : id, file_name, file_type, file_size, download_url
6. Les pieces jointes sont egalement uploadables lors de la creation de l'incident (multipart form)
7. Seul le rapporteur ou le Surveillant General peut supprimer une piece jointe
8. Tests feature verifiant l'upload, la consultation et la suppression

**Dependances** : Story 2.1

---

#### Story 2.4 : Creer l'Interface d'Enregistrement d'Incident (Frontend)

**En tant que** Surveillant General,
**Je veux** une interface intuitive pour enregistrer un incident disciplinaire,
**Afin de** documenter rapidement un evenement en moins de 2 minutes.

**Acceptance Criteria** :
1. Page Next.js `/admin/discipline/incidents/create` creee
2. Formulaire structure en 4 sections tel que decrit dans la section 3.3.2
3. Champ de recherche d'eleves avec auto-completion (par nom, matricule, classe)
4. Selection multiple d'eleves avec possibilite de definir une sanction differente par eleve
5. Liste deroulante des types d'incidents filtree par gravite
6. Liste deroulante des sanctions avec indicateur visuel de gravite (couleurs)
7. Champ de duree d'exclusion apparaissant uniquement si la sanction est "exclusion temporaire"
8. Zone de drag & drop pour les pieces jointes (max 3 fichiers, preview des images)
9. Bouton "Enregistrer" pour les enseignants (statut pending)
10. Bouton "Enregistrer et valider" visible uniquement pour le Surveillant General
11. Toast de succes avec redirection vers la liste des incidents
12. Formulaire accessible sur tablette (responsive)

**Dependances** : Stories 2.1, 2.3

---

#### Story 2.5 : Creer l'Interface de Validation des Incidents (Frontend)

**En tant que** Surveillant General,
**Je veux** une file d'attente des incidents a valider avec des actions rapides,
**Afin de** traiter efficacement les signalements des enseignants.

**Acceptance Criteria** :
1. Page Next.js `/admin/discipline/incidents/pending` creee
2. Tableau avec colonnes : Date, Eleve(s), Classe, Type, Sanction proposee, Rapporteur, Actions
3. Badge rouge dans la navigation indiquant le nombre d'incidents en attente
4. Actions par ligne : "Valider" (vert), "Modifier" (bleu), "Rejeter" (rouge)
5. Clic sur "Valider" ouvre une confirmation avec resume de l'incident
6. Clic sur "Modifier" ouvre le formulaire d'edition avec champs pre-remplis
7. Clic sur "Rejeter" ouvre un modal demandant un motif de rejet (obligatoire)
8. Mise a jour en temps reel du tableau apres action
9. Filtres : classe, type d'incident, rapporteur

**Dependances** : Story 2.2

---

#### Story 2.6 : Creer la Liste des Incidents (Frontend)

**En tant que** Surveillant General ou Admin,
**Je veux** consulter la liste complete des incidents avec filtres avances,
**Afin de** retrouver facilement un incident ou suivre l'ensemble des evenements.

**Acceptance Criteria** :
1. Page Next.js `/admin/discipline/incidents` creee
2. Tableau avec colonnes tel que decrit dans la section 3.3.4
3. Filtres : annee scolaire, semestre, classe, type d'incident, type de sanction, statut, rapporteur, periode personnalisee
4. Recherche globale par nom d'eleve ou description d'incident
5. Tri par date (decroissant par defaut), cliquable sur toutes les colonnes
6. Pagination (20 incidents par page)
7. Clic sur une ligne ouvre le detail de l'incident
8. Boutons d'export : Excel, PDF
9. Compteur de resultats en haut du tableau

**Dependances** : Story 2.1

---

### Epic 3 : Dossier Disciplinaire par Eleve

**Goal detaille** : Fournir une vue complete et centralisee du dossier disciplinaire de chaque eleve, avec historique chronologique, resume statistique, calcul automatique du statut disciplinaire, et export PDF pour les conseils de discipline et transferts.

#### Story 3.1 : Creer le Service de Calcul du Statut Disciplinaire

**En tant que** developpeur backend,
**Je veux** centraliser la logique de calcul du statut disciplinaire d'un eleve,
**Afin de** reutiliser ce code dans les differents endpoints et rapports.

**Acceptance Criteria** :
1. Service `DisciplinaryStatusService` cree dans `Modules/Discipline/Services/`
2. Methode `calculateStatus(int $studentId, ?int $academicYearId = null): string` retournant le statut disciplinaire
3. Logique de calcul basee sur les seuils configurables dans `discipline_settings` :
   - Compter les sanctions validees par type pour l'annee scolaire
   - Appliquer les regles de seuils (cf. FR19)
4. Methode `getSanctionSummary(int $studentId, ?int $academicYearId = null): array` retournant le nombre de sanctions par type
5. Methode `getStatusLabel(string $status): string` retournant le libelle en francais
6. Methode `getStatusColor(string $status): string` retournant le code couleur
7. Methode `resetStatusForNewYear(int $academicYearId): void` reinitialisant les statuts si config `auto_reset_yearly` = true
8. Cache des resultats pour 5 minutes (invalide lors de la validation d'un nouvel incident)
9. Tests unitaires exhaustifs couvrant tous les cas (0 sanctions, seuils limites, multi-sanctions)

**Dependances** : Story 1.4

---

#### Story 3.2 : Creer l'API du Dossier Disciplinaire

**En tant que** Surveillant General,
**Je veux** consulter le dossier disciplinaire complet d'un eleve via l'API,
**Afin de** avoir une vue d'ensemble de son parcours disciplinaire.

**Acceptance Criteria** :
1. Route `GET /api/admin/discipline/students/{studentId}/record` retournant :
   - Informations de l'eleve (nom, classe, matricule, photo)
   - Statut disciplinaire actuel (via DisciplinaryStatusService)
   - Resume : nombre de sanctions par type pour l'annee en cours
   - Historique complet : liste des incidents valides (ordre chronologique inverse) avec detail des sanctions
   - Conseils de discipline associes (le cas echeant)
2. `DisciplinaryRecordResource` incluant toutes les donnees ci-dessus avec relations
3. Filtres optionnels : `academic_year_id`, `sanction_type_id`
4. Eager loading des relations (incidents -> type, sanctions, rapporteur, pieces jointes)
5. Permission `manage-discipline` ou `view-discipline-record` requise
6. Route enseignant `GET /api/teacher/discipline/students/{studentId}/record` (lecture seule, uniquement les eleves de ses classes)
7. Tests feature verifiant les permissions et le contenu de la reponse

**Dependances** : Story 3.1, Story 2.1

---

#### Story 3.3 : Creer l'Export PDF du Dossier Disciplinaire

**En tant que** Surveillant General,
**Je veux** exporter le dossier disciplinaire d'un eleve en PDF,
**Afin de** le fournir au Conseil de Discipline ou lors d'un transfert.

**Acceptance Criteria** :
1. Route `GET /api/admin/discipline/students/{studentId}/record/export` retournant un PDF
2. Template Blade `discipline-record-pdf.blade.php` cree avec :
   - En-tete : Logo etablissement, Nom de l'etablissement, "Dossier Disciplinaire"
   - Informations de l'eleve : Nom, Classe, Matricule, Annee scolaire
   - Statut disciplinaire actuel
   - Resume statistique : tableau des sanctions par type
   - Historique detaille : tableau chronologique (Date, Type d'incident, Description, Sanction, Rapporteur)
   - Pied de page : Date de generation, "Document genere automatiquement"
3. Utilisation de `barryvdh/laravel-dompdf` pour la generation
4. Nom de fichier : `dossier-disciplinaire-{matricule}-{date}.pdf`
5. Generation en < 3 secondes
6. Tests verifiant la generation et la presence des informations cles

**Dependances** : Story 3.2

---

#### Story 3.4 : Creer l'Interface du Dossier Disciplinaire (Frontend)

**En tant que** Surveillant General,
**Je veux** une interface claire et complete pour consulter le dossier disciplinaire d'un eleve,
**Afin de** avoir une vue d'ensemble rapide et detaillee.

**Acceptance Criteria** :
1. Page Next.js `/admin/discipline/students/{studentId}` creee
2. En-tete affichant : photo, nom complet, classe, matricule, badge du statut disciplinaire (colore)
3. Section Resume : compteurs par type de sanction avec icones et couleurs
4. Section Historique : timeline verticale des incidents (du plus recent au plus ancien)
   - Chaque entree affiche : date, icone type d'incident, titre, sanction (badge colore), rapporteur
   - Clic pour deployer le detail complet (description, notes, pieces jointes)
5. Bouton "Exporter le dossier (PDF)" declenchant le telechargement
6. Bouton "Enregistrer un nouvel incident" pre-remplissant l'eleve dans le formulaire
7. Accessible depuis la liste des eleves (lien rapide "Dossier discipline")
8. Recherche d'eleve par nom/matricule avec acces direct au dossier

**Dependances** : Stories 3.2, 3.3

---

### Epic 4 : Notification Automatique des Parents

**Goal detaille** : Envoyer automatiquement des notifications email aux parents/tuteurs lorsque leur enfant recoit une sanction ecrite (avertissement ecrit, blame, exclusion), avec suivi de l'envoi et possibilite de demande de rendez-vous.

#### Story 4.1 : Creer le Service de Notification aux Parents

**En tant que** developpeur backend,
**Je veux** creer un service de notification centralise pour les evenements disciplinaires,
**Afin de** automatiser l'envoi des notifications aux parents.

**Acceptance Criteria** :
1. Service `DisciplineNotificationService` cree dans `Modules/Discipline/Services/`
2. Methode `notifyParentsOfSanction(IncidentStudent $incidentStudent, bool $requestMeeting = false): void`
3. Le service recupere les parents/tuteurs de l'eleve via la relation parent-eleve (Module Inscriptions)
4. Pour chaque parent, creation d'un enregistrement `ParentNotification` avec statut "pending"
5. Dispatch d'un job `SendParentSanctionNotificationJob` dans la queue Laravel
6. Le job envoie l'email via la notification Laravel `ParentSanctionNotification`
7. Mise a jour du statut de notification apres envoi (sent, failed)
8. En cas d'echec, le statut passe a "failed" avec la raison stockee dans `failure_reason`
9. Tests unitaires verifiant le dispatch du job et la creation de la notification

**Dependances** : Story 1.1, Module Inscriptions (relation parent-eleve)

---

#### Story 4.2 : Creer la Notification Email pour les Sanctions

**En tant que** parent,
**Je veux** recevoir un email detaille lorsque mon enfant recoit une sanction,
**Afin d'** etre informe rapidement et pouvoir reagir.

**Acceptance Criteria** :
1. Notification Laravel `ParentSanctionNotification` creee (implements `ShouldQueue`)
2. Template email (`discipline-sanction`) contenant :
   - Nom de l'etablissement (en-tete)
   - "Madame, Monsieur {nom parent}"
   - Nom de l'eleve et classe
   - Date et description de l'incident
   - Type de sanction prononcee
   - Duree d'exclusion si applicable (avec dates debut/fin)
   - Nom du rapporteur
   - Coordonnees du Surveillant General
   - Lien vers le portail parent
   - Si demande de RDV : "Nous souhaitons vous rencontrer. Merci de prendre contact avec le Surveillant General."
3. Email envoye dans la queue pour traitement asynchrone
4. Mise a jour du statut dans `parent_notifications` apres envoi
5. Tests verifiant le contenu de l'email et l'envoi via la queue

**Dependances** : Story 4.1

---

#### Story 4.3 : Gerer le Suivi et le Renvoi des Notifications

**En tant que** Surveillant General,
**Je veux** voir le statut des notifications envoyees et pouvoir renvoyer celles qui ont echoue,
**Afin de** m'assurer que tous les parents sont informes.

**Acceptance Criteria** :
1. Route `GET /api/admin/discipline/notifications` retournant la liste des notifications avec filtres
2. Route `POST /api/admin/discipline/notifications/{id}/resend` renvoyant une notification echouee
3. `ParentNotificationResource` incluant : id, eleve, parent, type, statut, date envoi, date lecture
4. Filtres : statut (pending, sent, failed, read), eleve, date
5. Le renvoi remet le statut a "pending" et dispatche un nouveau job
6. Interface frontend : page `/admin/discipline/notifications` avec tableau et actions
7. Badge rouge sur les notifications echouees
8. Tests feature verifiant le renvoi et la mise a jour du statut

**Dependances** : Story 4.2

---

#### Story 4.4 : Integrer les Notifications dans le Portail Parent

**En tant que** parent,
**Je veux** consulter les notifications disciplinaires de mon enfant dans le portail parent,
**Afin de** suivre les evenements et sanctions.

**Acceptance Criteria** :
1. Route `GET /api/parent/discipline/notifications` retournant les notifications du parent connecte
2. Route `PUT /api/parent/discipline/notifications/{id}/read` marquant une notification comme lue
3. Affichage dans le portail parent :
   - Badge rouge avec nombre de notifications non lues
   - Liste des notifications avec detail : date, eleve, type de sanction, description
   - Clic sur une notification affiche le detail complet et la marque comme lue
4. Mise a jour du statut `read_at` lors de la consultation
5. Tests feature verifiant les permissions (parent ne voit que ses propres notifications)

**Dependances** : Story 4.2, Module Portail Parent

---

### Epic 5 : Conseil de Discipline

**Goal detaille** : Gerer le workflow complet du Conseil de Discipline : planification de la seance, generation des convocations formelles, constitution du dossier, saisie du proces-verbal, enregistrement de la decision, et notification des parties prenantes.

#### Story 5.1 : Creer l'API de Gestion du Conseil de Discipline

**En tant qu'** Admin/Directeur,
**Je veux** planifier et gerer un Conseil de Discipline via l'API,
**Afin de** formaliser les procedures disciplinaires graves.

**Acceptance Criteria** :
1. Route `POST /api/admin/discipline/councils` creant un conseil avec :
   - `student_id` (required, exists:students,id)
   - `scheduled_date` (required, date, after:now)
   - `location` (required, max:255)
   - `reason` (required, min:20, max:5000)
   - `incident_ids` (required, array, min:1) : incidents ayant motive la convocation
   - `members` (required, array, min:3) : liste des membres avec nom et role
2. Reference auto-generee : "CD-{annee}-{numero sequentiel 4 chiffres}"
3. Route `GET /api/admin/discipline/councils` listant les conseils (filtres : statut, annee, eleve)
4. Route `GET /api/admin/discipline/councils/{id}` retournant le detail avec toutes les relations
5. Route `PUT /api/admin/discipline/councils/{id}` modifiant un conseil (uniquement si statut = planned)
6. Route `DELETE /api/admin/discipline/councils/{id}` annulant un conseil (uniquement si statut = planned)
7. Route `PUT /api/admin/discipline/councils/{id}/status` pour changer de statut (workflow)
8. `DisciplinaryCouncilResource` incluant : reference, eleve, date, lieu, motif, membres, incidents, statut, minutes
9. Permission `manage-disciplinary-council` requise
10. Tests feature couvrant la creation, modification, annulation et le workflow de statut

**Dependances** : Story 1.1, Story 2.1

---

#### Story 5.2 : Generer les Convocations Formelles

**En tant qu'** Admin/Directeur,
**Je veux** generer les convocations officielles pour le Conseil de Discipline,
**Afin d'** informer formellement toutes les parties prenantes.

**Acceptance Criteria** :
1. Route `POST /api/admin/discipline/councils/{id}/convocations` generant les convocations
2. Le systeme genere une convocation PDF distincte pour :
   - L'eleve concerne
   - Les parents/tuteurs
   - Chaque membre du conseil
3. Template Blade `council-convocation-pdf.blade.php` contenant :
   - En-tete officiel de l'etablissement
   - "Convocation au Conseil de Discipline"
   - Nom du destinataire et sa qualite
   - Nom de l'eleve concerne
   - Date, heure et lieu de la seance
   - Motif de la convocation (resume)
   - Mention "La presence est obligatoire"
   - Signature du Directeur/Proviseur
4. Route `GET /api/admin/discipline/councils/{id}/convocations/pdf` retournant un ZIP contenant toutes les convocations
5. Le statut du conseil passe a "convocations_sent" apres generation
6. Envoi automatique par email aux parents et membres ayant un email
7. Mise a jour de `convocation_sent` et `convocation_sent_at` pour chaque membre
8. Tests verifiant la generation des PDF et le changement de statut

**Dependances** : Story 5.1

---

#### Story 5.3 : Constituer le Dossier du Conseil de Discipline

**En tant qu'** Admin/Directeur,
**Je veux** consulter le dossier constitue pour le Conseil de Discipline,
**Afin de** preparer la seance avec toutes les informations necessaires.

**Acceptance Criteria** :
1. Route `GET /api/admin/discipline/councils/{id}/dossier` retournant le dossier complet :
   - Dossier disciplinaire de l'eleve (historique complet via DisciplinaryRecordResource)
   - Detail des incidents motifs de la convocation
   - Rapports des enseignants concernes
   - Pieces jointes de tous les incidents lies
   - Informations scolaires de l'eleve (classe, moyennes si disponibles)
2. Le dossier doit etre exportable en PDF complet
3. Route `GET /api/admin/discipline/councils/{id}/dossier/pdf` generant le dossier en PDF
4. Template Blade `council-dossier-pdf.blade.php` compilant toutes les informations
5. Tests verifiant la completude du dossier

**Dependances** : Stories 5.1, 3.2, 3.3

---

#### Story 5.4 : Saisir le Proces-Verbal du Conseil de Discipline

**En tant qu'** Admin/Directeur,
**Je veux** saisir le proces-verbal de la seance du Conseil de Discipline,
**Afin de** documenter officiellement les debats et la decision.

**Acceptance Criteria** :
1. Route `POST /api/admin/discipline/councils/{id}/minutes` creant le PV avec :
   - `session_date` (required, date)
   - `summary` (required, min:50, max:10000) : resume des debats
   - `decision` (required, enum: acquittal, intermediate_sanction, definitive_exclusion)
   - `sanction_type_id` (required_if decision = intermediate_sanction)
   - `exclusion_days` (required_if decision = intermediate_sanction et sanction = exclusion temporaire)
   - `votes_for` (required, integer, min:0)
   - `votes_against` (required, integer, min:0)
   - `votes_abstention` (required, integer, min:0)
   - `signatories` (required, max:2000)
   - `additional_notes` (nullable, max:5000)
2. Route `PUT /api/admin/discipline/councils/{id}/minutes` modifiant le PV
3. Mise a jour des presences des membres du conseil
4. Le statut du conseil passe a "decision_rendered"
5. Si decision = "definitive_exclusion", la sanction est automatiquement enregistree dans le dossier de l'eleve
6. Si decision = "intermediate_sanction", la sanction est enregistree avec le type choisi
7. `CouncilMinutesResource` incluant toutes les donnees du PV avec relations
8. Tests feature verifiant les 3 types de decision et l'impact sur le dossier de l'eleve

**Dependances** : Story 5.1

---

#### Story 5.5 : Generer le PV en PDF et Notifier la Decision

**En tant qu'** Admin/Directeur,
**Je veux** generer le PV en PDF et notifier les parents de la decision,
**Afin de** formaliser officiellement les resultats du Conseil de Discipline.

**Acceptance Criteria** :
1. Route `GET /api/admin/discipline/councils/{id}/minutes/pdf` retournant le PV en PDF
2. Template Blade `council-minutes-pdf.blade.php` contenant :
   - En-tete officiel de l'etablissement
   - "Proces-Verbal du Conseil de Discipline"
   - Date, heure et lieu de la seance
   - Nom de l'eleve et classe
   - Liste des membres presents et absents
   - Resume des debats
   - Decision prise avec details (sanction, duree)
   - Resultats du vote
   - Signataires
3. Route `POST /api/admin/discipline/councils/{id}/decision` generant la notification officielle aux parents
4. Notification email aux parents contenant :
   - Decision du Conseil de Discipline
   - Sanction prononcee
   - Dates d'exclusion si applicable
   - Voies de recours si prevues
5. Le statut du conseil passe a "closed"
6. Enregistrement de la notification dans `parent_notifications`
7. Tests verifiant la generation du PDF et l'envoi de la notification

**Dependances** : Story 5.4, Story 4.1

---

#### Story 5.6 : Creer l'Interface du Conseil de Discipline (Frontend)

**En tant qu'** Admin/Directeur,
**Je veux** une interface complete pour gerer le Conseil de Discipline,
**Afin de** suivre le workflow de bout en bout.

**Acceptance Criteria** :
1. Page Next.js `/admin/discipline/councils` listant les conseils avec statuts (badges colores)
2. Page `/admin/discipline/councils/create` pour planifier un nouveau conseil
3. Page `/admin/discipline/councils/{id}` avec sous-onglets :
   - **Informations** : Detail du conseil, eleve, date, lieu, motif
   - **Membres** : Liste des membres avec statut de convocation
   - **Dossier** : Vue du dossier constitue avec lien de telechargement PDF
   - **Convocations** : Bouton de generation, liste des convocations avec telechargement individuel
   - **Seance** : Formulaire du PV (apparait uniquement si statut >= "in_session")
   - **Decision** : Resume de la decision avec bouton de notification (apparait uniquement si statut = "decision_rendered")
4. Barre de progression horizontale affichant le statut du workflow
5. Actions contextuelles selon le statut (progression lineaire du workflow)
6. Tests E2E verifiant le workflow complet

**Dependances** : Stories 5.1 a 5.5

---

### Epic 6 : Rapports et Statistiques

**Goal detaille** : Generer des rapports statistiques detailles sur la discipline : nombre d'incidents par classe/periode, types d'incidents les plus frequents, eleves les plus sanctionnes, evolution temporelle, avec visualisation graphique et exports Excel/PDF.

#### Story 6.1 : Creer le Service de Statistiques Disciplinaires

**En tant que** developpeur backend,
**Je veux** creer un service centralise pour les calculs statistiques disciplinaires,
**Afin de** reutiliser la logique dans les differents endpoints et rapports.

**Acceptance Criteria** :
1. Service `DisciplineStatisticsService` cree dans `Modules/Discipline/Services/`
2. Methode `getDashboardStats(?int $academicYearId = null): array` retournant les indicateurs cles
3. Methode `getIncidentsByClass(array $filters): Collection` retournant les incidents groupes par classe
4. Methode `getIncidentsByType(array $filters): Collection` retournant les incidents groupes par type
5. Methode `getIncidentsByPeriod(array $filters): Collection` retournant l'evolution temporelle (mensuelle)
6. Methode `getTopSanctionedStudents(int $limit, array $filters): Collection` retournant les eleves les plus sanctionnes
7. Methode `getRecidivismRate(array $filters): float` calculant le taux de recidive
8. Methode `getClassComparison(array $filters): Collection` retournant le taux d'incidents par effectif par classe
9. Cache des resultats pour 10 minutes
10. Tests unitaires couvrant tous les calculs avec differents jeux de donnees

**Dependances** : Story 2.1

---

#### Story 6.2 : Creer les API de Statistiques

**En tant que** Surveillant General ou Admin,
**Je veux** acceder aux statistiques disciplinaires via l'API,
**Afin de** piloter la politique disciplinaire de l'etablissement.

**Acceptance Criteria** :
1. Route `GET /api/admin/discipline/statistics/dashboard` retournant les indicateurs cles (cf. FR40)
2. Route `GET /api/admin/discipline/statistics/incidents-by-class` avec filtres (annee, semestre, periode)
3. Route `GET /api/admin/discipline/statistics/incidents-by-type` avec filtres
4. Route `GET /api/admin/discipline/statistics/incidents-by-period` avec filtres
5. Route `GET /api/admin/discipline/statistics/top-sanctioned` avec parametres `limit` et filtres
6. Route `GET /api/admin/discipline/statistics/recidivism` avec filtres
7. Filtres communs : `academic_year_id`, `semester_id`, `class_id`, `start_date`, `end_date`
8. Reponses formatees pour faciliter l'affichage graphique (labels, values, colors)
9. Permission `manage-discipline` ou `export-discipline-reports` requise
10. Tests feature verifiant les reponses pour chaque endpoint

**Dependances** : Story 6.1

---

#### Story 6.3 : Creer le Dashboard Discipline (Frontend)

**En tant que** Surveillant General,
**Je veux** un tableau de bord avec les indicateurs cles de la discipline,
**Afin d'** avoir une vue d'ensemble immediate de la situation.

**Acceptance Criteria** :
1. Page Next.js `/admin/discipline/dashboard` creee
2. Indicateurs cles en haut :
   - Nombre d'incidents du mois (avec fleche tendance vs mois precedent)
   - Incidents en attente de validation (badge rouge, lien vers la file d'attente)
   - Conseils de discipline planifies (badge, lien vers la liste)
   - Top 3 types d'incidents (barres horizontales miniatures)
3. Graphique principal : evolution mensuelle des incidents (line chart, 12 derniers mois)
4. Liste des 5 derniers incidents enregistres (acces rapide)
5. Raccourcis : "Enregistrer un incident", "Incidents en attente", "Dossiers eleves", "Rapports"
6. Refresh automatique toutes les 5 minutes
7. Responsive tablette

**Dependances** : Story 6.2

---

#### Story 6.4 : Creer la Page Rapports et Statistiques (Frontend)

**En tant que** Surveillant General ou Admin,
**Je veux** une page de rapports avec graphiques et filtres avances,
**Afin d'** analyser en detail les tendances disciplinaires.

**Acceptance Criteria** :
1. Page Next.js `/admin/discipline/reports` creee
2. Barre de filtres : annee scolaire, semestre, classe, periode personnalisee (date picker)
3. Graphiques (utilisant Recharts ou Chart.js) :
   - Evolution mensuelle des incidents (line chart)
   - Repartition par type d'incident (pie chart)
   - Repartition par type de sanction (bar chart)
   - Comparaison entre classes (bar chart horizontal, incidents par effectif)
4. Tableaux detailles sous les graphiques avec donnees numeriques
5. Section "Eleves les plus sanctionnes" (top 10 avec lien vers dossier)
6. Section "Taux de recidive" avec indicateur visuel
7. Boutons d'export : "Export Excel", "Export PDF"
8. Graphiques responsives et interactifs (tooltips)
9. Sauvegarde des filtres en localStorage

**Dependances** : Story 6.2

---

#### Story 6.5 : Implementer les Exports Excel et PDF des Rapports

**En tant que** Surveillant General ou Admin,
**Je veux** exporter les rapports disciplinaires en Excel et PDF,
**Afin de** les partager avec la direction ou les archiver.

**Acceptance Criteria** :
1. Route `GET /api/admin/discipline/reports/export` generant un fichier Excel
2. Classe `DisciplinaryReportExport` implementant `FromCollection` (maatwebsite/excel) avec :
   - Feuille "Incidents" : Date, Eleve, Classe, Type, Sanction, Rapporteur, Statut
   - Feuille "Statistiques" : Resume par classe, par type, par sanction
   - Feuille "Recidive" : Eleves avec plus de N sanctions
3. Formatage professionnel : en-tetes en gras, couleurs par gravite, filtres auto
4. Route `GET /api/admin/discipline/reports/export-pdf` generant un PDF de synthese
5. Template Blade `discipline-report-pdf.blade.php` avec graphiques inclus en image
6. Nom de fichier : `rapport-discipline-{annee}-{date}.xlsx` ou `.pdf`
7. Generation en < 10 secondes meme pour 5000 incidents
8. Les filtres de la page rapports sont appliques a l'export
9. Tests verifiant la structure et le contenu des exports

**Dependances** : Story 6.2

---

## 7. Checklist Results Report

**A completer apres implementation** : Executer le checklist PM pour valider :
- [ ] Tous les types de sanctions sont implementes avec la hierarchie correcte
- [ ] L'enregistrement d'un incident fonctionne pour les enseignants et le Surveillant General
- [ ] Le workflow de validation (pending -> validated / rejected) est operationnel
- [ ] Le dossier disciplinaire par eleve est complet et exportable en PDF
- [ ] Le statut disciplinaire est calcule automatiquement selon les regles configurees
- [ ] Les notifications email aux parents sont envoyees automatiquement
- [ ] Le workflow complet du Conseil de Discipline fonctionne (planification -> decision)
- [ ] Les convocations et PV sont generables en PDF
- [ ] Les rapports statistiques avec graphiques sont fonctionnels
- [ ] Les exports Excel et PDF sont operationnels
- [ ] Les permissions sont correctement appliquees par role
- [ ] Les pieces jointes sont geres correctement (upload, stockage, consultation)
- [ ] L'interface est responsive (desktop, tablette)
- [ ] Les tests couvrent tous les scenarios critiques
- [ ] Le journal d'audit trace toutes les actions sensibles
- [ ] L'integration avec le Module Portail Parent est fonctionnelle

---

## 8. Next Steps

### 8.1 UX Expert Prompt

> "Creez les maquettes UI pour le Module Discipline en vous basant sur ce PRD. Focus sur les ecrans critiques : formulaire d'enregistrement d'incident (avec selection multiple d'eleves et sanctions differenciees), dossier disciplinaire par eleve (timeline + resume), dashboard discipline (indicateurs + graphiques), interface du Conseil de Discipline (workflow multi-etapes). Assurez l'accessibilite WCAG AA, le responsive design (tablette prioritaire pour le Surveillant General), et une palette de couleurs coherente par niveau de gravite."

### 8.2 Architect Prompt

> "Concevez l'architecture technique du Module Discipline en suivant les patterns etablis dans les modules existants (UsersGuard, StructureAcademique). Definissez les tables de base de donnees avec relations et index, les models Eloquent avec eager loading et SoftDeletes, les controllers avec Form Requests, les API Resources, les services metier (DisciplinaryStatusService, DisciplineNotificationService, DisciplineStatisticsService, CouncilService), les notifications Laravel queued, les exports Excel (maatwebsite/excel), la generation PDF (dompdf), et le plan de tests complet (unitaires, feature, cas limites). Portez une attention particuliere a la securite des donnees (mineurs) et a la tracabilite (journal d'audit)."

---

**Document cree par** : John (Product Manager Agent)
**Date** : 2026-03-16
**Version** : 1.0
**Statut** : Draft pour review
