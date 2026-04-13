# Architecture de Gestion Scolaire - Enseignement Secondaire Multi-Tenant

> **Document d'Architecture Brownfield**
>
> Intégration de 12 modules métier dans le système multi-tenant existant
>
> **Version** : 2.0
>
> **Date** : 2026-03-16
>
> **Statut** : Final pour implémentation
>
> **Auteur** : Winston - Architecte Système

---

## Table des Matières

1. [Introduction et Vue d'Ensemble](./introduction.md)
   - Objectif du Document
   - Relation avec la Documentation Existante
   - Modules à Intégrer (12 modules en 3 phases)
   - Contexte Business (Collèges et Lycées au Niger)

2. [Architecture de Haut Niveau](./high-level-architecture.md)
   - Analyse du Système Existant
   - Périmètre de l'Amélioration
   - Stratégie d'Intégration
   - Niveaux d'Accès et Rôles (8 rôles)

3. [Stack Technique](./tech-stack.md)
   - Stack Existante
   - Nouvelles Dépendances Requises
   - Versions et Compatibilité

4. [Modèles de Données](./data-models.md)
   - Module Structure Académique (cycles, classes, matières, coefficients)
   - Module Inscriptions (élèves, parents, affectations)
   - Module Notes & Évaluations (notes, moyennes, bulletins)
   - Module Conseil de Classe (sessions, décisions, PV)
   - Module Documents Officiels (bulletins PDF, attestations)
   - Module Présences & Absences (appel, justificatifs, alertes)
   - Module Discipline (incidents, sanctions, conseil de discipline)
   - Module Emplois du Temps (salles, séances par classe)
   - Module Comptabilité & Finances (frais, paiements, dépenses)
   - Module Paie Personnel (contrats, fiches de paie)

5. [Architecture des Composants](./components.md)
   - Architecture Backend (Laravel)
   - Architecture Frontend (Next.js)
   - Services Transverses

6. [APIs Externes](./external-apis.md)
   - Convention URL
   - Endpoints par Module (12 modules)
   - Format des Réponses
   - Authentification

7. [Workflows Métier](./core-workflows.md)
   - Workflow Inscription Élève + Parent
   - Workflow Saisie Notes & Évaluations
   - Workflow Conseil de Classe
   - Workflow Génération Bulletins Semestriels
   - Workflow Appel & Suivi Absences
   - Workflow Gestion Discipline
   - Workflow Import CSV
   - Workflow Gestion Financière

8. [Arborescence du Code](./source-tree.md)
   - Structure Backend (12 modules Laravel)
   - Structure Frontend (12 modules Next.js)
   - Organisation des Fichiers

9. [Infrastructure et Déploiement](./infrastructure-and-deployment.md)
   - Architecture Base de Données
   - Configuration Multi-tenant
   - Queues et Jobs
   - Cache et Performance

10. [Stratégie de Gestion des Erreurs](./error-handling-strategy.md)
    - Gestion des Erreurs Backend
    - Gestion des Erreurs Frontend
    - Logging et Monitoring

11. [Standards de Codage](./coding-standards.md)
    - Conventions Backend (Laravel)
    - Conventions Frontend (Next.js)
    - Templates de Code

12. [Stratégie et Standards de Tests](./test-strategy-and-standards.md)
    - Tests Backend (PHPUnit)
    - Tests Frontend (Jest)
    - Couverture Cible

13. [Sécurité](./security.md)
    - Authentification et Autorisation
    - Protection des Données (dont mineurs)
    - Bonnes Pratiques

14. [Prochaines Étapes](./next-steps.md)
    - Plan de Développement (3 phases)
    - Phases d'Implémentation
    - Références

---

## Navigation Rapide

- **Pour commencer** : Voir [Introduction](./introduction.md)
- **Comprendre l'architecture** : Voir [Architecture de Haut Niveau](./high-level-architecture.md)
- **Implémenter un module** : Voir [Architecture des Composants](./components.md) et [Standards de Codage](./coding-standards.md)
- **Configurer l'environnement** : Voir [Stack Technique](./tech-stack.md) et [Infrastructure](./infrastructure-and-deployment.md)
- **Commencer le développement** : Voir [Prochaines Étapes](./next-steps.md)

---

## Documents Connexes

- **Brief Projet** : `docs/brief.md` (Secondaire v2.0)
- **PRD** : `docs/prd/` (Modules détaillés pour le secondaire)
- **Architecture Brownfield** : `docs/brownfield-architecture.md`
- **Documentation Modules** : `DOCUMENTATION_MODULES.md`

---

**Version** : 2.0
**Date** : 2026-03-16
**Auteur** : Winston - Architecte Système
**Changement** : Refonte complète — Passage du LMD (supérieur) au secondaire (collèges/lycées)
