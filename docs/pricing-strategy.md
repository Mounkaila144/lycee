# 💰 Stratégie de Pricing - Gestion Scolaire

> **Projet** : Système de Gestion Scolaire LMD Multi-Tenant
> **Version** : 1.0
> **Date** : 2026-01-22
> **Marchés Cibles** : France & Pays Francophones Africains (Niger, Bénin, Burkina Faso, Mali, Sénégal)
> **Modèle** : SaaS - Abonnement Annuel

---

## 📊 Vue d'Ensemble de la Stratégie

### Modèle Économique

**Type de vente** : SaaS (Software as a Service) en abonnement annuel
**Facturation** : Par établissement (tenant) + tranches de nombre d'étudiants
**Engagement** : 12 mois minimum (paiement annuel ou mensuel selon formule)
**Support** : Inclus dans tous les forfaits (niveau variable)

### Positionnement Marché

Le pricing est stratifié pour adresser **3 segments d'établissements** distincts :

1. **Essentiel** : Petits établissements/écoles (100-500 étudiants) cherchant à digitaliser leurs opérations de base
2. **Professionnel** : Établissements moyens (500-2000 étudiants) nécessitant une gestion académique et opérationnelle complète
3. **Entreprise** : Grandes institutions (2000+ étudiants) avec besoins avancés en analytics, finance et RH

---

## 🎯 Tiers d'Abonnement

### 📦 TIER 1 : ESSENTIEL (Basique)

**Public Cible** : Écoles supérieures, petits instituts, établissements en phase de digitalisation

**Capacité** : Jusqu'à 500 étudiants

#### Modules Inclus

✅ **Structure Académique** (Complet)
- Gestion facultés, départements, filières
- Niveaux (L1, L2, L3, M1, M2)
- Modules/UE avec crédits ECTS
- Affectation enseignants aux modules

✅ **Inscriptions** (Complet)
- Inscription administrative
- Inscription pédagogique
- Affectation aux groupes
- Import CSV/Excel
- Gestion statuts étudiants (Actif, Suspendu, Diplômé)

✅ **Notes & Évaluations** (Simplifié)
- Saisie notes par enseignant (CC, TP, Examen, Rattrapage)
- Calcul automatique moyennes modules
- Calcul moyennes semestres avec crédits ECTS
- Application règles LMD (compensation paramétrable)
- Publication des résultats

✅ **Documents Officiels** (Base)
- Génération relevés de notes (PDF)
- Attestations de scolarité
- Attestations d'inscription
- Templates standards (1 modèle par type)

✅ **Authentification & Utilisateurs**
- Multi-tenant avec isolation complète
- Rôles : Admin, Enseignant, Étudiant
- Gestion permissions fines
- Profils utilisateurs basiques

#### Fonctionnalités Limitées

⚠️ **Présences** : Non incluses
⚠️ **Emplois du Temps** : Non inclus
⚠️ **Examens & Planning** : Non inclus
⚠️ **Comptabilité Étudiants** : Non incluse
⚠️ **Paie Personnel** : Non incluse
⚠️ **Personnalisation Templates** : Templates standards uniquement
⚠️ **Support** : Email uniquement (72h de réponse)
⚠️ **Stockage** : 10 GB
⚠️ **API Access** : Non inclus

#### Tarification

| Marché | Tarif Annuel | Tarif Mensuel |
|--------|--------------|---------------|
| **Niger/Afrique** | **350 000 FCFA/an** (~530 EUR) | 35 000 FCFA/mois (~53 EUR) |
| **France** | **1 200 EUR/an** | 120 EUR/mois |

**Tranche supplémentaire** :
- +100 étudiants (Niger) : +50 000 FCFA/an (~76 EUR)
- +100 étudiants (France) : +200 EUR/an

---

### 🏆 TIER 2 : PROFESSIONNEL (Premium)

**Public Cible** : Universités moyennes, instituts établis, écoles multi-sites

**Capacité** : Jusqu'à 2000 étudiants

#### Modules Inclus

✅ **TOUT de ESSENTIEL +**

✅ **Présences & Absences** (Complet)
- Appel par séance (Présent, Absent, Retard, Excusé)
- Upload justificatifs
- Historique présences par étudiant/module
- Alertes seuils d'absences
- Rapports taux de présence

✅ **Emplois du Temps** (Complet)
- Création EDT par groupe et niveau
- Détection automatique conflits (salle, enseignant, groupe)
- Vue grille hebdomadaire
- Consultation multi-rôles (Enseignant, Étudiant, Admin)
- Export PDF/Excel

✅ **Examens & Planning** (Complet)
- Planning sessions examens
- Affectation salles et surveillants
- Détection conflits étudiants (2 examens simultanés)
- Calendrier examens
- Export PDF

✅ **Documents Officiels** (Avancé)
- **TOUT de ESSENTIEL +**
- Génération diplômes (PDF personnalisables)
- Attestations de réussite
- Templates personnalisables (logo, en-têtes établissement)
- Export en masse (batch generation)
- 3 modèles par type de document

✅ **Comptabilité Étudiants** (Simplifié)
- Paramétrage types de frais (inscription, scolarité, carte)
- Facturation automatique
- Enregistrement paiements
- Génération reçus PDF
- Tableau de bord impayés
- Historique paiements par étudiant

#### Fonctionnalités Avancées

✨ **Personnalisation**
- Templates documents personnalisables (logo, couleurs, en-têtes)
- Interface customisable (logo établissement dans le header)

✨ **Support & Formation**
- Support email prioritaire (24h de réponse)
- Support téléphonique (heures ouvrables)
- Formation initiale incluse (2 sessions de 2h)
- Webinaires trimestriels

✨ **Stockage & Performance**
- 50 GB de stockage
- Backups quotidiens automatiques
- Bande passante prioritaire

#### Fonctionnalités Limitées

⚠️ **Paie Personnel** : Non incluse
⚠️ **Analytics Avancés** : Non inclus
⚠️ **API Access** : Accès limité (lecture seule)
⚠️ **Notifications Automatiques** : Email uniquement (pas de SMS)

#### Tarification

| Marché | Tarif Annuel | Tarif Mensuel |
|--------|--------------|---------------|
| **Niger/Afrique** | **1 200 000 FCFA/an** (~1 830 EUR) | 120 000 FCFA/mois (~183 EUR) |
| **France** | **4 500 EUR/an** | 450 EUR/mois |

**Tranche supplémentaire** :
- +200 étudiants (Niger) : +150 000 FCFA/an (~230 EUR)
- +200 étudiants (France) : +600 EUR/an

---

### 💎 TIER 3 : ENTREPRISE (Gold)

**Public Cible** : Grandes universités, réseaux multi-campus, établissements premium

**Capacité** : 2000+ étudiants (illimité)

#### Modules Inclus

✅ **TOUT de PROFESSIONNEL +**

✅ **Paie Personnel** (Complet)
- Fiches personnel (type contrat, salaire fixe/horaire)
- Calcul automatique paie (salaire fixe ou taux horaire)
- Gestion déductions (CNSS, impôts, etc.)
- Génération bulletins de paie PDF
- Historique paiements personnel
- États mensuels masse salariale

✅ **Analytics & Reporting Avancés**
- Tableaux de bord direction (KPIs académiques, financiers, RH)
- Statistiques académiques avancées (taux de réussite, décrochage)
- Reporting ministère automatisé
- Prévisions financières
- Analyse performances enseignants
- Rapports personnalisables

✅ **Documents Officiels** (Premium)
- **TOUT de PROFESSIONNEL +**
- Watermarks dynamiques
- Numérotation unique sécurisée
- QR codes de vérification
- Signatures électroniques
- Archivage long terme (10+ ans)
- Templates illimités

✅ **Notifications Automatiques**
- Email automatiques (résultats, paiements, absences)
- SMS (alertes critiques, échéances paiements)
- Notifications in-app temps réel
- Alertes personnalisables par rôle

#### Fonctionnalités Premium

✨ **Intégrations Avancées**
- API complète (lecture + écriture)
- Webhooks pour événements critiques
- Intégration paiement mobile (Orange Money, Moov Money, MTN)
- SSO (Single Sign-On) avec systèmes existants

✨ **Multi-Campus / Multi-Sites**
- Gestion centralisée de plusieurs campus
- Transferts d'étudiants inter-campus
- Consolidation reporting multi-sites

✨ **Portail Parent** (Phase 2)
- Consultation notes et absences
- Situation financière
- Communication avec administration

✨ **Support & Formation VIP**
- Support 24/7 (email, téléphone, chat)
- Temps de réponse : 2h pour incidents critiques
- Formation illimitée (sur site ou distanciel)
- Accompagnement dédié (Customer Success Manager)
- Webinaires mensuels personnalisés

✨ **Sécurité & Performance**
- Stockage illimité
- Backups quotidiens + réplication
- SLA 99.9% de disponibilité
- Audit logs complets
- Chiffrement avancé des données sensibles

✨ **Customisation**
- Développement de modules sur-mesure (sur devis)
- Workflows personnalisés
- Interface 100% personnalisable (white-label possible)
- Templates documents illimités

#### Tarification

| Marché | Tarif Annuel | Tarif Mensuel |
|--------|--------------|---------------|
| **Niger/Afrique** | **3 000 000 FCFA/an** (~4 575 EUR) | 300 000 FCFA/mois (~458 EUR) |
| **France** | **12 000 EUR/an** | 1 200 EUR/mois |

**Tarification Personnalisée** :
- Au-delà de 5000 étudiants : Pricing sur-mesure
- Multi-campus (3+ campus) : Remise volume 15-30%
- Développements spécifiques : Devis au cas par cas

---

## 📋 Tableau Comparatif des Tiers

| Fonctionnalité | ESSENTIEL | PROFESSIONNEL | ENTREPRISE |
|----------------|-----------|---------------|------------|
| **Capacité Étudiants** | 100-500 | 500-2000 | 2000+ (illimité) |
| **Structure Académique** | ✅ Complet | ✅ Complet | ✅ Complet |
| **Inscriptions** | ✅ Complet | ✅ Complet | ✅ Complet |
| **Notes & Évaluations** | ✅ Simplifié | ✅ Complet | ✅ Complet + Analytics |
| **Documents Officiels** | ✅ Base (1 template) | ✅ Avancé (3 templates) | ✅ Premium (illimité) |
| **Présences & Absences** | ❌ | ✅ Complet | ✅ Complet |
| **Emplois du Temps** | ❌ | ✅ Complet | ✅ Complet |
| **Examens & Planning** | ❌ | ✅ Complet | ✅ Complet |
| **Comptabilité Étudiants** | ❌ | ✅ Simplifié | ✅ Complet + Analytics |
| **Paie Personnel** | ❌ | ❌ | ✅ Complet |
| **Analytics & Reporting** | ❌ Base | ⚠️ Standard | ✅ Avancés |
| **Notifications** | ❌ | ⚠️ Email uniquement | ✅ Email + SMS + In-App |
| **Personnalisation Templates** | ❌ Standard | ✅ Logo + En-têtes | ✅ Illimitée + White-label |
| **API Access** | ❌ | ⚠️ Lecture seule | ✅ Complet + Webhooks |
| **Portail Parent** | ❌ | ❌ | ✅ (Phase 2) |
| **Multi-Campus** | ❌ | ❌ | ✅ |
| **Support** | Email (72h) | Email (24h) + Tél | 24/7 + CSM dédié |
| **Stockage** | 10 GB | 50 GB | Illimité |
| **SLA Disponibilité** | 99% | 99.5% | 99.9% |
| **Formation** | ❌ | ✅ 2 sessions | ✅ Illimitée |
| **Paiement Mobile** | ❌ | ❌ | ✅ Intégré |

---

## 💡 Recommandations Stratégiques

### 1. Politique de Tarification

#### Remises & Incentives

**Paiement Annuel**
- 🎁 **2 mois gratuits** si paiement annuel complet (vs mensuel)
- Économie de ~17% sur le coût total

**Établissements Pilotes**
- 🎁 **50% de réduction** la première année pour les 3 premiers clients (validation MVP)
- Engagement : feedback régulier, participation tests utilisateurs

**Multi-établissements (Groupes)**
- 🎁 **15% de réduction** à partir de 2 établissements
- 🎁 **25% de réduction** à partir de 5 établissements
- 🎁 **30% de réduction** à partir de 10 établissements

**ONG / Établissements Publics**
- 🎁 **20% de réduction** sur tarif Niger/Afrique pour projets à vocation sociale

**Programme de Parrainage**
- 🎁 **1 mois gratuit** pour chaque nouveau client référé qui souscrit (min 6 mois)

#### Période d'Essai

**Tous les tiers** :
- ✅ **30 jours d'essai gratuit** (accès complet au tier choisi)
- ✅ **Formation initiale incluse** pendant la période d'essai
- ✅ **Migration de données assistée** (si abonnement confirmé)

### 2. Add-ons Optionnels (Tous Tiers)

| Add-on | Description | Prix Niger/Afrique | Prix France |
|--------|-------------|--------------------| ------------|
| **Formation Avancée** | Sessions formation supplémentaires (4h) | 100 000 FCFA | 300 EUR |
| **Migration de Données** | Import données historiques assisté | 200 000 FCFA | 600 EUR |
| **Développement Custom** | Module ou fonctionnalité sur-mesure | Sur devis | Sur devis |
| **Stockage Additionnel** | +50 GB de stockage | 50 000 FCFA/an | 150 EUR/an |
| **SMS Premium** | Pack de 5000 SMS supplémentaires | 150 000 FCFA | 450 EUR |
| **Support Étendu** | Support on-site (1 jour) | 300 000 FCFA | 900 EUR |

### 3. Stratégie de Montée en Gamme (Upsell)

**Parcours Client Recommandé** :

1. **Phase d'Essai (30 jours)** : Tier ESSENTIEL
   ↓
2. **Année 1** : Tier ESSENTIEL (validation du système, adoption)
   ↓
3. **Année 2** : Upgrade vers PROFESSIONNEL (ajout présences, EDT, examens)
   ↓
4. **Année 3+** : Upgrade vers ENTREPRISE (gestion financière complète, analytics)

**Triggers d'Upsell** :
- Croissance du nombre d'étudiants (franchissement de seuils)
- Demandes répétées de fonctionnalités du tier supérieur
- Demandes de support/formation au-delà de l'inclus
- Besoin d'intégrations (paiement mobile, API)

### 4. Modèle de Revenus Projetés

#### Scénario Conservateur (Année 1 - Marché Niger)

| Tier | Clients | ARPU (Annual) | Revenus Annuels |
|------|---------|---------------|-----------------|
| ESSENTIEL | 10 | 350 000 FCFA | 3 500 000 FCFA (~5 340 EUR) |
| PROFESSIONNEL | 3 | 1 200 000 FCFA | 3 600 000 FCFA (~5 490 EUR) |
| ENTREPRISE | 1 | 3 000 000 FCFA | 3 000 000 FCFA (~4 575 EUR) |
| **TOTAL** | **14** | - | **10 100 000 FCFA (~15 405 EUR)** |

#### Scénario Optimiste (Année 3 - Marché Niger + France)

| Tier | Clients Niger | Clients France | Revenus Annuels (EUR) |
|------|---------------|----------------|-----------------------|
| ESSENTIEL | 25 | 5 | 19 350 EUR |
| PROFESSIONNEL | 15 | 8 | 63 450 EUR |
| ENTREPRISE | 5 | 3 | 58 875 EUR |
| **TOTAL** | **45** | **16** | **~141 675 EUR** |

### 5. Justification du Pricing

#### Pricing Niger/Afrique

**Rationale** :
- Adapter au pouvoir d'achat local (salaires moyens plus faibles)
- Aligner sur budgets établissements privés nigériens
- Compétitif vs solutions internationales (5-10x moins cher)
- Coût/étudiant : ~700 FCFA/étudiant/an (Essentiel) = très accessible

**Benchmark Marché** :
- Solutions internationales : $5000-$20000/an (3-12M FCFA) → **Trop cher**
- Solutions locales basiques : 500K-1M FCFA → **Notre ESSENTIEL est compétitif**
- Notre positionnement : **Qualité internationale, prix local**

#### Pricing France

**Rationale** :
- Aligner sur coût de vie et budgets français (4-5x plus élevés)
- Comparable aux SaaS éducatifs français/européens
- Coût/étudiant : ~2.4 EUR/étudiant/an (Essentiel) = très compétitif
- Valoriser le support en français et conformité LMD

**Benchmark Marché** :
- Pronote (France) : ~3000-5000 EUR/an pour établissements moyens
- EcoleDirecte : ~2500-4500 EUR/an
- Charlemagne : ~8000-15000 EUR/an pour grandes institutions
- **Notre positionnement : Milieu de marché avec fonctionnalités avancées**

### 6. Go-to-Market par Tier

#### ESSENTIEL
**Cible** : Écoles débutant la digitalisation
**Message** : "Abandonnez le papier sans vous ruiner"
**Canal** : Marketing digital, partenariats avec associations d'écoles

#### PROFESSIONNEL
**Cible** : Établissements établis cherchant l'efficacité
**Message** : "Gestion académique complète, zéro papier"
**Canal** : Vente directe, démonstrations sur site, webinaires

#### ENTREPRISE
**Cible** : Grandes institutions avec besoins complexes
**Message** : "Pilotez votre institution avec data et analytics"
**Canal** : Vente enterprise (CSM dédié), POCs personnalisés

---

## 🎯 Métriques de Succès Pricing

### KPIs à Suivre

1. **ARPU (Average Revenue Per User)** : Revenu moyen par établissement
2. **LTV (Lifetime Value)** : Valeur vie client (objectif : 3+ ans de rétention)
3. **Churn Rate** : Taux d'attrition (objectif : <10%/an)
4. **Upgrade Rate** : % clients passant au tier supérieur (objectif : 30%/an)
5. **CAC (Customer Acquisition Cost)** : Coût d'acquisition client (objectif : <30% de l'ARPU année 1)
6. **Trial-to-Paid Conversion** : % essais gratuits → clients payants (objectif : >40%)

### Objectifs Année 1

- ✅ Acquérir **10-15 clients** (principalement ESSENTIEL + PROFESSIONNEL)
- ✅ Atteindre **10-15M FCFA** de MRR (~15K EUR)
- ✅ Valider le product-market fit avec taux de satisfaction >4/5
- ✅ Taux de renouvellement >85%

---

## 📞 Prochaines Actions

1. **Valider cette stratégie** avec les stakeholders (direction, finance, tech)
2. **Créer les pages pricing** sur le site web et app
3. **Préparer les contrats** (CGV, SLA) par tier
4. **Former l'équipe commerciale** sur le pitch par tier
5. **Lancer le programme pilote** avec 3 établissements (1 par tier)
6. **Itérer** basé sur retours marché (6 mois)

---

**Maintenu par** : John (PM Agent)
**Dernière mise à jour** : 2026-01-22
**Statut** : Draft v1.0 - En validation
