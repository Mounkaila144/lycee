# 🗺️ Roadmap Produit - Planning Stratégique

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Version** : v5
> **Date** : 2026-03-16
> **Type** : Documentation Transverse

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 2.0 | Refonte complète - Roadmap adaptée secondaire (11 modules, nouvelles phases) | John (PM) |
| 2026-01-07 | 1.0 | Création initiale - Roadmap produit par phases (LMD) | John (PM) |

---

## 1. Vue d'Ensemble

Cette roadmap définit le **planning stratégique produit** organisé en **5 phases** progressives pour le système de gestion scolaire des collèges et lycées au Niger.

**Approche** :
- ✅ Déploiement incrémental (phase par phase)
- ✅ Validation par établissements pilotes (1 collège + 1 lycée)
- ✅ Feedback utilisateurs intégré entre les phases
- ✅ Priorisation basée sur la valeur business

---

## 2. Timeline Globale

```
┌─────────────────────────────────────────────────────────────────────┐
│                         ROADMAP                                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Phase 1: MVP Core                [████████████████] 4-6 mois       │
│  Structure, Inscriptions, Notes, Conseil de Classe, Documents        │
│                                                                       │
│  Phase 2: Vie Scolaire            [████████████] 3 mois             │
│  Présences, Discipline, EDT, Portail Parent                          │
│                                                                       │
│  Phase 3: Finances                [████████] 2 mois                 │
│  Comptabilité & Finances, Paie Personnel                             │
│                                                                       │
│  Phase 4: Analytics               [████] 1 mois                     │
│  Dashboards, Stats, Reporting Ministère                              │
│                                                                       │
│  Phase 5: Advanced Features       [████████████] Continu            │
│  SMS, Mobile, Paiement Mobile, Cahier de textes                      │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 3. Phase 1 : MVP Core (4-6 mois)

### 3.1 Objectifs

**Vision** : Livrer un MVP fonctionnel permettant le cycle complet sans papier : inscription → notes → bulletins.

**Valeur Business** :
- ✅ Remplacement complet des bulletins manuels
- ✅ Calculs automatiques des moyennes et classements
- ✅ Génération de bulletins professionnels en un clic
- ✅ Validation product-market fit avec 2 établissements pilotes

### 3.2 Modules Inclus

| # | Module | Description |
|---|--------|-------------|
| 1 | **Structure Académique** | Années scolaires, semestres, cycles, classes, séries, matières, coefficients, affectations enseignants |
| 2 | **Inscriptions** | Inscription élèves, liaison parent-élève, affectation classe, import CSV, réinscription |
| 3 | **Notes & Évaluations** | Saisie notes (devoirs, interrogations, compositions), calcul moyennes semestrielles, classement, appréciations, mentions |
| 4 | **Conseil de Classe** | Tableau récapitulatif, statistiques de classe, décisions (passage/redoublement), PV automatique |
| 5 | **Documents Officiels** | Bulletins semestriels/annuels, attestations, certificats, cartes scolaires, relevés de notes |

### 3.3 Critères de Succès

| Métrique | Objectif |
|----------|----------|
| Établissements pilotes | 2 (1 collège + 1 lycée) |
| Bulletins par classe (60 élèves) | < 5 minutes |
| Erreurs dans les bulletins | < 0.5% |
| Adoption enseignants saisie notes | > 80% |

---

## 4. Phase 2 : Vie Scolaire & Opérations (3 mois)

### 4.1 Objectifs

**Vision** : Compléter la gestion de la vie scolaire quotidienne et ouvrir le portail parent.

### 4.2 Modules Inclus

| # | Module | Description |
|---|--------|-------------|
| 6 | **Présences & Absences** | Appel par séance, justificatifs, consolidation, alertes parents, rapports |
| 7 | **Discipline** | Sanctions, incidents, historique par élève, conseil de discipline, notification parents |
| 8 | **Emplois du Temps** | Création EDT par classe, détection conflits, visualisation multi-vues, export PDF |
| 9 | **Portail Parent** | Tableau de bord, notes, absences, discipline, bulletins PDF, finances, multi-enfants |

### 4.3 Critères de Succès

| Métrique | Objectif |
|----------|----------|
| Parents ayant activé le portail | > 40% |
| Absences notifiées aux parents | > 90% |
| Adoption EDT | > 70% établissements |

---

## 5. Phase 3 : Gestion Financière (2 mois)

### 5.1 Modules Inclus

| # | Module | Description |
|---|--------|-------------|
| 10 | **Comptabilité & Finances** | Frais scolarité (inscription, APE, cantine, tenue), paiements, échéanciers, bourses, dépenses, tableau de bord |
| 11 | **Paie Personnel** | Fiches personnel, calcul paie (permanents, vacataires, contractuels), bulletins de paie, états mensuels |

### 5.2 Critères de Succès

| Métrique | Objectif |
|----------|----------|
| Réduction impayés | 20% → 10% |
| Temps génération bulletin paie | < 2 min |
| Paiements enregistrés < 24h | > 90% |

---

## 6. Phase 4 : Analytics & Reporting (1 mois)

### 6.1 Fonctionnalités Clés

| Fonctionnalité | Description |
|----------------|-------------|
| **Dashboard Direction** | Vue d'ensemble : inscriptions, taux de réussite, situation financière |
| **Dashboard Académique** | Statistiques par classe : effectifs, moyennes, taux de réussite |
| **Dashboard Financier** | Revenus, dépenses, trésorerie, impayés |
| **Rapports Ministère** | Export statistiques format requis par le MEN |

---

## 7. Phase 5 : Advanced Features (Continu)

### 7.1 Fonctionnalités (Post-MVP)

| Fonctionnalité | Description | Priorité |
|----------------|-------------|----------|
| **Notifications SMS** | Alertes absences, résultats, relances paiements via SMS | 🟠 Haute |
| **Cahier de textes numérique** | Suivi progression des programmes | 🟠 Haute |
| **Examens blancs** | Organisation examens blancs BEPC/Bac | 🟠 Haute |
| **Documents avancés** | QR codes, watermarks, numérotation sécurisée | 🟡 Moyenne |
| **Gestion bibliothèque** | Catalogue, prêts/retours | 🟡 Moyenne |
| **Gestion cantine** | Inscriptions, repas, paiements | 🟡 Moyenne |
| **Paiement mobile** | Orange Money, Moov Money, Airtel Money | 🟡 Moyenne |
| **Mobile Apps** | iOS/Android avec notifications push | ⚪ Future |
| **Intégration Ministère** | Remontée automatique statistiques | ⚪ Future |
| **Multi-pays** | Adaptation Bénin, Burkina, Mali, Sénégal | ⚪ Future |

---

## 8. Milestones Critiques

| Milestone | Description | Critère Go |
|-----------|-------------|------------|
| **M1** | Structure Académique fonctionnelle | Tests passent, démo réussie |
| **M2** | Inscriptions + Import CSV | 100 élèves importés avec succès |
| **M3** | Notes + Calculs automatiques | Moyennes et classements corrects |
| **M4** | MVP Core Complet (Phase 1) | Bulletins générés, 2 pilotes validés |
| **M5** | Vie Scolaire Complète (Phase 2) | Portail parent opérationnel |
| **M6** | Finances Complètes (Phase 3) | Comptabilité + Paie fonctionnels |

---

## 9. Contraintes

| Contrainte | Description |
|------------|-------------|
| **Budget** | Budget limité, focus MVP avec ressources internes |
| **Équipe** | 2-3 développeurs |
| **Brownfield** | Respect architecture existante |
| **Connectivité** | Internet variable au Niger, optimisation obligatoire |
| **Pilotes** | 2 établissements (1 collège + 1 lycée) |

---

## 10. Documents Connexes

- **[Overview](./00-overview.md)** : Vision stratégique globale
- **[Business Context](./01-business-context.md)** : Marché et opportunités
- **[Success Metrics](./02-success-metrics.md)** : KPIs détaillés par phase
- **[Integration Architecture](./05-integration-architecture.md)** : Séquence déploiement technique

---

**Maintenu par** : John (PM Agent)
**Dernière mise à jour** : 2026-03-16
