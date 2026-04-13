# Introduction et Vue d'Ensemble

[← Retour à l'index](./index.md)

---

## 1.1 Objectif du Document

Ce document définit l'architecture d'intégration de **12 modules métier** dans le système multi-tenant existant. L'objectif est de transformer la base d'authentification actuelle (module **UsersGuard** uniquement) en une **plateforme complète de gestion scolaire** pour les établissements d'enseignement secondaire au Niger (collèges et lycées).

## 1.2 Relation avec la Documentation Existante

Ce document complète la documentation brownfield existante (`docs/brownfield-architecture.md`) en définissant précisément comment les nouveaux modules s'intégreront avec :

- L'architecture multi-tenant déjà opérationnelle (stancl/tenancy)
- Le système d'authentification et permissions (UsersGuard + Spatie)
- Les patterns établis (API Resources, Form Requests, migrations tenant)
- La structure modulaire backend/frontend (Laravel Modules + Next.js)

## 1.3 Modules à Intégrer

**12 Modules répartis en 3 Phases** :

### Phase 1 : MVP Core (4-6 mois)

| # | Module | Priorité | Dépendances |
|---|--------|----------|-------------|
| 1 | Structure Académique | 🔴 Critique | UsersGuard |
| 2 | Inscriptions | 🔴 Critique | Structure Académique |
| 3 | Notes & Évaluations | 🔴 Critique | Inscriptions, Structure |
| 4 | Conseil de Classe | 🔴 Critique | Notes, Structure |
| 5 | Documents Officiels | 🔴 Critique | Notes, Inscriptions, Conseil |

### Phase 2 : Vie Scolaire & Opérations (3 mois)

| # | Module | Priorité | Dépendances |
|---|--------|----------|-------------|
| 6 | Présences & Absences | 🟠 Haute | Inscriptions, EDT |
| 7 | Discipline | 🟠 Haute | Inscriptions |
| 8 | Emplois du Temps | 🟠 Haute | Structure, Inscriptions |
| 9 | Portail Parent | 🟠 Haute | Inscriptions, Notes, Présences, Discipline |

### Phase 3 : Gestion Financière (2 mois)

| # | Module | Priorité | Dépendances |
|---|--------|----------|-------------|
| 10 | Comptabilité & Finances | 🟡 Moyenne | Inscriptions |
| 11 | Paie Personnel | 🟡 Moyenne | UsersGuard |
| 12 | Statistiques & Reporting | 🟡 Moyenne | Tous modules |

### Diagramme de Dépendances

```
UsersGuard (✅ Existant - Base d'authentification)
    ↓
Structure Académique (Fondation - DOIT être développé en premier)
    ↓
Inscriptions (Dépend de Structure + crée les comptes Parents)
    ↓
├─→ Notes & Évaluations
├─→ Emplois du Temps
├─→ Présences & Absences (dépend aussi de EDT)
├─→ Discipline
├─→ Comptabilité & Finances
└─→ Paie Personnel
    ↓
Conseil de Classe (Dépend de Notes)
    ↓
Documents Officiels (Dépend de Notes + Conseil + Comptabilité)
    ↓
Portail Parent (Agrège Notes + Présences + Discipline + Finances)
```

## 1.4 Contexte Business

### Problème Résolu

Les collèges et lycées au Niger gèrent actuellement leurs opérations manuellement (cahiers de notes, registres papier, feuilles d'appel, fichiers Excel), entraînant des pertes de temps considérables, des erreurs fréquentes dans les bulletins, et une communication quasi inexistante avec les parents d'élèves.

### Proposition de Valeur

- **Numérisation complète** du cycle scolaire (inscription → notes → bulletins → finances)
- **Bulletins semestriels automatiques** : Génération en < 5 minutes pour une classe de 60 élèves
- **Portail Parent intégré** : Suivi en temps réel des notes, absences, discipline
- **Gestion de la discipline** : Historique centralisé, notifications parents automatiques
- **Conseil de classe outillé** : Données consolidées pour décisions de passage/redoublement
- **Gestion financière intégrée** : Frais scolaires, APE, cantine, paie personnel
- **Architecture multi-tenant** : Plusieurs établissements sur une seule instance

### Fonctionnalités Clés par Module

**Module 1 : Structure Académique**
- Années scolaires, Semestres (S1, S2)
- Cycles (Collège 6e-3e, Lycée 2nde-Tle)
- Classes (6e A, 5e B, Tle C1, etc.)
- Séries lycée (A Littéraire, C Maths-Physique, D Sciences Naturelles)
- Matières avec coefficients par classe/série
- Affectations enseignants ↔ matière ↔ classe(s)
- Professeur principal par classe
- Barèmes configurables (mentions, seuils)

**Module 2 : Inscriptions**
- Inscription élèves avec données personnelles, photo, contacts d'urgence
- Création automatique du compte parent/tuteur lors de l'inscription
- Liaison Parent ↔ Élève(s) (un parent peut suivre plusieurs enfants)
- Affectation en classe par année scolaire
- Import en masse via CSV/Excel
- Gestion des statuts (Actif, Transféré, Exclu, Diplômé)
- Réinscription et passage en classe supérieure

**Module 3 : Notes & Évaluations**
- Types : Devoir surveillé, Interrogation, Composition semestrielle, TP
- Saisie des notes par enseignant (/20)
- Calcul automatique : moyenne par matière, moyenne générale avec coefficients, classement
- Appréciations par matière (enseignant) + appréciation générale (conseil de classe)
- Mentions : Tableau d'honneur, Encouragements, Félicitations, Avertissement

**Module 4 : Conseil de Classe**
- Récapitulatif de classe : toutes notes et moyennes d'un semestre
- Statistiques de classe : moyenne générale, taux de réussite, répartition par tranches
- Décisions : Passage, Redoublement, Exclusion (fin d'année)
- Procès-verbal automatique du conseil de classe
- Appréciations générales par le président du conseil

**Module 5 : Documents Officiels**
- Bulletins semestriels PDF (notes, moyennes, rang, appréciations, mentions)
- Bulletin annuel récapitulatif des 2 semestres
- Attestations (scolarité, inscription)
- Certificat de scolarité / Exeat
- Cartes scolaires avec photo
- Reçus de paiement, bulletins de paie

**Module 6 : Présences & Absences**
- Appel par séance : Présent, Absent, Retard, Excusé
- Justificatifs d'absence avec upload documents
- Consolidation : total absences par élève, par semestre, par matière
- Alertes parents automatiques en cas d'absence non justifiée
- Seuils d'alerte configurables

**Module 7 : Discipline**
- Types de sanctions : Avertissement verbal/écrit, Blâme, Exclusion temporaire/définitive
- Enregistrement des incidents avec rapporteur
- Historique disciplinaire par élève
- Notification automatique aux parents
- Conseil de discipline : convocations, PV

**Module 8 : Emplois du Temps**
- Création par classe (jour, heure, salle, enseignant, matière)
- Détection automatique des conflits (enseignant/salle occupé)
- Vues : par classe, par enseignant, par salle
- Consultation multi-rôles
- Export PDF

**Module 9 : Portail Parent**
- Tableau de bord par enfant (notes, absences, discipline)
- Notifications proactives (absences, sanctions, nouveaux bulletins)
- Consultation et téléchargement bulletins PDF
- Situation financière (frais payés, impayés)
- Multi-enfants dans un seul compte

**Module 10 : Comptabilité & Finances**
- Paramétrage frais : inscription, scolarité, APE, cantine, tenue, transport
- Facturation automatique par élève
- Enregistrement paiements + génération reçus PDF
- Paiements partiels et échéanciers
- Bourses et exonérations
- Tableau de bord financier

**Module 11 : Paie Personnel**
- Fiches personnel : type contrat (permanent, vacataire, contractuel)
- Calcul automatique de la paie
- Génération bulletins de paie PDF
- États mensuels (masse salariale)

**Module 12 : Statistiques & Reporting**
- Tableaux de bord direction
- Statistiques académiques (taux réussite, moyennes par classe)
- Rapports pour le Ministère de l'Éducation Nationale
- Analyse comparative entre classes

---

[Suivant : Architecture de Haut Niveau →](./high-level-architecture.md)
