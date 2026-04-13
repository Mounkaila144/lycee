# 📊 Success Metrics - Métriques de Succès et KPIs

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Version** : v5
> **Date** : 2026-03-16
> **Type** : Documentation Transverse

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 2.0 | Refonte complète - Métriques adaptées secondaire (collèges/lycées) | John (PM) |
| 2026-01-07 | 1.0 | Création initiale - Métriques de succès et KPIs (LMD) | John (PM) |

---

## 1. Vue d'Ensemble

Ce document définit l'ensemble des **métriques de succès** et **Key Performance Indicators (KPIs)** permettant de mesurer l'atteinte des objectifs business et produit du système de Gestion Scolaire pour l'enseignement secondaire.

Les métriques sont organisées en 4 catégories :
1. **Métriques d'Adoption** : Utilisation et engagement utilisateurs
2. **Métriques d'Efficacité** : Gains opérationnels et performance
3. **Métriques de Satisfaction** : Satisfaction et fidélisation
4. **Métriques Business** : Revenus et rentabilité

---

## 2. Métriques d'Adoption

### 2.1 Adoption Globale

| Métrique | Définition | Objectif | Fréquence |
|----------|------------|----------|-----------|
| **Nombre d'Établissements** | Établissements clients actifs | Année 1: 5, Année 2: 12, Année 3: 20 | Mensuelle |
| **Taux d'Activation** | % établissements ayant terminé l'onboarding | >90% | Mensuelle |
| **Adoption Enseignants** | % enseignants saisissant les notes en ligne | >80% après 1 semestre, >90% après 2 | Semestrielle |
| **Engagement Parents** | % parents ayant activé leur portail | >40% après 1 semestre, >60% après 1 an | Semestrielle |
| **Utilisateurs Actifs Mensuels (MAU)** | Utilisateurs uniques dans le mois | Croissance +10% MoM | Mensuelle |

### 2.2 Adoption par Module

| Module | Métrique Clé | Objectif Phase 1 | Objectif Phase 2 |
|--------|-------------|------------------|------------------|
| **Structure Académique** | % établissements avec structure complète | 100% | 100% |
| **Inscriptions** | % inscriptions via plateforme vs papier | >80% | 95% |
| **Notes & Évaluations** | % notes saisies en ligne vs cahier/Excel | >80% | 95% |
| **Conseil de Classe** | % conseils de classe utilisant le système | >70% | 90% |
| **Documents Officiels** | % bulletins générés automatiquement | >90% | 100% |
| **Présences & Absences** | % appels faits numériquement | N/A | >50% |
| **Discipline** | % sanctions enregistrées dans le système | N/A | >60% |
| **Emplois du Temps** | % établissements utilisant le module | N/A | >70% |
| **Portail Parent** | % parents actifs mensuels | N/A | >50% |
| **Comptabilité & Finances** | % paiements enregistrés dans le système | N/A | >80% |
| **Paie Personnel** | % bulletins de paie générés automatiquement | N/A | >70% |

---

## 3. Métriques d'Efficacité

### 3.1 Gains de Temps

| Processus | Temps Avant (Manuel) | Temps Après (Automatisé) | Gain | Objectif |
|-----------|---------------------|-------------------------|------|----------|
| **Génération bulletins d'une classe (60 élèves)** | 2-3 jours | < 5 minutes | -99% | <5 min |
| **Calcul moyennes et classements** | 1-2 semaines | Instantané | -99% | <1 min |
| **Appel d'une classe** | 5-10 minutes | < 3 minutes | -60% | <3 min |
| **Génération attestation de scolarité** | 1 jour | 1 minute | -99% | <1 min |
| **Recherche information élève** | 10 minutes | 10 secondes | -98% | <10s |
| **Production statistiques ministère** | 5 jours | 1 heure | -95% | <1h |
| **Génération bulletin de paie** | 1h/bulletin | 2 minutes | -97% | <2 min |

**Gain de Temps Total Objectif** : **-80% temps administratif global**

### 3.2 Réduction des Erreurs

| Type d'Erreur | Taux Avant | Taux Après | Objectif |
|---------------|-----------|-----------|----------|
| **Erreurs dans les bulletins** | 10-15% | <0.5% | <0.5% |
| **Erreurs calcul moyennes** | 10% | 0% | 0% |
| **Erreurs classement** | 5-10% | 0% | 0% |
| **Erreurs transcription notes** | 15% | 0% | 0% |
| **Erreurs bulletins de paie** | 10% | <0.5% | <0.5% |

### 3.3 Performance Système

| Métrique | Définition | Objectif | Priorité |
|----------|------------|----------|----------|
| **Response Time (API)** | Temps de réponse moyen API | <200ms (p95) | 🔴 Critique |
| **Page Load Time** | Temps de chargement page | <3s (p95) | 🔴 Critique |
| **Uptime** | Disponibilité du système | >99.5% | 🔴 Critique |
| **PDF Generation Time (1 bulletin)** | Génération bulletin PDF | <3s | 🟠 Haute |
| **PDF Generation Time (classe entière)** | Génération bulletins classe de 60 | <5 min | 🟠 Haute |

---

## 4. Métriques de Satisfaction

### 4.1 Net Promoter Score (NPS)

**Objectifs** :
| Période | NPS Cible |
|---------|-----------|
| Après 1 semestre | >40 |
| Après 1 an | >50 |
| Après 2 ans | >60 |

**Segmentation** :
- NPS par persona (Admin/Directeur, Enseignant, Parent)
- NPS par taille d'établissement

### 4.2 Satisfaction par Persona

| Persona | Métrique Clé | Objectif |
|---------|-------------|----------|
| **Admin / Directeur** | Temps production bulletins d'un semestre | < 1 jour (vs 1-2 semaines) |
| **Enseignant** | Taux d'adoption saisie notes en ligne | > 90% après 2 semestres |
| **Parent** | Fréquence consultation portail | > 2 fois/mois |
| **Comptable** | Temps enregistrement d'un paiement | < 2 minutes |

### 4.3 Taux de Rétention

| Métrique | Définition | Objectif | Fréquence |
|----------|------------|----------|-----------|
| **Rétention Établissements** | % établissements renouvelant abonnement | >90% | Annuelle |
| **Rétention Utilisateurs** | % utilisateurs actifs mois N actifs mois N+1 | >80% | Mensuelle |

---

## 5. Métriques Business

### 5.1 Croissance

| Métrique | Année 1 | Année 2 | Année 3 |
|----------|---------|---------|---------|
| **Établissements clients** | 5 | 12 | 20 |
| **Taux de recouvrement** | Amélioration +15% | +20% | +25% |
| **Rétention annuelle** | >85% | >90% | >95% |

### 5.2 Métriques d'Impact

| Métrique | Objectif | Impact |
|----------|----------|--------|
| **Réduction impayés** | 20% → 10% | Meilleure trésorerie établissements |
| **Temps admin économisé** | -80% | Personnel recentré sur le pédagogique |
| **Bulletins sans erreur** | 99.5% | Confiance parents renforcée |

---

## 6. Métriques par Module

### 6.1 Module Structure Académique

| Métrique | Objectif |
|----------|----------|
| Temps création structure complète | <2 heures |
| % établissements avec structure complète | 100% |

### 6.2 Module Inscriptions

| Métrique | Objectif |
|----------|----------|
| Temps inscription élève moyen | <5 minutes |
| % inscriptions complètes vs brouillons | >90% |
| Temps import masse (100 élèves CSV) | <10 minutes |

### 6.3 Module Notes & Évaluations

| Métrique | Objectif |
|----------|----------|
| Temps saisie notes par matière par classe | <15 minutes |
| % notes saisies avant conseil de classe | 100% |
| Erreurs de calcul détectées | 0% |

### 6.4 Module Conseil de Classe

| Métrique | Objectif |
|----------|----------|
| Temps préparation données conseil de classe | <5 minutes |
| Génération PV automatique | <2 minutes |
| % conseils utilisant le système | >70% Phase 1, >90% Phase 2 |

### 6.5 Module Documents Officiels

| Métrique | Objectif |
|----------|----------|
| Temps génération bulletins d'une classe | <5 minutes (60 élèves) |
| % bulletins générés sans erreur | >99.5% |
| Temps génération attestation individuelle | <30 secondes |

### 6.6 Module Présences & Absences

| Métrique | Objectif |
|----------|----------|
| Temps appel par séance | <3 minutes |
| % absences notifiées aux parents | >90% |
| Détection dépassement seuils d'alerte | 100% |

### 6.7 Module Discipline

| Métrique | Objectif |
|----------|----------|
| % sanctions notifiées aux parents | 100% |
| Temps préparation dossier conseil discipline | <30 minutes |

### 6.8 Module Emplois du Temps

| Métrique | Objectif |
|----------|----------|
| Temps création EDT par classe | <1 heure |
| % conflits détectés automatiquement | 100% |

### 6.9 Module Portail Parent

| Métrique | Objectif |
|----------|----------|
| % parents ayant activé le portail | >40% après 1 semestre |
| Fréquence consultation | >2 fois/mois |
| % parents consultant les bulletins en ligne | >60% |

### 6.10 Module Comptabilité & Finances

| Métrique | Objectif |
|----------|----------|
| Réduction taux impayés | 20% → 10% |
| Temps génération reçu de paiement | <1 minute |
| % paiements enregistrés dans les 24h | >90% |

### 6.11 Module Paie Personnel

| Métrique | Objectif |
|----------|----------|
| Temps génération bulletin de paie | <2 minutes |
| % erreurs bulletins de paie | <0.5% |

---

## 7. KPIs Système

| KPI | Objectif | Fréquence |
|-----|----------|-----------|
| **Taux d'utilisation active** | >80% MAU | Mensuelle |
| **Bulletins générés par semestre** | >2000 pour 700 élèves | Semestrielle |
| **Taux de disponibilité** | >99.5% | Continue |
| **Temps de réponse moyen** | <2s pour 95% requêtes | Continue |
| **Taux résolution bugs critiques** | >90% dans les 48h | Continue |

---

## 8. Objectifs SMART par Phase

### Phase 1 (MVP Core)

| Objectif | Mesurable | Temporel |
|----------|-----------|----------|
| 2 établissements pilotes opérationnels | Comptage | 6 mois |
| Bulletins générés en <5 min / classe | Mesure technique | 6 mois |
| NPS >40 pilotes | Score NPS | 6 mois |
| <0.5% erreurs bulletins | Taux d'erreur | 6 mois |

### Phase 2 (Vie Scolaire)

| Objectif | Mesurable | Temporel |
|----------|-----------|----------|
| 5+ établissements clients | Comptage | 12 mois |
| >40% parents actifs portail | % activation | 12 mois |
| NPS >50 | Score NPS | 12 mois |

### Phase 3 (Financier)

| Objectif | Mesurable | Temporel |
|----------|-----------|----------|
| Réduction impayés 20% → 10% | Taux mesurable | 18 mois |
| >90% rétention établissements | Taux renouvellement | 18 mois |

---

## 9. Documents Connexes

- **[Overview](./00-overview.md)** : Vision stratégique et objectifs globaux
- **[Business Context](./01-business-context.md)** : Analyse marché et opportunités
- **[Roadmap](./roadmap.md)** : Planning produit par phases
- **[PRD Modules](./index.md)** : Détails métriques spécifiques par module

---

**Maintenu par** : John (PM Agent)
**Dernière mise à jour** : 2026-03-16
