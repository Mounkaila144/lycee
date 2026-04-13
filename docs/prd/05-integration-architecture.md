# 🏗️ Integration Architecture - Architecture d'Intégration

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Version** : v5
> **Date** : 2026-03-16
> **Type** : Documentation Transverse

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 2.0 | Refonte complète - 11 modules secondaire (vs 9 LMD), nouveaux flux | John (PM) & Winston (Architect) |
| 2026-01-07 | 1.0 | Création initiale - Architecture d'intégration inter-modules (LMD) | John (PM) & Winston (Architect) |

---

## 1. Vue d'Ensemble

Ce document définit l'**architecture d'intégration** entre les 11 modules métier du système de gestion scolaire pour l'enseignement secondaire et le système existant (UsersGuard).

---

## 2. Graphe de Dépendances Modules

### 2.1 Représentation Visuelle

```
┌─────────────────────────────────────────────────────────────┐
│                     SYSTÈME EXISTANT                         │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  UsersGuard (✅ Opérationnel)                       │    │
│  │  - Authentification multi-niveaux                    │    │
│  │  - Multi-tenancy (stancl/tenancy)                    │    │
│  │  - Permissions (Spatie)                              │    │
│  │  - API Sanctum                                       │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    PHASE 1 : MVP CORE                        │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  1. Structure Académique (🔴 FONDATION)            │    │
│  │  → Années scolaires, Semestres, Cycles             │    │
│  │  → Classes (6e-Tle), Séries (A,C,D)               │    │
│  │  → Matières avec coefficients                      │    │
│  │  → Affectations enseignants ↔ matières ↔ classes   │    │
│  └─────────────────────────────────────────────────────┘    │
│                           │                                   │
│                           ↓                                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  2. Inscriptions (🔴 CRITIQUE)                     │    │
│  │  → Inscription élèves + liaison parent             │    │
│  │  → Affectation en classe                           │    │
│  │  → Import en masse CSV/Excel                        │    │
│  └─────────────────────────────────────────────────────┘    │
│                           │                                   │
│                           ↓                                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  3. Notes & Évaluations (🔴 CRITIQUE)              │    │
│  │  → Devoirs, Interrogations, Compositions           │    │
│  │  → Moyennes semestrielles avec coefficients         │    │
│  │  → Classement, Appréciations, Mentions             │    │
│  └─────────────────────────────────────────────────────┘    │
│                           │                                   │
│                           ↓                                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  4. Conseil de Classe (🔴 CRITIQUE)                │    │
│  │  → Tableau récapitulatif                            │    │
│  │  → Décisions (passage, redoublement)                │    │
│  │  → PV automatique                                   │    │
│  └─────────────────────────────────────────────────────┘    │
│                           │                                   │
│                           ↓                                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  5. Documents Officiels (🔴 CRITIQUE)              │    │
│  │  → Bulletins semestriels/annuels PDF                │    │
│  │  → Attestations, Certificats, Cartes scolaires     │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│            PHASE 2 : VIE SCOLAIRE & OPÉRATIONS             │
│                                                               │
│  ┌──────────────────┐  ┌──────────────────┐                 │
│  │ 6. Présences &   │  │ 7. Discipline    │                 │
│  │    Absences       │  │ → Sanctions      │                 │
│  │ → Appel séance    │  │ → Incidents      │                 │
│  │ → Alertes parents │  │ → Conseil disc.  │                 │
│  └──────────────────┘  └──────────────────┘                 │
│                                                               │
│  ┌──────────────────┐  ┌──────────────────┐                 │
│  │ 8. Emplois du    │  │ 9. Portail       │                 │
│  │    Temps          │  │    Parent        │                 │
│  │ → EDT par classe  │  │ → Notes, Abs.   │                 │
│  │ → Conflits        │  │ → Bulletins      │                 │
│  └──────────────────┘  └──────────────────┘                 │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              PHASE 3 : GESTION FINANCIÈRE                   │
│                                                               │
│  ┌──────────────────┐  ┌──────────────────┐                 │
│  │ 10. Comptabilité │  │ 11. Paie         │                 │
│  │  & Finances      │  │  Personnel       │                 │
│  │ → Frais scolarité│  │ → Salaires       │                 │
│  │ → Paiements      │  │ → Bulletins paie │                 │
│  └──────────────────┘  └──────────────────┘                 │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Matrice de Dépendances

| Module | Dépend de | Est requis par | Criticité |
|--------|-----------|----------------|-----------|
| **UsersGuard** | - | Tous les modules | 🔴 Existant |
| **Structure Académique** | UsersGuard | Tous sauf UsersGuard | 🔴 Fondation |
| **Inscriptions** | Structure | Notes, EDT, Présences, Discipline, Compta, Portail Parent | 🔴 Critique |
| **Notes & Évaluations** | Structure, Inscriptions | Conseil de Classe, Documents, Portail Parent | 🔴 Critique |
| **Conseil de Classe** | Notes, Inscriptions | Documents | 🔴 Critique |
| **Documents Officiels** | Notes, Conseil de Classe, Comptabilité | Portail Parent | 🔴 Critique |
| **Présences & Absences** | Structure, Inscriptions, EDT | Portail Parent, Conseil de Classe | 🟠 Haute |
| **Discipline** | Inscriptions | Portail Parent, Conseil de Classe | 🟠 Haute |
| **Emplois du Temps** | Structure, Inscriptions | Présences | 🟠 Haute |
| **Portail Parent** | Inscriptions, Notes, Documents, Présences, Discipline, Compta | - | 🟠 Haute |
| **Comptabilité & Finances** | Inscriptions | Documents, Portail Parent | 🟡 Moyenne |
| **Paie Personnel** | UsersGuard | - | 🟡 Moyenne |

---

## 3. Flux de Données Inter-Modules

### 3.1 Flux Académique Principal

```
[1. Structure Académique]
    ↓ (Classes, Matières, Coefficients, Enseignants)
[2. Inscriptions]
    ↓ (Élèves inscrits avec classe + liaison parent)
[3. Notes & Évaluations]
    ↓ (Notes, moyennes, classement, appréciations)
[4. Conseil de Classe]
    ↓ (Décisions : passage, redoublement, mentions)
[5. Documents Officiels]
    ↓ (Bulletins semestriels/annuels PDF)
```

**Exemple Concret** :
1. Admin crée la classe "Tle D1" avec matières et coefficients dans **Structure**
2. Élève Moussa s'inscrit en "Tle D1", son père est lié automatiquement dans **Inscriptions**
3. Enseignant saisit note de Moussa (15/20 en Maths, coeff 5) dans **Notes**
4. Le conseil de classe décide "Admis au Bac" dans **Conseil de Classe**
5. Le bulletin semestriel est généré dans **Documents**
6. Le père de Moussa consulte le bulletin sur le **Portail Parent**

### 3.2 Flux Vie Scolaire

```
[Inscriptions + Structure]
    ↓ (Élèves, Classes, Enseignants)
[Emplois du Temps]
    ↓ (Séances planifiées)
[Présences & Absences]
    ↓ (Appel, justificatifs, alertes parents)
[Discipline]
    ↓ (Sanctions, historique, notification parents)
[Portail Parent]
    ↓ (Vue consolidée : notes, absences, discipline)
```

### 3.3 Flux Financier

```
[Inscriptions]
    ↓ (Élèves inscrits)
[Comptabilité & Finances]
    ↓ (Frais, paiements, reçus)
[Documents Officiels]
    ↓ (Reçus de paiement PDF)

[UsersGuard]
    ↓ (Personnel : enseignants, administratifs)
[Paie Personnel]
    ↓ (Salaires, bulletins de paie)
```

---

## 4. Gestion des Permissions Inter-Modules

### 4.1 Rôles Utilisateurs

| Rôle | Modules Accessibles | Permissions Typiques |
|------|---------------------|---------------------|
| **SuperAdmin** | Tous (lecture) | Gestion tenants, monitoring global |
| **Admin / Directeur** | Tous (CRUD) | Gestion complète établissement |
| **Censeur** | Notes, Présences, Discipline, EDT | Gestion vie scolaire |
| **Surveillant Général** | Présences, Discipline | Absences, sanctions |
| **Enseignant** | Notes (écriture), Présences (écriture), EDT (lecture) | Saisie notes/présences matières assignées |
| **Comptable** | Comptabilité, Paie | Gestion financière complète |
| **Élève** | Notes (lecture), Documents (lecture), EDT (lecture) | Consultation uniquement (ses données) |
| **Parent** | Portail Parent (lecture) | Consultation enfants uniquement |

### 4.2 Matrice de Permissions

| Module | Admin | Censeur | Surv.Gén | Enseignant | Comptable | Élève | Parent |
|--------|-------|---------|----------|-----------|-----------|-------|--------|
| **Structure** | CRUD | Read | - | Read | - | - | - |
| **Inscriptions** | CRUD | Read | Read | Read | - | Read(self) | Read(enfants) |
| **Notes** | CRUD | Read | - | CRUD(matières) | - | Read(self) | Read(enfants) |
| **Conseil Classe** | CRUD | Read | Read | Read | - | - | - |
| **Documents** | CRUD | Read | Read | Read | Read | Read(self) | Read(enfants) |
| **Présences** | Read | CRUD | CRUD | CRUD(matières) | - | Read(self) | Read(enfants) |
| **Discipline** | CRUD | CRUD | CRUD | Read | - | Read(self) | Read(enfants) |
| **EDT** | CRUD | Read | Read | Read | - | Read(self) | Read(enfants) |
| **Portail Parent** | - | - | - | - | - | - | Read(enfants) |
| **Comptabilité** | CRUD | - | - | - | CRUD | Read(self) | Read(enfants) |
| **Paie** | CRUD | - | - | - | CRUD | - | - |

---

## 5. Événements et Notifications Inter-Modules

| Événement | Émis par | Écouté par | Action |
|-----------|----------|------------|--------|
| `StudentEnrolled` | Inscriptions | Notes, Présences, Compta, Portail Parent | Créer enregistrements associés |
| `GradesSubmitted` | Notes | Conseil de Classe, Documents, Portail Parent | Mettre à jour moyennes |
| `ClassCouncilDecided` | Conseil de Classe | Documents, Inscriptions | Générer bulletins, mettre à jour statut |
| `AbsenceRecorded` | Présences | Portail Parent | Notifier parent |
| `DisciplineActionTaken` | Discipline | Portail Parent | Notifier parent |
| `PaymentReceived` | Comptabilité | Documents, Portail Parent | Générer reçu, mettre à jour solde |
| `BulletinGenerated` | Documents | Portail Parent | Bulletin disponible en ligne |

---

## 6. Séquence de Déploiement

### 6.1 Ordre de Déploiement Strict

**Phase 1 (MVP Core)** :
1. Structure Académique
2. Inscriptions
3. Notes & Évaluations
4. Conseil de Classe
5. Documents Officiels

**Phase 2 (Vie Scolaire)** :
6. Emplois du Temps
7. Présences & Absences
8. Discipline
9. Portail Parent

**Phase 3 (Finances)** :
10. Comptabilité & Finances
11. Paie Personnel

---

## 7. Documents Connexes

- **[Overview](./00-overview.md)** : Vision globale du système
- **[Technical Constraints](./03-technical-constraints.md)** : Contraintes techniques brownfield
- **[Roadmap](./roadmap.md)** : Planning déploiement par phases
- **[Architecture Brownfield](../brownfield-architecture.md)** : Documentation technique existante

---

**Maintenu par** : John (PM Agent) & Winston (Architect Agent)
**Dernière mise à jour** : 2026-03-16
