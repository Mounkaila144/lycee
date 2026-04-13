# PRD - Module Portail Parent

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Module** : Portail Parent (Parent Portal)
> **Version** : 1.0
> **Date** : 2026-03-16
> **Phase** : Phase 2 - Vie Scolaire & Operations
> **Priorite** : HAUTE 🟠

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 1.0 | Creation initiale du PRD Module Portail Parent | John (PM) |

---

## 1. Goals and Background Context

### 1.1 Goals

- **Offrir aux parents un suivi en temps reel de la scolarite de leurs enfants** : Notes, absences, discipline, bulletins et finances accessibles depuis un smartphone
- **Reduire les deplacements des parents a l'etablissement** : Toutes les informations essentielles consultables en ligne
- **Ameliorer la communication ecole-famille** : Notifications automatiques pour les evenements importants (absences, sanctions, nouveaux bulletins)
- **Supporter le multi-enfants** : Un seul compte parent pour suivre plusieurs enfants, y compris dans des etablissements differents
- **Garantir la securite des donnees** : Acces en lecture seule, strictement limite aux donnees des enfants du parent connecte
- **Faciliter le suivi financier** : Transparence totale sur les frais, paiements et impayes
- **Favoriser la detection precoce du decrochage** : Indicateurs visuels d'evolution des moyennes et alertes sur les absences repetees

### 1.2 Background Context

Le **Module Portail Parent** est un composant strategique de la plateforme Gestion Scolaire. Il constitue l'interface principale entre l'etablissement et les familles, remplacant les bulletins papier, les convocations transmises par l'eleve (souvent perdues), et les deplacements physiques des parents.

Ce module s'inscrit dans la **Phase 2 - Vie Scolaire & Operations** car il depend de plusieurs modules Phase 1 :
1. **Structure Academique** : Classes, matieres, coefficients (Phase 1)
2. **Inscriptions** : Liaison parent-eleve, creation automatique du compte parent (Phase 1)
3. **Notes & Evaluations** : Notes detaillees, moyennes, classements (Phase 1)
4. **Documents Officiels** : Bulletins semestriels PDF (Phase 1)

Et de modules Phase 2 complementaires :
5. **Presences & Absences** : Historique des absences, justificatifs (Phase 2)
6. **Discipline** : Sanctions, incidents (Phase 2)
7. **Comptabilite & Finances** : Frais, paiements, impayes (Phase 3 - integration progressive)

**Contexte Niger** : Les parents d'eleves au Niger utilisent principalement le smartphone pour acceder a Internet. L'interface doit etre **mobile-first**, optimisee pour les connexions 3G/4G variables, et extremement simple d'utilisation. L'Association de Parents d'Eleves (APE) jouera un role cle dans la sensibilisation et l'adoption du portail.

**Pain points resolus** :
- Les parents ne connaissent les resultats de leurs enfants qu'a la distribution des bulletins papier (1 fois par semestre)
- Les absences et sanctions ne sont communiquees que par convocations papier transmises via l'eleve
- Les parents doivent se deplacer physiquement pour obtenir des informations sur la situation financiere
- Aucune vision consolidee pour les parents ayant plusieurs enfants

---

## 2. Requirements

### 2.1 Functional Requirements (FR)

#### 2.1.1 Tableau de Bord Parent

- **FR1** : Le systeme doit afficher un tableau de bord personnalise pour chaque parent connecte, avec une vue d'ensemble par enfant
- **FR2** : Pour chaque enfant, le tableau de bord affiche : photo (si disponible), nom complet, classe actuelle, annee scolaire en cours
- **FR3** : Le tableau de bord affiche les dernieres notes (5 dernieres) avec matiere, type d'evaluation et note obtenue
- **FR4** : Le tableau de bord affiche les absences recentes (5 dernieres) avec date, matiere et statut (justifiee/non justifiee)
- **FR5** : Le tableau de bord affiche les prochains paiements dus ou en retard avec montant et echeance
- **FR6** : Le tableau de bord affiche des indicateurs visuels :
  - Evolution de la moyenne generale (fleche hausse/baisse par rapport a la derniere periode)
  - Nombre total d'absences du semestre (justifiees et non justifiees)
  - Statut financier (a jour, en retard, impayes)
- **FR7** : Les alertes recentes (absences non justifiees, sanctions, nouveaux bulletins) sont affichees en surbrillance en haut du tableau de bord
- **FR8** : Si le parent a plusieurs enfants, un selecteur d'enfant (onglets ou liste deroulante) permet de basculer entre les vues

#### 2.1.2 Notes Detaillees

- **FR9** : Le systeme doit afficher toutes les notes de l'enfant pour le semestre en cours, groupees par matiere
- **FR10** : Pour chaque matiere, afficher : nom de la matiere, coefficient, liste des notes (devoir, interrogation, composition) avec date et note /20
- **FR11** : Pour chaque matiere, afficher la moyenne calculee de la matiere et le rang de l'eleve dans la classe pour cette matiere
- **FR12** : Afficher la moyenne generale semestrielle et le rang global de l'eleve dans la classe
- **FR13** : Afficher un graphique d'evolution des moyennes generales (ligne temporelle par evaluation/mois)
- **FR14** : Permettre la comparaison Semestre 1 vs Semestre 2 : moyennes par matiere cote a cote
- **FR15** : Le parent peut filtrer les notes par : semestre, matiere, type d'evaluation
- **FR16** : Afficher les statistiques de la classe pour contexte : moyenne de la classe, note la plus haute, note la plus basse (sans nommer les eleves)

#### 2.1.3 Absences

- **FR17** : Le systeme doit afficher l'historique complet des absences de l'enfant pour le semestre en cours
- **FR18** : Chaque absence affiche : date, heure (creneau), matiere, enseignant ayant fait l'appel, statut (justifiee / non justifiee / en attente de justification)
- **FR19** : Afficher les totaux : nombre d'absences justifiees, nombre d'absences non justifiees, total heures d'absence
- **FR20** : Le parent doit pouvoir soumettre un justificatif pour une absence non justifiee : upload de document (photo, PDF) avec un commentaire
- **FR21** : Le justificatif soumis passe en statut "en attente de validation" jusqu'a approbation par le surveillant general
- **FR22** : Le systeme envoie une alerte en temps reel (notification in-app + email) lorsqu'une absence non justifiee est enregistree
- **FR23** : Le parent peut filtrer les absences par : semestre, statut (toutes / justifiees / non justifiees), matiere

#### 2.1.4 Discipline

- **FR24** : Le systeme doit afficher l'historique complet des sanctions disciplinaires de l'enfant
- **FR25** : Chaque sanction affiche : date, type de sanction (avertissement verbal, avertissement ecrit, blame, exclusion temporaire, exclusion definitive), description de l'incident, duree (si exclusion temporaire)
- **FR26** : Le systeme envoie une notification en temps reel (in-app + email) lorsqu'une nouvelle sanction est enregistree
- **FR27** : Le parent doit pouvoir demander un rendez-vous avec la direction via un formulaire : motif, dates/heures preferees
- **FR28** : Le systeme affiche le nombre total de sanctions par type pour le semestre en cours

#### 2.1.5 Bulletins

- **FR29** : Le systeme doit afficher la liste des bulletins disponibles pour l'enfant : semestre, annee scolaire, date de disponibilite
- **FR30** : Le parent peut consulter un bulletin en ligne (vue web responsive avec toutes les informations du bulletin)
- **FR31** : Le parent peut telecharger le bulletin au format PDF
- **FR32** : L'historique des bulletins des semestres et annees precedents est accessible
- **FR33** : Le systeme envoie une notification (in-app + email) lorsqu'un nouveau bulletin est disponible
- **FR34** : Le bulletin en ligne affiche : notes par matiere, moyennes, rang, appreciations des enseignants, appreciation generale du conseil de classe, mentions, decision

#### 2.1.6 Finances

- **FR35** : Le systeme doit afficher l'etat financier de chaque enfant : montant total du, montant total paye, solde restant
- **FR36** : Afficher le detail par type de frais : inscription, scolarite, APE, cantine, tenue, transport, etc. avec montant du et montant paye pour chacun
- **FR37** : Afficher l'historique des paiements : date, montant, mode de paiement, type de frais
- **FR38** : Le parent peut telecharger les recus de paiement au format PDF
- **FR39** : Si un echeancier est defini, afficher les echeances a venir avec dates et montants
- **FR40** : Le systeme affiche un indicateur visuel du statut financier : "A jour" (vert), "Echeance proche" (orange), "En retard" (rouge)

#### 2.1.7 Multi-Enfants

- **FR41** : Un compte parent unique permet de suivre plusieurs enfants inscrits dans le systeme
- **FR42** : Les enfants peuvent etre dans le meme etablissement ou dans des etablissements differents (tenants differents)
- **FR43** : Le tableau de bord offre une vue consolidee : resume de tous les enfants sur une seule page
- **FR44** : Le parent peut basculer vers la vue detaillee d'un enfant specifique
- **FR45** : La liaison parent-enfant est creee automatiquement lors de l'inscription de l'enfant (le compte parent est cree ou mis a jour)
- **FR46** : Un parent ne peut voir que les donnees de ses propres enfants (verification stricte a chaque requete)

#### 2.1.8 Notifications

- **FR47** : Le systeme doit envoyer des notifications email pour les evenements suivants :
  - Absence non justifiee enregistree
  - Nouvelle sanction disciplinaire
  - Nouveau bulletin disponible
  - Relance de paiement (echeance depassee)
  - Nouvelle note saisie (optionnel, configurable par le parent)
- **FR48** : Le parent peut configurer ses preferences de notification : activer/desactiver chaque type de notification
- **FR49** : Un centre de notifications dans l'interface affiche toutes les notifications recentes avec statut (lue / non lue)
- **FR50** : Les notifications non lues sont indiquees par un badge numerique sur l'icone de notification
- **FR51** : Le parent peut marquer une notification comme lue ou marquer toutes comme lues

#### 2.1.9 Acces et Securite

- **FR52** : Le compte parent est cree automatiquement lors de l'inscription du premier enfant, avec generation d'un mot de passe initial
- **FR53** : Le mot de passe initial est envoye par email (et/ou SMS en Phase 2)
- **FR54** : Le parent doit changer son mot de passe lors de la premiere connexion
- **FR55** : Le portail parent est en lecture seule : le parent ne peut PAS modifier les notes, absences, sanctions ou donnees financieres
- **FR56** : Les seules actions d'ecriture autorisees sont : soumettre un justificatif d'absence, demander un rendez-vous, configurer ses notifications, modifier son profil (email, telephone, mot de passe)
- **FR57** : Le systeme verifie a chaque requete API que le parent accede uniquement aux donnees de ses propres enfants (guard parent-enfant)
- **FR58** : Le systeme journalise les connexions et actions du parent pour audit de securite

---

### 2.2 Non-Functional Requirements (NFR)

- **NFR1** : Le tableau de bord parent doit se charger en moins de 2 secondes sur une connexion 3G (performance mobile-first)
- **NFR2** : L'interface doit etre entierement fonctionnelle sur les ecrans de smartphones (largeur >= 320px) avec une experience optimale sur mobile
- **NFR3** : Le systeme doit supporter 500+ parents connectes simultanement par tenant sans degradation de performance
- **NFR4** : Les notifications email doivent etre envoyees dans les 5 minutes suivant l'evenement declencheur
- **NFR5** : Le telechargement d'un bulletin PDF doit se faire en moins de 5 secondes
- **NFR6** : L'upload de justificatif d'absence doit supporter les formats : JPEG, PNG, PDF, avec une taille maximale de 5 Mo
- **NFR7** : Le portail doit fonctionner sur les navigateurs modernes : Chrome (Android), Safari (iOS), Firefox, Edge
- **NFR8** : Les donnees affichees doivent etre en temps reel (ou quasi temps reel avec un cache de maximum 5 minutes)
- **NFR9** : L'interface doit etre disponible en francais (langue unique pour le contexte Niger)
- **NFR10** : Le systeme doit respecter les standards d'accessibilite WCAG 2.1 niveau AA

---

## 3. User Interface Design Goals

### 3.1 Overall UX Vision

L'interface du Portail Parent doit etre **mobile-first, ultra-simple et rassurante**. Les parents d'eleves au Niger utilisent principalement des smartphones avec des connexions variables. L'experience doit etre fluide, avec un minimum de clics pour acceder a l'information souhaitee.

**Principes cles** :
- **Mobile-first** : Conception pour smartphone en priorite, puis adaptation desktop/tablette
- **Simplicite radicale** : Interface epuree, pas de surcharge d'informations, hierarchie visuelle claire
- **Indicateurs visuels** : Codes couleur pour statuts (vert = bien, orange = attention, rouge = alerte)
- **Navigation simple** : Barre de navigation fixe en bas (mobile) avec 5 onglets maximum
- **Chargement rapide** : Lazy loading, pagination, compression des images
- **Offline-friendly** : Affichage du dernier etat connu si la connexion est perdue (Phase 2 - PWA)

### 3.2 Key Interaction Paradigms

- **Navigation par onglets** : Barre inferieure fixe avec onglets (Accueil, Notes, Absences, Bulletins, Plus)
- **Selecteur d'enfant** : En haut de chaque page, dropdown ou onglets horizontaux pour choisir l'enfant
- **Pull-to-refresh** : Geste de rafraichissement sur mobile pour mettre a jour les donnees
- **Cartes informatives** : Chaque bloc d'information dans une carte (card) avec resume et lien "Voir plus"
- **Notifications badge** : Icone cloche avec badge numerique pour les notifications non lues
- **Bottom sheets** : Formulaires et details affiches en bottom sheet sur mobile (justificatif, rendez-vous)

### 3.3 Core Screens and Views

#### 3.3.1 Ecran : Tableau de Bord Parent (Accueil)

**Vue mobile (prioritaire)** :
```
┌─────────────────────────────────┐
│  🔔(3)           Gestion Scolaire│
├─────────────────────────────────┤
│  [Photo] Mamadou IBRAHIM        │
│  Classe : 3e A - S1 2025-2026   │
│  ▼ Changer d'enfant             │
├─────────────────────────────────┤
│  ⚠️ ALERTES (2)                  │
│  ┌─────────────────────────────┐│
│  │ Absence non justifiee 12/03 ││
│  │ Nouvelle note Maths: 14/20  ││
│  └─────────────────────────────┘│
├─────────────────────────────────┤
│  📊 RESUME                      │
│  ┌──────┐ ┌──────┐ ┌──────┐   │
│  │Moy.  │ │Abs.  │ │Fin.  │   │
│  │12.5  │ │ 3    │ │A jour│   │
│  │ ↑0.5 │ │ 1 NJ │ │  ✅  │   │
│  └──────┘ └──────┘ └──────┘   │
├─────────────────────────────────┤
│  📝 DERNIERES NOTES             │
│  Maths - Devoir: 14/20         │
│  Francais - Interro: 11/20     │
│  Physique - Compo: 13/20       │
│  > Voir toutes les notes        │
├─────────────────────────────────┤
│  📅 ABSENCES RECENTES           │
│  12/03 - SVT - Non justifiee   │
│  08/03 - EPS - Justifiee       │
│  > Voir toutes les absences     │
├─────────────────────────────────┤
│ 🏠  📝  📅  📄  ⋯              │
│Accueil Notes Abs. Bull. Plus    │
└─────────────────────────────────┘
```

- Selecteur d'enfant en haut (si multi-enfants)
- Bloc alertes en surbrillance (fond rouge/orange clair)
- 3 indicateurs resumes (moyenne, absences, finances)
- Dernieres notes (5 max) avec lien "Voir toutes"
- Dernieres absences (3 max) avec lien "Voir toutes"
- Barre de navigation fixe en bas avec 5 onglets

#### 3.3.2 Ecran : Vue Consolidee Multi-Enfants

**Affiche quand le parent a plusieurs enfants** :
```
┌─────────────────────────────────┐
│  🔔(5)           Gestion Scolaire│
├─────────────────────────────────┤
│  MES ENFANTS                    │
│  ┌─────────────────────────────┐│
│  │ [Photo] Mamadou IBRAHIM     ││
│  │ 3e A - Lycee Kassai         ││
│  │ Moy: 12.5 ↑ | Abs: 3 | ✅  ││
│  │              > Voir detail   ││
│  └─────────────────────────────┘│
│  ┌─────────────────────────────┐│
│  │ [Photo] Aisha IBRAHIM       ││
│  │ 6e B - CEG Niamey Centre    ││
│  │ Moy: 14.2 ↑ | Abs: 1 | ✅  ││
│  │              > Voir detail   ││
│  └─────────────────────────────┘│
│  ┌─────────────────────────────┐│
│  │ [Photo] Ousmane IBRAHIM     ││
│  │ Tle D1 - Lycee Kassai       ││
│  │ Moy: 10.8 ↓ | Abs: 5 | ⚠️  ││
│  │              > Voir detail   ││
│  └─────────────────────────────┘│
├─────────────────────────────────┤
│ 🏠  📝  📅  📄  ⋯              │
└─────────────────────────────────┘
```

- Carte resume pour chaque enfant
- Indicateurs cles : moyenne (avec tendance), absences, statut financier
- Clic sur "Voir detail" pour basculer vers le tableau de bord de l'enfant
- Code couleur pour attirer l'attention sur les situations critiques (orange/rouge)

#### 3.3.3 Ecran : Notes Detaillees

```
┌─────────────────────────────────┐
│  ← Notes    Mamadou IBRAHIM     │
├─────────────────────────────────┤
│  S1 2025-2026   ▼               │
├─────────────────────────────────┤
│  MOY. GENERALE : 12.50/20      │
│  Rang : 8e / 45 eleves         │
│  [======Graphique evolution====] │
├─────────────────────────────────┤
│  📐 Mathematiques (Coeff 4)     │
│  Moyenne: 14.00 | Rang: 5/45   │
│  ├─ Devoir 1 : 15/20 (10/10)   │
│  ├─ Interro 1 : 12/20 (08/10)  │
│  └─ Composition : 14/20 (12/10)│
│                                  │
│  📖 Francais (Coeff 3)          │
│  Moyenne: 11.50 | Rang: 15/45  │
│  ├─ Devoir 1 : 10/20           │
│  ├─ Interro 1 : 13/20          │
│  └─ Composition : 11/20        │
│                                  │
│  🔬 Physique-Chimie (Coeff 3)   │
│  Moyenne: 13.00 | Rang: 10/45  │
│  ├─ ...                         │
│                                  │
│  [Comparer S1 vs S2]            │
├─────────────────────────────────┤
│ 🏠  📝  📅  📄  ⋯              │
└─────────────────────────────────┘
```

- Filtre par semestre en haut
- Resume general (moyenne, rang) avec graphique
- Liste des matieres avec detail des notes
- Chaque matiere : coefficient, moyenne, rang, liste des evaluations
- Bouton de comparaison S1 vs S2

#### 3.3.4 Ecran : Historique des Absences

```
┌─────────────────────────────────┐
│  ← Absences  Mamadou IBRAHIM    │
├─────────────────────────────────┤
│  S1 2025-2026   ▼               │
├─────────────────────────────────┤
│  RESUME                         │
│  Total: 8h | Just.: 5h | NJ: 3h│
│  [====Barre de progression====] │
├─────────────────────────────────┤
│  📅 12/03/2026 - 10h-11h       │
│  SVT - M. Abdoulaye             │
│  ❌ Non justifiee               │
│  [Soumettre justificatif]       │
│                                  │
│  📅 08/03/2026 - 08h-09h       │
│  EPS - Mme Fatima               │
│  ✅ Justifiee                    │
│                                  │
│  📅 01/03/2026 - 14h-15h       │
│  Maths - M. Hamidou             │
│  🕐 En attente de validation    │
│                                  │
│  ...                             │
├─────────────────────────────────┤
│ 🏠  📝  📅  📄  ⋯              │
└─────────────────────────────────┘
```

- Resume en haut avec totaux
- Liste chronologique des absences (les plus recentes en premier)
- Code couleur par statut
- Bouton "Soumettre justificatif" pour les absences non justifiees
- Formulaire de soumission en bottom sheet (upload document + commentaire)

#### 3.3.5 Ecran : Historique Discipline

```
┌─────────────────────────────────┐
│  ← Discipline  Mamadou IBRAHIM  │
├─────────────────────────────────┤
│  RESUME S1 2025-2026            │
│  Avert. verbaux: 1              │
│  Avert. ecrits: 0              │
│  Blames: 0 | Exclusions: 0     │
├─────────────────────────────────┤
│  📋 15/02/2026                   │
│  Type: Avertissement verbal     │
│  Motif: Bavardage repete en     │
│  cours de Mathematiques         │
│  Rapporte par: M. Hamidou       │
│                                  │
│  (Aucune autre sanction)        │
├─────────────────────────────────┤
│  [Demander un rendez-vous]      │
├─────────────────────────────────┤
│ 🏠  📝  📅  📄  ⋯              │
└─────────────────────────────────┘
```

- Resume des sanctions par type
- Liste chronologique des sanctions
- Detail de chaque sanction (date, type, motif, rapporteur)
- Bouton "Demander un rendez-vous" avec formulaire en bottom sheet

#### 3.3.6 Ecran : Bulletins

```
┌─────────────────────────────────┐
│  ← Bulletins  Mamadou IBRAHIM   │
├─────────────────────────────────┤
│  BULLETINS DISPONIBLES          │
│                                  │
│  📄 Bulletin S1 2025-2026       │
│  Disponible depuis le 15/01/26  │
│  Moy: 12.50 | Rang: 8/45       │
│  [Consulter] [Telecharger PDF]  │
│                                  │
│  📄 Bulletin S2 2024-2025       │
│  Moy: 11.80 | Rang: 12/48      │
│  [Consulter] [Telecharger PDF]  │
│                                  │
│  📄 Bulletin S1 2024-2025       │
│  Moy: 11.20 | Rang: 15/48      │
│  [Consulter] [Telecharger PDF]  │
│                                  │
├─────────────────────────────────┤
│ 🏠  📝  📅  📄  ⋯              │
└─────────────────────────────────┘
```

- Liste des bulletins disponibles par ordre chronologique inverse
- Resume rapide : moyenne, rang
- Deux actions : consulter en ligne (vue web) ou telecharger PDF
- Historique complet des semestres precedents

#### 3.3.7 Ecran : Finances

```
┌─────────────────────────────────┐
│  ← Finances  Mamadou IBRAHIM    │
├─────────────────────────────────┤
│  SITUATION FINANCIERE 2025-2026 │
│  ┌─────────────────────────────┐│
│  │ Total du    : 185 000 FCFA  ││
│  │ Total paye  : 135 000 FCFA  ││
│  │ Solde restant: 50 000 FCFA  ││
│  │ Statut : ⚠️ Echeance proche  ││
│  └─────────────────────────────┘│
├─────────────────────────────────┤
│  DETAIL PAR FRAIS               │
│  Inscription   : 25 000 ✅ Paye │
│  Scolarite S1  : 50 000 ✅ Paye │
│  Scolarite S2  : 50 000 ⚠️ Du   │
│  APE           : 10 000 ✅ Paye │
│  Cantine       : 50 000 ✅ Paye │
├─────────────────────────────────┤
│  HISTORIQUE DES PAIEMENTS       │
│  15/10/2025 - 85 000 FCFA      │
│  Especes - Inscription+Scol.S1 │
│  [Telecharger recu]            │
│                                  │
│  20/12/2025 - 50 000 FCFA      │
│  Virement - Cantine             │
│  [Telecharger recu]            │
├─────────────────────────────────┤
│  ECHEANCIER                     │
│  01/04/2026 : 50 000 FCFA      │
│  (Scolarite S2)                 │
├─────────────────────────────────┤
│ 🏠  📝  📅  📄  ⋯              │
└─────────────────────────────────┘
```

- Resume financier en carte avec indicateur couleur
- Detail par type de frais avec statut
- Historique des paiements avec recus telechargeables
- Echeancier a venir

#### 3.3.8 Ecran : Centre de Notifications

```
┌─────────────────────────────────┐
│  ← Notifications  [Tout marquer]│
├─────────────────────────────────┤
│  🔴 AUJOURD'HUI                 │
│  ┌─────────────────────────────┐│
│  │ Absence non justifiee       ││
│  │ Mamadou - SVT - 12/03      ││
│  │ Il y a 2 heures             ││
│  └─────────────────────────────┘│
│  ┌─────────────────────────────┐│
│  │ Nouvelle note               ││
│  │ Mamadou - Maths: 14/20     ││
│  │ Il y a 4 heures             ││
│  └─────────────────────────────┘│
│                                  │
│  CETTE SEMAINE                  │
│  ┌─────────────────────────────┐│
│  │ Bulletin disponible          ││
│  │ Aisha - S1 2025-2026       ││
│  │ 10 mars 2026                ││
│  └─────────────────────────────┘│
│  ...                             │
├─────────────────────────────────┤
│ 🏠  📝  📅  📄  ⋯              │
└─────────────────────────────────┘
```

- Regroupement par periode (Aujourd'hui, Cette semaine, Plus ancien)
- Notifications non lues avec fond colore
- Bouton "Tout marquer comme lu"
- Clic sur une notification redirige vers la page concernee

#### 3.3.9 Ecran : Parametres et Profil Parent

```
┌─────────────────────────────────┐
│  ← Parametres                   │
├─────────────────────────────────┤
│  MON PROFIL                     │
│  Nom : Ibrahim Moussa           │
│  Email : ibrahim@email.com      │
│  Tel : +227 90 XX XX XX         │
│  [Modifier le profil]           │
│  [Changer le mot de passe]      │
├─────────────────────────────────┤
│  NOTIFICATIONS                  │
│  Absence non justifiee   [ON]   │
│  Nouvelle sanction       [ON]   │
│  Bulletin disponible     [ON]   │
│  Relance paiement        [ON]   │
│  Nouvelle note           [OFF]  │
├─────────────────────────────────┤
│  MES ENFANTS                    │
│  Mamadou - 3e A - Lycee Kassai  │
│  Aisha - 6e B - CEG Niamey     │
│  Ousmane - Tle D1 - Lycee Kassai│
├─────────────────────────────────┤
│  [Se deconnecter]               │
├─────────────────────────────────┤
│ 🏠  📝  📅  📄  ⋯              │
└─────────────────────────────────┘
```

- Informations du profil avec bouton de modification
- Toggles pour chaque type de notification
- Liste des enfants lies
- Bouton de deconnexion

### 3.4 Accessibility

**WCAG 2.1 AA** : Le portail parent doit respecter les standards d'accessibilite :
- Navigation au clavier complete (tabs, enter, espace)
- Labels ARIA pour tous les elements interactifs
- Contraste de couleurs suffisant (ratio 4.5:1 minimum), critique sur mobile en plein soleil
- Textes lisibles : taille de police minimum 16px sur mobile
- Zones tactiles suffisantes : minimum 44x44px pour les boutons sur mobile
- Messages d'erreur clairs et accessibles aux lecteurs d'ecran
- Support du zoom navigateur jusqu'a 200% sans perte de contenu

### 3.5 Branding

- Interface professionnelle, rassurante et chaleureuse
- Couleurs :
  - **Vert (#4CAF50)** : Bon statut (a jour, justifiee, paye)
  - **Orange (#FF9800)** : Attention (echeance proche, en attente)
  - **Rouge (#F44336)** : Alerte (absence non justifiee, impaye, sanction)
  - **Bleu (#2196F3)** : Actions, liens, elements interactifs
  - **Gris clair (#F5F5F5)** : Fond de page, separation de sections
- Typographie : Police systeme (meilleure performance sur mobile)
- Icones : Set d'icones coherent et intuitif

### 3.6 Target Device and Platforms

**Mobile-first (prioritaire)** :
- Smartphones Android (Chrome) : Priorite 1 (majorite des utilisateurs)
- Smartphones iOS (Safari) : Priorite 2
- Taille ecran minimum : 320px de largeur

**Desktop/Tablette (secondaire)** :
- Desktop : Interface adaptee avec meilleure utilisation de l'espace
- Tablette : Layout intermediaire

---

## 4. Technical Assumptions

### 4.1 Repository Structure

**Polyrepo** : Backend (Laravel) et Frontend (Next.js) dans des depots separes (architecture existante).

### 4.2 Service Architecture

**Backend - Architecture modulaire (nwidart/laravel-modules)** :

Nouveau module Laravel : `Modules/PortailParent/`

```
Modules/PortailParent/
├── Config/
│   └── config.php                    # Configuration du module
├── Database/
│   └── Migrations/
│       └── tenant/
│           ├── create_parent_students_table.php
│           ├── create_absence_justifications_table.php
│           ├── create_appointment_requests_table.php
│           ├── create_parent_notifications_table.php
│           └── create_notification_preferences_table.php
├── Entities/
│   ├── ParentStudent.php             # Model pivot parent-eleve
│   ├── AbsenceJustification.php      # Model justificatif d'absence
│   ├── AppointmentRequest.php        # Model demande de rendez-vous
│   ├── ParentNotification.php        # Model notification parent
│   └── NotificationPreference.php    # Model preferences de notification
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── ParentDashboardController.php
│   │       ├── ParentGradesController.php
│   │       ├── ParentAbsencesController.php
│   │       ├── ParentDisciplineController.php
│   │       ├── ParentBulletinsController.php
│   │       ├── ParentFinancesController.php
│   │       ├── ParentNotificationsController.php
│   │       ├── ParentChildrenController.php
│   │       ├── AbsenceJustificationController.php
│   │       ├── AppointmentRequestController.php
│   │       └── ParentProfileController.php
│   ├── Middleware/
│   │   └── EnsureParentOwnership.php  # Verifie l'acces parent-enfant
│   ├── Requests/
│   │   ├── StoreAbsenceJustificationRequest.php
│   │   ├── StoreAppointmentRequestRequest.php
│   │   ├── UpdateNotificationPreferencesRequest.php
│   │   └── UpdateParentProfileRequest.php
│   └── Resources/
│       ├── DashboardResource.php
│       ├── GradeResource.php
│       ├── AbsenceResource.php
│       ├── DisciplineResource.php
│       ├── BulletinResource.php
│       ├── FinanceResource.php
│       ├── ParentNotificationResource.php
│       ├── ChildSummaryResource.php
│       └── AbsenceJustificationResource.php
├── Notifications/
│   ├── AbsenceRecordedNotification.php
│   ├── SanctionRecordedNotification.php
│   ├── BulletinAvailableNotification.php
│   ├── PaymentReminderNotification.php
│   └── NewGradeNotification.php
├── Observers/
│   ├── AbsenceObserver.php           # Ecoute les absences pour notifier
│   ├── SanctionObserver.php          # Ecoute les sanctions pour notifier
│   ├── BulletinObserver.php          # Ecoute la publication de bulletins
│   └── PaymentObserver.php           # Ecoute les echeances depassees
├── Policies/
│   └── ParentStudentPolicy.php       # Politique d'acces parent-enfant
├── Providers/
│   ├── PortailParentServiceProvider.php
│   └── EventServiceProvider.php
├── Routes/
│   └── api.php                       # Routes API parent
├── Services/
│   ├── ParentDashboardService.php    # Logique metier tableau de bord
│   ├── ParentGradeService.php        # Agregation des notes
│   ├── ParentNotificationService.php # Gestion des notifications
│   └── ParentChildService.php        # Gestion multi-enfants
└── Tests/
    └── Feature/
        ├── ParentDashboardTest.php
        ├── ParentGradesTest.php
        ├── ParentAbsencesTest.php
        ├── ParentDisciplineTest.php
        ├── ParentBulletinsTest.php
        ├── ParentFinancesTest.php
        ├── ParentNotificationsTest.php
        ├── ParentMultiChildrenTest.php
        ├── AbsenceJustificationTest.php
        ├── ParentOwnershipTest.php
        └── ParentProfileTest.php
```

**Frontend Next.js** :

Nouveau module : `src/modules/PortailParent/`

```
src/modules/PortailParent/
├── parent/
│   ├── components/
│   │   ├── ChildSelector.tsx
│   │   ├── DashboardAlerts.tsx
│   │   ├── DashboardSummaryCards.tsx
│   │   ├── RecentGrades.tsx
│   │   ├── RecentAbsences.tsx
│   │   ├── GradesBySubject.tsx
│   │   ├── GradeEvolutionChart.tsx
│   │   ├── SemesterComparison.tsx
│   │   ├── AbsenceHistory.tsx
│   │   ├── JustificationUploadForm.tsx
│   │   ├── DisciplineHistory.tsx
│   │   ├── AppointmentRequestForm.tsx
│   │   ├── BulletinList.tsx
│   │   ├── BulletinViewer.tsx
│   │   ├── FinanceSummary.tsx
│   │   ├── PaymentHistory.tsx
│   │   ├── NotificationCenter.tsx
│   │   ├── NotificationPreferences.tsx
│   │   ├── MultiChildOverview.tsx
│   │   └── ParentBottomNav.tsx
│   ├── pages/
│   │   ├── DashboardPage.tsx
│   │   ├── GradesPage.tsx
│   │   ├── AbsencesPage.tsx
│   │   ├── DisciplinePage.tsx
│   │   ├── BulletinsPage.tsx
│   │   ├── FinancesPage.tsx
│   │   ├── NotificationsPage.tsx
│   │   └── ProfilePage.tsx
│   ├── hooks/
│   │   ├── useParentDashboard.ts
│   │   ├── useChildGrades.ts
│   │   ├── useChildAbsences.ts
│   │   ├── useChildDiscipline.ts
│   │   ├── useChildBulletins.ts
│   │   ├── useChildFinances.ts
│   │   ├── useParentNotifications.ts
│   │   └── useChildSelector.ts
│   └── services/
│       └── parentApi.ts
```

### 4.3 Base de Donnees

**Connexion** : `tenant` (base de donnees tenant dynamique)

**Tables existantes utilisees (lecture seule depuis le portail)** :
- `users` : Comptes parents (role "Parent")
- `students` : Informations des eleves
- `classes` : Classes des eleves
- `subjects` : Matieres
- `grades` : Notes
- `absences` : Absences
- `sanctions` : Sanctions disciplinaires
- `bulletins` : Bulletins generes
- `fees` : Types de frais
- `payments` : Paiements enregistres
- `payment_receipts` : Recus de paiement

**Nouvelles tables a creer** :

#### Table `parent_students` (Liaison parent-eleve)
```sql
CREATE TABLE parent_students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,       -- FK → users.id (role Parent)
    student_id BIGINT UNSIGNED NOT NULL,      -- FK → students.id
    relationship VARCHAR(50) NOT NULL,         -- 'pere', 'mere', 'tuteur', 'tutrice'
    is_primary_contact BOOLEAN DEFAULT FALSE,  -- Contact principal
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_parent_student (parent_id, student_id)
);
```

#### Table `absence_justifications` (Justificatifs soumis par les parents)
```sql
CREATE TABLE absence_justifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    absence_id BIGINT UNSIGNED NOT NULL,      -- FK → absences.id
    parent_id BIGINT UNSIGNED NOT NULL,       -- FK → users.id (parent qui soumet)
    document_path VARCHAR(500) NOT NULL,       -- Chemin du fichier upload
    document_type VARCHAR(10) NOT NULL,        -- 'pdf', 'jpg', 'png'
    comment TEXT NULL,                         -- Commentaire du parent
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,          -- FK → users.id (surveillant general)
    reviewed_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,                -- Motif de rejet
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (absence_id) REFERENCES absences(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);
```

#### Table `appointment_requests` (Demandes de rendez-vous)
```sql
CREATE TABLE appointment_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,       -- FK → users.id (parent)
    student_id BIGINT UNSIGNED NOT NULL,      -- FK → students.id
    reason TEXT NOT NULL,                      -- Motif du rendez-vous
    preferred_date_1 DATE NOT NULL,            -- Premier choix de date
    preferred_date_2 DATE NULL,               -- Deuxieme choix (optionnel)
    preferred_time VARCHAR(20) NULL,           -- Creneau prefere (ex: 'matin', 'apres-midi')
    status ENUM('pending', 'confirmed', 'rejected', 'completed') DEFAULT 'pending',
    admin_response TEXT NULL,                  -- Reponse de la direction
    confirmed_date DATE NULL,                  -- Date confirmee
    confirmed_time TIME NULL,                  -- Heure confirmee
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
```

#### Table `parent_notifications` (Notifications parent)
```sql
CREATE TABLE parent_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,       -- FK → users.id (parent)
    student_id BIGINT UNSIGNED NULL,          -- FK → students.id (enfant concerne)
    type VARCHAR(50) NOT NULL,                 -- 'absence', 'sanction', 'bulletin', 'payment', 'grade'
    title VARCHAR(255) NOT NULL,               -- Titre de la notification
    message TEXT NOT NULL,                     -- Contenu de la notification
    data JSON NULL,                            -- Donnees supplementaires (id de la note, de l'absence, etc.)
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    email_sent BOOLEAN DEFAULT FALSE,          -- Email envoye ?
    email_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_parent_read (parent_id, is_read),
    INDEX idx_parent_created (parent_id, created_at)
);
```

#### Table `notification_preferences` (Preferences de notification)
```sql
CREATE TABLE notification_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,       -- FK → users.id (parent)
    notify_absence BOOLEAN DEFAULT TRUE,
    notify_sanction BOOLEAN DEFAULT TRUE,
    notify_bulletin BOOLEAN DEFAULT TRUE,
    notify_payment BOOLEAN DEFAULT TRUE,
    notify_grade BOOLEAN DEFAULT FALSE,        -- Desactive par defaut (trop frequent)
    email_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_parent_prefs (parent_id)
);
```

**Relations cles** :
- `users` (Parent) hasMany `parent_students` → belongsToMany `students`
- `parent_students` belongsTo `users` (parent), belongsTo `students`
- `absence_justifications` belongsTo `absences`, belongsTo `users` (parent)
- `appointment_requests` belongsTo `users` (parent), belongsTo `students`
- `parent_notifications` belongsTo `users` (parent), belongsTo `students`
- `notification_preferences` belongsTo `users` (parent)
- Utiliser **eager loading** pour eviter les N+1 queries (critique pour performance mobile)

### 4.4 API Endpoints

**Prefix** : `/api/parent/`

**Middleware** : `['tenant', 'tenant.auth', 'role:Parent', 'parent.ownership']`

#### Tableau de Bord
| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/parent/dashboard` | Tableau de bord consolide (tous les enfants) |
| GET | `/api/parent/dashboard/{studentId}` | Tableau de bord detaille d'un enfant |

#### Enfants
| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/parent/children` | Liste des enfants du parent |
| GET | `/api/parent/children/{studentId}` | Detail d'un enfant (classe, infos) |

#### Notes
| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/parent/children/{studentId}/grades` | Notes de l'enfant (filtrable par semestre, matiere) |
| GET | `/api/parent/children/{studentId}/grades/summary` | Resume des moyennes et rangs |
| GET | `/api/parent/children/{studentId}/grades/evolution` | Donnees pour graphique d'evolution |
| GET | `/api/parent/children/{studentId}/grades/comparison` | Comparaison S1 vs S2 |

#### Absences
| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/parent/children/{studentId}/absences` | Historique des absences (filtrable) |
| GET | `/api/parent/children/{studentId}/absences/summary` | Resume (totaux justifiees/non justifiees) |
| POST | `/api/parent/children/{studentId}/absences/{absenceId}/justify` | Soumettre un justificatif |
| GET | `/api/parent/justifications` | Liste des justificatifs soumis par le parent |

#### Discipline
| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/parent/children/{studentId}/discipline` | Historique des sanctions |
| GET | `/api/parent/children/{studentId}/discipline/summary` | Resume des sanctions par type |

#### Bulletins
| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/parent/children/{studentId}/bulletins` | Liste des bulletins disponibles |
| GET | `/api/parent/children/{studentId}/bulletins/{bulletinId}` | Detail d'un bulletin (vue en ligne) |
| GET | `/api/parent/children/{studentId}/bulletins/{bulletinId}/download` | Telecharger le bulletin PDF |

#### Finances
| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/parent/children/{studentId}/finances` | Etat financier de l'enfant |
| GET | `/api/parent/children/{studentId}/finances/payments` | Historique des paiements |
| GET | `/api/parent/children/{studentId}/finances/payments/{paymentId}/receipt` | Telecharger recu PDF |
| GET | `/api/parent/children/{studentId}/finances/schedule` | Echeancier |

#### Notifications
| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/parent/notifications` | Centre de notifications (paginee) |
| GET | `/api/parent/notifications/unread-count` | Nombre de notifications non lues |
| PATCH | `/api/parent/notifications/{notificationId}/read` | Marquer une notification comme lue |
| PATCH | `/api/parent/notifications/read-all` | Marquer toutes comme lues |
| GET | `/api/parent/notification-preferences` | Preferences de notification |
| PUT | `/api/parent/notification-preferences` | Mettre a jour les preferences |

#### Rendez-vous
| Methode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/parent/appointments` | Demander un rendez-vous |
| GET | `/api/parent/appointments` | Liste des demandes de rendez-vous |

#### Profil
| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/parent/profile` | Informations du profil parent |
| PUT | `/api/parent/profile` | Mettre a jour le profil (email, telephone) |
| PUT | `/api/parent/profile/password` | Changer le mot de passe |

### 4.5 Testing Requirements

**Tests obligatoires** :

- **Tests de securite** : Verification que le parent ne peut acceder qu'aux donnees de ses propres enfants (guard parent-enfant strict)
- **Tests d'acces** : Verification que le parent ne peut PAS modifier les notes, absences ou donnees financieres
- **Tests fonctionnels** : Dashboard, notes, absences, discipline, bulletins, finances, notifications
- **Tests de soumission** : Upload de justificatif, demande de rendez-vous
- **Tests multi-enfants** : Verification du bon fonctionnement avec 1, 2 et 3+ enfants
- **Tests de notifications** : Envoi correct des notifications lors des evenements declencheurs
- **Tests d'edge cases** : Parent sans enfant, enfant sans notes, enfant transfere

**Outils** :
- Laravel : PHPUnit (`php artisan test`)
- Frontend : Jest + React Testing Library

### 4.6 Additional Technical Assumptions

- **API REST** : Endpoints suivant les conventions existantes avec prefix `/api/parent/`
- **Middleware parent.ownership** : Middleware custom qui verifie a chaque requete que le `studentId` dans l'URL appartient bien au parent connecte
- **Permissions** : Role "Parent" avec permissions lecture seule + permissions d'ecriture limitees (justificatif, rendez-vous, profil, notifications)
- **Validation** : Form Requests pour toutes les saisies (justificatif, rendez-vous, profil)
- **API Resources** : Retourner toujours des Resources, jamais de models bruts
- **SoftDeletes** : Utiliser sur toutes les nouvelles tables
- **Casts Laravel 12** : Utiliser `casts()` method sur les models
- **File Storage** : Justificatifs stockes via Laravel Storage (disk configurable : local ou S3)
- **Queue** : Envoi des emails de notification via queued jobs (`ShouldQueue`)
- **Cache** : Mise en cache du dashboard parent (5 minutes) pour optimiser les performances sur mobile
- **Pagination** : Toutes les listes paginées (15 elements par page par defaut)
- **Eager Loading** : Obligatoire pour toutes les requetes parent afin d'eviter les N+1 (performance critique)
- **Rate Limiting** : 60 requetes par minute par parent (protection contre les abus)

---

## 5. Epic List

### Epic 1 : Acces Parent et Securite
**Goal** : Mettre en place le systeme d'authentification parent, la liaison parent-enfant, et le guard de securite garantissant l'acces en lecture seule aux seules donnees des enfants du parent.

### Epic 2 : Tableau de Bord Parent
**Goal** : Fournir au parent une vue d'ensemble synthetique et actionnable de la situation scolaire de chaque enfant (notes, absences, finances, alertes).

### Epic 3 : Notes Detaillees
**Goal** : Permettre au parent de consulter en detail les notes de son enfant par matiere, avec moyennes, rangs, et graphiques d'evolution.

### Epic 4 : Absences et Justificatifs
**Goal** : Permettre au parent de consulter l'historique des absences et de soumettre des justificatifs directement depuis le portail.

### Epic 5 : Discipline
**Goal** : Permettre au parent de consulter l'historique disciplinaire de son enfant et de demander un rendez-vous avec la direction.

### Epic 6 : Bulletins en Ligne
**Goal** : Permettre au parent de consulter et telecharger les bulletins semestriels de son enfant.

### Epic 7 : Finances
**Goal** : Fournir au parent une vue transparente de la situation financiere (frais, paiements, impayes, echeancier).

### Epic 8 : Multi-Enfants
**Goal** : Permettre au parent de suivre plusieurs enfants depuis un seul compte, avec vue consolidee et vue detaillee par enfant.

### Epic 9 : Systeme de Notifications
**Goal** : Mettre en place le systeme de notifications temps reel (in-app + email) avec preferences configurables par le parent.

---

## 6. Epic Details

### Epic 1 : Acces Parent et Securite

**Goal detaille** : Le systeme doit garantir que chaque parent ne peut acceder qu'aux donnees de ses propres enfants, en lecture seule, avec des actions d'ecriture strictement limitees (justificatif, rendez-vous, profil). La liaison parent-enfant est creee automatiquement lors de l'inscription de l'eleve.

#### Story 1.1 : Creation Automatique du Compte Parent

**As an** Admin (lors de l'inscription d'un eleve),
**I want** que le compte parent soit cree automatiquement avec les informations parentales fournies,
**so that** le parent puisse se connecter au portail des que l'eleve est inscrit.

**Acceptance Criteria** :
1. Lors de l'inscription d'un eleve, si l'email parent fourni n'existe pas dans le systeme, un nouveau compte utilisateur est cree avec le role "Parent"
2. Si l'email parent existe deja (parent ayant deja un enfant inscrit), l'eleve est automatiquement lie au compte parent existant
3. Un mot de passe initial aleatoire est genere (8 caracteres, alphanumerique)
4. Un email est envoye au parent avec ses identifiants de connexion (email + mot de passe initial)
5. L'entree dans la table `parent_students` est creee avec la relation (pere, mere, tuteur)
6. Le parent est marque comme "premiere connexion" pour forcer le changement de mot de passe

**Dependances** : Module Inscriptions, Module UsersGuard

---

#### Story 1.2 : Middleware de Verification Parent-Enfant (Guard)

**As a** developpeur,
**I want** un middleware qui verifie a chaque requete que le parent accede aux donnees d'un de ses enfants,
**so that** aucun parent ne puisse consulter les donnees d'un eleve qui n'est pas son enfant.

**Acceptance Criteria** :
1. Le middleware `EnsureParentOwnership` intercepte toutes les requetes contenant un `{studentId}` dans l'URL
2. Le middleware verifie que le `studentId` est lie au parent connecte dans la table `parent_students`
3. Si le parent n'est pas autorise, une reponse 403 Forbidden est retournee
4. Le middleware est applique a toutes les routes du prefix `/api/parent/children/{studentId}`
5. Les requetes sans `studentId` (ex: `/api/parent/notifications`) ne sont pas affectees
6. Le middleware est teste avec des cas positifs (parent autorise) et negatifs (parent non autorise)

**Dependances** : Story 1.1

---

#### Story 1.3 : Premiere Connexion et Changement de Mot de Passe

**As a** Parent,
**I want** changer mon mot de passe lors de ma premiere connexion,
**so that** mon compte soit securise avec un mot de passe que j'ai choisi.

**Acceptance Criteria** :
1. Lors de la premiere connexion, le parent est redirige vers un ecran de changement de mot de passe
2. Le formulaire demande : mot de passe actuel (celui envoye par email), nouveau mot de passe, confirmation
3. Le nouveau mot de passe doit respecter les regles de securite : minimum 8 caracteres, au moins une lettre et un chiffre
4. Apres le changement, le flag "premiere connexion" est retire
5. Le parent est redirige vers le tableau de bord
6. Un email de confirmation est envoye

**Dependances** : Story 1.1, Module UsersGuard

---

#### Story 1.4 : Acces Lecture Seule

**As a** Parent,
**I want** consulter les informations de mes enfants sans pouvoir les modifier,
**so that** l'integrite des donnees scolaires soit preservee.

**Acceptance Criteria** :
1. Les routes API parent n'exposent que des methodes GET (sauf pour justificatif, rendez-vous, profil, notifications)
2. Les API Resources ne retournent aucune information sensible (pas de donnees des autres eleves, pas d'identifiants internes)
3. Les notes, absences, sanctions, bulletins et donnees financieres ne sont accessibles qu'en lecture
4. Toute tentative d'acces a une route d'ecriture non autorisee retourne un 403 Forbidden
5. Les tests verifient exhaustivement qu'aucune route de modification n'est accessible

**Dependances** : Story 1.2

---

### Epic 2 : Tableau de Bord Parent

**Goal detaille** : Le tableau de bord est la page d'accueil du parent. Il doit fournir en un coup d'oeil une vision synthetique de la situation de chaque enfant, avec les alertes recentes, les indicateurs cles et les informations les plus importantes.

#### Story 2.1 : Tableau de Bord - Vue par Enfant

**As a** Parent,
**I want** voir un resume complet de la situation scolaire de mon enfant sur une seule page,
**so that** je puisse savoir rapidement si tout va bien.

**Acceptance Criteria** :
1. Le tableau de bord affiche : photo de l'enfant (si disponible), nom complet, classe, annee scolaire
2. Section "Alertes" en haut : absences non justifiees, sanctions recentes, paiements en retard (fond colore)
3. Trois indicateurs resumes : moyenne generale (avec tendance ↑↓), nombre d'absences (justifiees/non justifiees), statut financier (icone couleur)
4. Section "Dernieres notes" : 5 dernieres notes avec matiere, type et valeur
5. Section "Absences recentes" : 3 dernieres absences avec date, matiere et statut
6. Chaque section a un lien "Voir tout" qui redirige vers la page detaillee correspondante
7. L'ensemble se charge en < 2 secondes sur connexion 3G

**Dependances** : Story 1.2, Modules Notes, Presences, Discipline, Comptabilite

---

#### Story 2.2 : Tableau de Bord - Selecteur d'Enfant

**As a** Parent ayant plusieurs enfants,
**I want** pouvoir basculer facilement entre les tableaux de bord de mes differents enfants,
**so that** je puisse suivre chacun d'eux sans me deconnecter/reconnecter.

**Acceptance Criteria** :
1. Si le parent a un seul enfant, le selecteur n'est pas affiche (l'enfant est selectionne par defaut)
2. Si le parent a plusieurs enfants, un selecteur est affiche en haut de page (dropdown mobile ou onglets desktop)
3. Le selecteur affiche : prenom de l'enfant, classe, nom de l'etablissement (si multi-tenant)
4. Le changement d'enfant met a jour tout le tableau de bord sans rechargement complet de la page
5. Le dernier enfant selectionne est memorise (localStorage) pour la prochaine visite

**Dependances** : Story 2.1, Epic 8

---

### Epic 3 : Notes Detaillees

**Goal detaille** : Le parent doit pouvoir consulter en detail l'ensemble des notes de son enfant, comprendre sa progression, et comparer ses performances entre semestres.

#### Story 3.1 : Consultation des Notes par Matiere

**As a** Parent,
**I want** voir toutes les notes de mon enfant regroupees par matiere,
**so that** je puisse identifier les matieres ou il reussit et celles ou il a besoin d'aide.

**Acceptance Criteria** :
1. Les notes sont regroupees par matiere, chaque matiere affichant : nom, coefficient, moyenne de la matiere, rang dans la classe
2. Pour chaque matiere, la liste des evaluations est affichee : date, type (devoir, interrogation, composition), note /20
3. La moyenne generale semestrielle et le rang global sont affiches en haut de la page
4. Un filtre par semestre permet de basculer entre S1 et S2
5. Un filtre par matiere permet de voir uniquement les notes d'une matiere
6. Les statistiques de classe sont affichees pour contexte : moyenne de la classe, min, max (sans nommer les eleves)

**Dependances** : Story 1.2, Module Notes

---

#### Story 3.2 : Graphique d'Evolution des Moyennes

**As a** Parent,
**I want** voir un graphique montrant l'evolution de la moyenne de mon enfant au fil du temps,
**so that** je puisse comprendre sa tendance (amelioration ou degradation).

**Acceptance Criteria** :
1. Un graphique en ligne affiche l'evolution de la moyenne generale par mois ou par evaluation
2. Le graphique affiche egalement la moyenne de la classe en pointilles (pour comparaison)
3. Les points du graphique sont cliquables pour voir le detail de la periode
4. Le graphique est responsive et lisible sur mobile (taille adaptee)
5. Si moins de 3 points de donnees existent, un message indique "Donnees insuffisantes pour afficher le graphique"

**Dependances** : Story 3.1

---

#### Story 3.3 : Comparaison Semestrielle

**As a** Parent,
**I want** comparer les resultats de mon enfant entre le Semestre 1 et le Semestre 2,
**so that** je puisse voir sa progression d'un semestre a l'autre.

**Acceptance Criteria** :
1. Un ecran de comparaison affiche cote a cote les moyennes par matiere pour S1 et S2
2. Pour chaque matiere, un indicateur visuel montre la progression (↑ amelioration en vert, ↓ baisse en rouge, = stable en gris)
3. La moyenne generale et le rang global sont compares pour les deux semestres
4. Si le S2 n'a pas encore de donnees, un message indique "Semestre 2 non encore disponible"
5. Le tableau est scrollable horizontalement sur mobile

**Dependances** : Story 3.1

---

### Epic 4 : Absences et Justificatifs

**Goal detaille** : Le parent doit pouvoir consulter l'historique complet des absences de son enfant, avec totaux et statuts, et pouvoir soumettre des justificatifs directement depuis le portail.

#### Story 4.1 : Historique des Absences

**As a** Parent,
**I want** voir l'historique complet des absences de mon enfant,
**so that** je puisse verifier sa regularite et identifier les absences non justifiees.

**Acceptance Criteria** :
1. La liste des absences affiche : date, creneau horaire, matiere, enseignant ayant fait l'appel, statut (justifiee, non justifiee, en attente)
2. Les absences sont triees par date (les plus recentes en premier)
3. Un resume en haut affiche : total heures d'absence, heures justifiees, heures non justifiees
4. Le statut est code par couleur : vert (justifiee), rouge (non justifiee), orange (en attente)
5. Des filtres sont disponibles : par semestre, par statut, par matiere
6. La liste est paginee (15 par page)

**Dependances** : Story 1.2, Module Presences

---

#### Story 4.2 : Soumission de Justificatif d'Absence

**As a** Parent,
**I want** soumettre un justificatif pour une absence non justifiee de mon enfant,
**so that** l'absence puisse etre reclassee comme justifiee.

**Acceptance Criteria** :
1. Un bouton "Soumettre justificatif" est visible pour chaque absence non justifiee
2. Le formulaire (bottom sheet sur mobile) demande : upload de document (obligatoire) et commentaire (optionnel)
3. Formats acceptes : JPEG, PNG, PDF | Taille max : 5 Mo
4. Le fichier est uploade et stocke de maniere securisee (Laravel Storage)
5. L'absence passe en statut "en attente de validation"
6. Le surveillant general est notifie de la soumission
7. Le parent voit le statut du justificatif : "en attente", "approuve", "rejete" (avec motif de rejet le cas echeant)
8. Un message de succes s'affiche apres soumission : "Justificatif soumis avec succes. Il sera examine par l'administration."

**Dependances** : Story 4.1

---

### Epic 5 : Discipline

**Goal detaille** : Le parent doit pouvoir consulter l'historique disciplinaire de son enfant et communiquer avec la direction en cas de besoin.

#### Story 5.1 : Historique des Sanctions

**As a** Parent,
**I want** voir l'historique complet des sanctions disciplinaires de mon enfant,
**so that** je sois informe de son comportement a l'ecole.

**Acceptance Criteria** :
1. La liste des sanctions affiche : date, type de sanction, description de l'incident, duree (si exclusion), enseignant/surveillant rapporteur
2. Un resume en haut affiche le nombre de sanctions par type pour le semestre en cours
3. Les sanctions sont triees par date (les plus recentes en premier)
4. Si aucune sanction, un message positif s'affiche : "Aucune sanction pour ce semestre. Felicitations !"
5. La liste est paginee

**Dependances** : Story 1.2, Module Discipline

---

#### Story 5.2 : Demande de Rendez-vous

**As a** Parent,
**I want** demander un rendez-vous avec la direction de l'etablissement,
**so that** je puisse discuter de la situation de mon enfant.

**Acceptance Criteria** :
1. Un bouton "Demander un rendez-vous" est disponible sur la page Discipline (et sur le profil)
2. Le formulaire demande : enfant concerne, motif (texte), date preferee 1 (obligatoire), date preferee 2 (optionnel), creneau prefere (matin/apres-midi)
3. La demande est soumise et visible avec statut : "en attente", "confirme" (avec date/heure), "rejete" (avec motif)
4. La direction est notifiee de la nouvelle demande
5. Le parent est notifie lorsque la demande est traitee (confirmee ou rejetee)
6. Le parent peut voir la liste de ses demandes de rendez-vous et leur statut

**Dependances** : Story 1.2

---

### Epic 6 : Bulletins en Ligne

**Goal detaille** : Le parent doit pouvoir consulter et telecharger les bulletins semestriels de son enfant, avec un historique complet des semestres precedents.

#### Story 6.1 : Consultation et Telechargement des Bulletins

**As a** Parent,
**I want** consulter et telecharger les bulletins de mon enfant,
**so that** je puisse suivre ses resultats officiels sans me deplacer a l'ecole.

**Acceptance Criteria** :
1. La page "Bulletins" affiche la liste des bulletins disponibles : semestre, annee scolaire, date de mise en ligne
2. Chaque bulletin affiche un resume : moyenne generale, rang
3. Un bouton "Consulter" ouvre le bulletin dans une vue web responsive (notes par matiere, moyennes, rangs, appreciations, mentions, decision)
4. Un bouton "Telecharger PDF" lance le telechargement du bulletin au format PDF
5. L'historique des bulletins des semestres et annees precedents est accessible
6. Le bulletin n'apparait que lorsqu'il a ete "publie" par l'administration (pas de bulletin en brouillon visible)
7. Le telechargement PDF se fait en < 5 secondes

**Dependances** : Story 1.2, Module Documents

---

#### Story 6.2 : Notification de Nouveau Bulletin

**As a** Parent,
**I want** etre notifie lorsqu'un nouveau bulletin est disponible,
**so that** je puisse le consulter rapidement.

**Acceptance Criteria** :
1. Lorsqu'un bulletin est publie par l'administration, une notification est creee pour le parent
2. Un email est envoye au parent (si la preference est active) : "Le bulletin du Semestre X de [Prenom Enfant] est disponible"
3. La notification in-app est creee avec lien direct vers le bulletin
4. La notification apparait dans le badge de la cloche

**Dependances** : Story 6.1, Epic 9

---

### Epic 7 : Finances

**Goal detaille** : Le parent doit avoir une visibilite complete sur la situation financiere de chaque enfant : frais dus, paiements effectues, solde restant, et echeancier.

#### Story 7.1 : Etat Financier et Paiements

**As a** Parent,
**I want** voir l'etat financier detaille de mon enfant,
**so that** je sache exactement ce qui est du, ce qui est paye, et ce qui reste a payer.

**Acceptance Criteria** :
1. Un resume financier affiche : montant total du, montant total paye, solde restant, avec indicateur couleur (vert/orange/rouge)
2. Le detail par type de frais est affiche : inscription, scolarite, APE, cantine, etc. avec montant du et montant paye
3. L'historique des paiements est liste : date, montant, mode de paiement, type de frais
4. Un bouton "Telecharger recu" permet de telecharger le recu PDF pour chaque paiement
5. Si un echeancier est defini, les echeances a venir sont affichees avec dates et montants
6. Les paiements sont tries par date (les plus recents en premier)
7. Les informations sont en lecture seule (pas de paiement en ligne pour le MVP)

**Dependances** : Story 1.2, Module Comptabilite

---

### Epic 8 : Multi-Enfants

**Goal detaille** : Le systeme doit permettre a un parent de suivre plusieurs enfants depuis un seul compte, avec une experience fluide de navigation entre les enfants.

#### Story 8.1 : Vue Consolidee Multi-Enfants

**As a** Parent ayant plusieurs enfants,
**I want** voir un resume de tous mes enfants sur une seule page,
**so that** je puisse identifier rapidement si l'un d'eux necessite mon attention.

**Acceptance Criteria** :
1. Si le parent a 2+ enfants, la page d'accueil affiche la vue consolidee (une carte par enfant)
2. Chaque carte affiche : photo, prenom, classe, etablissement, moyenne (avec tendance), nombre d'absences, statut financier
3. Les cartes avec alertes (absences non justifiees, sanctions, impayes) sont mises en surbrillance (bordure orange/rouge)
4. Le clic sur une carte redirige vers le tableau de bord detaille de l'enfant
5. Si le parent a un seul enfant, la vue consolidee est sautee et le tableau de bord detaille est affiche directement

**Dependances** : Epic 2, Stories 1.1, 1.2

---

#### Story 8.2 : Multi-Tenant pour Parents

**As a** Parent ayant des enfants dans des etablissements differents,
**I want** voir tous mes enfants dans une seule interface,
**so that** je n'aie pas besoin de me connecter separement pour chaque etablissement.

**Acceptance Criteria** :
1. Le compte parent est identifie par son email, commun a tous les tenants
2. La vue consolidee affiche les enfants de tous les tenants avec le nom de l'etablissement
3. Lorsque le parent selectionne un enfant d'un tenant specifique, le contexte tenant est automatiquement bascule
4. Les donnees de chaque enfant sont isolees dans le tenant correspondant
5. L'authentification est unique (single sign-on cross-tenant pour les parents)

**Note** : Cette story est complexe et peut necessiter une adaptation de l'architecture multi-tenant. A evaluer avec l'equipe technique pour determiner si elle fait partie du MVP de ce module ou d'une iteration ulterieure.

**Dependances** : Story 8.1, Architecture multi-tenant (stancl/tenancy)

---

### Epic 9 : Systeme de Notifications

**Goal detaille** : Le systeme doit notifier proactivement les parents des evenements importants concernant leurs enfants, avec des preferences configurables.

#### Story 9.1 : Notifications In-App

**As a** Parent,
**I want** recevoir des notifications dans l'interface lorsqu'un evenement important se produit,
**so that** je sois informe en temps reel.

**Acceptance Criteria** :
1. Une icone cloche avec badge numerique (nombre de non lues) est visible en permanence dans le header
2. Le clic sur la cloche ouvre le centre de notifications
3. Les notifications sont regroupees par jour (Aujourd'hui, Cette semaine, Plus ancien)
4. Les notifications non lues ont un fond colore distinct
5. Le clic sur une notification la marque comme lue et redirige vers la page concernee (ex: page absences, page discipline)
6. Un bouton "Tout marquer comme lu" est disponible
7. Les notifications sont paginées (20 par page)
8. Les types de notifications : absence non justifiee, nouvelle sanction, bulletin disponible, relance paiement, nouvelle note

**Dependances** : Table `parent_notifications`

---

#### Story 9.2 : Notifications Email

**As a** Parent,
**I want** recevoir des emails pour les evenements importants,
**so that** je sois informe meme si je ne consulte pas le portail regulierement.

**Acceptance Criteria** :
1. Un email est envoye pour chaque notification (si la preference correspondante est active)
2. L'email est envoye via une job en queue (ShouldQueue) pour ne pas bloquer le processus principal
3. L'email est envoye dans les 5 minutes suivant l'evenement
4. L'email contient : objet clair, resume de l'evenement, lien direct vers la page concernee dans le portail
5. L'email utilise un template professionnel et responsive (HTML + texte brut)
6. Le statut d'envoi est journalise (email_sent, email_sent_at dans la table parent_notifications)

**Dependances** : Story 9.1, Configuration Laravel Mail

---

#### Story 9.3 : Preferences de Notification

**As a** Parent,
**I want** configurer quelles notifications je souhaite recevoir,
**so that** je ne sois pas submerge par des notifications non souhaitees.

**Acceptance Criteria** :
1. Un ecran "Preferences de notification" est accessible depuis le profil ou les parametres
2. Le parent peut activer/desactiver chaque type de notification via des toggles :
   - Absence non justifiee (active par defaut)
   - Nouvelle sanction (active par defaut)
   - Bulletin disponible (active par defaut)
   - Relance paiement (active par defaut)
   - Nouvelle note (desactivee par defaut - potentiellement frequent)
3. Le parent peut desactiver globalement les emails (tout en conservant les notifications in-app)
4. Les preferences sont sauvegardees immediatement (pas de bouton "Sauvegarder" necessaire, auto-save)
5. Les preferences par defaut sont creees automatiquement lors de la creation du compte parent

**Dependances** : Table `notification_preferences`

---

#### Story 9.4 : Declencheurs de Notifications (Observers)

**As a** systeme,
**I want** generer automatiquement des notifications lorsqu'un evenement concernant un eleve se produit,
**so that** les parents soient informes sans intervention manuelle.

**Acceptance Criteria** :
1. **Observer Absence** : Lorsqu'une absence non justifiee est enregistree pour un eleve, une notification est creee pour chaque parent lie a cet eleve (si la preference est active)
2. **Observer Sanction** : Lorsqu'une sanction est enregistree, notification creee pour les parents (si active)
3. **Observer Bulletin** : Lorsqu'un bulletin est publie (statut passe a "publie"), notification creee (si active)
4. **Observer Paiement** : Lorsqu'une echeance est depassee, notification de relance creee (si active)
5. **Observer Note** : Lorsqu'une nouvelle note est saisie, notification creee (si active, desactive par defaut)
6. Les observers sont enregistres dans le EventServiceProvider du module
7. Chaque observer verifie les preferences du parent avant de creer la notification

**Dependances** : Stories 9.1, 9.2, 9.3, Modules Notes, Presences, Discipline, Comptabilite

---

## 7. Checklist Results Report

**A completer apres implementation** : Executer le checklist PM pour valider :
- [ ] Le compte parent est cree automatiquement lors de l'inscription d'un eleve
- [ ] Le guard parent-enfant fonctionne correctement (aucun acces aux donnees d'autres eleves)
- [ ] Le portail est en lecture seule (sauf justificatif, rendez-vous, profil, notifications)
- [ ] Le tableau de bord affiche correctement les indicateurs cles de chaque enfant
- [ ] Les notes detaillees sont affichees avec moyennes, rangs et graphiques
- [ ] L'historique des absences est complet avec possibilite de soumettre un justificatif
- [ ] L'historique disciplinaire est consultable avec possibilite de demander un rendez-vous
- [ ] Les bulletins sont consultables en ligne et telechargeables en PDF
- [ ] La situation financiere est affichee avec detail par type de frais et recus
- [ ] Le multi-enfants fonctionne correctement (1, 2 et 3+ enfants)
- [ ] Les notifications sont envoyees correctement (in-app + email)
- [ ] Les preferences de notification sont configurables
- [ ] L'interface est mobile-first et fonctionne sur smartphone (320px+)
- [ ] Les performances sont satisfaisantes (< 2s sur 3G pour le tableau de bord)
- [ ] Les tests unitaires et d'integration sont passes

---

## 8. Next Steps

### 8.1 UX Expert Prompt

> "Creez les maquettes UI mobile-first pour le Module Portail Parent en vous basant sur ce PRD. Focus sur les ecrans critiques : Tableau de bord parent (vue enfant unique + vue multi-enfants), Notes detaillees (avec graphique d'evolution), Historique des absences (avec soumission de justificatif), Centre de notifications. L'interface doit etre ultra-simple, optimisee pour les smartphones Android en Afrique de l'Ouest, avec des zones tactiles de 44px+ et un chargement rapide. Utilisez les codes couleur definis (vert/orange/rouge) pour les indicateurs de statut. Assurez l'accessibilite WCAG AA."

### 8.2 Architect Prompt

> "Concevez l'architecture technique du Module Portail Parent en suivant les patterns etablis dans les modules existants (UsersGuard, StructureAcademique). Points critiques : (1) Middleware `EnsureParentOwnership` pour securiser l'acces parent-enfant, (2) Observers sur les modules Notes, Presences, Discipline et Comptabilite pour declencher les notifications, (3) Optimisation des requetes avec eager loading pour performance mobile (< 2s sur 3G), (4) Jobs en queue pour l'envoi des emails de notification, (5) Cache du dashboard parent (5 min) pour reduire la charge base de donnees. Architecture du multi-enfants cross-tenant a evaluer. Tables a creer : parent_students, absence_justifications, appointment_requests, parent_notifications, notification_preferences."

---

**Document cree par** : John (Product Manager Agent)
**Date** : 2026-03-16
**Version** : 1.0
**Statut** : Draft pour review
