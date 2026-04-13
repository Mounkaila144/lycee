# 🎯 Overview - Vision Globale du Système

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Version** : v5
> **Date** : 2026-03-16
> **Type** : Documentation Transverse

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 2.0 | Refonte complète - Passage du LMD (supérieur) au secondaire (collèges/lycées) | John (PM) |
| 2026-01-07 | 1.0 | Création initiale - Vision globale du système (LMD) | John (PM) |

---

## 1. Vision Stratégique

### 1.1 Concept Central

**Gestion Scolaire** est une plateforme web unifiée qui numérise et automatise l'ensemble des processus pédagogiques, administratifs et financiers des **collèges et lycées** au Niger.

La solution remplace les cahiers de notes, registres papier, feuilles d'appel et fichiers Excel par un système centralisé, accessible depuis n'importe quel navigateur web, avec une architecture multi-tenant permettant à plusieurs établissements de partager une même instance tout en maintenant une isolation complète de leurs données.

### 1.2 Objectifs Stratégiques

1. **Numérisation Complète de l'Établissement**
   - Abandon du papier pour une gestion 100% numérique des notes, absences et documents
   - Centraliser toutes les données pédagogiques, disciplinaires et financières
   - Fournir un accès temps réel aux informations critiques

2. **Génération Automatique des Bulletins**
   - Bulletins semestriels professionnels générés en un clic pour toute une classe
   - Calcul automatique des moyennes par matière, moyennes générales, classements
   - Appréciations, mentions et décisions du conseil de classe intégrées

3. **Engagement des Parents**
   - Portail parent intégré dès le MVP pour suivi en temps réel
   - Notifications d'absences et de sanctions disciplinaires
   - Consultation des bulletins et de la situation financière en ligne

4. **Scalabilité et Multi-Établissement**
   - Architecture multi-tenant pour servir plusieurs établissements
   - Accessibilité depuis n'importe quel appareil connecté à Internet
   - Interface moderne et intuitive en français

---

## 2. Proposition de Valeur

### 2.1 Pour les Établissements

- **Gain de Temps Massif** : Bulletins générés en < 5 minutes pour une classe de 60 élèves (vs 2-3 jours manuellement)
- **Zéro Erreur** : Calculs automatiques des moyennes, classements et coefficients
- **Pilotage Éclairé** : Statistiques de classe, taux de réussite, suivi des absences
- **Image Professionnelle** : Bulletins, attestations et documents de qualité

### 2.2 Pour les Parents

- **Suivi en Temps Réel** : Notes, absences et discipline accessibles en ligne
- **Notifications Proactives** : Alertes automatiques en cas d'absence ou de sanction
- **Bulletins en Ligne** : Consultation et téléchargement PDF sans déplacement
- **Transparence Financière** : État des paiements, impayés

### 2.3 Pour les Enseignants

- **Saisie Simplifiée** : Interface intuitive pour la saisie des notes et présences
- **Appel Rapide** : Appel numérique en début de cours (< 3 minutes)
- **Calculs Automatiques** : Moyennes par matière calculées instantanément
- **Emploi du Temps** : Consultation en ligne, notifications de changements

### 2.4 Pour les Directeurs / Administrateurs

- **Conseil de Classe Outillé** : Données consolidées pour les décisions
- **Gestion Discipline** : Historique centralisé, statistiques, notifications parents
- **Contrôle Financier** : Suivi des frais, impayés, paie du personnel
- **Statistiques Ministère** : Rapports facilement générables

---

## 3. Périmètre Fonctionnel

### 3.1 Modules Phase 1 : MVP Core

| Module | Description | Priorité |
|--------|-------------|----------|
| **Structure Académique** | Années scolaires, semestres, cycles (collège/lycée), classes, séries, matières avec coefficients | 🔴 Critique |
| **Inscriptions** | Inscription élèves, liaison parent-élève, affectation classes, import masse | 🔴 Critique |
| **Notes & Évaluations** | Saisie notes (devoirs, interrogations, compositions), moyennes semestrielles, classement, appréciations | 🔴 Critique |
| **Conseil de Classe** | Récapitulatif de classe, décisions (passage/redoublement), PV automatique | 🔴 Critique |
| **Documents Officiels** | Bulletins semestriels/annuels, attestations, certificats, cartes scolaires | 🔴 Critique |

### 3.2 Modules Phase 2 : Vie Scolaire & Opérations

| Module | Description | Priorité |
|--------|-------------|----------|
| **Présences & Absences** | Appel par séance, justificatifs, alertes parents, seuils d'alerte | 🟠 Haute |
| **Discipline** | Sanctions, incidents, historique, conseil de discipline, notification parents | 🟠 Haute |
| **Emplois du Temps** | Création EDT par classe, détection conflits, vues multiples | 🟠 Haute |
| **Portail Parent** | Tableau de bord, notes, absences, discipline, bulletins, finances | 🟠 Haute |

### 3.3 Modules Phase 3 : Gestion Financière

| Module | Description | Priorité |
|--------|-------------|----------|
| **Comptabilité & Finances** | Frais scolarité, APE, cantine, paiements, échéanciers, bourses, dépenses | 🟡 Moyenne |
| **Paie Personnel** | Paie enseignants/personnel, heures sup, bulletins de paie | 🟡 Moyenne |

---

## 4. Architecture Technique - Vue d'Ensemble

### 4.1 Stack Technologique

**Backend**
- Laravel 12 (PHP 8.3+)
- Architecture modulaire (Laravel Modules)
- Multi-tenancy (stancl/tenancy)
- API REST (Laravel Sanctum)

**Frontend**
- Next.js 15 (React 18)
- TypeScript
- Tailwind CSS v4
- Architecture Polyrepo

**Base de Données**
- MySQL avec isolation multi-tenant
- Base centrale (superadmin, tenants, domains)
- Bases tenant isolées (tenant_{id})

### 4.2 Architecture Multi-Tenant

```
┌─────────────────────────────────────────────────────────────┐
│                    PLATEFORME CENTRALE                       │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌───────────────────────┐      ┌───────────────────────┐   │
│  │   BACKEND (Laravel)   │◄────►│  FRONTEND (Next.js)   │   │
│  │   - API REST          │      │  - Interface Web      │   │
│  │   - Business Logic    │      │  - Client HTTP        │   │
│  │   - Multi-tenant      │      │  - Header X-Tenant    │   │
│  └───────────────────────┘      └───────────────────────┘   │
│           │                                                  │
│  ┌────────▼──────────────────────────────────────┐          │
│  │          BASES DE DONNÉES ISOLÉES              │          │
│  ├────────────────────────────────────────────────┤          │
│  │  Base Centrale (mysql)                         │          │
│  │  - users (superadmin)                          │          │
│  │  - tenants (établissements)                    │          │
│  │  - domains                                     │          │
│  ├────────────────────────────────────────────────┤          │
│  │  Bases Tenant (tenant_1, tenant_2, ...)       │          │
│  │  - users (admin, enseignants, élèves, parents) │          │
│  │  - structure académique (classes, matières)     │          │
│  │  - inscriptions, notes, discipline, finances   │          │
│  │  - permissions et rôles                        │          │
│  └────────────────────────────────────────────────┘          │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### 4.3 Niveaux d'Accès

1. **SuperAdmin (Central)** : Gestion des tenants, configuration globale, monitoring
2. **Admin / Directeur (Tenant)** : Gestion complète de l'établissement
3. **Censeur / Surveillant Général (Tenant)** : Discipline, absences, vie scolaire
4. **Enseignant (Tenant)** : Saisie notes/présences, consultation EDT
5. **Comptable (Tenant)** : Gestion financière, paie
6. **Élève (Tenant)** : Consultation notes, bulletins, EDT
7. **Parent / Tuteur (Tenant)** : Suivi enfants, notes, absences, discipline, finances

---

## 5. Différenciateurs Compétitifs

### 5.1 Conçu pour le Système Éducatif Nigérien

- **Interface en Français** : Terminologie scolaire nigérienne
- **Conformité Niger** : Semestres, coefficients, séries (A, C, D), BEPC/Bac
- **Conseil de Classe** : Outils d'aide à la décision pour passage/redoublement
- **Performance** : Optimisé pour les connexions Internet variables

### 5.2 Bulletins Semestriels Automatiques

- **Calcul automatique** : Moyennes par matière, moyenne générale, classement
- **Appréciations** : Par matière (enseignant) + générale (conseil de classe)
- **Mentions** : Tableau d'honneur, Encouragements, Félicitations
- **Génération en masse** : Tous les bulletins d'une classe en un clic

### 5.3 Portail Parent Intégré

- **Suivi temps réel** : Notes, absences, discipline
- **Notifications** : Alertes automatiques (absences, sanctions)
- **Multi-enfants** : Un compte parent pour plusieurs enfants
- **Bulletins en ligne** : Téléchargement PDF

### 5.4 Architecture Multi-Tenant

- **Coût Réduit** : Partage d'infrastructure entre établissements
- **Isolation Complète** : Sécurité et confidentialité garanties
- **Déploiement Simplifié** : Un seul déploiement pour tous les établissements
- **Évolutivité** : Ajout de nouveaux établissements sans modification du code

---

## 6. Marché Cible

### 6.1 Segments Prioritaires

1. **Lycées Privés** : Budget disponible, besoin d'image professionnelle
2. **Collèges d'Enseignement Général (CEG)** : Gestion simplifiée, effectifs importants
3. **Lycées Publics** : Effectifs élevés, besoin d'automatisation

### 6.2 Critères de Ciblage

- Établissements d'enseignement secondaire au Niger
- 200 à 2000 élèves (taille optimale pour MVP)
- Volonté de digitalisation
- Capacité d'investissement ou soutien APE

### 6.3 Potentiel de Marché

- **Niger** : Marché significativement plus large que le supérieur (des centaines de collèges et lycées)
- **Extension régionale** : Potentiel d'expansion vers pays francophones d'Afrique de l'Ouest (systèmes éducatifs similaires)

---

## 7. Roadmap Stratégique

### Phase 1 : MVP Core (4-6 mois)
**Focus** : Cycle académique de base + bulletins
- Structure Académique, Inscriptions, Notes & Évaluations, Conseil de Classe, Documents Officiels
- **Objectif** : Cycle complet sans papier (inscription → notes → bulletins)

### Phase 2 : Vie Scolaire & Opérations (3 mois)
**Focus** : Opérations quotidiennes et communication parents
- Présences & Absences, Discipline, Emplois du Temps, Portail Parent
- **Objectif** : Suivi complet de la vie scolaire

### Phase 3 : Gestion Financière (2 mois)
**Focus** : Finances de l'établissement
- Comptabilité & Finances, Paie Personnel
- **Objectif** : Centraliser la gestion financière

### Phase 4 : Analytics & Reporting (1 mois)
**Focus** : Pilotage et décision
- Tableaux de bord direction, statistiques académiques, reporting ministère

### Phase 5 : Advanced Features (Continu)
**Focus** : Innovation
- Notifications SMS, paiements mobile (Orange Money), cahier de textes numérique, mobile apps

---

## 8. Métriques de Succès Globales

### 8.1 Métriques d'Adoption

- **Taux d'adoption** : % utilisateurs actifs / total utilisateurs
- **Nombre d'établissements clients** : Croissance mensuelle
- **Engagement parents** : % parents utilisant activement le portail

### 8.2 Métriques d'Efficacité

- **Gain de temps** : Réduction 80% temps consacré aux bulletins
- **Réduction erreurs** : < 0.5% erreurs dans les bulletins (vs 10-15% manuellement)
- **Délai bulletins** : < 5 min pour une classe de 60 élèves

### 8.3 Métriques de Satisfaction

- **NPS (Net Promoter Score)** : Objectif > 50
- **Satisfaction utilisateurs** : Objectif > 4/5
- **Satisfaction parents** : Objectif > 4/5

### 8.4 Métriques Business

- **Nombre d'établissements** : 5 la première année, 20 en 3 ans
- **Taux de recouvrement** : Amélioration de 25% grâce au suivi automatisé
- **Rétention** : > 90% établissements renouvelant l'abonnement

---

## 9. Risques et Mitigation

### 9.1 Risques Techniques

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Performance sur bande passante limitée | Élevé | Optimisation agressive, lazy loading, compression |
| Qualité des bulletins | Critique | Tests exhaustifs, validation manuelle pilote |
| Complexité multi-tenant | Moyen | Package éprouvé (stancl/tenancy) |

### 9.2 Risques Business

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Résistance au changement des enseignants | Élevé | Formation intensive, champions internes |
| Adoption parentale faible | Moyen | Interface ultra-simple, sensibilisation via APE |
| Scope creep | Moyen | Scope strict, roadmap Phase 2 claire |

### 9.3 Risques Contextuels

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Coupures Internet/électricité | Moyen | Mode dégradé, saisie différée, PWA offline (Phase 2) |
| Dépendance à la connectivité | Moyen | Optimisation pour 3G, pages légères |

---

## 10. Prochaines Étapes

1. **Finaliser les PRDs** : Modules détaillés pour le secondaire
2. **Adapter l'architecture** : Mise à jour architecture brownfield
3. **Identifier pilotes** : 1 collège + 1 lycée motivés et collaboratifs
4. **Démarrage développement** : Sprint 1 - Module Structure Académique
5. **Tests pilotes** : Déploiement sur les 2 établissements testeurs

---

## 11. Documents Connexes

- **[Contexte Business](./01-business-context.md)** : Analyse détaillée du marché
- **[Success Metrics](./02-success-metrics.md)** : KPIs détaillés par module
- **[Technical Constraints](./03-technical-constraints.md)** : Architecture brownfield
- **[User Personas](./04-user-personas.md)** : Profils utilisateurs détaillés
- **[Roadmap](./roadmap.md)** : Planning détaillé par phases
- **[Brief Projet](../brief.md)** : Cahier des charges complet (Secondaire v2.0)
- **[Architecture Brownfield](../brownfield-architecture.md)** : Documentation technique existante

---

**Maintenu par** : John (PM Agent)
**Dernière mise à jour** : 2026-03-16
