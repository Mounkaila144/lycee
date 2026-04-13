# OFFRE BASIC - Gestion Scolaire LMD

## Présentation de l'Offre

L'offre **Basic** est conçue pour les petits établissements d'enseignement supérieur souhaitant digitaliser leurs processus fondamentaux. Elle couvre les besoins essentiels de gestion académique et administrative.

**Cible** : Petits instituts, écoles privées en démarrage (< 500 étudiants)

---

## 1. MODULE STRUCTURE ACADÉMIQUE (Fonctionnalités de Base)

### 1.1 Gestion des Programmes de Formation
- **Création de programmes**
  - Définir le nom, code et description du programme
  - Associer le programme à un département
  - Définir le type de diplôme (Licence, Master)
  - Spécifier la durée totale en semestres
- **Consultation des programmes**
  - Liste des programmes actifs
  - Recherche par nom ou code
  - Affichage des détails d'un programme

### 1.2 Gestion des Niveaux d'Études
- **Configuration des niveaux**
  - Créer les niveaux (L1, L2, L3, M1, M2)
  - Associer chaque niveau à un programme
  - Définir l'ordre des niveaux dans le parcours
- **Consultation**
  - Liste des niveaux par programme
  - Visualisation de la progression pédagogique

### 1.3 Gestion des Semestres
- **Paramétrage des semestres**
  - Créer les semestres (S1 à S10)
  - Associer à un niveau et une année académique
  - Définir les dates de début et fin
- **Consultation**
  - Calendrier des semestres
  - Semestre actif en cours

### 1.4 Gestion des Unités d'Enseignement (UE)
- **Création d'UE**
  - Nom, code et type d'UE (fondamentale, méthodologique, découverte, transversale)
  - Association au semestre
  - Définition des crédits ECTS
  - Coefficient de l'UE
- **Consultation**
  - Liste des UE par semestre
  - Détails d'une UE

### 1.5 Gestion des Modules/Matières
- **Création de modules**
  - Nom et code du module
  - Association à une UE
  - Crédits et coefficient du module
  - Volume horaire (CM, TD, TP)
- **Consultation**
  - Liste des modules par UE
  - Fiche détaillée d'un module

---

## 2. MODULE INSCRIPTIONS (Fonctionnalités de Base)

### 2.1 Inscription Administrative
- **Enregistrement des nouveaux étudiants**
  - Saisie des informations personnelles (nom, prénom, date de naissance)
  - Coordonnées (adresse, téléphone, email)
  - Information sur le tuteur/parent
  - Génération automatique du matricule étudiant
- **Téléchargement de documents**
  - Photo d'identité
  - Pièce d'identité
  - Diplômes et relevés antérieurs
- **Validation de l'inscription**
  - Vérification des documents
  - Validation par l'administration
  - Génération de la fiche d'inscription

### 2.2 Inscription Pédagogique
- **Affectation au programme**
  - Choix du programme d'études
  - Affectation au niveau
  - Affectation au semestre
- **Inscription aux modules**
  - Inscription automatique aux modules obligatoires
  - Liste des modules inscrits
- **Consultation du dossier**
  - Fiche étudiant complète
  - Historique des inscriptions

### 2.3 Gestion des Groupes
- **Création de groupes pédagogiques**
  - Nom du groupe (ex: L1-Info-A)
  - Capacité maximale
  - Association au niveau/programme
- **Affectation aux groupes**
  - Affectation manuelle des étudiants
  - Liste des étudiants par groupe

### 2.4 Recherche et Consultation
- **Recherche d'étudiants**
  - Par matricule
  - Par nom/prénom
  - Par programme/niveau
- **Fiches étudiants**
  - Informations personnelles
  - Parcours académique
  - Statut d'inscription

---

## 3. MODULE EMPLOIS DU TEMPS (Fonctionnalités de Base)

### 3.1 Gestion des Ressources
- **Gestion des salles**
  - Nom et code de la salle
  - Capacité d'accueil
  - Type de salle (amphithéâtre, salle de cours, labo)
- **Consultation des enseignants**
  - Liste des enseignants disponibles
  - Matières enseignées par enseignant

### 3.2 Création des Séances
- **Planification manuelle**
  - Choix du module/matière
  - Sélection de l'enseignant
  - Attribution de la salle
  - Définition du créneau horaire (jour, heure début, heure fin)
  - Type de séance (CM, TD, TP)
- **Association au groupe**
  - Lier la séance à un groupe pédagogique

### 3.3 Consultation des Emplois du Temps
- **Vue par groupe**
  - Emploi du temps hebdomadaire du groupe
  - Affichage grille horaire
- **Vue par enseignant**
  - Planning hebdomadaire de l'enseignant
- **Vue par salle**
  - Occupation de la salle sur la semaine
- **Export PDF**
  - Génération PDF de l'emploi du temps

### 3.4 Détection de Conflits (Basique)
- **Vérification automatique**
  - Alerte si salle déjà occupée sur le créneau
  - Alerte si enseignant déjà affecté sur le créneau
  - Alerte si groupe déjà en cours

---

## 4. MODULE PRÉSENCES/ABSENCES (Fonctionnalités de Base)

### 4.1 Feuilles de Présence
- **Génération de feuilles**
  - Création automatique basée sur l'emploi du temps
  - Liste des étudiants du groupe
  - Date et créneau de la séance
- **Saisie des présences**
  - Marquage Présent/Absent pour chaque étudiant
  - Enregistrement par l'enseignant

### 4.2 Consultation des Absences
- **Par étudiant**
  - Liste des absences d'un étudiant
  - Total d'absences par module
- **Par séance**
  - Liste des absents d'une séance
  - Taux de présence de la séance

### 4.3 Rapports Simples
- **Récapitulatif par module**
  - Nombre d'absences par étudiant
  - Taux d'absentéisme du groupe
- **Export**
  - Export de la liste des absences (PDF)

---

## 5. MODULE NOTES & ÉVALUATIONS (Fonctionnalités de Base)

### 5.1 Configuration des Évaluations
- **Types d'évaluations**
  - Contrôle Continu (CC)
  - Examen Final
- **Pondération simple**
  - Coefficient CC vs Examen (ex: 40/60)
  - Configuration par module

### 5.2 Saisie des Notes
- **Saisie manuelle**
  - Interface de saisie par module
  - Liste des étudiants inscrits
  - Saisie de la note CC
  - Saisie de la note Examen
- **Validation**
  - Notes sur 20
  - Vérification des plages valides

### 5.3 Calcul des Moyennes
- **Moyenne du module**
  - Calcul automatique : (CC × coef_cc + Exam × coef_exam) / total_coef
- **Validation du module**
  - Module validé si moyenne ≥ 10/20
  - Affichage du statut (Validé/Non validé)

### 5.4 Consultation des Résultats
- **Par étudiant**
  - Bulletin de notes du semestre
  - Notes par module
  - Statut de validation
- **Par module**
  - Liste des notes de tous les étudiants
  - Statistiques basiques (moyenne de classe, min, max)

### 5.5 Export des Résultats
- **Procès-verbal simple**
  - PV des notes d'un module
  - Export PDF

---

## 6. MODULE DOCUMENTS OFFICIELS (Fonctionnalités de Base)

### 6.1 Relevé de Notes
- **Génération de relevé**
  - Relevé de notes d'un semestre
  - Informations étudiant
  - Liste des modules avec notes et crédits
  - Moyenne du semestre
- **Format PDF**
  - Mise en page standard
  - Logo de l'établissement

### 6.2 Certificat de Scolarité
- **Génération**
  - Attestation d'inscription en cours
  - Année académique et niveau
  - Numéro unique de certificat
- **Validation**
  - QR Code de vérification basique

### 6.3 Historique des Documents
- **Traçabilité**
  - Date de génération
  - Type de document
  - Étudiant concerné

---

## 7. MODULE COMPTABILITÉ ÉTUDIANTS (Fonctionnalités de Base)

### 7.1 Configuration des Frais
- **Définition des frais de scolarité**
  - Montant par programme et niveau
  - Frais d'inscription
  - Année académique concernée

### 7.2 Facturation
- **Génération de factures**
  - Facture individuelle par étudiant
  - Détail des frais dus
  - Montant total
  - Date d'échéance
- **Consultation**
  - Liste des factures d'un étudiant
  - Statut (En attente, Payée, Partielle)

### 7.3 Enregistrement des Paiements
- **Saisie des paiements**
  - Montant payé
  - Date du paiement
  - Mode de paiement (Espèces, Virement, Chèque)
  - Référence de paiement
- **Reçu de paiement**
  - Génération du reçu PDF
  - Numéro de reçu unique

### 7.4 Suivi des Paiements
- **Solde étudiant**
  - Montant dû
  - Montant payé
  - Reste à payer
- **Liste des impayés**
  - Étudiants avec solde négatif
  - Export de la liste

---

## LIMITATIONS DE L'OFFRE BASIC

Cette offre **n'inclut pas** :
- ❌ Gestion de la paie du personnel
- ❌ Sessions de rattrapage
- ❌ Compensation entre UE/modules
- ❌ Justification des absences avec workflow
- ❌ Paiements échelonnés
- ❌ Tableaux de bord avancés
- ❌ API d'intégration
- ❌ Multi-campus
- ❌ Notifications automatiques
- ❌ Rapports statistiques avancés

---

## RÉCAPITULATIF DES FONCTIONNALITÉS

| Module | Nb Fonctionnalités |
|--------|-------------------|
| Structure Académique | 5 |
| Inscriptions | 4 |
| Emplois du Temps | 4 |
| Présences/Absences | 3 |
| Notes & Évaluations | 5 |
| Documents Officiels | 3 |
| Comptabilité Étudiants | 4 |
| **TOTAL** | **28 fonctionnalités** |

---

## TARIFICATION SUGGÉRÉE

- **Licence annuelle** : À définir
- **Nombre d'utilisateurs** : Jusqu'à 10 administrateurs
- **Nombre d'étudiants** : Jusqu'à 500
- **Support** : Email uniquement (48h)
- **Mises à jour** : Corrections de bugs uniquement
