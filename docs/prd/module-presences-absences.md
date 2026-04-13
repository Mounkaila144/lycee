# PRD - Module Presences & Absences

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Module** : Presences & Absences (Attendance)
> **Version** : v5
> **Date** : 2026-03-16
> **Phase** : Phase 2 - Vie Scolaire & Operations
> **Priorite** : HAUTE 🟠

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 5.0 | Refonte complete - Passage du LMD (superieur) au secondaire (colleges/lycees). Ajout role Surveillant General, alertes parents automatiques, justificatifs, seuils configurables, consolidation par semestre/matiere/classe | John (PM) |
| 2026-01-07 | 1.0 | Creation initiale du PRD Module Presences/Absences (LMD) | Claude (PM Agent) |

---

## 1. Goals and Background Context

### 1.1 Goals

- **Numeriser l'appel quotidien** : Permettre aux enseignants d'effectuer l'appel rapidement et facilement pour chaque seance de cours, en remplacement des feuilles d'appel papier
- **Centraliser le suivi des absences** : Offrir au Surveillant General une vue consolidee de toutes les absences par eleve, par classe, par matiere et par semestre
- **Alerter les parents automatiquement** : Notifier les parents/tuteurs des qu'une absence non justifiee est enregistree pour leur enfant
- **Gerer les justificatifs** : Permettre l'upload de documents justificatifs (certificats medicaux, etc.) et leur validation par le Surveillant General ou l'administration
- **Detecter les eleves a risque** : Identifier automatiquement les eleves depassant un seuil configurable d'absences non justifiees et alerter la direction et les parents
- **Produire des rapports detailles** : Fournir des rapports de taux de presence par classe, par eleve, par matiere et par periode
- **Reduire le temps d'appel** : Passer de 10-15 minutes (appel papier) a moins de 3 minutes par seance
- **Eliminer le papier** : Supprimer les feuilles d'appel papier, les cahiers d'absences et la saisie manuelle

### 1.2 Background Context

Dans les colleges et lycees au Niger, le suivi des presences repose entierement sur des **feuilles d'appel papier**. L'enseignant fait l'appel en debut de cours, note les absents sur la feuille, puis la transmet au Surveillant General en fin de journee. Le Surveillant General consolide manuellement les absences dans un registre, identifie les eleves ayant trop d'absences, et convoque les parents via des lettres transmises par l'eleve lui-meme -- un processus lent, sujet aux erreurs, et souvent inefficace (lettres perdues, non transmises).

**Problemes majeurs identifies** :
- **Feuilles perdues ou incompletes** : Les feuilles d'appel circulent, se perdent, et ne sont pas toujours remplies
- **Consolidation manuelle laborieuse** : Le Surveillant General passe des heures a compiler les absences de toutes les classes
- **Parents non informes** : Les parents ne decouvrent les absences de leur enfant que tardivement (souvent au bulletin)
- **Absence de donnees fiables** : Impossible de produire rapidement des statistiques de presence par classe ou par eleve
- **Detection tardive du decrochage** : Les eleves accumulant des absences ne sont identifies qu'apres des semaines

Le **Module Presences & Absences** numerise l'ensemble de ce processus : l'enseignant fait l'appel depuis son interface web ou tablette, les donnees sont immediatement disponibles pour le Surveillant General, les parents sont alertes automatiquement, et les seuils d'alerte configurables permettent une intervention precoce aupres des eleves en difficulte.

Ce module s'integre avec :
- **Module Emplois du Temps** : L'appel est contextuel a chaque seance planifiee
- **Module Structure Academique** : Classes, matieres, enseignants
- **Module Inscriptions** : Listes d'eleves par classe
- **Module Portail Parent** : Notifications et consultation des absences
- **Module Discipline** : Les absences repetees peuvent declencher des sanctions

---

## 2. Requirements

### 2.1 Functional Requirements (FR)

#### 2.1.1 Appel et Enregistrement des Presences

- **FR1** : Le systeme doit permettre aux enseignants de creer une feuille d'appel pour chaque seance planifiee dans leur emploi du temps
- **FR2** : La feuille d'appel doit lister automatiquement tous les eleves inscrits dans la classe concernee
- **FR3** : L'enseignant doit pouvoir marquer chaque eleve avec l'un des statuts suivants : **Present**, **Absent**, **Retard**, **Excuse**
- **FR4** : Le statut par defaut de tous les eleves doit etre "Present" pour accelerer l'appel (l'enseignant marque uniquement les exceptions)
- **FR5** : L'enseignant doit pouvoir modifier les statuts de presence jusqu'a 24 heures apres la seance (duree configurable par l'admin)
- **FR6** : Le systeme doit supporter la sauvegarde en masse (bulk update) de tous les statuts d'une feuille d'appel en une seule requete
- **FR7** : L'enseignant doit pouvoir ajouter une note/commentaire sur un enregistrement de presence (ex: "Arrive 15 min en retard", "Sorti pour raison medicale")

#### 2.1.2 Consolidation et Calcul des Absences

- **FR8** : Le systeme doit calculer automatiquement le total d'absences par eleve, par matiere et par semestre
- **FR9** : Le systeme doit calculer le taux de presence de chaque eleve par matiere (formule : nombre de presences / nombre total de seances * 100)
- **FR10** : Le systeme doit distinguer les absences justifiees et non justifiees dans tous les calculs et rapports
- **FR11** : Le systeme doit calculer le taux de presence global d'une classe (moyenne des taux de presence de tous les eleves)
- **FR12** : Le systeme doit fournir un recapitulatif mensuel et semestriel des absences par eleve

#### 2.1.3 Justification des Absences

- **FR13** : Les parents ou le Surveillant General doivent pouvoir soumettre un justificatif pour une absence en uploadant un document (certificat medical, justificatif administratif, etc.)
- **FR14** : Les formats acceptes pour les justificatifs doivent etre : PDF, JPG, PNG (taille max : 5 MB par fichier)
- **FR15** : Le Surveillant General doit pouvoir valider ou refuser les justificatifs soumis
- **FR16** : Lors de la validation d'un justificatif, le statut de l'absence doit passer automatiquement de "Absent" a "Excuse"
- **FR17** : Lors du refus d'un justificatif, le Surveillant General doit obligatoirement fournir un motif de refus
- **FR18** : Les parents doivent pouvoir consulter le statut de leurs justificatifs (En attente, Approuve, Refuse)

#### 2.1.4 Alertes et Notifications Parents

- **FR19** : Le systeme doit envoyer une notification automatique aux parents/tuteurs de l'eleve des qu'une absence non justifiee est enregistree
- **FR20** : Le systeme doit envoyer une notification a la direction et aux parents lorsqu'un eleve depasse le seuil d'absences non justifiees configurable (par defaut : 3 absences non justifiees)
- **FR21** : Le Surveillant General doit pouvoir envoyer une convocation aux parents directement depuis le systeme
- **FR22** : Le systeme doit tenir un historique de toutes les notifications envoyees (date, destinataire, contenu, statut de lecture)
- **FR23** : Les notifications doivent etre envoyees par email (MVP) avec possibilite d'extension SMS en Phase ulterieure

#### 2.1.5 Seuils d'Alerte Configurables

- **FR24** : L'administrateur doit pouvoir configurer le seuil d'absences non justifiees declenchant une alerte (par defaut : 3 absences)
- **FR25** : L'administrateur doit pouvoir configurer la duree de modification d'appel autorisee pour les enseignants (par defaut : 24 heures)
- **FR26** : L'administrateur doit pouvoir activer/desactiver les alertes automatiques aux parents
- **FR27** : L'administrateur doit pouvoir configurer le seuil de taux d'absence declenchant une alerte direction (par defaut : 25% du volume horaire)
- **FR28** : Les seuils doivent etre configurables par etablissement (tenant)

#### 2.1.6 Consultation et Historique

- **FR29** : Les eleves doivent pouvoir consulter leur historique de presences/absences par matiere et par semestre
- **FR30** : Les parents doivent pouvoir consulter l'historique de presences/absences de leur(s) enfant(s) depuis le portail parent
- **FR31** : Les enseignants doivent pouvoir consulter l'historique de presence d'un eleve specifique dans leur matiere
- **FR32** : Le Surveillant General doit pouvoir consulter les absences de tous les eleves de l'etablissement avec filtres multiples

#### 2.1.7 Rapports et Exports

- **FR33** : Le systeme doit generer un rapport de taux de presence par classe (tous les eleves d'une classe avec leur taux)
- **FR34** : Le systeme doit generer un rapport de taux de presence par eleve (toutes les matieres d'un eleve avec son taux par matiere)
- **FR35** : Le systeme doit generer un rapport de taux de presence par matiere (comparaison entre classes)
- **FR36** : Les rapports doivent etre filtrables par periode (semaine, mois, semestre, annee scolaire)
- **FR37** : Le systeme doit permettre l'export des donnees de presence en Excel et PDF
- **FR38** : Le systeme doit generer une fiche recapitulative d'absences par eleve pour le bulletin semestriel (total heures d'absence justifiees et non justifiees)

### 2.2 Non-Functional Requirements (NFR)

- **NFR1** : L'interface d'appel doit permettre de marquer la presence de 60 eleves en moins de 3 minutes
- **NFR2** : Le calcul du taux de presence doit etre en temps reel et affiche instantanement apres mise a jour
- **NFR3** : Le systeme doit supporter 50 enseignants faisant l'appel simultanement sans degradation de performance
- **NFR4** : Les uploads de justificatifs doivent etre limites a 5 MB par fichier et aux formats PDF, JPG, PNG
- **NFR5** : L'historique de presence doit etre conserve pendant toute la duree de scolarite de l'eleve (minimum 7 ans)
- **NFR6** : L'interface d'appel doit etre responsive et utilisable sur tablette pour les enseignants en classe
- **NFR7** : Les donnees de presence doivent etre isolees par tenant (multi-tenant) sans possibilite de fuite
- **NFR8** : Les notifications aux parents doivent etre envoyees dans les 15 minutes suivant l'enregistrement d'une absence
- **NFR9** : La generation de rapports de presence doit s'executer en moins de 10 secondes pour une classe de 60 eleves sur un semestre complet

---

## 3. User Interface Design Goals

### 3.1 Overall UX Vision

L'interface d'appel doit etre **ultra-simple et rapide**, concue pour un usage quotidien par des enseignants dont le niveau technique est variable. L'enseignant voit une liste d'eleves avec des boutons/toggles pour marquer les exceptions (absents, retards). Le design privilegie la **vitesse d'execution** et la **clarte** : noms lisibles, statuts visuels (icones + couleurs), scrolling fluide.

Pour le **Surveillant General**, l'interface de consolidation doit offrir une vue d'ensemble centralisee de toutes les absences, avec des filtres puissants et des indicateurs visuels d'alerte.

Pour les **parents**, la consultation doit etre accessible depuis un smartphone avec une interface epuree : historique clair, notifications visibles, possibilite de soumettre un justificatif facilement.

### 3.2 Key Interaction Paradigms

- **Appel rapide** : Liste d'eleves avec statut par defaut "Present", toggle rapide pour marquer Absent/Retard/Excuse
- **Recherche eleve** : Barre de recherche en haut de la liste pour trouver un eleve rapidement dans une grande classe
- **Validation en un clic** : Bouton "Enregistrer l'appel" sauvegarde tous les statuts en une seule requete
- **Upload de justificatif** : Drag & drop ou selection de fichier avec previsualisation
- **Validation Surveillant General** : Liste d'attente des justificatifs avec boutons Valider/Refuser
- **Tableau de bord consolide** : Vue Surveillant General avec absences du jour, de la semaine, alertes actives

### 3.3 Core Screens and Views

1. **Liste de mes seances (Enseignant)** : Liste des seances a venir et passees avec statut d'appel (Fait/A faire)
2. **Feuille d'appel (Enseignant)** : Liste d'eleves avec toggles de statut, compteur "X/Y presents"
3. **Historique de presence d'un eleve (Enseignant)** : Modal avec taux de presence et liste chronologique des seances
4. **Tableau de bord Absences (Surveillant General)** : Vue consolidee de toutes les absences du jour/semaine, eleves a risque, justificatifs en attente
5. **Validation des justificatifs (Surveillant General/Admin)** : File d'attente avec details de l'absence et document uploade
6. **Historique de presence (Eleve)** : Tableau avec statuts par seance et taux de presence par matiere
7. **Suivi absences enfant (Parent)** : Historique des absences, soumission de justificatifs, notifications
8. **Configuration des seuils (Admin)** : Page de parametrage des seuils d'alerte et durees
9. **Rapports de presence (Admin/Surveillant General)** : Filtres multiples + tableaux/graphiques de presence par classe, eleve, matiere
10. **Dashboard eleves a risque (Admin/Surveillant General)** : Liste des eleves depassant le seuil avec actions

### 3.4 Accessibility

- **WCAG AA** : Navigation clavier complete pour l'interface d'appel
- Statuts visuels combinant couleur + icone pour les daltoniens
- Labels explicites sur tous les toggles et boutons
- Interface testee sur lecteur d'ecran

### 3.5 Branding

Coherence avec l'application Gestion Scolaire. Palette de couleurs pour statuts :
- **Vert** : Present
- **Rouge** : Absent
- **Orange** : Retard
- **Bleu** : Excuse

### 3.6 Target Devices and Platforms

- **Web Responsive** : Interface desktop complete, optimisee tablette pour enseignants faisant l'appel en classe
- **Mobile-friendly** : Consultation de l'historique sur smartphone pour parents et eleves
- Fonctions d'appel utilisables sur tablette tactile (iOS/Android)
- Interface optimisee pour connexions a bande passante limitee (contexte Niger)

---

## 4. Technical Assumptions

### 4.1 Repository Structure

**Polyrepo** : Backend Laravel dans `crm-api`, frontend Next.js dans `crm-frontend`.

### 4.2 Service Architecture

**Architecture modulaire Laravel** :
- Module backend : `Modules/Presences` contenant Models (`AttendanceRecord`, `AttendanceJustification`), Controllers, Services, Form Requests, Resources
- Frontend Next.js : Pages `/teacher/presences`, `/surveillant/absences`, `/student/mes-presences`, `/parent/absences`, `/admin/presences`
- API RESTful avec routes admin, teacher, surveillant, student et parent guards

### 4.3 Testing Requirements

- **Tests unitaires** : Calcul du taux de presence, detection des seuils d'alerte, service de consolidation
- **Tests feature** : API CRUD pour attendance records, upload de justificatifs, validation/refus, alertes parents
- **Tests de validation** : Verification des regles metier (statuts valides, seuils, permissions par role)
- **Tests frontend** : Composants React pour la feuille d'appel, historique, dashboard Surveillant General
- **Tests E2E** : Playwright pour tester le workflow complet (appel -> notification parent -> justificatif -> validation)

### 4.4 Additional Technical Assumptions

- **Base de donnees** :
  - Table `attendance_records` : Enregistrements individuels de presence par eleve/seance
  - Table `attendance_justifications` : Justificatifs uploades avec statut de validation
  - Table `attendance_alerts` : Historique des alertes envoyees (parents, direction) pour eviter les doublons
  - Table `attendance_settings` : Configuration des seuils par tenant (ou integration dans `tenant_settings`)

- **Relations** :
  - `attendance_records` -> `sessions` (emplois du temps), `students` (eleves), `teachers` (enseignant qui fait l'appel)
  - `attendance_justifications` -> `attendance_records`, `students`, `reviewed_by` (Surveillant General ou Admin)
  - `attendance_alerts` -> `students`, `parents`

- **Services metier** :
  - `AttendanceCalculationService` : Calcul du taux de presence par eleve/matiere/classe/semestre
  - `AttendanceConsolidationService` : Consolidation des absences pour le Surveillant General
  - `AttendanceAlertService` : Detection des seuils depasses et envoi des notifications parents/direction
  - `AttendanceSheetService` : Generation automatique des feuilles d'appel depuis l'emploi du temps
  - `AttendanceSettingsService` : Recuperation des seuils configurables par tenant

- **Notifications** : Queue Laravel pour envoi asynchrone des alertes aux parents (email MVP, SMS Phase ulterieure)
- **Stockage fichiers** : Justificatifs stockes dans `storage/app/public/justifications/{tenant_id}/{student_id}/` avec liens symboliques
- **Permissions** :
  - Enseignant : CRUD attendance pour ses seances uniquement
  - Surveillant General : Lecture de toutes les absences, validation des justificatifs, envoi de convocations
  - Admin : Configuration des seuils, rapports complets, validation des justificatifs
  - Eleve : Consultation de ses propres presences uniquement
  - Parent : Consultation des presences de ses enfants, soumission de justificatifs
- **API Bulk Update** : Endpoint acceptant un array de statuts pour enregistrer tous les eleves d'une feuille d'appel en une seule requete

---

## 5. Epic List

### Epic 1 : Fondations et Modele de Donnees
Creer les entites de base (AttendanceRecord, AttendanceJustification, AttendanceAlert) et l'infrastructure API pour enregistrer et consulter les presences.

### Epic 2 : Interface d'Appel pour Enseignants
Permettre aux enseignants de faire l'appel rapidement pour chaque seance avec interface intuitive et sauvegarde en masse.

### Epic 3 : Consolidation et Dashboard Surveillant General
Fournir au Surveillant General une vue centralisee de toutes les absences avec outils de consolidation, filtres et indicateurs d'alerte.

### Epic 4 : Justification des Absences
Permettre aux parents de soumettre des justificatifs d'absence et au Surveillant General de les valider ou refuser.

### Epic 5 : Alertes Automatiques et Notifications Parents
Mettre en place les notifications automatiques aux parents et a la direction lorsqu'une absence non justifiee est enregistree ou qu'un seuil est depasse.

### Epic 6 : Consultation Eleve et Portail Parent
Permettre aux eleves et aux parents de consulter l'historique de presences/absences.

### Epic 7 : Rapports, Statistiques et Exports
Generer des rapports de presence detailles avec filtres multiples et exports Excel/PDF.

---

## 6. Epic 1 : Fondations et Modele de Donnees

**Objectif** : Etablir les fondations du module Presences avec creation des tables, models, relations, enums et endpoints API CRUD de base pour enregistrer et consulter les presences.

### Story 1.1 : Creer les Migrations et Models Backend

**En tant qu'** architecte technique,
**Je veux** creer les tables et models pour enregistrer les presences, justificatifs et alertes,
**Afin de** stocker les donnees d'assiduite des eleves.

**Acceptance Criteria :**

1. Migration `create_attendance_records_table` creee avec colonnes : `id`, `tenant_id`, `session_id` (FK vers sessions emplois du temps), `student_id` (FK vers students), `status` (enum: present/absent/late/excused), `marked_by` (teacher_id), `marked_at` (datetime), `notes` (text nullable), `timestamps`
2. Migration `create_attendance_justifications_table` creee avec colonnes : `id`, `tenant_id`, `attendance_record_id` (FK), `student_id` (FK), `submitted_by` (user_id - parent ou surveillant), `file_path`, `reason` (text), `status` (enum: pending/approved/rejected), `reviewed_by` (user_id nullable), `reviewed_at` (datetime nullable), `review_comment` (text nullable), `timestamps`
3. Migration `create_attendance_alerts_table` creee avec colonnes : `id`, `tenant_id`, `student_id` (FK), `parent_id` (FK nullable), `alert_type` (enum: single_absence/threshold_reached/convocation), `absence_count`, `message` (text), `sent_at`, `read_at` (nullable), `timestamps`
4. Model `AttendanceRecord` cree avec relations : `belongsTo(Session)`, `belongsTo(Student)`, `belongsTo(Teacher, 'marked_by')`, `hasOne(AttendanceJustification)`
5. Model `AttendanceJustification` cree avec relations : `belongsTo(AttendanceRecord)`, `belongsTo(Student)`, `belongsTo(User, 'submitted_by')`, `belongsTo(User, 'reviewed_by')`
6. Model `AttendanceAlert` cree avec relations : `belongsTo(Student)`, `belongsTo(Parent, 'parent_id')`
7. Enum `AttendanceStatus` cree : `Present`, `Absent`, `Late`, `Excused`
8. Enum `JustificationStatus` cree : `Pending`, `Approved`, `Rejected`
9. Enum `AlertType` cree : `SingleAbsence`, `ThresholdReached`, `Convocation`
10. Models utilisant le trait `BelongsToTenant` pour isolation multi-tenant
11. Factories pour generation de donnees de test
12. Index sur `(tenant_id, student_id, session_id)` pour performance des requetes
13. Tests unitaires verifiant les relations et les enums

### Story 1.2 : Creer les API Endpoints CRUD pour Attendance Records

**En tant qu'** enseignant,
**Je veux** enregistrer et modifier les presences via l'API,
**Afin de** sauvegarder l'appel de mes seances.

**Acceptance Criteria :**

1. Route `GET /api/teacher/sessions/{session_id}/attendance` retournant tous les enregistrements de presence pour une seance
2. Route `POST /api/teacher/sessions/{session_id}/attendance/bulk` acceptant un array de `[{student_id, status, notes?}]` pour creation/mise a jour en masse
3. Route `PUT /api/teacher/attendance/{id}` mettant a jour le statut d'un enregistrement individuel
4. `AttendanceRecordResource` transformant les donnees avec infos eleve (nom complet, matricule, photo)
5. `StoreAttendanceRequest` et `UpdateAttendanceRequest` pour validation
6. Validation : `session_id` doit appartenir a l'enseignant authentifie
7. Validation : `status` doit etre une valeur de l'enum `AttendanceStatus`
8. Validation : modification interdite si la seance date de plus de X heures (configurable, defaut 24h)
9. Middleware `auth:sanctum` et verification du role enseignant
10. Tests feature couvrant tous les endpoints avec verification des permissions

### Story 1.3 : Creer le Service de Calcul du Taux de Presence

**En tant que** developpeur backend,
**Je veux** centraliser la logique de calcul du taux de presence,
**Afin de** reutiliser ce code dans les differents endpoints et rapports.

**Acceptance Criteria :**

1. Service `AttendanceCalculationService` cree dans `Modules/Presences/Services/`
2. Methode `calculateAttendanceRateForStudent(int $studentId, int $subjectId, ?int $semesterId): float` retournant le taux en pourcentage
3. Formule : `(nombre de presences + nombre de retards + nombre d'excuses) / nombre total de seances * 100`
4. Methode `calculateAttendanceRateForClass(int $classId, ?int $semesterId): float` retournant le taux moyen de la classe
5. Methode `getAbsenceCountForStudent(int $studentId, ?int $subjectId, ?int $semesterId): array` retournant `['justified' => X, 'unjustified' => Y, 'total' => Z]`
6. Methode `getConsolidatedAbsencesForStudent(int $studentId, int $semesterId): Collection` retournant le detail par matiere
7. Cache des resultats pour 5 minutes pour optimiser les performances
8. Tests unitaires exhaustifs couvrant tous les cas de figure (eleve sans absences, toutes absences, mix justifiees/non justifiees)

### Story 1.4 : Implémenter la Generation Automatique des Feuilles d'Appel

**En tant que** systeme,
**Je veux** creer automatiquement une feuille d'appel vierge lorsqu'une seance est planifiee,
**Afin que** l'enseignant n'ait pas a la creer manuellement.

**Acceptance Criteria :**

1. Service `AttendanceSheetService` cree avec methode `generateSheetForSession(int $sessionId): void`
2. Lors de la creation d'une seance (dans module Emplois du Temps), appel automatique via event/listener
3. Generation d'un enregistrement `AttendanceRecord` par defaut "Present" pour chaque eleve inscrit dans la classe de la seance
4. Si une feuille existe deja pour cette seance, ne pas regenerer (idempotence)
5. Command Artisan `attendance:generate-sheets` permettant de regenerer pour toutes les seances sans feuille (rattrapage)
6. Tests verifiant la generation correcte et l'idempotence

---

## 7. Epic 2 : Interface d'Appel pour Enseignants

**Objectif** : Creer l'interface frontend permettant aux enseignants de faire l'appel rapidement avec liste d'eleves, toggles de statut, et sauvegarde en masse.

### Story 2.1 : Creer la Page Liste des Seances avec Statut d'Appel

**En tant qu'** enseignant,
**Je veux** voir la liste de mes seances a venir et passees avec le statut d'appel,
**Afin de** savoir pour quelles seances je dois encore faire l'appel.

**Acceptance Criteria :**

1. Page Next.js `/teacher/presences` creee avec authentification guard teacher
2. Appel API recuperant les seances de l'enseignant pour le semestre en cours
3. Composant `SessionList` affichant un tableau avec colonnes : Date, Heure, Matiere, Classe, Statut d'appel
4. Statut d'appel : "Fait" si l'appel a ete enregistre, "A faire" sinon
5. Badge colore : vert (Fait), orange (A faire), gris (Seance future)
6. Filtres : Semaine en cours / Toutes les seances, Filtre par matiere, Filtre par classe
7. Bouton "Faire l'appel" sur chaque ligne redirigeant vers la page d'appel
8. Pour les seances passees non appelees, affichage d'un badge "Appel en retard"
9. Compteur en haut : "X seances a appeler aujourd'hui"

### Story 2.2 : Creer l'Interface de Feuille d'Appel

**En tant qu'** enseignant,
**Je veux** faire l'appel rapidement avec une liste d'eleves et des toggles de statut,
**Afin de** marquer les absences en moins de 3 minutes.

**Acceptance Criteria :**

1. Page Next.js `/teacher/presences/session/[sessionId]/appel` creee
2. En-tete affichant : Date, Heure, Matiere, Classe, Salle
3. Composant `AttendanceSheet` affichant une liste d'eleves avec :
   - Photo de l'eleve (thumbnail)
   - Nom complet de l'eleve
   - Numero matricule
   - Toggle buttons pour statuts : **P** (Present), **A** (Absent), **R** (Retard), **E** (Excuse)
4. Statut par defaut "Present" pour tous (bouton P active par defaut)
5. Clic sur un bouton de statut met a jour visuellement immediatement (optimistic UI)
6. Compteur en temps reel : "X/Y eleves presents"
7. Barre de recherche permettant de filtrer la liste par nom
8. Bouton "Tout marquer present" pour reinitialiser tous les statuts
9. Bouton "Enregistrer l'appel" en bas de page (sticky)
10. Clic sur "Enregistrer" appelle API `POST /api/teacher/sessions/{id}/attendance/bulk` avec tous les statuts
11. Toast de succes "Appel enregistre avec succes" et redirection vers la liste des seances
12. Auto-save toutes les 30 secondes en background (draft) avec indicateur visuel
13. Champ de note optionnel pour chaque eleve (icone commentaire a cote du statut)

### Story 2.3 : Ajouter la Possibilite de Modifier un Appel Passe

**En tant qu'** enseignant,
**Je veux** modifier les presences d'une seance passee pendant la duree autorisee,
**Afin de** corriger d'eventuelles erreurs.

**Acceptance Criteria :**

1. Sur la page `/teacher/presences`, bouton "Modifier l'appel" disponible pour les seances dans la duree autorisee (configurable, defaut 24h)
2. Bouton desactive si delai depasse avec tooltip "Modification non autorisee apres Xh"
3. Clic sur "Modifier" ouvre la meme interface de feuille d'appel avec statuts pre-remplis
4. Appel API `PUT` pour mettre a jour les enregistrements modifies uniquement
5. Logging de toutes les modifications (qui, quand, ancien statut, nouveau statut) dans un historique d'audit
6. Affichage d'un badge "Modifie" sur les seances dont l'appel a ete modifie
7. Tests verifiant les permissions et la contrainte de duree

### Story 2.4 : Implementer la Vue Mobile/Tablette pour l'Appel

**En tant qu'** enseignant utilisant une tablette en classe,
**Je veux** une interface optimisee tactile pour faire l'appel,
**Afin d'** utiliser ma tablette plutot qu'un ordinateur.

**Acceptance Criteria :**

1. Interface d'appel responsive adaptee aux ecrans tactiles (tablette 10"+)
2. Boutons de statut plus grands (min 44x44px) pour interaction tactile facile
3. Mode plein ecran optionnel masquant la navigation pour maximiser la liste
4. Photos eleves plus grandes sur tablette pour identification facile
5. Bouton flottant "Enregistrer" toujours visible en bas (sticky)
6. Performance optimisee : chargement de la feuille d'appel en moins de 2 secondes sur connexion 3G

### Story 2.5 : Ajouter l'Historique de Presence d'un Eleve (Vue Enseignant)

**En tant qu'** enseignant,
**Je veux** consulter l'historique de presence d'un eleve specifique dans ma matiere,
**Afin de** suivre son assiduite et intervenir si necessaire.

**Acceptance Criteria :**

1. Clic sur le nom d'un eleve dans la feuille d'appel ouvre un modal avec son historique
2. Modal affiche : Photo, Nom, Matricule, Classe, Taux de presence dans cette matiere
3. Liste chronologique des seances avec statuts et dates
4. Nombre total d'absences justifiees et non justifiees
5. Indicateur visuel si taux d'absence > seuil (ex: badge rouge "Assiduite faible")
6. Tests verifiant que l'enseignant ne voit que les donnees de ses matieres

---

## 8. Epic 3 : Consolidation et Dashboard Surveillant General

**Objectif** : Fournir au Surveillant General une vue centralisee et consolidee de toutes les absences de l'etablissement, avec des outils de suivi, filtrage et actions rapides.

### Story 3.1 : Creer le Dashboard Absences du Surveillant General

**En tant que** Surveillant General,
**Je veux** avoir un tableau de bord centralise de toutes les absences,
**Afin de** suivre l'assiduite de tous les eleves de l'etablissement.

**Acceptance Criteria :**

1. Page Next.js `/surveillant/absences` creee avec authentification guard surveillant
2. **Widget "Absences du jour"** : Nombre total d'absences enregistrees aujourd'hui, reparties par classe
3. **Widget "Eleves a risque"** : Liste des 10 eleves ayant le plus d'absences non justifiees ce semestre
4. **Widget "Justificatifs en attente"** : Nombre de justificatifs en attente de validation avec lien direct
5. **Widget "Appels non effectues"** : Liste des seances du jour pour lesquelles l'appel n'a pas ete fait
6. Graphique : Evolution des absences par semaine (line chart)
7. Filtres rapides : Par classe, par niveau (6e-Tle), par semestre
8. Endpoint API `GET /api/surveillant/absences/dashboard` retournant les donnees agreees
9. Refresh automatique toutes les 5 minutes
10. Tests feature verifiant les donnees agreees et les permissions

### Story 3.2 : Creer la Vue Consolidee des Absences par Classe

**En tant que** Surveillant General,
**Je veux** voir les absences consolidees par classe,
**Afin d'** identifier les classes avec les taux d'absence les plus eleves.

**Acceptance Criteria :**

1. Page `/surveillant/absences/classes` creee
2. Tableau avec colonnes : Classe, Effectif, Absences totales (semestre), Absences non justifiees, Taux de presence moyen
3. Tri par defaut : Taux de presence croissant (les pires en premier)
4. Indicateur visuel : rouge si taux < 70%, orange si 70-85%, vert si > 85%
5. Clic sur une classe ouvre le detail par eleve
6. Endpoint API `GET /api/surveillant/absences/by-class?semester_id=X`
7. Export Excel du tableau

### Story 3.3 : Creer la Vue Detaillee des Absences par Eleve

**En tant que** Surveillant General,
**Je veux** voir le detail complet des absences d'un eleve,
**Afin de** preparer une eventuelle convocation des parents.

**Acceptance Criteria :**

1. Page `/surveillant/absences/eleves/[studentId]` creee
2. En-tete : Photo, Nom, Prenom, Matricule, Classe, Professeur principal
3. Statistiques globales : Total absences, Justifiees, Non justifiees, Taux de presence global
4. Detail par matiere : Tableau avec Matiere, Heures d'absence, Taux de presence, Statut (normal/alerte)
5. Historique chronologique de toutes les absences avec date, matiere, statut, justificatif
6. Informations parents : Nom, telephone, email, historique des notifications envoyees
7. Bouton "Convoquer les parents" ouvrant un formulaire de convocation
8. Bouton "Exporter fiche d'absences" (PDF) pour impression
9. Tests verifiant l'acces aux donnees cross-matiere

### Story 3.4 : Implementer la Recherche et le Filtrage Avances

**En tant que** Surveillant General,
**Je veux** rechercher et filtrer les absences selon plusieurs criteres,
**Afin de** trouver rapidement les informations dont j'ai besoin.

**Acceptance Criteria :**

1. Page `/surveillant/absences/recherche` creee
2. Filtres combinables : Classe, Eleve (recherche par nom), Matiere, Periode (dates), Statut (justifiee/non justifiee)
3. Resultats en tableau avec colonnes : Eleve, Classe, Matiere, Date, Statut, Justificatif
4. Pagination (20 resultats par page)
5. Export Excel des resultats filtres
6. Endpoint API `GET /api/surveillant/absences/search` avec query params multiples

---

## 9. Epic 4 : Justification des Absences

**Objectif** : Permettre aux parents de soumettre des justificatifs d'absence (certificats medicaux, etc.) et au Surveillant General de les valider ou rejeter, avec mise a jour automatique du statut de l'absence.

### Story 4.1 : Creer l'API Backend pour les Justificatifs

**En tant que** developpeur backend,
**Je veux** creer les endpoints API pour gerer les justificatifs d'absence,
**Afin de** permettre la soumission, consultation et validation.

**Acceptance Criteria :**

1. Route `POST /api/parent/attendance/{id}/justify` creant un justificatif (guard parent)
2. Route `POST /api/surveillant/attendance/{id}/justify` creant un justificatif au nom de l'eleve (guard surveillant)
3. Validation : `reason` (required, max 500), `file` (required, mimes:pdf,jpg,png, max:5120KB)
4. Route `GET /api/parent/my-children/{studentId}/justifications` listant les justificatifs d'un enfant
5. Route `GET /api/surveillant/justifications/pending` listant les justificatifs en attente de validation
6. Route `PUT /api/surveillant/justifications/{id}/approve` validant un justificatif
7. Route `PUT /api/surveillant/justifications/{id}/reject` rejetant un justificatif avec commentaire obligatoire
8. Lors de l'approbation : mise a jour du statut `AttendanceRecord` de "Absent" a "Excused" et recalcul des taux
9. `AttendanceJustificationResource` incluant les relations et l'URL de telechargement du fichier
10. `StoreJustificationRequest` pour validation des regles
11. Tests feature couvrant tous les endpoints avec verification des permissions (parent ne voit que ses enfants, surveillant voit tout)

### Story 4.2 : Creer le Formulaire de Soumission de Justificatif (Parent)

**En tant que** parent,
**Je veux** justifier une absence de mon enfant en uploadant un document,
**Afin que** l'absence soit consideree comme excusee.

**Acceptance Criteria :**

1. Page Next.js dans le portail parent `/parent/absences/{attendanceRecordId}/justifier` creee
2. Formulaire affichant les details de l'absence : Nom de l'enfant, Date, Matiere, Enseignant
3. Champ `reason` (textarea, max 500 caracteres) pour expliquer la raison de l'absence
4. Zone de drag & drop pour upload de fichier justificatif
5. Formats acceptes : PDF, JPG, PNG (max 5 MB)
6. Previsualisation du fichier uploade (thumbnail pour image, icone pour PDF)
7. Bouton "Soumettre le justificatif"
8. Appel API `POST /api/parent/attendance/{id}/justify` avec FormData (file + reason)
9. Backend sauvegarde le fichier dans `storage/app/public/justifications/{tenant_id}/{student_id}/`
10. Toast de succes "Justificatif soumis avec succes. En attente de validation par le Surveillant General."
11. Possibilite de soumettre un justificatif depuis la liste des absences non justifiees

### Story 4.3 : Creer la Page de Validation des Justificatifs (Surveillant General)

**En tant que** Surveillant General,
**Je veux** voir la liste des justificatifs en attente et les valider ou rejeter,
**Afin de** traiter les demandes des parents.

**Acceptance Criteria :**

1. Page Next.js `/surveillant/justificatifs` creee
2. Tableau avec colonnes : Eleve, Classe, Matiere, Date absence, Raison, Document, Soumis par, Date soumission, Actions
3. Colonne "Document" avec bouton "Telecharger" ou previsualisation inline (iframe pour PDF, image pour JPG/PNG)
4. Boutons d'action : "Approuver" (vert) et "Rejeter" (rouge)
5. Clic sur "Rejeter" ouvre un modal demandant un commentaire obligatoire
6. Mise a jour en temps reel du tableau apres action (retrait de la ligne traitee)
7. Filtres : Classe, Eleve (recherche), Date, Statut (en attente / tous)
8. Badge dans la navigation indiquant le nombre de justificatifs en attente
9. Possibilite de saisir un justificatif manuellement pour un eleve (papier depose en main propre)
10. Tests E2E verifiant le workflow complet de validation

### Story 4.4 : Notifier les Parents du Statut de la Justification

**En tant que** parent,
**Je veux** etre informe lorsque le justificatif est valide ou refuse,
**Afin de** savoir si l'absence de mon enfant a ete excusee.

**Acceptance Criteria :**

1. Notification automatique envoyee au parent lors de la validation ou du refus d'un justificatif
2. Contenu de la notification : Nom de l'enfant, Date de l'absence, Decision (approuve/refuse), Commentaire si refuse
3. Liste des justificatifs soumis visible dans le portail parent avec statuts : En attente (orange), Approuve (vert), Refuse (rouge)
4. Pour les justifications refusees, affichage du commentaire du Surveillant General et possibilite de re-soumettre un nouveau justificatif
5. Tests verifiant l'envoi des notifications

---

## 10. Epic 5 : Alertes Automatiques et Notifications Parents

**Objectif** : Mettre en place un systeme de notifications automatiques pour alerter les parents en temps reel des absences de leur enfant et alerter la direction lorsqu'un eleve depasse les seuils d'absences configurables.

### Story 5.1 : Implementer la Notification d'Absence Unitaire aux Parents

**En tant que** parent,
**Je veux** etre notifie immediatement lorsque mon enfant est marque absent,
**Afin de** pouvoir reagir rapidement (verifier, justifier, intervenir).

**Acceptance Criteria :**

1. Listener sur l'evenement `AttendanceRecordCreated` (ou `AttendanceRecordUpdated` si statut passe a "Absent")
2. Si le statut est "Absent" et que les alertes unitaires sont activees, dispatch d'un job `NotifyParentAbsenceJob` dans la queue
3. Le job recupere les parents de l'eleve et leur envoie un email avec : Nom de l'enfant, Date, Heure, Matiere, Enseignant
4. Creation d'un enregistrement `AttendanceAlert` (type: `SingleAbsence`) pour historique
5. Le job ne doit pas envoyer de doublon si la meme absence est modifiee puis remodifiee
6. Envoi asynchrone via queue Laravel (ne pas bloquer l'enseignant)
7. Template email professionnel et clair avec lien vers le portail parent
8. Tests verifiant l'envoi et l'absence de doublons

### Story 5.2 : Implementer la Detection de Seuil et Alerte Direction

**En tant qu'** administrateur ou Surveillant General,
**Je veux** etre alerte automatiquement lorsqu'un eleve depasse le seuil d'absences non justifiees,
**Afin d'** intervenir avant que la situation ne se degrade.

**Acceptance Criteria :**

1. Service `AttendanceAlertService` avec methode `checkThresholdForStudent(int $studentId): bool`
2. Apres chaque enregistrement d'absence, verification du seuil configure (par defaut : 3 absences non justifiees)
3. Si seuil depasse et pas d'alerte deja envoyee pour ce seuil : dispatch de `NotifyThresholdReachedJob`
4. Notification envoyee a : parents de l'eleve, Surveillant General, direction (Admin)
5. Contenu : Nom de l'eleve, Classe, Nombre d'absences non justifiees, Detail par matiere
6. Creation d'un enregistrement `AttendanceAlert` (type: `ThresholdReached`) pour eviter les doublons
7. Command Artisan `attendance:check-thresholds` pour verification batch quotidienne (scheduled daily)
8. Tests verifiant la detection correcte et l'absence de doublons

### Story 5.3 : Creer la Page de Configuration des Seuils d'Alerte

**En tant qu'** administrateur,
**Je veux** configurer les seuils d'alerte d'absence pour mon etablissement,
**Afin d'** adapter les regles aux politiques de mon etablissement.

**Acceptance Criteria :**

1. Page Next.js `/admin/settings/presences` creee (partie des settings tenant)
2. Formulaire avec champs :
   - Seuil d'absences non justifiees declenchant une alerte : defaut 3
   - Seuil de taux d'absence declenchant une alerte direction (%) : defaut 25
   - Duree de modification d'appel autorisee (heures) : defaut 24
   - Activer les notifications automatiques aux parents (absence unitaire) : checkbox
   - Activer les alertes de seuil (direction + parents) : checkbox
3. Endpoint API `GET /api/admin/settings/attendance` recuperant les settings
4. Endpoint API `PUT /api/admin/settings/attendance` mettant a jour les settings
5. `UpdateAttendanceSettingsRequest` avec validation : seuil entre 1 et 50, duree entre 1 et 72 heures, taux entre 1 et 100
6. Service `AttendanceSettingsService` pour recuperer facilement ces valeurs avec cache
7. Tests verifiant la sauvegarde et l'application des settings

### Story 5.4 : Implementer l'Historique des Notifications et Convocations

**En tant que** Surveillant General,
**Je veux** consulter l'historique de toutes les notifications envoyees aux parents d'un eleve,
**Afin de** suivre les communications et preparer les entretiens.

**Acceptance Criteria :**

1. Page `/surveillant/absences/eleves/[studentId]/notifications` creee
2. Tableau chronologique : Date, Type (absence unitaire / seuil depasse / convocation), Destinataire, Contenu, Statut (envoye / lu)
3. Bouton "Envoyer une convocation" ouvrant un formulaire avec : Motif, Date souhaitee de l'entretien, Message personnalise
4. Endpoint API `POST /api/surveillant/students/{id}/convocations` creant une convocation
5. La convocation est envoyee par email au parent et enregistree dans `attendance_alerts` (type: `Convocation`)
6. Tests verifiant l'historique et l'envoi de convocations

---

## 11. Epic 6 : Consultation Eleve et Portail Parent

**Objectif** : Permettre aux eleves de consulter leur historique de presence et aux parents de suivre l'assiduite de leur(s) enfant(s) depuis le portail parent.

### Story 6.1 : Creer la Page d'Historique de Presence (Eleve)

**En tant qu'** eleve,
**Je veux** consulter mon historique de presence pour toutes mes matieres,
**Afin de** suivre mon assiduite et identifier mes absences.

**Acceptance Criteria :**

1. Page Next.js `/student/mes-presences` creee avec authentification guard student
2. Endpoint API `GET /api/student/my-attendance/summary` retournant le resume par matiere
3. En-tete affichant le taux de presence global avec jauge visuelle (ex: 85% = vert)
4. Seuil visuel : vert (>85%), orange (70-85%), rouge (<70%)
5. Liste des matieres avec pour chacune :
   - Nom de la matiere
   - Taux de presence (%)
   - Nombre de seances : X present, Y absent, Z retard, W excuse
   - Badge d'alerte si taux < seuil
6. Clic sur une matiere ouvre le detail (liste chronologique des seances avec statuts)
7. Affichage du nombre total d'absences non justifiees

### Story 6.2 : Creer la Vue Absences dans le Portail Parent

**En tant que** parent,
**Je veux** voir les absences de mon enfant depuis mon portail,
**Afin de** suivre son assiduite et reagir en cas de probleme.

**Acceptance Criteria :**

1. Section "Absences" dans le portail parent `/parent/enfants/[studentId]/absences`
2. Endpoint API `GET /api/parent/my-children/{studentId}/attendance/summary`
3. Resume : Taux de presence global, Total absences (justifiees / non justifiees)
4. Detail par matiere : Matiere, Taux de presence, Absences non justifiees, Statut
5. Liste des absences recentes avec : Date, Matiere, Statut (justifiee ou non), Lien "Justifier"
6. Bouton "Justifier une absence" redirigeant vers le formulaire de soumission (Story 4.2)
7. Section "Mes justificatifs" : Liste des justificatifs soumis avec statuts
8. Section "Notifications recues" : Historique des alertes recues
9. Si multi-enfants : selection de l'enfant via dropdown en haut de page
10. Tests verifiant que le parent ne voit que les donnees de ses propres enfants

### Story 6.3 : Ajouter des Alertes sur le Dashboard Eleve et Parent

**En tant qu'** eleve ou parent,
**Je veux** voir une alerte visible si le taux de presence est faible,
**Afin d'** etre conscient du risque et agir rapidement.

**Acceptance Criteria :**

1. Composant `AttendanceAlert` ajoute au dashboard eleve (`/student/dashboard`) et parent (`/parent/dashboard`)
2. Appel API verifiant si le taux de presence global ou d'une matiere < seuil
3. Si seuil depasse, affichage d'une alerte visuelle en haut de page :
   - Message : "Attention : Votre taux de presence en {Matiere} est de X% (seuil minimum : Y%)" (eleve)
   - Message : "Attention : Le taux de presence de {Prenom} en {Matiere} est de X%" (parent)
   - Bouton "Voir les absences"
4. Alerte affichee uniquement si seuil depasse (sinon, rien)
5. Tests verifiant l'affichage conditionnel

---

## 12. Epic 7 : Rapports, Statistiques et Exports

**Objectif** : Fournir des rapports detailles de presence avec filtres multiples, statistiques visuelles, et exports Excel/PDF pour l'administration, le Surveillant General et les enseignants.

### Story 7.1 : Creer la Page de Rapports de Presence (Admin/Surveillant General)

**En tant qu'** administrateur ou Surveillant General,
**Je veux** acceder a des rapports de presence avec filtres avances,
**Afin d'** analyser l'assiduite sous differents angles.

**Acceptance Criteria :**

1. Page Next.js `/admin/presences/rapports` creee (accessible aussi au Surveillant General)
2. Formulaire de filtres avec champs :
   - Periode : Date debut - Date fin (date pickers)
   - Classe : Select multiple
   - Matiere : Select multiple
   - Enseignant : Select
   - Niveau : Select (6e, 5e, 4e, 3e, 2nde, 1ere, Tle)
   - Statut : Tous / Absent / Retard / Non justifie
3. Bouton "Generer le rapport"
4. Endpoint API `GET /api/admin/presences/reports` avec query params pour filtres
5. Affichage des resultats en tableau et graphiques
6. Sauvegarde des filtres en localStorage pour reutilisation rapide

### Story 7.2 : Generer des Statistiques Visuelles de Presence

**En tant qu'** administrateur,
**Je veux** voir des graphiques de presence,
**Afin de** visualiser rapidement les tendances.

**Acceptance Criteria :**

1. Composant `AttendanceCharts` ajoute a la page rapports
2. Graphique 1 : Taux de presence global par semaine (line chart)
3. Graphique 2 : Repartition des statuts (pie chart : Present, Absent, Retard, Excuse)
4. Graphique 3 : Taux de presence par classe (bar chart, du meilleur au pire)
5. Graphique 4 : Top 10 eleves avec le plus d'absences non justifiees (bar chart)
6. Graphiques responsive et interactifs (tooltips au survol)
7. Tests verifiant la generation des donnees correctes pour les graphiques

### Story 7.3 : Implementer l'Export Excel des Rapports de Presence

**En tant qu'** administrateur ou Surveillant General,
**Je veux** exporter les donnees de presence en Excel,
**Afin de** les analyser dans un tableur ou les partager.

**Acceptance Criteria :**

1. Endpoint API `GET /api/admin/presences/reports/export` avec memes filtres que rapports
2. Classe Export `AttendanceReportExport` implementant `FromCollection`
3. Feuille "Detail" avec colonnes : Eleve, Matricule, Classe, Matiere, Date, Statut, Enseignant, Justifie (Oui/Non)
4. Feuille "Resume par eleve" avec colonnes : Eleve, Classe, Total seances, Presents, Absents, Retards, Excuses, Taux presence
5. Feuille "Resume par classe" avec colonnes : Classe, Effectif, Taux presence moyen, Total absences, Absences non justifiees
6. Formatage : en-tetes en gras, filtres automatiques, colonnes auto-dimensionnees, codes couleur par statut
7. Nom de fichier : `rapport-presences-{date-debut}-{date-fin}.xlsx`
8. Generation en < 10 secondes meme pour 10 000 enregistrements
9. Tests verifiant la structure et le contenu

### Story 7.4 : Generer la Fiche Recapitulative d'Absences pour le Bulletin

**En tant qu'** administrateur,
**Je veux** generer automatiquement le recapitulatif d'absences pour chaque eleve,
**Afin de** l'inclure dans le bulletin semestriel.

**Acceptance Criteria :**

1. Service `AttendanceBulletinService` avec methode `getAbsenceSummaryForBulletin(int $studentId, int $semesterId): array`
2. Retour : `['total_hours_absent' => X, 'justified_hours' => Y, 'unjustified_hours' => Z, 'late_count' => W]`
3. Endpoint API `GET /api/admin/students/{id}/attendance-bulletin?semester_id=X` retournant les donnees
4. Endpoint batch `GET /api/admin/classes/{id}/attendance-bulletin?semester_id=X` retournant les donnees pour toute une classe
5. Integration avec le Module Documents Officiels pour insertion dans le template de bulletin
6. Tests verifiant les calculs

### Story 7.5 : Creer le Rapport de Presence par Matiere pour Enseignant

**En tant qu'** enseignant,
**Je veux** generer un rapport de presence pour ma matiere dans une classe,
**Afin de** suivre l'assiduite de mes eleves.

**Acceptance Criteria :**

1. Page Next.js `/teacher/presences/rapports` creee (guard teacher)
2. Endpoint API `GET /api/teacher/my-subjects/{subjectId}/classes/{classId}/attendance-report`
3. Tableau affichant : Eleve, Taux de presence, Nombre absences (justifiees/non justifiees), Nombre retards
4. Tri par defaut : taux de presence croissant (les pires en premier)
5. Graphique : Evolution du taux de presence moyen de la classe au fil des semaines
6. Export PDF du rapport avec en-tete (etablissement, matiere, classe, enseignant)
7. Export Excel pour analyse detaillee
8. Tests verifiant que l'enseignant ne voit que ses matieres et classes

### Story 7.5 : Dashboard Eleves a Risque

**En tant qu'** administrateur ou Surveillant General,
**Je veux** avoir un dashboard listant les eleves depassant le seuil d'absences,
**Afin d'** intervenir rapidement.

**Acceptance Criteria :**

1. Page Next.js `/admin/presences/eleves-a-risque` creee (accessible aussi au Surveillant General)
2. Endpoint API `GET /api/admin/presences/at-risk-students` utilisant `AttendanceAlertService`
3. Tableau avec colonnes : Eleve (photo + nom), Classe, Absences non justifiees, Taux de presence, Derniere absence, Actions
4. Tri par defaut : absences non justifiees decroissantes (les pires en premier)
5. Filtres : Classe, Niveau, Seuil personnalise
6. Indicateur visuel : rouge si > seuil + 50%, orange si proche du seuil
7. Bouton "Voir le detail" ouvrant la fiche de l'eleve
8. Bouton "Convoquer les parents" pour action rapide
9. Export Excel de la liste
10. Compteur en haut : "X eleves a risque"
11. Widget integre au dashboard principal admin avec les 5 eleves les plus critiques

---

## 13. Next Steps

### Architect Prompt

Le PRD du Module Presences & Absences est complet. Veuillez creer le document d'architecture technique detaille couvrant :
- Structure de base de donnees (tables `attendance_records`, `attendance_justifications`, `attendance_alerts`, indexes, relations)
- Architecture API (endpoints CRUD par role : enseignant, surveillant general, admin, parent, eleve)
- Services metier (`AttendanceCalculationService`, `AttendanceConsolidationService`, `AttendanceAlertService`, `AttendanceSheetService`, `AttendanceBulletinService`, `AttendanceSettingsService`)
- Stockage et securisation des fichiers justificatifs (isolation par tenant)
- Strategie de notification (events, listeners, jobs, queue, templates email)
- Integration avec les modules adjacents (Emplois du Temps, Inscriptions, Portail Parent, Discipline, Documents Officiels)
- Plan de tests (unitaires, features, E2E)
- Optimisations de performance (caching des calculs, eager loading, indexes)

### UX Expert Prompt

Merci de creer les wireframes et maquettes pour les ecrans principaux du Module Presences & Absences :
- Interface de feuille d'appel pour enseignant (desktop et tablette)
- Dashboard consolidation absences pour Surveillant General
- Formulaire de justification d'absence (portail parent)
- Page de validation des justificatifs (Surveillant General)
- Historique de presence pour eleve
- Vue absences dans le portail parent
- Dashboard eleves a risque
- Page de rapports avec graphiques
- Page de configuration des seuils d'alerte

Assurez-vous que le design soit coherent avec l'application Gestion Scolaire, optimise pour les interactions tactiles sur tablette (enseignants) et pour les smartphones (parents, eleves), et adapte aux connexions a bande passante limitee.

---

**Maintenu par** : John (PM)
**Derniere mise a jour** : 2026-03-16
