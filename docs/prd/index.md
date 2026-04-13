# 📚 Product Requirements Documentation (PRD) - Index

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Version PRD** : v5
> **Date mise à jour** : 2026-03-16
> **Statut** : Sharded PRD (Documentation modulaire)

---

## 🎯 Vue d'ensemble

Ce dossier contient l'ensemble des **Product Requirements Documents (PRD)** du système de gestion scolaire pour les **collèges et lycées** au Niger, avec architecture **multi-tenant** et gestion complète du cycle de vie scolaire.

Le système est développé en mode **brownfield** sur une base Laravel 12 existante, avec une approche modulaire permettant un déploiement progressif par phases.

---

## 📖 Table des matières

### 🌐 Documentation Transverse

Ces documents fournissent le contexte global et les fondations du projet :

1. **[Vision & Overview](./00-overview.md)**
   Vision stratégique, objectifs business et positionnement du système

2. **[Contexte Business](./01-business-context.md)**
   Analyse du marché nigérien, pain points, opportunités et différenciateurs

3. **[Métriques de Succès](./02-success-metrics.md)**
   KPIs, objectifs mesurables et critères de succès par phase

4. **[Contraintes Techniques](./03-technical-constraints.md)**
   Stack technique, contraintes brownfield, architecture multi-tenant

5. **[User Personas](./04-user-personas.md)**
   Profils utilisateurs détaillés (superadmin, admin/directeur, enseignant, élève, parent, comptable, surveillant général)

6. **[Architecture d'Intégration](./05-integration-architecture.md)**
   Architecture globale, intégration inter-modules, API communes

---

### 🧩 PRD Modules Fonctionnels

Documentation détaillée de chaque module avec requirements, user stories et acceptance criteria :

#### **Phase 1 : MVP Core (Priorité Critique 🔴)**

7. **[Module Structure Académique](./module-structure-academique.md)** 🔴
   Fondation du système : années scolaires, semestres, cycles (collège/lycée), classes, séries, matières avec coefficients, affectation enseignants

8. **[Module Inscriptions](./module-inscriptions.md)** 🔴
   Inscription des élèves, liaison parent-élève, affectation en classe, import en masse, réinscription

9. **[Module Notes & Évaluations](./module-notes-evaluations.md)** 🔴
   Saisie des notes (devoirs, interrogations, compositions), calcul moyennes semestrielles avec coefficients, classement, appréciations, mentions

10. **[Module Conseil de Classe](./module-conseil-de-classe.md)** 🔴
    Tableau récapitulatif, statistiques de classe, décisions (passage/redoublement), PV automatique, appréciations générales

11. **[Module Documents Officiels](./module-documents-officiels.md)** 🔴
    Bulletins semestriels et annuels, attestations, certificats de scolarité, cartes scolaires, relevés de notes, reçus de paiement

#### **Phase 2 : Vie Scolaire & Opérations (Priorité Haute 🟠)**

12. **[Module Présences & Absences](./module-presences-absences.md)** 🟠
    Appel par séance, justificatifs, consolidation, alertes parents, seuils d'alerte, rapports

13. **[Module Discipline](./module-discipline.md)** 🟠
    Sanctions (avertissement, blâme, exclusion), incidents, historique par élève, conseil de discipline, notification parents

14. **[Module Emplois du Temps](./module-emplois-du-temps.md)** 🟠
    Création EDT par classe, détection conflits, visualisation multi-vues, consultation multi-rôles, export PDF

15. **[Module Portail Parent](./module-portail-parent.md)** 🟠
    Tableau de bord parent, notes détaillées, absences, discipline, bulletins PDF, finances, multi-enfants, notifications

#### **Phase 3 : Gestion Financière (Priorité Moyenne 🟡)**

16. **[Module Comptabilité & Finances](./module-comptabilite-etudiants.md)** 🟡
    Frais de scolarité (inscription, APE, cantine, tenue), paiements, échéanciers, bourses, dépenses, tableau de bord

17. **[Module Paie Personnel](./module-paie-personnel.md)** 🟡
    Fiches personnel, calcul paie (permanents, vacataires, contractuels), bulletins de paie, états mensuels

---

### 🗺️ Documents de Planification

18. **[Roadmap Produit](./roadmap.md)**
    Planning par phases, jalons, dépendances et timeline de déploiement

19. **[Glossaire Métier](./glossary.md)**
    Définitions des termes de l'enseignement secondaire (semestre, coefficient, conseil de classe, série, etc.)

---

## 🎨 Légende des Priorités

| Icône | Priorité | Description |
|-------|----------|-------------|
| 🔴 | **CRITIQUE** | Phase 1 MVP Core - Fonctionnalités fondamentales bloquantes |
| 🟠 | **HAUTE** | Phase 2 Vie Scolaire & Opérations - Fonctionnalités opérationnelles clés |
| 🟡 | **MOYENNE** | Phase 3 - Gestion financière |
| ⚪ | **BASSE** | Améliorations futures, nice-to-have |

---

## 🔗 Documents Connexes

- **[Brief Projet](../brief.md)** - Cahier des charges (Enseignement Secondaire v2.0)
- **[Architecture Brownfield](../brownfield-architecture.md)** - Architecture technique existante
- **[Backlog Phase 1](../backlog-phase1.md)** - Backlog détaillé MVP Core
- **[Planning Sprints](../planning-sprints.md)** - Organisation des sprints de développement

---

## 📋 Méthodologie de Documentation

### Structure Standard d'un PRD Module

Chaque PRD de module suit cette structure normalisée :

1. **En-tête** : Métadonnées (projet, version, date, phase, priorité)
2. **Change Log** : Historique des versions
3. **Goals and Background Context** : Objectifs et contexte métier
4. **Requirements** : Exigences fonctionnelles et non-fonctionnelles
5. **User Stories** : Stories utilisateur avec acceptance criteria
6. **Out of Scope** : Ce qui n'est PAS couvert dans cette version
7. **Technical Considerations** : Considérations techniques, dépendances, risques
8. **Success Metrics** : Métriques de succès spécifiques au module
9. **Open Questions** : Questions ouvertes nécessitant clarification

### Principes de Rédaction

- **Approche User-Centric** : Toujours partir des besoins utilisateurs
- **Data-Driven** : Justifier les choix par des données métier
- **MVP-Focused** : Prioriser ruthlessly, phase par phase
- **Clear & Concise** : Éviter l'ambiguïté, être précis
- **Iterative** : Les PRDs évoluent avec les retours et apprentissages

---

## 🤝 Contribution

Pour proposer des modifications aux PRDs :

1. Identifier le fichier PRD concerné
2. Proposer les changements avec justification business/technique
3. Mettre à jour le Change Log avec version incrémentée
4. Valider avec l'équipe produit et technique

---

## 📞 Contact

**Product Manager** : John (PM Agent)
**Date dernière mise à jour** : 2026-03-16

---

*Ce document est maintenu automatiquement par le système BMAD™ Core PM Agent.*
