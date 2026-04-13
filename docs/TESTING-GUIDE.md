# Guide de Test Fonctionnel - Jandoo (Gestion Scolaire)

> Ce document liste toutes les fonctionnalités de l'application, organisées par rôle utilisateur et par menu.
> Chaque section décrit les actions à tester dans le navigateur via Chrome DevTools MCP.

---

## Identifiants de connexion

| Rôle | URL de connexion | Username | Password |
|------|-----------------|----------|----------|
| SuperAdmin | `/en/superadmin/login` | `superadmin` | `password` |
| Admin (tenant company1) | `http://tenant1.local/en/admin/login` | `admin` | `password` |
| Manager (tenant company1) | `http://tenant1.local/en/admin/login` | `manager` | `password` |
| User Frontend 1 | `http://tenant1.local/en/login` | `user1` | `password` |
| User Frontend 2 | `http://tenant1.local/en/login` | `user2` | `password` |

---

## PARTIE 1 : RÔLE ADMIN (Directeur / Proviseur)

L'admin a accès à l'ensemble du back-office de l'établissement.
URL de base : `http://tenant1.local/en/admin/`

---

### 1. Menu "Users" (`/admin/users`)

#### 1.1 Liste des utilisateurs
- [x] Affichage du tableau avec colonnes : #, Username, Firstname, Lastname, Email, Application, Status, Roles, Created At, Actions
- [x] Recherche par nom d'utilisateur (champ "Search User") — filtre en temps réel OK
- [x] Pagination (Rows per page : 10, 25, 50) — contrôle affiché, "1-4 of 4" OK
- [ ] Bouton "Refresh" recharge la liste

#### 1.2 Création d'un utilisateur
- [x] Cliquer sur "Add" → formulaire de création — dialog modal avec sections Basic Info, Roles, Permissions, Additional Info
- [x] Remplir : Username, Email, Password, Firstname, Lastname, Application (admin/frontend), Rôle — tous les champs fonctionnels
- [ ] Validation : username et email uniques
- [x] Utilisateur créé apparaît dans la liste — enseignant1 (Moussa Ibrahim, Professeur) créé avec succès

#### 1.3 Modification d'un utilisateur
- [ ] Cliquer sur l'icône "Edit" (crayon) d'un utilisateur
- [ ] Modifier les champs et sauvegarder
- [ ] Vérifier que les modifications sont persistées

#### 1.4 Suppression d'un utilisateur
- [ ] Cliquer sur l'icône "Delete" (poubelle)
- [ ] Confirmer la suppression
- [ ] L'utilisateur disparaît de la liste

#### 1.5 Voir détails d'un utilisateur
- [ ] Cliquer sur l'icône "View" (œil)
- [ ] Vérifier que toutes les informations s'affichent

#### 1.6 Gestion des rôles
- [ ] Cliquer "Manage Roles" — bouton présent mais dialog ne s'ouvre pas (à investiguer)
- [x] Voir la liste des rôles : Administrator, Manager, User, Professeur, Étudiant, Caissier, Agent Comptable, Comptable — 8 rôles confirmés via le sélecteur de rôles dans Add User
- [ ] Créer un nouveau rôle
- [ ] Assigner des permissions à un rôle

#### 1.7 Gestion des permissions
- [ ] Cliquer "Manage Permissions"
- [ ] Voir la liste des permissions (20+)
- [ ] Créer une nouvelle permission
- [ ] Vérifier les catégories : users, roles, settings, reports, dashboard, academic, student

---

### 2. Menu "Structure Académique" (`/admin/structure/`)

#### 2.1 Années Scolaires (`/admin/structure/academic-years`)
- [x] Affichage de la liste des années scolaires — page "Calendrier Académique" avec tableau OK
- [x] **Créer** une année : Nom (ex: "2026-2027"), Date début, Date fin — dialog avec champs Nom, Date début, Date fin OK
- [x] Validation : date fin > date début, nom unique — validations durée 9-12 mois et date future confirmées
- [x] **Auto-création** de 2 semestres (S1, S2) à la création — "2 semestre(s)" affiché après création
- [ ] **Modifier** une année scolaire et ses dates
- [x] **Activer** une année (bouton "Activer") → une seule active à la fois — activation OK, bouton disparaît après
- [x] Badge "Active" sur l'année active — statut "Active" affiché dans le tableau
- [ ] **Supprimer** une année (bloqué si dépendances existent) — bouton "Supprimer" présent
- [ ] Tri par nom, date début, statut
- [ ] Gestion des semestres dans le détail de l'année :
  - [ ] Modifier les dates du semestre — bouton "Gérer les semestres" présent
  - [ ] Validation : pas de chevauchement entre S1 et S2
  - [ ] Clôturer un semestre (irréversible)

#### 2.2 Cycles et Niveaux (`/admin/structure/cycles-levels`)
- [x] Affichage des cycles pré-créés : **Collège** (COL) et **Lycée** (LYC) — les 2 cycles affichés avec toggles
- [ ] Niveaux affichés par cycle (expandable chevrons présents) :
  - Collège : 6ème, 5ème, 4ème, 3ème
  - Lycée : 2nde, 1ère, Terminale
- [ ] Activer/Désactiver un cycle — toggles présents
- [ ] Blocage de la désactivation si des classes existent
- [ ] Modification de la description uniquement (pas le code)

#### 2.3 Séries (`/admin/structure/series`)
- [x] Affichage des séries pré-créées : **A** (Littéraire), **C** (Maths-Physique), **D** (Sciences Naturelles) — tableau avec Code, Nom, Description, Statut, Actions
- [ ] **Créer** une nouvelle série : Code (auto-majuscule), Nom, Description
- [ ] **Modifier** nom et description
- [ ] **Activer/Désactiver** une série — icône désactivation (œil barré) présente
- [ ] Blocage de la désactivation si des classes existent
- [ ] Suppression interdite (seulement désactivation)
- [x] Rappel visuel : "Applicable aux niveaux 1ère et Terminale" — message affiché en sous-titre

#### 2.4 Classes (`/admin/structure/classes`)
- [ ] Affichage liste : Nom, Cycle, Niveau, Série, PP, Effectif/Capacité, Salle
- [ ] **Créer** une classe :
  - Sélection Niveau → Cycle auto-déterminé
  - Sélection Série (obligatoire pour 1ère/Tle, masqué pour 6e-2nde)
  - Section (lettre : A, B, C...)
  - Capacité maximale
  - Salle de cours
  - Professeur Principal (PP)
- [ ] Auto-génération du nom : ex. "Tle C1", "6ème A"
- [ ] Unicité du nom par année scolaire
- [ ] **Modifier** (champs limités si élèves inscrits)
- [ ] **Supprimer** (bloqué si élèves inscrits)
- [ ] Affectation du PP :
  - [ ] Liste des enseignants disponibles (non PP ailleurs)
  - [ ] Enseignants grisés si déjà PP
  - [ ] Changement de PP possible
  - [ ] Alerte orange pour classes sans PP
- [ ] Filtrage :
  - [ ] Par Cycle
  - [ ] Par Niveau
  - [ ] Par Série
  - [ ] Par Année scolaire
  - [ ] Filtres cumulables (AND)
  - [ ] Bouton "Réinitialiser filtres"
  - [ ] Compteur de classes affichées

#### 2.5 Matières (`/admin/structure/subjects`)
- [x] Affichage liste : Code, Nom, Nom abrégé, Catégorie, Statut — tableau complet avec toutes les colonnes
- [x] 13 matières pré-créées (seeder nigérien) : ALL, ANG, ARAB, EC, EPS, ESP, FRAN... — visibles avec badges catégorie colorés
- [ ] **Créer** : Code unique, Nom, Nom abrégé, Catégorie, Description — bouton "+ Nouvelle matière" présent
- [x] Catégories : Langues, Sciences Humaines, Éducation Physique, Lettres — badges colorés affichés
- [ ] **Modifier** (code bloqué si coefficients/notes existent) — icône crayon présente
- [x] **Désactiver** une matière — toggle actif/inactif présent sur chaque ligne
- [ ] **Supprimer** (bloqué si dépendances) — icône poubelle présente
- [x] Recherche par code/nom — champ "Rechercher par code ou nom..." présent
- [x] Filtres : Catégorie — sélecteur "Catégorie" présent

#### 2.6 Coefficients (`/admin/structure/coefficients`)
- [x] Sélecteur Niveau / Série — sélecteur "Niveau" présent avec message d'invite
- [ ] Tableau : Matières, Coefficient (1-8), Heures hebdomadaires
- [ ] **Ajouter** une matière à un niveau/série
- [ ] **Modifier** coefficient et heures
- [ ] **Supprimer** (bloqué si notes existent)
- [ ] Pas de doublon (même matière/niveau/série)
- [ ] Niveaux 6e-2nde : "Tronc commun" (pas de série)
- [ ] Niveaux 1ère-Tle : série obligatoire (A, C, D)
- [ ] Coefficients par défaut pré-configurés via seeder
- [ ] **Vue comparative** (1ère et Tle uniquement) :
  - [ ] Tableau croisé Matières × Séries (A, C, D)
  - [ ] Somme des coefficients par colonne
  - [ ] Différences significatives mises en évidence
  - [ ] Export PDF
- [ ] **Duplication** de configuration :
  - [ ] Bouton "Dupliquer cette configuration"
  - [ ] Sélection niveau/série cible
  - [ ] Options : Remplacer tout, Fusionner, Annuler

---

### 3. Menu "Enrollments" (`/admin/enrollment/`)

> **Note** : Module LMD - non encore migré vers le système secondaire. Les pages frontend existent mais le backend est désactivé.

#### 3.1 Students (`/admin/enrollment/students`)
- [ ] Liste des élèves avec matricule, nom, prénom, classe, statut
- [ ] Création d'un élève : matricule auto, infos personnelles, contact d'urgence
- [ ] Import CSV/Excel en masse
- [ ] Modification des informations
- [ ] Recherche et filtrage

#### 3.2 Statistiques (`/admin/enrollment/statistics`)
- [ ] Dashboard inscriptions : total élèves, par cycle/niveau, par genre

#### 3.3 Validation des inscriptions (`/admin/enrollment/validation`)
- [ ] Validation/rejet des inscriptions en attente

#### 3.4 Cartes étudiants (`/admin/enrollment/student-cards`)
- [ ] Génération de cartes étudiants

---

### 4. Menu "Grades & Evaluations" (`/admin/grades/`)

> **Note** : Module LMD - non encore migré. Pages frontend existantes.

#### 4.1 Saisie des notes (`/admin/grades/entry`)
- [ ] Sélection classe/matière/période
- [ ] Tableau de saisie : élèves × notes
- [ ] Notes sur /20

#### 4.2 Validation des notes (`/admin/grades/validations`)
- [ ] Liste des saisies à valider
- [ ] Approuver/Rejeter

#### 4.3 Résultats semestriels (`/admin/grades/semester-results`)
- [ ] Calcul automatique des moyennes
- [ ] Classement par classe

#### 4.4 Délibérations (`/admin/grades/deliberation`)
- [ ] Sessions de délibération (conseil de classe)
- [ ] Décisions : passage, redoublement, exclusion

#### 4.5 Publications (`/admin/grades/publications`)
- [ ] Publication des résultats

#### 4.6 Classements (`/admin/grades/rankings`)
- [ ] Classement par classe, par niveau

#### 4.7 Statistiques (`/admin/grades/statistics`)
- [ ] Taux de réussite, moyennes par matière

---

### 5. Menu "Emplois du Temps" (`/admin/timetable/`)

> **Note** : Module LMD - non encore migré. Pages frontend existantes.

#### 5.1 Planification (`/admin/timetable/schedule`)
- [ ] Grille emploi du temps par classe/semaine

#### 5.2 Gestion des salles (`/admin/timetable/rooms`)
- [ ] CRUD des salles : nom, capacité, équipements

#### 5.3 Vue par enseignant (`/admin/timetable/teacher-view`)
- [ ] EDT individuel de chaque enseignant

#### 5.4 Vue par groupe/classe (`/admin/timetable/group-view`)
- [ ] EDT par classe

#### 5.5 Export PDF (`/admin/timetable/pdf-export`)
- [ ] Export de l'emploi du temps en PDF

---

### 6. Menu "Présences & Absences" (`/admin/attendance/`)

> **Note** : Module LMD - non encore migré.

#### 6.1 Feuille d'appel (`/admin/attendance/sessions`)
- [ ] Créer une session d'appel par classe/créneau
- [ ] Marquer présent/absent/retard

#### 6.2 Justificatifs (`/admin/attendance/justifications`)
- [ ] Soumettre/valider des justificatifs d'absence

#### 6.3 Suivi & Alertes (`/admin/attendance/monitoring`)
- [ ] Alertes de seuils d'absences

#### 6.4 Rapports (`/admin/attendance/reports`)
- [ ] Rapports statistiques d'assiduité

---

### 7. Menu "Examens" (`/admin/exams/`)

> **Note** : Module LMD - non encore migré.

#### 7.1 Planification (`/admin/exams/planning`)
- [ ] Planifier les sessions d'examen

#### 7.2 Gestion des épreuves (`/admin/exams/management`)
- [ ] CRUD des épreuves par matière

#### 7.3 Surveillance (`/admin/exams/supervision`)
- [ ] Affecter les surveillants

#### 7.4 Rapports (`/admin/exams/reports`)
- [ ] Statistiques des examens

---

### 8. Menu "Comptabilité" (`/admin/finance/`)

#### 8.1 Facturation (`/admin/finance/invoices`)
- [x] KPIs affichés : Total facturé (2 850 000 FCFA), Total encaissé, Total en retard, Taux de recouvrement (50%)
- [x] Boutons "Types de frais" et "+ Nouvelle Facture" présents
- [ ] Créer un type de frais (fee_type) : code, nom, montant, catégorie
- [ ] Catégories : tuition, registration, exam, library, lab, sports, insurance, card, other
- [ ] Générer des factures par élève
- [x] Factures liées à l'année scolaire — références FAC-2026-XXXX
- [x] Liste des factures avec statut (Payée, En attente, En retard, Brouillon, Annulée) — badges colorés

#### 8.2 Paiements (`/admin/finance/payments`)
- [ ] Enregistrer un paiement sur une facture
- [ ] Modes de paiement : espèces, virement, chèque, mobile money
- [ ] Paiement partiel possible
- [ ] Historique des paiements par élève

#### 8.3 Recouvrement (`/admin/finance/collection`)
- [ ] Rappels de paiement
- [ ] Blocage de services pour impayés
- [ ] Échéanciers de paiement

#### 8.4 Rapports financiers (`/admin/finance/reports`)
- [ ] État des recettes
- [ ] Élèves en impayés
- [ ] Rapprochement bancaire

---

### 9. Menu "Paie & RH" (`/admin/payroll/`)

#### 9.1 Employés (`/admin/payroll/employees`)
- [x] KPIs : 4 Employés actifs, 3 Contrats CDI, 1 200 000 FCFA Masse salariale, 1 Contrats terminés
- [x] Liste des employés : Matricule, Nom complet, Département, Poste, Type contrat, Salaire, Statut, Actions
- [x] Bouton "+ Nouvel Employé" présent
- [ ] Lien avec un compte utilisateur (teacher → user)
- [x] Types de contrat : CDI, CDD, Stage — badges colorés affichés

#### 9.2 Éléments de Paie (`/admin/payroll/components`)
- [ ] Définir les éléments de salaire : indemnités, primes, retenues
- [ ] Types : gains, retenues

#### 9.3 Traitement de la Paie (`/admin/payroll/processing`)
- [ ] Créer une période de paie
- [ ] Calculer les bulletins : brut → net
- [ ] Générer les bulletins de paie (PDF)

#### 9.4 Déclarations sociales (`/admin/payroll/declarations`)
- [ ] Déclarations CNSS/ONPE

#### 9.5 Rapports RH (`/admin/payroll/reports`)
- [ ] Récapitulatif des salaires
- [ ] Journal de paie

---

### 10. Menu "Documents Officiels" (`/admin/documents/`)

> **Note** : Module LMD - non encore migré.

#### 10.1 Relevés de notes (`/admin/documents/transcripts`)
- [ ] Générer des relevés de notes par élève/classe

#### 10.2 Diplômes (`/admin/documents/diplomas`)
- [ ] Registre de diplômes

#### 10.3 Attestations (`/admin/documents/certificates`)
- [ ] Générer attestations de scolarité, de réussite

#### 10.4 Cartes étudiants (`/admin/documents/cards`)
- [ ] Impression de cartes

#### 10.5 Vérification (`/admin/documents/verification`)
- [ ] Vérification par QR code

---

### 11. Menu "Settings" (`/admin/settings/`)

#### 11.1 Paramètres généraux (`/admin/settings`)
- [ ] **BUG** : Page en erreur — `useAuth` non exporté depuis `@/modules/UsersGuard` (import manquant dans barrel)
- [ ] Configuration de l'établissement
- [ ] Barèmes de passage et mentions :
  - [ ] Seuil de passage (défaut : 10/20)
  - [ ] Seuil de rachat (défaut : 9/20)
  - [ ] Mentions : Passable (10), AB (12), Bien (14), TB (16)
  - [ ] Validation cohérence : rachat < passage
- [ ] Tableau d'honneur et distinctions :
  - [ ] Tableau d'honneur (14), Encouragements (12), Félicitations (16)
  - [ ] Activation/désactivation individuelle
- [ ] Sanctions :
  - [ ] Avertissement travail (7), Blâme (5)
  - [ ] Validation : blâme < avertissement
- [ ] Barèmes par cycle (optionnel) :
  - [ ] Toggle "Barèmes différenciés par cycle"
  - [ ] Si activé : onglets "Collège" et "Lycée" séparés
- [ ] Bouton "Réinitialiser aux défauts"

#### 11.2 Gestion des menus (`/admin/settings/menus`)
- [ ] Activer/désactiver des menus de navigation

---

## PARTIE 2 : RÔLE SUPERADMIN (Gestionnaire plateforme)

URL de base : `http://localhost/en/superadmin/` (domaine central)

### 1. Login SuperAdmin (`/superadmin/login`)
- [ ] Connexion avec superadmin / password
- [ ] Redirection vers le dashboard

### 2. Dashboard (`/superadmin/dashboard`)
- [ ] Vue d'ensemble : nombre de tenants, statistiques globales

### 3. Sites / Tenants (`/superadmin/sites`)
- [ ] Liste des tenants (company1, company2, demo)
- [ ] Créer un nouveau tenant
- [ ] Configurer : nom, domaine, base de données
- [ ] Activer/Désactiver un tenant
- [ ] Créer un admin pour le tenant

---

## PARTIE 3 : RÔLE ENSEIGNANT (Frontend)

> **Note** : L'interface enseignant utilise les routes `/frontend/` côté API.

### 1. Mes Matières et Classes
- [ ] Vue des affectations de l'année active
- [ ] Liste : Matière (code+nom), Classe, Nombre d'élèves, Coefficient, Heures/semaine
- [ ] Charge horaire totale en haut
- [ ] Filtre par semestre
- [ ] Lien "Voir les élèves" par classe

### 2. Saisie des Notes
- [ ] Sélection matière → classe → période d'évaluation
- [ ] Tableau de saisie avec liste des élèves
- [ ] Notes sur /20
- [ ] Soumission pour validation

---

## PARTIE 4 : FONCTIONNALITÉS AVANCÉES (Admin)

### Affectation des Enseignants (dans Structure Académique)

#### Affectation enseignant → matière → classe(s)
- [ ] Écran "Affectations Enseignants" (année active)
- [ ] Bouton "Affecter" : sélection Enseignant, Matière, Classe(s)
- [ ] Sélection matière filtre les classes disponibles
- [ ] Multi-select classes (affectation en masse)
- [ ] Vérification pas de doublon
- [ ] Charge horaire affichée

#### Liste et filtrage des affectations
- [ ] Filtres : Enseignant, Matière, Classe, Niveau, Cycle, Année
- [ ] Regroupement par enseignant
- [ ] Récapitulatif : X enseignants, Y matières, Z classes
- [ ] Retirer une affectation (avertissement si notes existent)

#### Couverture des matières
- [ ] Vue par classe : matières avec enseignant ou badge "Non affecté"
- [ ] % couverture par classe (ex: 8/10 - 80%)
- [ ] % couverture global
- [ ] Bouton "Affecter" sur lignes "Non affecté"
- [ ] Classes < 100% en surbrillance

#### Reconduction des affectations
- [ ] Bouton "Reconduire les affectations"
- [ ] Sélection année source → année cible
- [ ] Rapport : X reconduites, Y non reconduites avec raison

---

### Vue d'ensemble de la Structure

#### Vue arborescente (`/admin/structure/tree` si implémenté)
- [ ] Arbre : Cycle > Niveau > Classes
- [ ] Nœuds cliquables (détails)
- [ ] Bouton "Modifier" sur chaque nœud
- [ ] Expansion/collapse
- [ ] Effectifs entre crochets
- [ ] Indicateurs vert/orange/rouge

#### Dashboard Structure
- [ ] Statistiques : total classes, total élèves, total matières, total enseignants
- [ ] Taux de couverture
- [ ] Classes sans PP
- [ ] Graphiques : élèves par cycle/niveau, classes par série

#### Rapport de validation
- [ ] Bouton "Générer rapport de validation"
- [ ] Catégories vérifiées :
  - [ ] Classes sans PP
  - [ ] Niveaux/Séries sans coefficients
  - [ ] Matières sans enseignant
  - [ ] Classes en sureffectif
  - [ ] Classes vides
  - [ ] Cohérence des barèmes
  - [ ] Enseignants sans affectation
- [ ] Score de complétude (ex: 87%)
- [ ] Export PDF
- [ ] Lien "Corriger" par ligne

#### Export de la structure
- [ ] Export PDF (document formaté)
- [ ] Export Excel (multi-onglets : Classes, Matières, Coefficients, Affectations, Barèmes)
- [ ] En-tête de l'établissement

---

## PARTIE 5 : API BACKEND - ENDPOINTS PRINCIPAUX

### Authentification
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/admin/auth/login` | Login admin (username + password → token) |
| GET | `/api/admin/auth/me` | Utilisateur connecté |
| POST | `/api/admin/auth/logout` | Déconnexion |

### Users (UsersGuard)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/users` | Liste des utilisateurs |
| POST | `/api/admin/users` | Créer un utilisateur |
| GET | `/api/admin/users/{id}` | Détail utilisateur |
| PUT | `/api/admin/users/{id}` | Modifier utilisateur |
| DELETE | `/api/admin/users/{id}` | Supprimer utilisateur |
| GET | `/api/admin/teachers` | Liste des enseignants |
| GET | `/api/admin/roles` | Liste des rôles |
| POST | `/api/admin/roles` | Créer un rôle |
| GET | `/api/admin/permissions` | Liste des permissions |

### Structure Académique
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET/POST | `/api/admin/academic-years` | CRUD années scolaires |
| PUT/DELETE | `/api/admin/academic-years/{id}` | Modifier/Supprimer |
| POST | `/api/admin/academic-years/{id}/activate` | Activer une année |
| GET | `/api/admin/academic-years/active` | Année active |
| PUT | `/api/admin/semesters/{id}` | Modifier semestre |
| GET | `/api/admin/semesters/current` | Semestre actuel |
| GET/PUT | `/api/admin/cycles` | Cycles |
| GET | `/api/admin/levels` | Niveaux |
| GET/POST | `/api/admin/series` | Séries |
| PUT | `/api/admin/series/{id}` | Modifier série |
| GET/POST | `/api/admin/classes` | Classes |
| PUT/DELETE | `/api/admin/classes/{id}` | Modifier/Supprimer classe |
| GET | `/api/admin/classes/stats` | Statistiques classes |
| GET/POST | `/api/admin/subjects` | Matières |
| PUT/DELETE | `/api/admin/subjects/{id}` | Modifier/Supprimer matière |
| GET/POST | `/api/admin/subject-class-coefficients` | Coefficients |
| PUT/DELETE | `/api/admin/subject-class-coefficients/{id}` | Modifier/Supprimer coefficient |
| GET | `/api/admin/subject-class-coefficients/compare` | Vue comparative |
| POST | `/api/admin/subject-class-coefficients/duplicate` | Dupliquer configuration |
| GET/POST | `/api/admin/teacher-assignments` | Affectations enseignants |
| DELETE | `/api/admin/teacher-assignments/{id}` | Supprimer affectation |
| GET | `/api/admin/teacher-assignments/coverage` | Couverture matières |
| POST | `/api/admin/teacher-assignments/carry-over` | Reconduction |
| GET/PUT | `/api/admin/class-settings` | Barèmes et paramètres |
| POST | `/api/admin/class-settings/reset` | Réinitialiser aux défauts |
| GET | `/api/admin/structure/tree` | Vue arborescente |
| GET | `/api/admin/structure/dashboard` | Dashboard stats |
| GET | `/api/admin/structure/validation-report` | Rapport de validation |
| GET | `/api/admin/structure/export` | Export PDF/Excel |

### Finance
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET/POST | `/api/admin/fee-types` | Types de frais |
| GET/POST | `/api/admin/invoices` | Factures |
| GET/POST | `/api/admin/payments` | Paiements |
| GET/POST | `/api/admin/payment-schedules` | Échéanciers |
| GET/POST | `/api/admin/discounts` | Remises |
| GET/POST | `/api/admin/service-blocks` | Blocages services |

### Paie (Payroll)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET/POST | `/api/admin/employees` | Employés |
| GET/POST | `/api/admin/employment-contracts` | Contrats |
| GET/POST | `/api/admin/salary-scales` | Grilles salariales |
| GET/POST | `/api/admin/payroll-components` | Éléments de paie |
| GET/POST | `/api/admin/payroll-periods` | Périodes de paie |
| GET/POST | `/api/admin/payroll-records` | Bulletins |
| GET/POST | `/api/admin/payslips` | Fiches de paie |

---

## PARTIE 6 : ORDRE DE TEST RECOMMANDÉ

Pour tester l'application de manière cohérente, suivre cet ordre :

### Phase 1 : Configuration initiale (Admin)
1. **Se connecter** en tant qu'admin → `/en/admin/login`
2. **Années Scolaires** → Créer l'année 2025-2026, l'activer
3. **Cycles et Niveaux** → Vérifier les cycles Collège/Lycée et leurs niveaux
4. **Séries** → Vérifier les séries A, C, D
5. **Matières** → Vérifier les 13 matières pré-créées
6. **Coefficients** → Configurer les coefficients par niveau/série

### Phase 2 : Configuration des classes
7. **Classes** → Créer des classes (ex: 6ème A, Tle C1, 2nde A)
8. **Enseignants** → Créer des utilisateurs enseignants dans "Users"
9. **Affectations** → Affecter enseignants aux matières/classes
10. **Couverture** → Vérifier la couverture des matières

### Phase 3 : Inscriptions (quand le module sera migré)
11. **Élèves** → Créer/importer des élèves
12. **Inscriptions** → Inscrire les élèves dans les classes

### Phase 4 : Finance
13. **Types de frais** → Configurer les frais scolaires
14. **Factures** → Générer les factures
15. **Paiements** → Enregistrer des paiements

### Phase 5 : Paie
16. **Employés** → Créer les dossiers employés
17. **Contrats** → Créer les contrats de travail
18. **Paie** → Traiter la paie mensuelle

### Phase 6 : Paramétrage
19. **Barèmes** → Configurer les seuils de passage et mentions
20. **Settings** → Configuration générale

---

## PARTIE 7 : CHECKLIST RAPIDE PAR MENU

| # | Menu | Sous-menus | Actions clés |
|---|------|-----------|--------------|
| 1 | Users | Liste, Add, Roles, Permissions | CRUD users, assign roles |
| 2 | Structure Académique | Années, Cycles, Séries, Classes, Matières, Coefficients | Config structure scolaire |
| 3 | Enrollments | Students, Statistics, Validation, Cards | Gestion élèves |
| 4 | Grades & Evaluations | Entry, Validations, Results, Deliberations, Rankings | Notes et moyennes |
| 5 | Emplois du Temps | Schedule, Rooms, Views, Export | Planification EDT |
| 6 | Présences & Absences | Sessions, Justificatifs, Monitoring, Reports | Assiduité |
| 7 | Examens | Planning, Management, Supervision, Reports | Examens |
| 8 | Comptabilité | Invoices, Payments, Collection, Reports | Finance élèves |
| 9 | Paie & RH | Employees, Components, Processing, Declarations | Paie personnel |
| 10 | Documents Officiels | Transcripts, Diplomas, Certificates, Cards, Verification | Documents |
| 11 | Settings | General, Menus | Configuration |

---

## Notes Importantes

### Modules opérationnels (backend migré)
- **UsersGuard** (Users, Roles, Permissions, Auth)
- **StructureAcademique** (Années, Cycles, Niveaux, Séries, Classes, Matières, Coefficients)
- **Finance** (Fee Types, Invoices, Payments, Discounts, Schedules)
- **Payroll** (Employees, Contracts, Salary Scales, Payroll)

### Modules frontend-only (backend LMD non migré)
- **Enrollment** (Students) - tables `students` migrées mais pas les inscriptions LMD
- **NotesEvaluations** (Grades)
- **Timetable** (Emplois du temps)
- **Attendance** (Présences)
- **Exams** (Examens)
- **Documents** (Documents officiels)

> Ces modules ont leurs pages frontend créées mais les API backend ne sont pas encore adaptées au système secondaire (collèges/lycées). Les tests de ces modules se limiteront à vérifier que les pages s'affichent sans erreur.
