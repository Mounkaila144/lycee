# PRD - Module Emplois du Temps (EDT)

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Module** : Emplois du Temps (Scheduling)
> **Version** : v5
> **Date** : 2026-03-16
> **Phase** : Phase 2 - Vie Scolaire & Operations
> **Priorite** : HAUTE 🟠

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 5.0 | Refonte complete - Passage du LMD (superieur) au secondaire (colleges/lycees Niger) | John (PM) |
| 2026-01-07 | 1.0 | Creation initiale du PRD Module EDT (systeme LMD) | Claude (PM Agent) |

---

## 1. Goals and Background Context

### 1.1 Goals

- Permettre aux administrateurs de creer et gerer les emplois du temps par classe de maniere centralisee et efficace
- Detecter automatiquement les conflits de planning (salle, enseignant) pour eviter les doubles reservations
- Offrir une visualisation claire et intuitive des emplois du temps pour tous les acteurs (eleves, enseignants, parents, administration)
- Permettre l'export des emplois du temps en format imprimable (PDF) pour affichage en classe et distribution
- Reduire le temps de creation d'un emploi du temps de plusieurs jours a quelques heures
- Eliminer les erreurs de planification et les conflits d'affectation de ressources
- Faciliter la consultation multi-roles : chaque acteur accede a l'information qui le concerne (son EDT, l'EDT de sa classe, la vue d'ensemble)

### 1.2 Background Context

La creation manuelle des emplois du temps dans les colleges et lycees au Niger est un processus chronophage et source d'erreurs. Les censeurs et surveillants generaux passent plusieurs jours, voire des semaines, a etablir les plannings sur tableau blanc ou papier, parfois en utilisant Excel. Les conflits (un enseignant programme dans deux classes en meme temps, une salle doublement reservee) ne sont detectes qu'apres affichage, necessitant des corrections couteuses en temps et generant de la confusion chez les eleves.

Le Module Emplois du Temps numerise ce processus critique en permettant la creation visuelle des plannings avec detection automatique des conflits en temps reel. Il s'integre avec le Module Structure Academique (pour les classes, matieres, enseignants, salles) et sert de base au Module Presences/Absences (l'appel se fait par seance). La consultation multi-roles garantit que chaque acteur a acces a l'information qui le concerne :

- **Admin/Directeur/Censeur** : Vue d'ensemble de tous les EDT, creation et modification
- **Enseignant** : Consultation de son EDT personnel (toutes ses seances dans toutes les classes)
- **Eleve** : Consultation de l'EDT de sa classe
- **Parent/Tuteur** : Consultation de l'EDT de la classe de son enfant
- **Surveillant General** : Vue d'ensemble pour le suivi de la vie scolaire

**Dependances** :
- **Prerequis** : Module Structure Academique (classes, matieres, enseignants, salles)
- **Consomme par** : Module Presences/Absences (appel par seance), Module Notes (contexte des evaluations)

---

## 2. Requirements

### 2.1 Functional Requirements

**FR1:** Le systeme doit permettre de creer des emplois du temps pour chaque classe (ex: 6e A, 5e B, 4e C, 3e A, 2nde A, 1ere C1, Tle D2) par annee scolaire et par semestre

**FR2:** Chaque seance d'emploi du temps doit inclure : jour de la semaine, heure de debut, heure de fin, matiere, enseignant, salle, et classe concernee

**FR3:** Le systeme doit detecter automatiquement et bloquer la creation de seances en conflit :
- Enseignant deja affecte a une autre seance au meme moment
- Salle deja occupee par une autre seance au meme moment

**FR4:** Le systeme doit afficher des avertissements (warnings) pour les situations potentiellement problematiques mais non bloquantes :
- Enseignant ayant plus de X heures de cours par jour (seuil configurable)
- Classe ayant plus de Y heures consecutives sans pause (seuil configurable)
- Salle sous-dimensionnee par rapport a l'effectif de la classe

**FR5:** Le systeme doit permettre la modification et la suppression de seances avec recalcul automatique des conflits

**FR6:** Le systeme doit offrir trois vues principales :
- Vue grille hebdomadaire par classe
- Vue grille hebdomadaire par enseignant
- Vue grille hebdomadaire par salle

**FR7:** Le systeme doit permettre la duplication d'un emploi du temps d'un semestre ou d'une annee precedente comme template de depart

**FR8:** Les eleves doivent pouvoir consulter l'emploi du temps de leur classe en lecture seule

**FR9:** Les parents/tuteurs doivent pouvoir consulter l'emploi du temps de la classe de leur(s) enfant(s) en lecture seule

**FR10:** Les enseignants doivent pouvoir consulter leur emploi du temps personnel (toutes leurs seances dans toutes les classes) en lecture seule

**FR11:** Les administrateurs (Directeur, Censeur, Surveillant General) doivent avoir acces a tous les emplois du temps avec possibilite de filtrer par classe, enseignant, salle, cycle, ou serie

**FR12:** Le systeme doit permettre l'export de l'emploi du temps en PDF (format imprimable avec grille hebdomadaire) pour affichage dans les classes

**FR13:** Le systeme doit permettre l'export de l'emploi du temps en Excel (format exploitable pour analyse)

**FR14:** Le systeme doit afficher un indicateur de remplissage horaire pour chaque classe (ex: 28h/30h de cours planifies)

**FR15:** Le systeme doit permettre l'ajout de notes/commentaires sur une seance (ex: "Salle changee temporairement", "Cours deplace au gymnase")

**FR16:** Le systeme doit logger toutes les modifications apportees a l'emploi du temps (qui, quand, quoi) pour tracabilite

**FR17:** Le systeme doit gerer les plages horaires standard des colleges/lycees nigeriens : typiquement Lundi-Vendredi (voire Samedi matin), de 7h30 a 12h30 et de 15h00 a 17h30

### 2.2 Non-Functional Requirements

**NFR1:** La detection de conflits doit etre instantanee (< 500ms) lors de l'ajout ou modification d'une seance

**NFR2:** La vue grille hebdomadaire doit charger en moins de 2 secondes meme pour un etablissement de 2000 eleves

**NFR3:** L'export PDF d'un emploi du temps hebdomadaire doit se generer en moins de 5 secondes

**NFR4:** Le systeme doit supporter au minimum 50 utilisateurs consultant simultanement des emplois du temps

**NFR5:** L'interface de creation d'emploi du temps doit etre intuitive et necessiter moins de 30 minutes de formation pour un censeur ou surveillant general

**NFR6:** Le systeme doit etre responsive et utilisable sur tablette et smartphone pour consultation en mobilite

**NFR7:** Les donnees d'emploi du temps doivent etre isolees par tenant (multi-tenant) avec aucune fuite possible entre etablissements

**NFR8:** L'interface doit etre optimisee pour les connexions a bande passante limitee (contexte Niger)

---

## 3. User Interface Design Goals

### 3.1 Overall UX Vision

L'interface d'emploi du temps doit ressembler a un calendrier moderne type Google Calendar : intuitive, visuelle, avec des interactions claires pour creer et modifier des seances. Les conflits doivent etre signales visuellement en temps reel (couleur rouge, icone d'alerte). La consultation doit etre simple et epuree, avec filtres clairs et navigation rapide entre semaines.

La priorite est donnee a la lisibilite : dans un college ou lycee, l'emploi du temps est consulte quotidiennement par des centaines de personnes (eleves, enseignants, parents). L'information doit etre accessible en un coup d'oeil.

### 3.2 Key Interaction Paradigms

- **Creation par clic sur plage horaire** : Cliquer sur une plage horaire vide dans la grille ouvre un modal de creation de seance
- **Edition par clic sur seance** : Clic sur une seance existante ouvre un modal de modification
- **Detection visuelle des conflits** : Seances en conflit affichees en rouge avec icone d'alerte
- **Filtrage dynamique** : Barre de filtres permettant de basculer rapidement entre vues (par classe, enseignant, salle)
- **Duplication rapide** : Bouton "Dupliquer" pour copier une seance a un autre jour/horaire
- **Navigation par semaine** : Fleches "Precedent" / "Suivant" pour naviguer entre semaines

### 3.3 Core Screens and Views

1. **Vue Grille Hebdomadaire par Classe** : Calendrier avec jours en colonnes (Lundi-Samedi) et heures en lignes (7h30-17h30), seances affichees en blocs colores par matiere
2. **Vue Grille Hebdomadaire par Enseignant** : Toutes les seances de l'enseignant dans toutes ses classes, avec indication de la classe et de la salle
3. **Vue Grille Hebdomadaire par Salle** : Occupation d'une salle donnee, utile pour identifier les creneaux libres
4. **Vue Liste des Seances** : Tableau filtrable/triable avec toutes les seances (colonnes : Jour, Heure, Matiere, Enseignant, Salle, Classe)
5. **Formulaire de Creation/Modification de Seance** : Modal avec champs (Jour, Heure debut/fin, Matiere, Enseignant, Salle)
6. **Vue Mon Emploi du Temps (Enseignant)** : Vue personnelle en lecture seule avec uniquement ses seances
7. **Vue Emploi du Temps de ma Classe (Eleve/Parent)** : Vue en lecture seule de l'EDT de la classe de l'eleve
8. **Page de Gestion des Conflits** : Dashboard listant tous les conflits existants avec boutons d'action rapide
9. **Page d'Export** : Interface de selection de format (PDF/Excel) et criteres d'export (classe, enseignant, etc.)

### 3.4 Accessibility

- **WCAG AA** : Respect des normes d'accessibilite pour navigation clavier et lecteurs d'ecran
- Contrastes de couleurs suffisants pour identifier les seances et conflits
- Labels clairs sur tous les elements interactifs
- Codes couleur toujours accompagnes d'icones ou de texte pour les daltoniens

### 3.5 Branding

Suivre la charte graphique de l'application Gestion Scolaire avec palette de couleurs coherente. Utiliser le logo de l'etablissement (tenant) dans l'en-tete des exports PDF. Le nom de l'etablissement, l'annee scolaire et la classe doivent figurer clairement en en-tete de chaque emploi du temps.

### 3.6 Target Device and Platforms

- **Desktop** : Interface complete de creation et d'edition pour les administrateurs/censeurs
- **Tablette** : Consultation optimisee avec navigation tactile
- **Smartphone** : Consultation prioritaire pour enseignants, eleves et parents (vue liste si ecran trop petit pour grille)
- Les fonctions d'edition avancees sont reservees aux ecrans desktop/tablette
- Optimisation pour connexions 3G/4G (chargement progressif, images compressees)

---

## 4. Personas et Acces par Role

### 4.1 Matrice des Acces

| Fonctionnalite | SuperAdmin | Admin/Directeur | Censeur/Surveillant | Enseignant | Eleve | Parent | Comptable |
|----------------|:----------:|:---------------:|:-------------------:|:----------:|:-----:|:------:|:---------:|
| Creer/Modifier EDT | - | Oui | Oui | - | - | - | - |
| Supprimer seance | - | Oui | Oui | - | - | - | - |
| Vue tous les EDT | - | Oui | Oui | - | - | - | - |
| Vue EDT par classe | - | Oui | Oui | - | Sa classe | Classe enfant | - |
| Vue EDT personnel | - | - | - | Oui | - | - | - |
| Export PDF/Excel | - | Oui | Oui | Son EDT | Sa classe | Classe enfant | - |
| Dashboard conflits | - | Oui | Oui | - | - | - | - |
| Configuration plages horaires | - | Oui | - | - | - | - | - |

### 4.2 Scenarios Utilisateurs Cles

**Scenario 1 - Censeur cree l'EDT d'une classe** :
Le censeur du lycee selectionne la classe "Tle C1", ouvre la grille hebdomadaire vide, et commence a placer les seances : Maths avec M. Abdou le Lundi de 8h a 10h en Salle 12. Le systeme verifie instantanement que M. Abdou et la Salle 12 sont disponibles. Il continue matiere par matiere jusqu'a completer les 30h hebdomadaires.

**Scenario 2 - Enseignant consulte son EDT** :
Mme Mariama, professeur de Francais, ouvre son emploi du temps personnel depuis son smartphone. Elle voit toutes ses seances de la semaine dans ses differentes classes (6e A, 6e B, 5e C, 3e A) avec les salles et horaires. Elle exporte en PDF pour l'imprimer.

**Scenario 3 - Parent consulte l'EDT de son enfant** :
M. Ibrahim, parent d'un eleve en 4e B, se connecte au portail parent et consulte l'emploi du temps de la classe 4e B. Il voit que son fils a cours de 7h30 a 12h30 le Lundi et de 7h30 a 17h30 le Mardi.

---

## 5. Technical Assumptions

### 5.1 Repository Structure

**Polyrepo** : Le backend Laravel (API) est dans le repository `crm-api` et le frontend Next.js dans un repository separe `crm-frontend`.

### 5.2 Service Architecture

**Architecture modulaire Laravel** avec `nwidart/laravel-modules` :
- Module backend : `Modules/EmploisDuTemps` contenant Models, Controllers, Requests, Resources, Services
- Frontend Next.js : Pages d'emploi du temps avec composants React pour calendrier et formulaires
- API RESTful suivant les patterns etablis dans UsersGuard (routes dans `Routes/admin.php`, `Routes/teacher.php`, `Routes/student.php`, `Routes/parent.php`)

### 5.3 Testing Requirements

- **Tests PHPUnit** : Tests unitaires pour la logique de detection de conflits
- **Tests Feature** : Tests API pour les endpoints CRUD des seances, avec authentification tenant
- **Tests de validation** : Validation des regles de conflit (enseignant, salle)
- **Tests frontend** : Tests unitaires React pour composants calendrier (Jest)
- **Tests E2E** : Playwright pour tester la creation complete d'une seance avec detection de conflit

### 5.4 Additional Technical Assumptions

- **Base de donnees** : Tables `timetables` (emplois du temps par classe/semestre), `timetable_slots` (seances individuelles) avec foreign keys vers `subjects` (matieres), `teachers`, `classrooms`, `school_classes`
- **Detection de conflits** : Query SQL optimisee verifiant les overlaps de plages horaires pour un meme enseignant ou une meme salle
- **Export PDF** : Utilisation de `barryvdh/laravel-dompdf` ou `barryvdh/laravel-snappy` avec template HTML/CSS pour grille hebdomadaire
- **Export Excel** : Utilisation de `maatwebsite/laravel-excel` pour generation rapide
- **Permissions** : Spatie Permission avec guards Admin/Censeur (CRUD complet), Teacher (lecture son EDT), Student (lecture sa classe), Parent (lecture classe enfant)
- **Calendar UI Component** : Utiliser une librairie React mature comme `react-big-calendar` ou `FullCalendar` pour affichage grille
- **Validation** : Form Requests Laravel pour valider les horaires (debut < fin, pas de chevauchements, respect des plages horaires de l'etablissement)
- **Plages horaires** : Configurables par tenant pour s'adapter aux horaires specifiques de chaque etablissement (matin, apres-midi, samedi matin)

---

## 6. Epic List

### Epic 1: Fondations et Modele de Donnees
Etablir les entites de base (Timetable, TimetableSlot) et l'infrastructure API permettant la gestion des emplois du temps avec validation de base.

### Epic 2: Creation et Gestion des Seances
Permettre la creation, modification et suppression de seances avec formulaires complets, duplication, et edition rapide.

### Epic 3: Detection Automatique des Conflits
Implementer la logique de detection de conflits en temps reel pour enseignants et salles avec affichage visuel des alertes.

### Epic 4: Visualisation Multi-Roles
Creer les vues hebdomadaires adaptees a chaque role (Admin, Enseignant, Eleve, Parent) avec filtres et navigation intuitive.

### Epic 5: Fonctionnalites Avancees et Export
Ajouter la duplication d'emplois du temps, les exports PDF/Excel professionnels, et le logging des modifications.

---

## 7. Epic 1: Fondations et Modele de Donnees

**Objectif** : Etablir les fondations backend et frontend du module Emplois du Temps avec les entites de base (Timetable, TimetableSlot), migrations, models, et endpoints API CRUD. Permettre la creation basique d'un emploi du temps et de ses seances sans logique de conflit (ajoutee en Epic 3).

### Story 1.1: Creer les Migrations et Models Backend

**En tant qu'** architecte technique,
**Je veux** creer les tables et models Eloquent pour les emplois du temps et seances,
**Afin de** stocker les donnees de planning dans la base de donnees.

**Acceptance Criteria:**

1. Migration `create_timetables_table` creee avec colonnes : `id`, `tenant_id`, `academic_year_id`, `semester_id`, `school_class_id` (FK vers la table des classes), `status` (enum: draft/published), `created_by`, `timestamps`
2. Migration `create_timetable_slots_table` creee avec colonnes : `id`, `timetable_id`, `subject_id` (FK vers matieres), `teacher_id`, `classroom_id` (FK vers salles), `day_of_week` (enum: Monday-Saturday), `start_time` (time), `end_time` (time), `notes` (text, nullable), `timestamps`
3. Model `Timetable` cree avec relations : `belongsTo(AcademicYear)`, `belongsTo(Semester)`, `belongsTo(SchoolClass)`, `hasMany(TimetableSlot)`, `belongsTo(User, 'created_by')`
4. Model `TimetableSlot` cree avec relations : `belongsTo(Timetable)`, `belongsTo(Subject)`, `belongsTo(Teacher)`, `belongsTo(Classroom)`
5. Models utilisant le trait `BelongsToTenant` pour isolation multi-tenant
6. Factories creees pour `Timetable` et `TimetableSlot` permettant la generation de donnees de test
7. Tests unitaires verifiant les relations entre models
8. Indexes sur `timetable_slots` : composite index sur (`teacher_id`, `day_of_week`, `start_time`, `end_time`) et (`classroom_id`, `day_of_week`, `start_time`, `end_time`) pour optimiser la detection de conflits

### Story 1.2: Creer les API Endpoints CRUD pour Timetables

**En tant qu'** administrateur (Directeur ou Censeur),
**Je veux** creer, lire, mettre a jour et supprimer des emplois du temps via l'API,
**Afin de** gerer les plannings de mon etablissement.

**Acceptance Criteria:**

1. Route `GET /api/admin/timetables` retournant la liste des emplois du temps du tenant avec filtres optionnels (`academic_year_id`, `semester_id`, `school_class_id`, `status`)
2. Route `GET /api/admin/timetables/{id}` retournant un emploi du temps avec ses seances en eager loading (slots avec matiere, enseignant, salle)
3. Route `POST /api/admin/timetables` creant un nouvel emploi du temps avec validation (`StoreTimetableRequest`)
4. `StoreTimetableRequest` validant : `academic_year_id` (required, exists), `semester_id` (required, exists), `school_class_id` (required, exists, unique combination avec academic_year_id et semester_id)
5. Route `PUT /api/admin/timetables/{id}` mettant a jour un emploi du temps (status principalement)
6. Route `DELETE /api/admin/timetables/{id}` supprimant un emploi du temps et toutes ses seances (cascade delete)
7. `TimetableResource` transformant les donnees pour l'API avec relations incluses (classe, annee, semestre, nombre de seances, heures planifiees)
8. Middleware d'authentification tenant applique sur toutes les routes
9. Tests feature couvrant tous les endpoints avec assertions sur statuts HTTP et structure JSON

### Story 1.3: Creer les API Endpoints CRUD pour TimetableSlots (Seances)

**En tant qu'** administrateur (Directeur ou Censeur),
**Je veux** creer, lire, mettre a jour et supprimer des seances individuelles via l'API,
**Afin de** construire progressivement l'emploi du temps d'une classe.

**Acceptance Criteria:**

1. Route `GET /api/admin/timetable-slots` retournant la liste des seances avec filtres (`timetable_id`, `teacher_id`, `classroom_id`, `day_of_week`, `subject_id`)
2. Route `GET /api/admin/timetable-slots/{id}` retournant une seance avec toutes ses relations
3. Route `POST /api/admin/timetable-slots` creant une nouvelle seance avec validation (`StoreTimetableSlotRequest`)
4. `StoreTimetableSlotRequest` validant : `timetable_id` (required, exists), `subject_id` (required, exists), `teacher_id` (required, exists), `classroom_id` (required, exists), `day_of_week` (required, enum), `start_time` (required, time format, dans les plages horaires configurees), `end_time` (required, time format, after start_time)
5. Route `PUT /api/admin/timetable-slots/{id}` mettant a jour une seance avec validation (`UpdateTimetableSlotRequest`)
6. Route `DELETE /api/admin/timetable-slots/{id}` supprimant une seance
7. Route `DELETE /api/admin/timetable-slots/bulk` acceptant un array d'IDs pour suppression en masse
8. `TimetableSlotResource` incluant les relations matiere, enseignant, salle avec noms lisibles
9. Tests feature verifiant la creation de seances valides et le rejet de donnees invalides

### Story 1.4: Creer la Page de Gestion des Emplois du Temps (Frontend)

**En tant qu'** administrateur,
**Je veux** acceder a une page listant tous les emplois du temps de mon etablissement,
**Afin de** voir rapidement les plannings existants et en creer de nouveaux.

**Acceptance Criteria:**

1. Page Next.js `/admin/emplois-du-temps` creee avec authentification requise (guard admin)
2. Composant `TimetableList` affichant un tableau avec colonnes : Classe, Annee scolaire, Semestre, Statut (Brouillon/Publie), Nombre de seances, Heures planifiees, Actions
3. Bouton "Creer un emploi du temps" ouvrant un modal/drawer
4. Composant `TimetableForm` avec champs : Annee scolaire (select), Semestre (select), Classe (select avec recherche)
5. Filtres en haut du tableau : Cycle (College/Lycee), Serie (pour lycee), Annee scolaire, Semestre, Statut
6. Appel API `POST /api/admin/timetables` lors de la soumission avec gestion erreurs
7. Affichage toast de succes apres creation et rechargement de la liste
8. Bouton "Voir/Editer" sur chaque ligne redirigeant vers la vue grille de l'emploi du temps
9. Bouton "Publier" pour changer le statut de brouillon a publie (rendant l'EDT visible pour les enseignants, eleves et parents)
10. Etat de loading pendant les appels API
11. Gestion des erreurs API avec affichage messages utilisateur

### Story 1.5: Creer la Page de Detail d'un Emploi du Temps avec Vue Grille

**En tant qu'** administrateur,
**Je veux** voir l'emploi du temps d'une classe sous forme de grille hebdomadaire,
**Afin d'** avoir une vue d'ensemble visuelle et de pouvoir ajouter des seances.

**Acceptance Criteria:**

1. Page Next.js `/admin/emplois-du-temps/[id]` affichant les details de l'emploi du temps (en-tete avec annee, semestre, classe, statut)
2. Composant `WeeklyGridView` affichant une grille : jours en colonnes (Lundi-Samedi), heures en lignes (configurable, par defaut 7h30-17h30 avec pause dejeuner 12h30-15h00)
3. Seances affichees en blocs colores dans la grille avec : Matiere (titre), Enseignant (nom), Salle
4. Clic sur un creneau vide ouvre le modal `SlotForm` pour ajouter une seance
5. Clic sur une seance existante ouvre le modal `SlotForm` pre-rempli pour modification
6. Composant `SlotForm` avec tous les champs requis (day_of_week, start_time, end_time, subject_id, teacher_id, classroom_id, notes)
7. Selects peuples dynamiquement via appels API (matieres de la classe, enseignants du tenant, salles disponibles)
8. Bouton "Ajouter une seance" en plus du clic sur grille (mode alternatif)
9. Boutons d'action "Modifier" et "Supprimer" dans le popover de chaque seance
10. Confirmation avant suppression avec modal "Etes-vous sur ?"
11. Affichage du nombre total de seances et des heures totales planifiees dans l'en-tete
12. Vue liste alternative (toggle "Grille / Liste") affichant les seances sous forme de tableau

---

## 8. Epic 2: Creation et Gestion des Seances Avancees

**Objectif** : Ameliorer l'experience de creation et gestion des seances avec duplication, informations contextuelles, notes, et suppression en masse pour faciliter la planification rapide.

### Story 2.1: Ajouter la Duplication de Seances

**En tant qu'** administrateur,
**Je veux** dupliquer une seance existante pour la repeter a un autre jour/horaire,
**Afin de** creer rapidement des seances similaires sans ressaisir toutes les informations.

**Acceptance Criteria:**

1. Bouton "Dupliquer" ajouté dans le popover d'une seance sur la grille
2. Clic sur "Dupliquer" ouvre le modal `SlotForm` pre-rempli avec les donnees de la seance source
3. Champs `day_of_week` et `start_time`/`end_time` modifiables pour choisir le nouvel horaire
4. Validation empechant la duplication au meme jour/horaire (doit etre different)
5. Appel API `POST /api/admin/timetable-slots` avec les nouvelles donnees
6. Affichage toast "Seance dupliquee avec succes" et mise a jour de la grille
7. Historique de duplication visible dans les logs systeme

### Story 2.2: Afficher les Informations Contextuelles sur le Formulaire

**En tant qu'** administrateur,
**Je veux** voir des informations contextuelles pendant la creation d'une seance,
**Afin de** prendre des decisions eclairees (capacite salle, charge enseignant).

**Acceptance Criteria:**

1. Lors de la selection d'une salle, affichage de sa capacite maximale sous le champ (ex: "Capacite : 60 places")
2. Comparaison avec l'effectif de la classe : si effectif > capacite salle, affichage warning "Salle sous-dimensionnee pour cette classe (60 eleves / 45 places)"
3. Lors de la selection d'un enseignant, affichage du nombre de seances deja planifiees ce jour (ex: "M. Abdou a deja 4 seances ce jour")
4. Si enseignant > seuil de seances dans la journee, affichage warning "Charge elevee"
5. Lors de la selection d'une matiere, affichage du coefficient et du volume horaire hebdomadaire recommande (ex: "Maths - Coeff. 5 - 5h/semaine recommandees")
6. Affichage du nombre d'heures deja planifiees pour cette matiere dans cette classe (ex: "3h/5h planifiees")
7. Tous les warnings sont non-bloquants (permettent quand meme la soumission)
8. Appels API optimises pour recuperer ces infos sans ralentir l'interface (< 300ms)

### Story 2.3: Ajouter un Champ Notes/Commentaires sur les Seances

**En tant qu'** administrateur,
**Je veux** ajouter des notes ou commentaires sur une seance,
**Afin de** documenter des informations exceptionnelles (changement temporaire de salle, cours en plein air, etc.).

**Acceptance Criteria:**

1. Champ `notes` (textarea, max 500 caracteres) ajoute au formulaire `SlotForm`
2. Icone de note affichee sur la seance dans la grille si des notes existent
3. Notes visibles dans le popover de detail de la seance
4. Notes sauvegardees en base de donnees (colonne `notes` dans la migration)
5. Notes visibles dans l'export PDF et Excel

### Story 2.4: Implementer la Suppression en Masse de Seances

**En tant qu'** administrateur,
**Je veux** selectionner plusieurs seances et les supprimer en une seule action,
**Afin de** nettoyer rapidement un emploi du temps errone ou obsolete.

**Acceptance Criteria:**

1. Mode "Selection multiple" activable via un bouton dans la barre d'outils de la grille
2. En mode selection, clic sur une seance la selectionne/deselectionne (visuel : bordure epaisse)
3. Bouton "Selectionner tout" et "Deselectionner tout"
4. Bouton "Supprimer la selection" (desactive si aucune seance selectionnee)
5. Clic sur "Supprimer la selection" ouvre un modal de confirmation indiquant le nombre de seances a supprimer
6. Confirmation declenche un appel API `DELETE /api/admin/timetable-slots/bulk` avec les IDs
7. Toast final indiquant "X seances supprimees avec succes"
8. Rafraichissement de la grille apres suppression

### Story 2.5: Gerer les Plages Horaires Configurables par Etablissement

**En tant qu'** administrateur,
**Je veux** configurer les plages horaires de mon etablissement,
**Afin que** la grille d'emploi du temps reflète les horaires reels de mon college/lycee.

**Acceptance Criteria:**

1. Page de configuration `/admin/settings/emplois-du-temps` creee
2. Formulaire avec champs :
   - Heure de debut matin (default: 7h30)
   - Heure de fin matin (default: 12h30)
   - Heure de debut apres-midi (default: 15h00)
   - Heure de fin apres-midi (default: 17h30)
   - Duree standard d'une seance (default: 1h ou 2h)
   - Jours ouvres (checkboxes Lundi-Samedi, Samedi optionnel)
3. Endpoint API `GET /api/admin/settings/timetable` et `PUT /api/admin/settings/timetable`
4. Sauvegarde dans table `tenant_settings`
5. La grille d'emploi du temps utilise ces plages pour l'affichage et la validation
6. Validation empechant la creation de seances hors des plages configurees
7. Tests verifiant l'application des settings dans la validation

---

## 9. Epic 3: Detection Automatique des Conflits

**Objectif** : Implementer la logique critique de detection des conflits de planning en temps reel, garantissant qu'aucun enseignant ou salle ne soit affecte simultanement a deux seances differentes.

### Story 3.1: Creer la Logique Backend de Detection de Conflits

**En tant que** developpeur backend,
**Je veux** implementer une classe de service detectant les conflits de planning,
**Afin de** centraliser la logique metier et la reutiliser dans les validations et API.

**Acceptance Criteria:**

1. Service `ConflictDetectionService` cree dans `Modules/EmploisDuTemps/Services/`
2. Methode `detectTeacherConflict(teacher_id, day_of_week, start_time, end_time, ?exclude_slot_id)` retournant les seances en conflit ou `null` si aucun conflit
3. Methode `detectClassroomConflict(classroom_id, day_of_week, start_time, end_time, ?exclude_slot_id)` retournant les seances en conflit ou `null`
4. Logique de detection : Query SQL verifiant overlap de plages horaires avec `WHERE day_of_week = ? AND NOT (end_time <= ? OR start_time >= ?)`
5. Scope de detection : tous les timetables du meme semestre et de la meme annee scolaire (un enseignant ne peut pas etre dans deux classes differentes en meme temps)
6. Parametre `exclude_slot_id` permettant d'exclure la seance en cours d'edition de la verification
7. Tests unitaires exhaustifs couvrant tous les cas :
   - Pas de conflit : seances non-chevauchantes
   - Conflit exact : meme jour, memes horaires
   - Conflit partiel : horaires chevauchants partiellement
   - Seances consecutives OK : fin seance A = debut seance B
   - Conflit cross-timetable : enseignant dans deux classes differentes au meme moment

### Story 3.2: Integrer la Detection de Conflits dans la Validation des Seances

**En tant qu'** administrateur,
**Je veux** recevoir une erreur explicite lors de la creation/modification d'une seance en conflit,
**Afin de** corriger immediatement le probleme avant sauvegarde.

**Acceptance Criteria:**

1. `StoreTimetableSlotRequest` et `UpdateTimetableSlotRequest` appellent `ConflictDetectionService` dans la methode `withValidator()`
2. Si conflit enseignant detecte, erreur de validation ajoutee : "L'enseignant {nom} est deja affecte a la classe {classe} le {jour} de {heure debut} a {heure fin} en salle {salle}"
3. Si conflit salle detecte, erreur : "La salle {nom} est deja occupee par la classe {classe} le {jour} de {heure debut} a {heure fin}"
4. Les 2 types de conflits sont verifies simultanement et tous les messages d'erreur pertinents sont retournes
5. HTTP 422 retourne avec array `errors` contenant les messages detailles
6. Frontend affiche ces erreurs clairement dans le formulaire
7. Tests feature verifiant le rejet de seances en conflit avec messages d'erreur corrects

### Story 3.3: Afficher les Conflits en Temps Reel sur le Frontend

**En tant qu'** administrateur,
**Je veux** voir immediatement les conflits potentiels pendant que je remplis le formulaire,
**Afin de** corriger avant meme de soumettre.

**Acceptance Criteria:**

1. Endpoint API `POST /api/admin/timetable-slots/check-conflicts` cree acceptant les memes parametres qu'une seance
2. Endpoint retourne un JSON : `{conflicts: {teacher: null|{...details}, classroom: null|{...details}}, warnings: []}`
3. Frontend appelle cet endpoint en debounce (500ms) des que les champs day_of_week, start_time, end_time, teacher_id, classroom_id sont remplis
4. Pendant la verification, spinner affiche a cote du bouton de soumission
5. Si conflits detectes, affichage d'alertes visuelles :
   - Badge rouge "Conflit enseignant" sous le select enseignant avec details de la seance en conflit
   - Badge rouge "Conflit salle" sous le select salle avec details
6. Bouton de soumission desactive tant que des conflits existent
7. Messages explicites affiches pour chaque type de conflit
8. Tests E2E verifiant l'affichage des alertes lors de la saisie de donnees conflictuelles

### Story 3.4: Creer un Dashboard de Resolution des Conflits

**En tant qu'** administrateur,
**Je veux** avoir une page dediee listant tous les conflits existants dans les emplois du temps,
**Afin de** les identifier et corriger rapidement.

**Acceptance Criteria:**

1. Endpoint API `GET /api/admin/timetables/conflicts` retournant la liste de toutes les seances en conflit du tenant pour le semestre courant
2. Pour chaque conflit, retour d'infos : type (teacher/classroom), seances concernees (avec classe, matiere, enseignant, salle, horaires)
3. Page Next.js `/admin/emplois-du-temps/conflits` creee
4. Composant `ConflictDashboard` affichant un tableau avec colonnes : Type de conflit, Seances concernees, Classes, Jour, Horaires, Actions
5. Filtres permettant d'afficher uniquement certains types de conflits
6. Badge dans la navigation principale indiquant le nombre total de conflits (ex: "EDT (3 conflits)")
7. Bouton "Resoudre" sur chaque ligne ouvrant un modal permettant de modifier l'une des seances en conflit
8. Rafraichissement automatique du dashboard apres resolution

### Story 3.5: Ajouter la Detection des Warnings Non-Bloquants

**En tant qu'** administrateur,
**Je veux** recevoir des avertissements pour des situations sous-optimales mais non-bloquantes,
**Afin d'** optimiser la qualite de l'emploi du temps.

**Acceptance Criteria:**

1. Service `ConflictDetectionService` enrichi avec methode `detectWarnings()` retournant un array de warnings
2. Warning 1 : "Enseignant a plus de {seuil} seances ce jour" (seuil configurable dans settings tenant, defaut: 5)
3. Warning 2 : "Classe a plus de {seuil} heures consecutives sans pause" (detection de seances enchainees, defaut: 4h)
4. Warning 3 : "Salle sous-dimensionnee par rapport a l'effectif de la classe" (capacite salle < effectif classe)
5. Warning 4 : "Volume horaire de la matiere depasse/insuffisant" (heures planifiees vs heures recommandees)
6. Endpoint `POST /api/admin/timetable-slots/check-conflicts` retourne egalement `{warnings: []}`
7. Frontend affiche les warnings en orange (vs conflits en rouge) avec icone d'avertissement
8. Warnings n'empechent pas la soumission mais sont clairement visibles
9. Logging des warnings ignores pour statistiques

---

## 10. Epic 4: Visualisation Multi-Roles

**Objectif** : Creer les interfaces de visualisation d'emploi du temps adaptees a chaque role (Admin vue complete, Enseignant vue personnelle, Eleve/Parent vue de classe) avec filtres et navigation intuitive.

### Story 4.1: Creer la Vue d'Ensemble pour Administrateur

**En tant qu'** administrateur,
**Je veux** naviguer facilement entre les emplois du temps de toutes les classes,
**Afin d'** avoir une vue d'ensemble de la planification de l'etablissement.

**Acceptance Criteria:**

1. Page `/admin/emplois-du-temps/vue-ensemble` creee
2. Selecteur rapide de classe avec regroupement par cycle (College : 6e-3e, Lycee : 2nde-Tle) et par serie (A, C, D pour le lycee)
3. Vue par enseignant : select permettant de choisir un enseignant et voir toutes ses seances dans toutes les classes
4. Vue par salle : select permettant de voir l'occupation d'une salle donnee avec creneaux libres visibles
5. Composant `WeeklyGridView` reutilise pour les trois vues (classe, enseignant, salle) avec legende adaptee
6. Filtres par jour : possibilite de voir un seul jour au lieu de toute la semaine
7. Bouton "Reinitialiser les filtres"
8. Compteurs globaux en haut : nombre de classes avec EDT publie, nombre de conflits, taux de remplissage moyen
9. Mode plein ecran pour maximiser la vue grille
10. Export de la vue actuelle en PDF (bouton "Imprimer cette vue")

### Story 4.2: Creer la Vue Emploi du Temps Personnel pour Enseignant

**En tant qu'** enseignant,
**Je veux** consulter mon emploi du temps personnel avec toutes mes seances,
**Afin de** connaitre rapidement mon planning de la semaine dans toutes mes classes.

**Acceptance Criteria:**

1. Endpoint API `GET /api/teacher/my-timetable` cree retournant les seances de l'enseignant authentifie (toutes les classes)
2. Filtre backend automatique par `teacher_id` correspondant a l'utilisateur connecte
3. Page Next.js `/teacher/mon-emploi-du-temps` creee avec authentification guard teacher
4. Reutilisation du composant `WeeklyGridView` en mode lecture seule (pas d'edition)
5. Affichage des infos par seance : Matiere, Classe (ex: "6e A"), Salle, Horaires
6. Code couleur par classe (chaque classe a une couleur distincte pour identifier rapidement)
7. Selecteur de semaine permettant de naviguer entre semaines (si l'EDT change par semaine, sinon un seul affichage)
8. Vue liste alternative (toggle "Grille / Liste") affichant les seances sous forme de tableau triable
9. Export PDF "Mon emploi du temps" avec logo de l'etablissement et nom de l'enseignant
10. Compteur "Total : X heures de cours cette semaine"
11. Affichage du prochain cours : "Prochain : Maths - 6e A - Salle 12 - dans 45 min"
12. Tests verifiant que l'enseignant ne voit QUE ses propres seances

### Story 4.3: Creer la Vue Emploi du Temps pour Eleve

**En tant qu'** eleve,
**Je veux** consulter l'emploi du temps de ma classe,
**Afin de** savoir quand et ou aller en cours.

**Acceptance Criteria:**

1. Endpoint API `GET /api/student/my-timetable` cree retournant les seances de la classe de l'eleve authentifie
2. Logique backend : recuperer la classe de l'eleve via son inscription, puis toutes les seances publiees de cette classe
3. Page Next.js `/student/mon-emploi-du-temps` creee avec authentification guard student
4. Affichage en grille hebdomadaire (reutilisation `WeeklyGridView` en lecture seule)
5. Affichage des infos par seance : Matiere, Enseignant, Salle
6. Code couleur par matiere pour reperage rapide
7. Sur smartphone : vue liste du jour en cours par defaut avec possibilite de basculer en vue semaine
8. Export PDF "Emploi du temps - {Classe}" personnalise
9. Affichage du prochain cours en haut de la page : "Prochain cours : {Matiere} a {Heure} en {Salle} avec {Enseignant}"
10. Tests verifiant que l'eleve ne voit que les seances de sa classe et uniquement les EDT publies

### Story 4.4: Creer la Vue Emploi du Temps pour Parent/Tuteur

**En tant que** parent/tuteur,
**Je veux** consulter l'emploi du temps de la classe de mon enfant,
**Afin de** connaitre ses horaires de cours et m'organiser en consequence.

**Acceptance Criteria:**

1. Endpoint API `GET /api/parent/children/{child_id}/timetable` cree retournant les seances de la classe de l'enfant
2. Logique backend : verifier que le parent est bien le tuteur de l'eleve, puis retourner l'EDT publie de la classe
3. Page Next.js dans le portail parent avec vue EDT par enfant
4. Si le parent a plusieurs enfants, selecteur d'enfant en haut de la page
5. Reutilisation du composant `WeeklyGridView` en lecture seule
6. Affichage des infos par seance : Matiere, Enseignant, Salle, Horaires
7. Export PDF de l'emploi du temps
8. Vue optimisee smartphone (liste du jour ou de la semaine)
9. Tests verifiant les permissions (un parent ne voit que les EDT de ses propres enfants)

### Story 4.5: Implementer la Navigation Multi-Vues avec Onglets

**En tant qu'** administrateur,
**Je veux** basculer rapidement entre vue grille et vue liste via des onglets,
**Afin de** choisir la visualisation la plus adaptee a ma tache du moment.

**Acceptance Criteria:**

1. Composant `TimetableViewTabs` avec deux onglets : "Vue Grille" et "Vue Liste"
2. Onglet "Vue Grille" affiche le composant `WeeklyGridView`
3. Onglet "Vue Liste" affiche un composant `SlotList` (tableau avec colonnes : Jour, Heure debut, Heure fin, Matiere, Enseignant, Salle, Actions)
4. Etat actif persiste en localStorage pour memoriser la preference utilisateur
5. Transition fluide entre les deux vues
6. Filtres appliques conserves lors du changement de vue
7. Boutons d'action (Creer seance, Export) presents dans les deux vues

---

## 11. Epic 5: Fonctionnalites Avancees et Export

**Objectif** : Completer le module avec les fonctionnalites avancees : duplication d'emploi du temps complet depuis un semestre/annee precedente, export PDF/Excel professionnel, et logging des modifications pour audit.

### Story 5.1: Implementer la Duplication d'Emploi du Temps depuis un Semestre Precedent

**En tant qu'** administrateur,
**Je veux** dupliquer l'emploi du temps du semestre precedent comme base de depart pour une classe,
**Afin de** gagner du temps et ne ressaisir que les modifications.

**Acceptance Criteria:**

1. Bouton "Creer depuis un template" ajoute sur la page `/admin/emplois-du-temps`
2. Clic ouvre un modal avec : select de la classe cible, select de l'emploi du temps source (filtre par meme classe ou meme niveau)
3. Endpoint API `POST /api/admin/timetables/{source_id}/duplicate` cree avec parametres : `target_academic_year_id`, `target_semester_id`, `target_school_class_id`
4. Backend copie le Timetable et toutes ses TimetableSlots en creant de nouvelles entrees
5. Nouveau timetable cree avec status "draft" et lie au semestre/annee cible
6. Mapping automatique des enseignants et salles si toujours disponibles ; sinon, champs laisses vides avec flag "a completer"
7. Affichage d'un rapport apres duplication : "X seances copiees, Y seances necessitant verification (enseignant ou salle non disponible)"
8. Redirection vers la page de detail (grille) du nouvel emploi du temps cree
9. Toast "Emploi du temps duplique avec succes"
10. Tests feature verifiant la duplication complete et l'isolation des donnees

### Story 5.2: Generer l'Export PDF de l'Emploi du Temps

**En tant qu'** administrateur/enseignant/eleve/parent,
**Je veux** exporter un emploi du temps en PDF imprimable,
**Afin de** l'imprimer, l'afficher en classe ou le partager hors ligne.

**Acceptance Criteria:**

1. Bouton "Exporter en PDF" ajoute dans toutes les vues d'emploi du temps
2. Endpoints API d'export :
   - `GET /api/admin/timetables/{id}/export/pdf` (admin)
   - `GET /api/teacher/my-timetable/export/pdf` (enseignant)
   - `GET /api/student/my-timetable/export/pdf` (eleve)
   - `GET /api/parent/children/{child_id}/timetable/export/pdf` (parent)
3. Template Blade `timetable-pdf.blade.php` cree avec :
   - En-tete : Logo etablissement, nom de l'etablissement, titre "Emploi du Temps", annee scolaire, semestre, classe (ou nom enseignant)
   - Grille hebdomadaire : jours en colonnes (Lundi-Samedi si applicable), heures en lignes, seances dans les cellules
   - Infos par seance : Matiere, Enseignant (si vue admin/eleve/parent), Salle
   - Pied de page : Date de generation, "Genere par Gestion Scolaire"
4. CSS optimise pour impression format A4 paysage (marges, tailles, couleurs print-friendly)
5. Nom de fichier : `emploi-du-temps-{classe|enseignant}-{annee}-S{semestre}.pdf`
6. Generation en moins de 5 secondes
7. Tests verifiant la generation reussie et la presence de toutes les seances dans le PDF

### Story 5.3: Generer l'Export Excel de l'Emploi du Temps

**En tant qu'** administrateur,
**Je veux** exporter l'emploi du temps en Excel,
**Afin de** l'analyser, le modifier ou le partager dans un format exploitable.

**Acceptance Criteria:**

1. Bouton "Exporter en Excel" ajoute dans la vue administrateur
2. Endpoint API `GET /api/admin/timetables/{id}/export/excel` cree
3. Classe Export `TimetableExport` implementant `FromCollection` de `maatwebsite/laravel-excel`
4. Feuille Excel avec colonnes : Jour, Heure debut, Heure fin, Matiere, Enseignant, Salle, Notes
5. Lignes triees par jour (Lundi en premier) puis heure
6. Formatage : en-tetes en gras, filtres automatiques actives, colonnes auto-dimensionnees
7. En-tete de feuille : Etablissement, Classe, Annee scolaire, Semestre
8. Nom de fichier : `emploi-du-temps-{classe}-{annee}-S{semestre}.xlsx`
9. Generation en moins de 3 secondes
10. Tests verifiant la structure et le contenu du fichier Excel genere

### Story 5.4: Implementer le Logging des Modifications pour Audit

**En tant qu'** administrateur,
**Je veux** avoir un historique de toutes les modifications apportees aux emplois du temps,
**Afin d'** auditer les changements et identifier les responsables en cas de probleme.

**Acceptance Criteria:**

1. Utilisation du package `spatie/laravel-activitylog` ou implementation custom
2. Logging automatique de toutes les actions CRUD sur models `Timetable` et `TimetableSlot`
3. Informations loggees : utilisateur (qui), action (created/updated/deleted), timestamp (quand), donnees modifiees (quoi - anciennes et nouvelles valeurs)
4. Table `activity_log` ou equivalente stockant les logs avec isolation par tenant
5. Endpoint API `GET /api/admin/timetables/{id}/activity` retournant l'historique d'un emploi du temps
6. Section "Historique des modifications" accessible depuis le detail d'un emploi du temps
7. Affichage chronologique avec infos : Date, Utilisateur, Action, Details (ex: "M. Moussa a deplace Maths du Lundi 8h au Mardi 10h")
8. Filtres par type d'action (creation, modification, suppression) et par utilisateur
9. Logs conserves pendant toute la duree de vie du tenant (minimum 5 ans)
10. Tests verifiant l'enregistrement correct des logs lors des operations CRUD

### Story 5.5: Ajouter un Indicateur de Remplissage de l'Emploi du Temps

**En tant qu'** administrateur,
**Je veux** voir un indicateur visuel du taux de remplissage de l'emploi du temps d'une classe,
**Afin de** savoir rapidement si j'ai planifie suffisamment de seances pour couvrir le volume horaire requis.

**Acceptance Criteria:**

1. Calcul backend du nombre total d'heures planifiees pour un emploi du temps (somme des durees de toutes les seances)
2. Endpoint API enrichi : `GET /api/admin/timetables/{id}` retourne `total_hours_planned` et `total_hours_expected`
3. `total_hours_expected` base sur le volume horaire officiel de la classe (somme des heures hebdomadaires de toutes les matieres affectees a cette classe/serie)
4. Affichage sur la page de detail : "28h planifiees sur 30h attendues"
5. Barre de progression visuelle (ex: 28h/30h = 93% rempli)
6. Code couleur : vert si >= 90%, orange si 70-89%, rouge si < 70%
7. Detail par matiere : tableau montrant pour chaque matiere les heures planifiees vs heures attendues (ex: "Maths : 4h/5h, Francais : 5h/5h, Physique : 2h/3h")
8. Calcul automatique a chaque ajout/modification/suppression de seance
9. Affichage egalement du nombre total de seances planifiees

---

## 12. Next Steps

### Architect Prompt

Le PRD du Module Emplois du Temps est complet. Veuillez passer a la phase d'architecture technique en creant le document d'architecture detaille couvrant :
- Structure de base de donnees complete (tables `timetables`, `timetable_slots`, relations, indexes)
- Architecture API (endpoints par role : admin, teacher, student, parent)
- Services metier (`ConflictDetectionService`, `TimetableExportService`)
- Integrations avec les modules existants (Structure Academique pour classes/matieres/enseignants/salles, Presences pour seances)
- Choix de librairie frontend pour le calendrier (`react-big-calendar` vs alternatives)
- Strategie de gestion des performances (caching des emplois du temps publies, eager loading)
- Plan de tests (unitaires, features, E2E)
- Configuration multi-tenant des plages horaires

### UX Expert Prompt

Merci de creer les wireframes et maquettes pour les ecrans principaux du Module Emplois du Temps :
- Vue grille hebdomadaire pour Admin (creation/edition) avec detection de conflits en temps reel
- Vue grille hebdomadaire pour Enseignant (lecture seule, toutes ses classes)
- Vue grille/liste pour Eleve et Parent (lecture seule, EDT de la classe)
- Formulaire de creation/modification de seance avec informations contextuelles
- Dashboard de resolution des conflits
- Page de liste des emplois du temps par classe avec filtres
- Template PDF imprimable (format A4 paysage)
- Adaptation mobile/smartphone (vue liste du jour)

Assurez-vous que le design soit coherent avec l'application Gestion Scolaire existante, optimise pour les connexions a bande passante limitee, et responsive (desktop/tablette/smartphone).
