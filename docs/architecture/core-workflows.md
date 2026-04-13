# Workflows Metier

[<- Retour a l'index](./index.md)

---

## Workflow 1 : Inscription Complete d'un Eleve

### Etapes

1. **Inscription Administrative** (Admin)
   - Saisie donnees personnelles de l'eleve (nom, prenom, date de naissance, sexe, etc.)
   - Upload photo de l'eleve
   - Generation automatique d'un matricule unique

2. **Creation Compte Parent** (Systeme)
   - Creation automatique du compte parent/tuteur avec ses coordonnees (telephone, adresse)
   - Liaison automatique parent-eleve

3. **Affectation en Classe** (Admin)
   - Selection de la classe pour l'annee scolaire active (ex : 6e A, 3e B, 2nde C, Tle D1)
   - L'eleve est rattache a la classe et apparait dans les listes

4. **Facturation** (Systeme)
   - Generation automatique des frais selon la configuration de l'etablissement
   - Types de frais : scolarite, APE, cantine, assurance, etc.
   - Montants determines par le niveau ou la classe

5. **Paiement** (Comptable)
   - Enregistrement du paiement (total ou partiel)
   - Generation du recu PDF
   - Mise a jour du statut des frais

### Acteurs
- Administrateur (Admin)
- Comptable
- Systeme

---

## Workflow 2 : Saisie et Publication de Notes

### Etapes

1. **Creation Evaluation** (Admin/Enseignant)
   - Selection matiere, classe, semestre (S1 ou S2)
   - Selection type d'evaluation : Devoir, Interrogation, Composition
   - Definition du poids de l'evaluation dans le calcul de la moyenne

2. **Saisie Notes** (Enseignant)
   - Consultation de la liste des eleves de la classe
   - Saisie des notes sur 20 pour chaque eleve
   - Marquage des absents (ABS)

3. **Calcul Automatique** (Systeme)
   - Calcul de la moyenne par matiere (ponderee par le poids des evaluations)
   - Calcul de la moyenne generale avec coefficients par matiere
   - Calcul du classement de chaque eleve dans la classe

4. **Saisie Appreciations** (Enseignant)
   - Redaction d'une appreciation par matiere pour chaque eleve
   - Commentaires sur le travail, le comportement, la progression

5. **Publication** (Admin)
   - Validation des resultats
   - Publication des notes visibles pour les eleves et les parents

### Acteurs
- Administrateur (Admin)
- Enseignant
- Systeme
- Eleve/Parent (consultation uniquement)

---

## Workflow 3 : Conseil de Classe

### Etapes

1. **Preparation** (Admin)
   - Planification du conseil de classe (date, heure, salle)
   - Generation du recapitulatif de la classe (moyennes, classements, statistiques)

2. **Session du Conseil** (President)
   - Vue consolidee de toutes les notes, moyennes et classements
   - Statistiques de la classe : moyenne generale, taux de reussite, repartition par tranches de moyennes

3. **Decisions - Mentions** (President)
   - Attribution des mentions par eleve :
     - Felicitations
     - Tableau d'honneur
     - Encouragements
     - Avertissement (travail ou conduite)
   - Saisie de l'appreciation generale du conseil pour chaque eleve

4. **Decisions de Fin d'Annee** (President - S2 uniquement)
   - Decision pour chaque eleve :
     - Passage en classe superieure
     - Redoublement
     - Exclusion
     - Orientation (changement de serie ou de filiere)

5. **Generation PV** (Systeme)
   - Generation automatique du proces-verbal du conseil de classe
   - Archivage du PV avec les decisions prises

### Acteurs
- Administrateur (Admin)
- President du conseil (Directeur/Censeur)
- Enseignants (consultation)
- Systeme

---

## Workflow 4 : Generation Bulletin Semestriel

### Etapes

1. **Declenchement** (Admin)
   - Generation pour un eleve individuel
   - Ou generation en masse pour toute une classe

2. **Validation Prerequis** (Systeme)
   - Verification que toutes les notes sont saisies pour le semestre
   - Verification que le conseil de classe est termine

3. **Collecte Donnees** (Systeme)
   - Notes par matiere et par evaluation
   - Moyennes par matiere et moyenne generale
   - Coefficients de chaque matiere
   - Rang de l'eleve dans la classe
   - Appreciations des enseignants et du conseil
   - Mentions attribuees
   - Total des absences sur la periode

4. **Generation PDF** (Queue Job Asynchrone)
   - Utilisation d'un template professionnel
   - Inclusion du logo de l'etablissement
   - Informations de l'eleve (matricule, classe, annee scolaire)
   - Tableau de toutes les matieres avec notes, moyennes et rangs
   - Appreciations par matiere
   - Moyenne generale, rang general, mention du conseil
   - Signature direction

5. **Stockage et Notification** (Systeme)
   - Sauvegarde du PDF sur le stockage du tenant
   - Notification au parent et a l'eleve de la disponibilite du bulletin

### Acteurs
- Administrateur (Admin)
- Systeme (Jobs asynchrones)

---

## Workflow 5 : Appel et Suivi des Absences

### Etapes

1. **Appel en Seance** (Enseignant)
   - Selection de la seance depuis l'emploi du temps
   - Marquage de chaque eleve : Present, Absent, Retard, Excuse

2. **Notification Parent** (Systeme)
   - Alerte automatique au parent en cas d'absence non justifiee
   - Envoi par SMS ou notification dans l'application

3. **Justification** (Admin/Surveillant General)
   - Reception du justificatif (certificat medical, mot du parent, etc.)
   - Validation ou rejet du justificatif
   - Mise a jour du statut de l'absence (Justifiee/Non justifiee)

4. **Alerte Seuil** (Systeme)
   - Suivi du cumul d'absences par eleve
   - Si le nombre d'absences depasse le seuil configure : notification a la direction et aux parents
   - Declenchement d'actions disciplinaires si necessaire

5. **Consolidation** (Systeme)
   - Calcul du total d'absences par eleve et par periode
   - Report automatique du total d'absences sur le bulletin semestriel

### Acteurs
- Enseignant
- Surveillant General
- Administrateur (Admin)
- Parent (notification)
- Systeme

---

## Workflow 6 : Gestion de la Discipline

### Etapes

1. **Signalement Incident** (Enseignant/Surveillant)
   - Enregistrement de l'incident : date, lieu, description detaillee
   - Indication du niveau de severite (mineur, moyen, grave)
   - Identification de l'eleve ou des eleves concernes

2. **Prononciation Sanction** (Surveillant General/Direction)
   - Choix de la sanction appropriee :
     - Avertissement verbal ou ecrit
     - Blame
     - Exclusion temporaire (avec duree)
     - Convocation des parents
     - Renvoi definitif (apres conseil de discipline)

3. **Notification Parent** (Systeme)
   - Alerte automatique au parent avec les details de l'incident et de la sanction
   - Demande de prise de rendez-vous si necessaire

4. **Conseil de Discipline** (Direction - si incident grave)
   - Convocation de l'eleve, des parents et des membres du conseil
   - Tenue du conseil de discipline
   - Deliberation et decision
   - Generation du proces-verbal

5. **Historique** (Systeme)
   - Archivage de tous les incidents et sanctions dans le dossier disciplinaire de l'eleve
   - Consultation de l'historique pour les decisions futures

### Acteurs
- Enseignant
- Surveillant General
- Direction
- Parent (notification)
- Systeme

---

## Workflow 7 : Import CSV d'Eleves

### Etapes

1. **Telechargement Template** (Admin)
   - Telechargement du fichier CSV modele
   - Colonnes : nom, prenom, date_naissance, sexe, parent_nom, parent_telephone, classe

2. **Upload et Previsualisation** (Admin)
   - Upload du fichier CSV rempli
   - Validation du format et des colonnes
   - Affichage d'un apercu des donnees
   - Detection et affichage des erreurs (doublons, dates invalides, classes inexistantes, etc.)

3. **Import Definitif** (Systeme)
   - Traitement asynchrone via queue job
   - Generation automatique des matricules pour chaque eleve
   - Creation automatique des comptes parents
   - Affectation des eleves dans leurs classes respectives

4. **Rapport Import** (Systeme)
   - Nombre d'eleves crees avec succes
   - Liste des erreurs rencontrees et lignes concernees
   - Possibilite de telecharger le rapport detaille

### Acteurs
- Administrateur (Admin)
- Systeme (Jobs asynchrones)

---

## Workflow 8 : Gestion Financiere

### Etapes

1. **Parametrage** (Admin)
   - Configuration des types de frais : scolarite, APE, cantine, assurance, etc.
   - Definition des montants par niveau (6e, 5e, ..., Tle) ou par classe
   - Configuration des echeances de paiement

2. **Facturation** (Systeme)
   - Generation automatique des frais a l'inscription de chaque eleve
   - Application des montants selon le niveau ou la classe de l'eleve

3. **Paiement** (Comptable)
   - Enregistrement du paiement (total ou partiel)
   - Generation du recu PDF
   - Mise a jour du statut : Impaye, Partiellement paye, Solde

4. **Suivi Impayes** (Comptable)
   - Tableau de bord des impayes par classe et par niveau
   - Generation de relances pour les parents
   - Suivi des echeanciers de paiement

5. **Rapports** (Admin)
   - Bilan financier par periode (mois, trimestre, semestre, annee)
   - Recettes par type de frais
   - Taux de recouvrement
   - Export des rapports

### Acteurs
- Administrateur (Admin)
- Comptable
- Parent (consultation)
- Systeme

---

[Suivant : Arborescence du Code ->](./source-tree.md)
