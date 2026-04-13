# Project Brief: Gestion Scolaire - Enseignement Secondaire

## Executive Summary

**Gestion Scolaire** est une plateforme complète de gestion pour les établissements d'enseignement secondaire au Niger (collèges et lycées). La solution couvre l'ensemble du cycle de vie scolaire : de l'inscription des élèves à la délivrance des bulletins et diplômes, en passant par la gestion pédagogique, la discipline, les finances et les ressources humaines.

**Problème résolu** : Les collèges et lycées au Niger gèrent actuellement leurs opérations de manière manuelle (cahiers de notes, registres papier, fiches d'appel) ou avec des outils bureautiques inadaptés (Excel, Word). Cette approche entraîne des pertes de temps considérables, des erreurs fréquentes dans les bulletins, et une difficulté à communiquer efficacement avec les parents d'élèves.

**Marché cible** : Établissements d'enseignement secondaire au Niger — collèges d'enseignement général (CEG), lycées publics et privés.

**Proposition de valeur clé** :
- **Numérisation complète** : Abandon du papier pour une gestion 100% numérique des notes, absences, et documents
- **Bulletins automatiques** : Génération instantanée des bulletins semestriels avec moyennes, classements et appréciations
- **Suivi parental** : Portail dédié permettant aux parents de suivre en temps réel la scolarité de leurs enfants
- **Gestion financière intégrée** : Frais de scolarité, contributions APE, cantine, et paie du personnel
- **Multi-tenant** : Architecture permettant à plusieurs établissements d'utiliser une seule instance de manière isolée
- **Conformité nigérienne** : Respect du système éducatif nigérien (semestres, coefficients, conseil de classe, BEPC/Bac)

---

## Problem Statement

### État Actuel et Points de Douleur

Les collèges et lycées au Niger font face à des défis opérationnels majeurs dans leur gestion quotidienne :

**1. Gestion manuelle des notes et bulletins**
- **Cahiers de notes** : Chaque enseignant tient un cahier physique avec les notes de devoirs, interrogations et compositions
- **Calculs manuels** : Moyennes par matière, moyennes générales, classements calculés à la main ou sur calculatrice
- **Bulletins fastidieux** : Rédaction manuelle bulletin par bulletin, recopie des notes, risque élevé d'erreurs de transcription
- **Retards importants** : Les bulletins sont souvent distribués des semaines après la fin du semestre
- **Conseil de classe** : Décisions de passage/redoublement prises sans données consolidées facilement accessibles

**2. Suivi des présences et de la discipline défaillant**
- **Feuilles d'appel papier** : Perdues, incomplètes, non consolidées
- **Cahier de textes** : Suivi des programmes non centralisé
- **Discipline** : Sanctions et avertissements notés dans des registres papier, sans historique facilement consultable
- **Parents non informés** : Les parents ne sont prévenus des absences ou problèmes de discipline que tardivement (convocations papier)

**3. Communication parent-école quasi inexistante**
- **Bulletins papier** : Seul moyen de communication formelle des résultats, distribués une fois par semestre
- **Convocations** : Envoyées via l'élève (souvent perdues ou non transmises)
- **Absence de transparence** : Parents ignorent la situation scolaire réelle jusqu'au bulletin
- **Association de parents (APE)** : Gestion informelle des contributions

**4. Gestion financière fragmentée**
- **Frais de scolarité** : Suivi sur registres papier, difficulté à identifier les impayés
- **Multiples frais** : Inscription, APE, tenue, cantine, activités — chacun suivi séparément
- **Paie du personnel** : Calculs manuels des salaires (enseignants permanents, vacataires, personnel d'appui)
- **Aucun tableau de bord** : Pas de vision d'ensemble de la situation financière

**5. Emplois du temps et planning complexes**
- **Création manuelle** : Sur tableau blanc ou papier, avec de fréquents conflits (salle, enseignant)
- **Modifications difficiles** : Un changement implique de tout recalculer
- **Pas de visibilité** : Élèves et parents découvrent les changements au dernier moment

### Impact du Problème

- **Temps perdu** : Des centaines d'heures par semestre consacrées aux bulletins et tâches administratives
- **Erreurs fréquentes** : Notes erronées dans les bulletins, moyennes mal calculées, classements incorrects
- **Confiance dégradée** : Parents méfiants envers des résultats sujets aux erreurs humaines
- **Décrochage non détecté** : Absences répétées et chute des résultats non signalées à temps
- **Pilotage aveugle** : Directeurs sans données consolidées pour orienter les décisions pédagogiques
- **Conformité difficile** : Statistiques demandées par le Ministère de l'Éducation Nationale produites laborieusement

### Pourquoi les Solutions Actuelles Échouent

- **Excel/Word** : Non conçus pour la gestion scolaire, pas de multi-utilisateurs, pas de workflows, corruption de fichiers
- **Logiciels internationaux** : Coût prohibitif, pas adaptés au système éducatif nigérien (semestres, coefficients, séries)
- **Cahiers de notes** : Aucune centralisation, aucune consolidation automatique, aucun partage avec les parents

### Urgence

Avec la croissance démographique et l'augmentation des effectifs scolaires au Niger, la gestion manuelle devient de plus en plus intenable. La digitalisation est indispensable pour maintenir la qualité du suivi pédagogique et améliorer la communication avec les familles.

---

## Proposed Solution

### Concept Central

**Gestion Scolaire** est une plateforme web unifiée qui numérise et automatise l'ensemble des processus pédagogiques, administratifs et financiers des collèges et lycées. La solution remplace les cahiers de notes, registres papier et fichiers Excel par un système centralisé, accessible depuis n'importe quel navigateur web.

### Architecture Technique

**Stack moderne et éprouvée** :
- **Backend** : Laravel 12 (PHP 8.3+) avec architecture modulaire
- **Frontend** : Next.js (React) pour une interface utilisateur moderne et réactive
- **Base de données** : MySQL avec architecture multi-tenant (isolation complète des données par établissement)
- **Authentification** : Système sécurisé avec gestion fine des permissions (Spatie + Laravel Sanctum)
- **Déploiement** : Architecture cloud-ready, accessible via Internet

**Multi-tenant** : Un seul déploiement peut servir plusieurs établissements, chacun ayant ses propres données totalement isolées, ses utilisateurs, et sa configuration.

### Différenciateurs Clés

**1. Conçu pour le système éducatif nigérien**
- Interface en français
- Structure en semestres (2 par année scolaire)
- Coefficients par matière selon les programmes officiels
- Séries du lycée : A (Littéraire), C (Maths-Physique), D (Sciences Naturelles), etc.
- Gestion du BEPC et du Baccalauréat
- Conseil de classe avec outils d'aide à la décision

**2. Bulletins semestriels automatiques**
- Calcul automatique des moyennes par matière et moyenne générale
- Classement automatique des élèves
- Appréciations par matière (enseignants) et appréciation générale (conseil de classe)
- Génération PDF professionnelle en un clic pour toute une classe
- Mentions et décisions : Tableau d'honneur, Encouragements, Avertissement travail/conduite

**3. Portail Parent intégré dès le MVP**
- Consultation en temps réel des notes, absences, et comportement
- Notification des absences et sanctions disciplinaires
- Consultation des bulletins en ligne (PDF téléchargeable)
- Suivi de la situation financière (frais payés, impayés)

**4. Gestion de la discipline**
- Suivi du comportement des élèves (avertissements, blâmes, exclusions temporaires)
- Historique disciplinaire par élève
- Notification automatique aux parents en cas de sanction
- Conseil de discipline : convocations, procès-verbaux

**5. Gestion financière complète**
- Suivi des frais de scolarité, contributions APE, cantine, tenues, activités
- Enregistrement des paiements avec génération de reçus
- Échéanciers et relances automatiques
- Paie du personnel (enseignants, administratifs, personnel d'appui)
- Tableau de bord financier

**6. Paramétrable par établissement**
- Coefficients par matière configurables
- Barèmes de mentions configurables (Tableau d'honneur à partir de X de moyenne)
- Types de frais et tarifs adaptables
- Structure des classes personnalisable

### Pourquoi Cette Solution Réussira

**Contrairement aux alternatives** :
- **Vs Papier/Cahiers** : Centralisation, automatisation des calculs, communication avec les parents
- **Vs Solutions internationales** : Coût accessible, adapté au contexte nigérien
- **Vs Excel** : Multi-utilisateurs, calculs automatiques, bulletins professionnels

**Adoption facilitée** :
- Interface intuitive ne nécessitant pas de formation lourde
- Déploiement progressif possible (module par module)
- Support et formation en français
- Import des données existantes (listes d'élèves Excel)

---

## Target Users

Le système **Gestion Scolaire** s'adresse à sept catégories d'utilisateurs distinctes.

### Segment 1 : SuperAdmin (Gestionnaire de Plateforme)

**Profil** : Administrateur système central, gestionnaire de la plateforme multi-tenant avec niveau technique avancé.

**Besoins clés** :
- Créer, configurer et gérer les tenants (établissements)
- Créer les comptes Admin pour chaque tenant
- Vue d'ensemble de tous les établissements et leur utilisation
- Surveillance proactive du système

---

### Segment 2 : Admin / Directeur (Gestionnaire d'Établissement)

**Profil** : Proviseur (lycée), Principal (collège), Directeur des études, Censeur, Surveillant général. Niveau technique intermédiaire.

**Comportements actuels** :
- Gère les inscriptions sur registres papier
- Crée manuellement les emplois du temps sur tableau
- Compile les résultats pour le conseil de classe à la main
- Produit les statistiques demandées par le Ministère manuellement

**Besoins clés** :
- Inscrire rapidement les élèves (inscription administrative)
- Gérer la structure des classes (6e à Terminale, séries, sections)
- Créer et gérer les emplois du temps sans conflits
- Piloter les conseils de classe avec données consolidées
- Consulter les statistiques (taux de réussite, absences, finances)
- Générer les bulletins semestriels pour toutes les classes
- Gérer la discipline (avertissements, exclusions, conseil de discipline)

**Objectifs** : Réduire le temps administratif de 70%, avoir une vision en temps réel de l'établissement.

---

### Segment 3 : Enseignant

**Profil** : Professeur permanent, vacataire, contractuel. Niveau technique variable (basique à intermédiaire).

**Comportements actuels** :
- Tient un cahier de notes physique
- Fait l'appel sur feuille papier
- Calcule les moyennes manuellement
- Rédige les appréciations sur les bulletins papier un par un

**Besoins clés** :
- Consulter son emploi du temps (web, mobile)
- Saisir les notes des devoirs, interrogations et compositions
- Faire l'appel rapidement (présent/absent/retard/excusé)
- Voir les moyennes calculées automatiquement
- Saisir les appréciations par élève
- Remplir le cahier de textes (progression du programme)

**Objectifs** : Gagner du temps sur les tâches administratives, se concentrer sur l'enseignement.

---

### Segment 4 : Élève

**Profil** : Élève inscrit (6e à Terminale), âge 11-20 ans. Niveau technique basique, accès principalement via smartphone.

**Comportements actuels** :
- Attend l'affichage des résultats au tableau
- Reçoit le bulletin papier en fin de semestre
- Consulte l'emploi du temps affiché en classe

**Besoins clés** :
- Consulter son emploi du temps en ligne
- Voir ses notes au fur et à mesure
- Consulter ses absences
- Télécharger ses bulletins semestriels

**Objectifs** : Accès autonome à ses informations scolaires, suivi de sa progression.

---

### Segment 5 : Parent / Tuteur

**Profil** : Parent d'élève, tuteur légal, responsable financier. Niveau technique basique (smartphone). Très impliqué dans le suivi scolaire des enfants mineurs.

**Comportements actuels** :
- Attend le bulletin papier pour connaître les résultats
- Se déplace à l'école pour obtenir des informations
- N'est prévenu des problèmes que tardivement (convocations via l'élève)
- Participe aux réunions APE pour les questions financières

**Besoins clés** :
- Consulter les notes et moyennes de l'enfant en temps réel
- Être notifié des absences et retards
- Être alerté en cas de sanction disciplinaire
- Télécharger les bulletins semestriels (PDF)
- Voir la situation financière (frais payés, solde restant)
- Suivre plusieurs enfants dans le même établissement ou dans des établissements différents

**Objectifs** : Suivi proactif de la scolarité, réagir rapidement en cas de problème, transparence totale.

---

### Segment 6 : Comptable / Intendant

**Profil** : Intendant, économe, caissier. Niveau technique intermédiaire.

**Comportements actuels** :
- Enregistre les paiements dans des registres papier
- Émet des reçus manuels
- Compile les états financiers manuellement
- Gère la paie sur Excel

**Besoins clés** :
- Enregistrer les paiements des frais scolaires
- Générer automatiquement des reçus
- Voir les impayés en temps réel
- Gérer les échéanciers de paiement
- Calculer et gérer la paie du personnel
- Générer des rapports financiers

**Objectifs** : Automatiser les tâches répétitives, visibilité financière en temps réel.

---

### Segment 7 : Surveillant Général

**Profil** : Responsable de la discipline et du suivi des absences. Niveau technique intermédiaire.

**Besoins clés** :
- Consolider les absences de tous les cours
- Gérer les sanctions disciplinaires (avertissements, blâmes, exclusions)
- Convoquer les parents en cas de besoin
- Préparer les dossiers pour le conseil de discipline
- Produire les rapports d'absences et de discipline

**Objectifs** : Vue centralisée de la discipline et des absences, communication efficace avec les parents.

---

## Goals & Success Metrics

### Objectifs Business

**1. Digitalisation complète de l'établissement**
- **Métrique** : 100% des processus de notation, bulletins et absences numérisés
- **Cible** : Atteinte dans les 3 mois après déploiement
- **Impact** : Réduction de 80% du temps consacré aux bulletins et calculs de moyennes

**2. Génération automatique des bulletins**
- **Métrique** : Temps moyen de génération de tous les bulletins d'une classe
- **Cible** : < 5 minutes pour une classe de 60 élèves (vs 2-3 jours manuellement)
- **Impact** : Bulletins disponibles dès la fin des conseils de classe

**3. Engagement des parents**
- **Métrique** : % de parents utilisant activement le portail
- **Cible** : > 50% après 2 semestres
- **Impact** : Meilleur suivi scolaire, détection précoce des décrochages

**4. Adoption multi-établissement**
- **Métrique** : Nombre d'établissements (tenants) actifs
- **Cible** : 5 établissements la première année, 20 en 3 ans
- **Impact** : Économies d'échelle, rentabilité de la plateforme

**5. Santé financière des établissements**
- **Métrique** : Taux de recouvrement des frais de scolarité
- **Cible** : Amélioration de 25% grâce au suivi automatisé
- **Impact** : Meilleure trésorerie, viabilité financière

### Métriques de Succès Utilisateur

**Pour les Admins / Directeurs** :
- Temps de production des bulletins d'un semestre : < 1 jour (vs 1-2 semaines)
- Taux d'erreurs dans les bulletins : < 0.5% (vs 10-15% manuellement)
- Satisfaction utilisateur (NPS) : > 50

**Pour les Enseignants** :
- Taux d'adoption de la saisie de notes en ligne : > 90% après 2 semestres
- Temps moyen de saisie des présences pour une classe : < 3 minutes
- Satisfaction utilisateur (NPS) : > 40

**Pour les Parents** :
- Taux d'activation du portail parent : > 60% après 1 an
- Fréquence de consultation : > 2 fois par mois
- Satisfaction utilisateur (NPS) : > 60

**Pour les Comptables** :
- Temps d'enregistrement d'un paiement : < 2 minutes
- Temps de génération d'un rapport financier mensuel : < 10 minutes
- Exactitude des calculs de paie : 100%

### KPIs Système

- **Taux d'utilisation active** : > 80% des utilisateurs actifs mensuels (cible : 3 mois après déploiement)
- **Bulletins générés par semestre** : > 2000 pour un établissement de 700 élèves
- **Taux de disponibilité** : > 99.5%
- **Temps de réponse moyen** : < 2 secondes pour 95% des requêtes
- **Transactions financières/mois** : > 500 pour un établissement de 700 élèves
- **Taux de résolution bugs critiques** : > 90% dans les 48h

---

## MVP Scope

Le MVP de **Gestion Scolaire** inclut toutes les fonctionnalités essentielles pour gérer un collège ou lycée de bout en bout.

### Core Features (Must Have - MVP)

#### Module 1 : Authentification & Gestion des Utilisateurs ✅ **EXISTANT (UsersGuard)**

**Base déjà en place** :
- **SuperAdmin** : Accès total, création de tenants, création des admins
- **Admin Tenant** : Gestion de son établissement
- **Multi-tenant** : Isolation complète des données par établissement
- **Permissions fines** : Système Spatie Permission
- **API Auth** : Laravel Sanctum (tokens API)

**À adapter pour le secondaire** :
- Rôles spécifiques : Admin/Directeur, Censeur, Surveillant Général, Enseignant, Élève, Parent, Comptable
- Liaison Parent ↔ Élève(s) : Un parent peut suivre plusieurs enfants
- Profils adaptés (photos, infos personnelles, contacts d'urgence)

#### Module 2 : Structure Académique (Cœur du Système)

- **Années scolaires** : Création et gestion (ex: 2025-2026)
- **Semestres** : S1, S2 avec dates de début/fin
- **Cycles** : Collège (6e-3e), Lycée (2nde-Tle)
- **Classes** : 6e A, 6e B, 5e A, ..., Tle C1, Tle D2, etc.
- **Séries** (lycée) : A (Littéraire), C (Maths-Physique), D (Sciences Naturelles), etc.
- **Matières** : Avec coefficients par classe/série (ex: Maths coeff 5 en Tle C, coeff 2 en Tle A)
- **Affectation enseignants** : Lier enseignant ↔ matière ↔ classe(s)
- **Professeur principal** : Désignation par classe
- **Barèmes configurables** : Seuils de passage, mentions, tableau d'honneur

#### Module 3 : Inscriptions

- **Inscription des élèves** : Infos personnelles, classe, numéro matricule
- **Informations parentales** : Nom, prénom, téléphone, adresse du/des parent(s)/tuteur(s)
- **Création automatique du compte parent** : Lors de l'inscription de l'élève
- **Affectation en classe** : Répartition des élèves dans les classes
- **Import en masse** : CSV/Excel pour listes d'élèves existantes
- **Gestion du statut** : Actif, Transféré, Exclu, Diplômé
- **Réinscription** : Passage en classe supérieure (promotion automatique ou manuelle)
- **Exeat / Certificat de scolarité** : Pour transferts

#### Module 4 : Emplois du Temps

- **Création d'emplois du temps** par classe
- **Séances** : Jour, heure, salle, enseignant, matière
- **Détection automatique des conflits** : Enseignant ou salle déjà occupé(e)
- **Visualisation** : Vue grille hebdomadaire par classe, par enseignant, par salle
- **Consultation multi-rôles** : Enseignant (son EDT), Élève/Parent (EDT classe), Admin (vue d'ensemble)
- **Export** : PDF, impression

#### Module 5 : Présences / Absences

- **Appel par séance** : Statuts (Présent, Absent, Retard, Excusé)
- **Justificatifs** : Upload de documents (certificats médicaux, etc.)
- **Consolidation** : Total absences par élève, par semestre, par matière
- **Alertes parents** : Notification automatique en cas d'absence non justifiée
- **Seuil d'alerte** : Configurable (ex: > 3 absences non justifiées → notification direction)
- **Rapports** : Taux de présence par classe, par élève

#### Module 6 : Notes & Évaluations

- **Types d'évaluations** : Devoir surveillé, Interrogation écrite/orale, Composition semestrielle, TP/Pratique
- **Saisie des notes** par enseignant (/20)
- **Calcul automatique** :
  - Moyenne par matière par semestre (configurable : moyenne simple ou pondérée devoirs/compositions)
  - Moyenne générale semestrielle avec coefficients
  - Classement par classe
  - Moyenne annuelle (moyenne des 2 semestres)
- **Appréciations** : Par matière (enseignant) + appréciation générale (conseil de classe)
- **Mentions et décisions** : Tableau d'honneur, Encouragements, Félicitations, Avertissement travail, Avertissement conduite, Blâme
- **Rang** : Classement automatique au sein de la classe

#### Module 7 : Conseil de Classe

- **Tableau récapitulatif** : Toutes les notes et moyennes d'une classe pour le semestre
- **Statistiques de classe** : Moyenne générale de la classe, taux de réussite, répartition par tranche
- **Décisions** : Passage, Redoublement, Exclusion (fin d'année)
- **Procès-verbal** : Génération automatique du PV du conseil de classe
- **Appréciations générales** : Saisie par le président du conseil

#### Module 8 : Discipline

- **Types de sanctions** : Avertissement verbal, Avertissement écrit, Blâme, Exclusion temporaire (1-8 jours), Exclusion définitive (conseil de discipline)
- **Enregistrement des incidents** : Date, description, sanction, enseignant/surveillant rapporteur
- **Historique par élève** : Vue complète du dossier disciplinaire
- **Notification parents** : Alerte automatique en cas de sanction
- **Conseil de discipline** : Convocations, dossier, procès-verbal
- **Rapports** : Statistiques disciplinaires par classe, par période

#### Module 9 : Comptabilité & Finances

- **Paramétrage des frais** : Types (inscription, scolarité, APE, cantine, tenue, transport, etc.) + montants par classe
- **Facturation** : Génération automatique par élève au moment de l'inscription
- **Enregistrement des paiements** : Montant, mode (espèces, virement), génération reçus PDF
- **Paiements partiels et échéanciers**
- **Gestion des bourses et exonérations** : Réductions partielles ou totales
- **Tableau de bord** : Impayés, état de caisse, bilan par période
- **Dépenses** : Enregistrement des sorties avec justificatifs

#### Module 10 : Paie Personnel

- **Fiches personnel** : Type contrat (permanent, vacataire, contractuel), salaire fixe ou taux horaire
- **Calcul automatique de la paie** : Salaire base, heures supplémentaires, déductions
- **Génération bulletins de paie** : PDF
- **Historique paiements**
- **États mensuels** : Masse salariale, charges

#### Module 11 : Documents Officiels

- **Bulletins semestriels** : Template professionnel avec notes, moyennes, rang, appréciations, mentions — génération PDF en masse (par classe) ou individuelle
- **Bulletin annuel** : Récapitulatif des 2 semestres avec décision finale
- **Attestation de scolarité** : Génération en un clic
- **Attestation d'inscription**
- **Certificat de scolarité / Exeat** : Pour transferts
- **Cartes scolaires** : Génération avec photo
- **Relevé de notes annuel**
- **Reçus de paiement** : Génération automatique
- **Bulletins de paie** : Génération automatique

#### Module 12 : Portail Parent

- **Tableau de bord** : Vue d'ensemble de chaque enfant (notes, absences, discipline)
- **Notes détaillées** : Par matière, par évaluation
- **Absences** : Historique et justification
- **Discipline** : Notifications et historique des sanctions
- **Bulletins** : Consultation et téléchargement PDF
- **Finances** : État des paiements, impayés
- **Multi-enfants** : Gestion de plusieurs enfants dans un seul compte
- **Notifications** : Alertes push/email pour événements importants (absence, sanction, nouveau bulletin)

### Out of Scope for MVP (Phase 2)

- Notifications SMS (Email uniquement pour MVP)
- Intégrations paiement mobile (Orange Money, Moov Money)
- Mobile Apps natives (web responsive suffit pour MVP)
- Cahier de textes numérique avancé (progression des programmes)
- Gestion de la bibliothèque
- Gestion des examens nationaux (BEPC, Bac) — le système gère les notes internes, pas l'organisation des examens d'État
- E-learning / Devoirs en ligne
- Analytics prédictifs (IA)
- Intégration Ministère de l'Éducation Nationale

### MVP Success Criteria

- **Fonctionnalité** : Cycle complet sans papier (inscription → notes → bulletins → paiements)
- **Performance** : Génération de tous les bulletins d'une classe (60 élèves) en < 5 minutes
- **Adoption enseignants** : > 80% des enseignants saisissent les notes en ligne après 1 semestre
- **Engagement parents** : > 40% des parents ont activé leur portail après 1 semestre
- **Qualité** : < 0.5% d'erreurs dans les bulletins
- **Déploiement** : Au moins 2 établissements pilotes (1 collège + 1 lycée)

---

## Post-MVP Vision

### Phase 2 Features (Après stabilisation MVP)

**Priorité Haute** :

**1. Notifications SMS** : Alertes absences, résultats disponibles, relances paiements via SMS (adapté au contexte Niger où le SMS est plus accessible que l'email)

**2. Cahier de textes numérique** : Suivi de la progression des programmes par matière, visibilité pour la direction et les inspecteurs

**3. Gestion des examens blancs** : Organisation des examens blancs BEPC/Bac avec planning, surveillance, et statistiques de préparation

**4. Documents avancés** : QR codes de vérification sur les bulletins, watermarks, numérotation sécurisée

**5. Statistiques & Rapports avancés** : Tableaux de bord analytiques, rapports pour le Ministère, analyse comparative entre classes

**Priorité Moyenne** :

**6. Gestion de la bibliothèque** : Catalogue, prêts/retours, inventaire

**7. Gestion de la cantine** : Inscriptions, repas, paiements

**8. Communication interne** : Messagerie direction-enseignants, annonces, circulaires

### Long-Term Vision (Années 2-3)

**1. Intégrations Paiement Mobile** : Orange Money, Moov Money, Airtel Money — paiement des frais scolaires via mobile

**2. Intégration Ministère de l'Éducation** : Remontée automatique des statistiques (effectifs, taux de réussite, etc.)

**3. Mobile Apps Natives** : iOS/Android avec notifications push et mode offline

**4. Analytics & IA** : Prédiction de décrochage scolaire, recommandations personnalisées pour les élèves en difficulté

**5. Multi-pays** : Adaptation pour le Bénin, Burkina Faso, Mali, Sénégal (systèmes éducatifs francophones similaires)

**6. Gestion des stages** : Pour les lycées techniques et professionnels

### Expansion Opportunities

**Géographique** : Niger → Afrique de l'Ouest francophone (systèmes éducatifs similaires)

**Segments** : Secondaire (actuel) → Primaire (version simplifiée), Formation professionnelle, Enseignement supérieur

**Business Models** : SaaS par abonnement (actuel), On-premise pour établissements publics, Freemium (fonctionnalités de base gratuites)

**Écosystème** : Partenariats opérateurs télécom, éditeurs de manuels scolaires, Ministère de l'Éducation

---

## Technical Considerations

### Platform Requirements

**Cibles de déploiement** :
- Web Application accessible via navigateurs modernes (Chrome, Firefox, Safari, Edge)
- Responsive Design : Interface adaptée mobile, tablette, desktop (priorité mobile pour parents et élèves)
- Progressive Web App (PWA) en Phase 2

**Performance Requirements** :
- Temps de chargement initial : < 3 secondes
- Temps de réponse API : < 500ms pour 95% des requêtes
- Génération bulletins PDF : < 30 secondes pour une classe de 60 élèves
- Support 500+ utilisateurs concurrents par tenant
- Optimisation pour connexions à bande passante limitée (contexte Niger)

### Technology Stack (Architecture Existante)

#### Backend : Laravel 12 (PHP 8.3+) ✅ **EN PLACE**

**Framework** : Laravel 12.x avec architecture modulaire `nwidart/laravel-modules`
- Chaque module métier isolé et auto-contenu

**Packages clés** :
- `stancl/tenancy` v3.9+ : Multi-tenancy avec isolation BD ✅
- `spatie/laravel-permission` v6.24+ : Gestion permissions ✅
- `laravel/sanctum` v4.0+ : Authentification API ✅
- `barryvdh/laravel-dompdf` : Génération PDF (à ajouter)

**Base de données** : MySQL 8.0+
- Base centrale (`mysql`) : Tenants, Domains, SuperAdmins ✅
- Bases tenant (`tenant_{id}`) : Données isolées par établissement ✅

#### Frontend : Next.js 14+ (React) ✅ **EN PLACE**

**Framework** : Next.js App Router
- Server-Side Rendering (SSR) pour performance

**Architecture modulaire** : Mirroring du backend Laravel ✅
- Structure `src/modules/` reflétant modules backend

**Libraries** :
- React 18+ avec TypeScript ✅
- TailwindCSS v4 : Styling responsive
- Custom API client avec multi-tenancy ✅

### Repository Structure

**Architecture** : Polyrepo (backend et frontend séparés)

**Backend (Modules)** :
```
Modules/
├── UsersGuard/              ✅ Existant (à adapter rôles secondaire)
├── StructureAcademique/     🔄 Existant (à adapter : semestres, classes, séries)
├── Inscriptions/            🔄 À adapter (liaison parent-élève)
├── EmploisDuTemps/          🔄 Existant (réutilisable)
├── Presences/               🔄 Existant (à enrichir alertes parents)
├── Notes/                   🆕 À refondre (devoirs/compositions/moyennes semestrielles)
├── ConseilDeClasse/         🆕 À créer
├── Discipline/              🆕 À créer
├── Comptabilite/            🔄 Existant (à adapter frais secondaire)
├── Paie/                    🔄 Existant (réutilisable)
├── Documents/               🔄 Existant (à adapter bulletins semestriels)
└── PortailParent/           🆕 À créer
```

### Security & Compliance

**Authentification** :
- Laravel Sanctum pour tokens API ✅
- Spatie Permission pour RBAC ✅
- Bcrypt pour mots de passe

**Protection données** :
- Isolation stricte par tenant ✅
- HTTPS obligatoire en production
- Protection spéciale données de mineurs (élèves < 18 ans)
- Backup quotidien automatique
- Logs d'audit pour actions sensibles

**Sécurité applicative** :
- Validation stricte inputs (Form Requests)
- Protection CSRF, XSS, SQL Injection (Laravel native)
- Rate limiting sur API

### Hosting & Infrastructure

**Production** :
- Hébergement : VPS ou Cloud (DigitalOcean, AWS, Hetzner)
- Serveur Web : Nginx
- PHP-FPM 8.3+, MySQL 8.0+
- Queue Worker (Supervisor) pour génération PDF asynchrone
- Redis pour cache et sessions

---

## Constraints & Assumptions

### Constraints

**Budget** :
- Développement : Budget limité, focus sur MVP avec ressources internes
- Infrastructure : Hébergement cloud abordable (VPS ~$50-100/mois)
- Maintenance : Support technique assuré par l'équipe de développement

**Timeline** :
- MVP : 4-6 mois de développement pour les 12 modules core
- Phase 2 : 6-12 mois après lancement MVP
- Déploiement progressif : Module par module si nécessaire

**Ressources** :
- Équipe de développement : 2-3 développeurs
- Tests utilisateurs : 2 établissements pilotes (1 collège + 1 lycée)
- Formation : Sessions en français pour les utilisateurs finaux

**Techniques** :
- Bande passante limitée au Niger : Optimisation impérative
- Connectivité parfois instable : Mode dégradé gracieux
- Équipements variés : Support navigateurs modernes
- Priorité mobile : Parents et élèves utilisent principalement le smartphone

**Légales & Réglementaires** :
- Conformité système éducatif nigérien (programmes, coefficients, examens)
- Protection renforcée des données de mineurs
- Archivage documents officiels : Minimum 10 ans
- Conformité Ministère de l'Éducation Nationale du Niger

### Key Assumptions

**Adoption** :
- Les établissements sont prêts à adopter le numérique pour la gestion scolaire
- Les enseignants ont un niveau minimum de compétence informatique (ou peuvent être formés rapidement)
- Les parents ont accès à un smartphone avec Internet (même basique)
- L'Association de Parents d'Élèves (APE) soutiendra le déploiement

**Technique** :
- L'infrastructure Laravel/Next.js existante est réutilisable avec adaptation
- Les modules existants (Structure Académique, EDT, Présences, etc.) peuvent être adaptés pour le secondaire
- Le multi-tenant `stancl/tenancy` peut supporter 20+ établissements
- La génération de PDF (bulletins en masse) est performante avec DomPDF + queues

**Business** :
- Le marché des collèges et lycées au Niger est significativement plus large que le supérieur
- Les établissements privés ont la capacité et la volonté de payer un abonnement
- Le bouche-à-oreille entre directeurs d'établissements sera un levier d'acquisition fort

**Données** :
- Les établissements peuvent fournir les listes d'élèves en format exploitable (Excel minimum)
- Les coefficients par matière sont standardisés au niveau national (ou facilement configurables)

---

## Risks & Open Questions

### Key Risks

- **Résistance au changement** : Enseignants habitués aux cahiers de notes refusent la saisie en ligne. *Mitigation* : Formation intensive, démonstration des gains de temps, champions internes.

- **Performance sur bande passante limitée** : Application lente sur connexions 3G. *Mitigation* : Optimisation agressive, lazy loading, compression, pagination stricte.

- **Adoption parentale faible** : Parents peu familiers avec la technologie n'utilisent pas le portail. *Mitigation* : Interface ultra-simple, tutoriels vidéo, sensibilisation via APE.

- **Scope creep** : Demandes continues de nouvelles fonctionnalités retardant le MVP. *Mitigation* : Scope strict, roadmap Phase 2 claire, processus de priorisation.

- **Qualité des bulletins** : Erreurs dans les calculs de moyennes ou les classements entamant la confiance. *Mitigation* : Tests exhaustifs, validation manuelle lors du premier semestre pilote.

- **Dépendance à la connectivité** : Coupures Internet ou électricité bloquant l'utilisation. *Mitigation* : Mode dégradé, saisie différée possible, PWA offline (Phase 2).

### Open Questions

**Produit** :
- Quels sont les coefficients exacts par matière pour chaque classe et série au Niger ?
- Le système de mentions (Tableau d'honneur, Encouragements) est-il standardisé ou propre à chaque établissement ?
- Les bulletins doivent-ils inclure le rang sur l'ensemble du niveau (ex: rang sur tous les Tle D) ou uniquement dans la classe ?
- Faut-il gérer les classes multigrades (cas de petits collèges ruraux) ?

**Technique** :
- DomPDF est-il suffisant pour la génération en masse de bulletins (60+ par classe, 10+ classes) ?
- Quel service d'hébergement offre le meilleur rapport qualité/prix avec bonne latence vers le Niger ?
- Faut-il implémenter Redis dès le MVP pour le cache ?

**Business** :
- Quel pricing pour les collèges/lycées (par élève, par établissement, forfait) ?
- Comment approcher les établissements publics (vs privés) ?
- Quel est le rôle du Ministère dans l'adoption de ce type d'outil ?

### Areas Needing Further Research

- Étude du marché des établissements secondaires au Niger (nombre, types, tailles)
- Analyse de la concurrence locale (autres solutions de gestion scolaire)
- Tests de performance sur connexions réelles au Niger (3G, 4G)
- Investigation des APIs paiement mobile (Orange Money Niger, Moov Niger)
- Entretiens avec proviseurs, censeurs, enseignants, parents pour valider les besoins

---

## Next Steps

### Immediate Actions

1. **Valider ce Project Brief** avec les parties prenantes — revue complète, validation du scope MVP, confirmation des hypothèses
2. **Adapter le PRD** — Réviser les spécifications détaillées module par module pour le secondaire
3. **Identifier les établissements pilotes** — 1 collège + 1 lycée, motivés et collaboratifs
4. **Adapter les modules existants** — Refactoring Structure Académique, Notes, Documents pour le système semestriel
5. **Développer les nouveaux modules** — Conseil de Classe, Discipline, Portail Parent

### Handoff to Product Manager

Ce Project Brief fournit le contexte complet pour **Gestion Scolaire**, système de gestion pour l'enseignement secondaire au Niger (collèges et lycées).

**Prochaine étape recommandée** :
- Réviser le **PRD** module par module pour l'adapter au secondaire
- Concevoir les **maquettes UI** adaptées (bulletins semestriels, portail parent, discipline)
- Établir le **plan de développement** avec priorisation et timeline

**Contexte clé** :
- Architecture technique solide déjà en place (Laravel modulaire + Next.js + multi-tenant)
- Plusieurs modules existants réutilisables avec adaptation
- MVP : 12 modules core incluant le Portail Parent (essentiel pour le secondaire)
- Cible : 2 établissements pilotes (1 collège + 1 lycée)
- Différenciateur principal : Bulletins automatiques + portail parent en temps réel

---

**Project Brief Complet - Gestion Scolaire (Secondaire) v2.0**

*Dernière mise à jour : 2026-03-16*
