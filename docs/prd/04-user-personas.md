# 👥 User Personas - Profils Utilisateurs

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Version** : v5
> **Date** : 2026-03-16
> **Type** : Documentation Transverse

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 2.0 | Refonte complète - 7 personas secondaire (vs 4 LMD) | John (PM) |
| 2026-01-07 | 1.0 | Création initiale - 4 profils utilisateurs (LMD) | John (PM) |

---

## 1. Vue d'Ensemble

Ce document définit les **7 personas** du système de Gestion Scolaire pour l'enseignement secondaire. Chaque persona représente un segment d'utilisateurs avec des besoins, objectifs et comportements spécifiques.

**7 Personas** :
1. **SuperAdmin** (Central) : Gestionnaire de la plateforme multi-tenant
2. **Admin / Directeur** (Tenant) : Proviseur, Principal, Censeur, Directeur des études
3. **Enseignant** (Tenant) : Professeur permanent, vacataire, contractuel
4. **Élève** (Tenant) : Élève inscrit (6e à Terminale)
5. **Parent / Tuteur** (Tenant) : Parent d'élève, tuteur légal
6. **Comptable / Intendant** (Tenant) : Intendant, économe, caissier
7. **Surveillant Général** (Tenant) : Responsable discipline et absences

---

## 2. Persona 1 : SuperAdmin (Central)

### 2.1 Profil

| Attribut | Valeur |
|----------|--------|
| **Rôle** | SuperAdministrateur Plateforme |
| **Organisation** | Équipe technique centrale |
| **Niveau Technique** | 🟢 Élevé (Informaticien) |
| **Fréquence d'Utilisation** | Quotidienne |

### 2.2 Objectifs Principaux

1. Créer et configurer rapidement les nouveaux tenants (établissements)
2. Créer les comptes Admin pour chaque tenant
3. Surveiller la santé globale du système
4. Résoudre les problèmes techniques escaladés

### 2.3 Besoins Clés

| Besoin | Priorité |
|--------|----------|
| Interface admin central efficace | 🔴 Critique |
| Création tenant en 1 clic | 🟠 Haute |
| Vue d'ensemble usage global | 🟠 Haute |
| Logs et monitoring | 🟠 Haute |

### 2.4 Modules Utilisés

| Module | Actions |
|--------|---------|
| Gestion Tenants | Créer, configurer, suspendre tenants |
| Monitoring Global | Vérifier uptime, performance |
| Gestion Users Central | Ajouter/supprimer SuperAdmins |

---

## 3. Persona 2 : Admin / Directeur (Tenant)

### 3.1 Profil

| Attribut | Valeur |
|----------|--------|
| **Rôle** | Proviseur (lycée), Principal (collège), Censeur, Surveillant général, Directeur des études |
| **Niveau Technique** | 🟡 Moyen (Utilisateur bureautique) |
| **Fréquence d'Utilisation** | Quotidienne |

### 3.2 Comportements Actuels

- Gère les inscriptions sur registres papier
- Crée manuellement les emplois du temps sur tableau
- Compile les résultats pour le conseil de classe à la main
- Produit les statistiques demandées par le Ministère manuellement

### 3.3 Objectifs Principaux

1. Inscrire rapidement les élèves (inscription administrative)
2. Gérer la structure des classes (6e à Terminale, séries, sections)
3. Créer et gérer les emplois du temps sans conflits
4. Piloter les conseils de classe avec données consolidées
5. Générer les bulletins semestriels pour toutes les classes
6. Gérer la discipline (avertissements, exclusions, conseil de discipline)
7. Consulter les statistiques (taux de réussite, absences, finances)

### 3.4 Besoins Clés

| Besoin | Priorité |
|--------|----------|
| Interface intuitive | 🔴 Critique |
| Génération bulletins automatique | 🔴 Critique |
| Tableaux de bord temps réel | 🟠 Haute |
| Outils conseil de classe | 🔴 Critique |
| Support réactif | 🟠 Haute |

### 3.5 Pain Points Actuels

- ⚠️ Bulletins manuscrits : erreurs fréquentes, retards importants
- ⚠️ Gestion Excel dispersée : fichiers multiples, incohérences
- ⚠️ Calculs manuels : moyennes, classements → erreurs, litiges
- ⚠️ Conseil de classe sans données consolidées
- ⚠️ Communication parent quasi inexistante

### 3.6 User Stories Clés

```gherkin
En tant que Directeur,
Je veux générer les bulletins de toute une classe en 1 clic,
Afin de les distribuer rapidement après le conseil de classe.

En tant que Directeur,
Je veux voir un récapitulatif de toutes les notes d'une classe,
Afin de préparer efficacement le conseil de classe.

En tant que Directeur,
Je veux inscrire un élève avec création automatique du compte parent,
Afin de simplifier le processus d'inscription.
```

### 3.7 Objectifs Chiffrés

- Réduire le temps administratif de 70%
- Bulletins d'un semestre : < 1 jour (vs 1-2 semaines)
- Taux d'erreurs dans les bulletins : < 0.5%

---

## 4. Persona 3 : Enseignant

### 4.1 Profil

| Attribut | Valeur |
|----------|--------|
| **Rôle** | Professeur permanent, vacataire, contractuel |
| **Niveau Technique** | 🟡 Variable (basique à intermédiaire) |
| **Fréquence d'Utilisation** | Hebdomadaire (pics en fin de semestre) |

### 4.2 Comportements Actuels

- Tient un cahier de notes physique
- Fait l'appel sur feuille papier
- Calcule les moyennes manuellement
- Rédige les appréciations sur les bulletins papier un par un

### 4.3 Objectifs Principaux

1. Saisir les notes des devoirs, interrogations et compositions
2. Faire l'appel rapidement (présent/absent/retard/excusé)
3. Voir les moyennes calculées automatiquement
4. Saisir les appréciations par élève
5. Consulter son emploi du temps

### 4.4 Besoins Clés

| Besoin | Priorité |
|--------|----------|
| Saisie notes simplifiée | 🔴 Critique |
| Interface mobile-friendly | 🔴 Critique |
| Appel rapide | 🟠 Haute |
| Pas de formation complexe | 🟠 Haute |

### 4.5 Pain Points Actuels

- ⚠️ Cahier de notes : pas de consolidation, risque de perte
- ⚠️ Calculs manuels des moyennes fastidieux
- ⚠️ Appréciations recopiées bulletin par bulletin
- ⚠️ Feuilles d'appel perdues, non consolidées

### 4.6 User Stories Clés

```gherkin
En tant qu'Enseignant,
Je veux saisir les notes d'un devoir pour une classe en 15 minutes,
Afin de ne pas passer trop de temps sur l'administratif.

En tant qu'Enseignant,
Je veux faire l'appel en début de cours via une liste simple,
Afin de gagner du temps (< 3 minutes).

En tant qu'Enseignant,
Je veux voir les moyennes de mes élèves calculées automatiquement,
Afin de préparer mes appréciations pour le conseil de classe.
```

---

## 5. Persona 4 : Élève

### 5.1 Profil

| Attribut | Valeur |
|----------|--------|
| **Rôle** | Élève inscrit (6e à Terminale), âge 11-20 ans |
| **Niveau Technique** | 🟡 Basique (accès principalement smartphone) |
| **Fréquence d'Utilisation** | Hebdomadaire |

### 5.2 Comportements Actuels

- Attend l'affichage des résultats au tableau
- Reçoit le bulletin papier en fin de semestre
- Consulte l'emploi du temps affiché en classe

### 5.3 Objectifs Principaux

1. Consulter son emploi du temps en ligne
2. Voir ses notes au fur et à mesure
3. Consulter ses absences
4. Télécharger ses bulletins semestriels

### 5.4 User Stories Clés

```gherkin
En tant qu'Élève,
Je veux consulter mes notes dès qu'elles sont saisies,
Afin de suivre ma progression.

En tant qu'Élève,
Je veux télécharger mon bulletin semestriel en PDF,
Afin de le montrer à mes parents.
```

---

## 6. Persona 5 : Parent / Tuteur

### 6.1 Profil

| Attribut | Valeur |
|----------|--------|
| **Rôle** | Parent d'élève, tuteur légal, responsable financier |
| **Niveau Technique** | 🟡 Basique (smartphone) |
| **Fréquence d'Utilisation** | Hebdomadaire à mensuelle |

### 6.2 Comportements Actuels

- Attend le bulletin papier pour connaître les résultats
- Se déplace à l'école pour obtenir des informations
- N'est prévenu des problèmes que tardivement (convocations via l'élève)
- Participe aux réunions APE pour les questions financières

### 6.3 Objectifs Principaux

1. Consulter les notes et moyennes de l'enfant en temps réel
2. Être notifié des absences et retards
3. Être alerté en cas de sanction disciplinaire
4. Télécharger les bulletins semestriels (PDF)
5. Voir la situation financière (frais payés, solde restant)
6. Suivre plusieurs enfants dans le même ou différents établissements

### 6.4 Besoins Clés

| Besoin | Priorité |
|--------|----------|
| Interface ultra-simple mobile | 🔴 Critique |
| Notifications proactives | 🔴 Critique |
| Bulletins en ligne | 🔴 Critique |
| Multi-enfants | 🟠 Haute |
| Situation financière | 🟠 Haute |

### 6.5 User Stories Clés

```gherkin
En tant que Parent,
Je veux recevoir une notification quand mon enfant est absent,
Afin de réagir rapidement.

En tant que Parent,
Je veux consulter les notes de mon enfant sur mon smartphone,
Afin de suivre sa progression sans me déplacer à l'école.

En tant que Parent,
Je veux télécharger le bulletin semestriel en PDF,
Afin de l'avoir toujours disponible.

En tant que Parent,
Je veux suivre mes 3 enfants dans le même compte,
Afin de tout gérer depuis une seule interface.
```

---

## 7. Persona 6 : Comptable / Intendant

### 7.1 Profil

| Attribut | Valeur |
|----------|--------|
| **Rôle** | Intendant, économe, caissier |
| **Niveau Technique** | 🟡 Intermédiaire |
| **Fréquence d'Utilisation** | Quotidienne |

### 7.2 Comportements Actuels

- Enregistre les paiements dans des registres papier
- Émet des reçus manuels
- Compile les états financiers manuellement
- Gère la paie sur Excel

### 7.3 Objectifs Principaux

1. Enregistrer les paiements des frais scolaires
2. Générer automatiquement des reçus
3. Voir les impayés en temps réel
4. Gérer les échéanciers de paiement
5. Calculer et gérer la paie du personnel
6. Générer des rapports financiers

### 7.4 User Stories Clés

```gherkin
En tant que Comptable,
Je veux enregistrer un paiement et générer un reçu en moins de 2 minutes,
Afin de servir rapidement les parents.

En tant que Comptable,
Je veux voir la liste des impayés en temps réel,
Afin de lancer les relances appropriées.
```

---

## 8. Persona 7 : Surveillant Général

### 8.1 Profil

| Attribut | Valeur |
|----------|--------|
| **Rôle** | Responsable de la discipline et du suivi des absences |
| **Niveau Technique** | 🟡 Intermédiaire |
| **Fréquence d'Utilisation** | Quotidienne |

### 8.2 Objectifs Principaux

1. Consolider les absences de tous les cours
2. Gérer les sanctions disciplinaires (avertissements, blâmes, exclusions)
3. Convoquer les parents en cas de besoin
4. Préparer les dossiers pour le conseil de discipline
5. Produire les rapports d'absences et de discipline

### 8.3 User Stories Clés

```gherkin
En tant que Surveillant Général,
Je veux voir un tableau consolidé des absences par classe,
Afin d'identifier rapidement les élèves à risque.

En tant que Surveillant Général,
Je veux enregistrer une sanction et notifier automatiquement les parents,
Afin d'assurer une communication rapide.

En tant que Surveillant Général,
Je veux préparer le dossier d'un conseil de discipline en 30 minutes,
Afin d'avoir un dossier complet avec historique.
```

---

## 9. Matrice Besoins vs Personas

| Besoin | SuperAdmin | Admin/Dir | Enseignant | Élève | Parent | Comptable | Surv. Gén. |
|--------|-----------|-----------|-----------|-------|--------|-----------|-----------|
| **Interface intuitive** | 🟡 | 🔴 | 🔴 | 🔴 | 🔴 | 🟠 | 🟠 |
| **Mobile-friendly** | ⚪ | 🟡 | 🔴 | 🔴 | 🔴 | 🟡 | 🟡 |
| **Notifications** | 🟡 | 🟠 | 🟡 | 🟡 | 🔴 | 🟡 | 🟠 |
| **Génération bulletins** | ⚪ | 🔴 | ⚪ | 🟠 | 🔴 | ⚪ | ⚪ |
| **Gestion discipline** | ⚪ | 🔴 | 🟡 | 🟡 | 🟠 | ⚪ | 🔴 |
| **Suivi absences** | ⚪ | 🟠 | 🟠 | 🟡 | 🔴 | ⚪ | 🔴 |
| **Gestion financière** | ⚪ | 🟠 | ⚪ | ⚪ | 🟠 | 🔴 | ⚪ |

**Légende** : 🔴 Critique | 🟠 Haute | 🟡 Moyenne | ⚪ Basse

---

## 10. Priorisation Features par Persona

### 10.1 Phase 1 MVP - Focus

| Persona | Features Critiques MVP |
|---------|------------------------|
| **SuperAdmin** | Gestion tenants, monitoring basique |
| **Admin / Directeur** | Structure académique, inscriptions, notes, bulletins, conseil de classe |
| **Enseignant** | Saisie notes, appréciations |
| **Élève** | Consultation notes (basique) |
| **Parent** | Consultation notes, bulletins (basique) |

### 10.2 Phase 2 - Enrichissement

| Persona | Features Phase 2 |
|---------|------------------|
| **Admin / Directeur** | Présences, discipline, emplois du temps |
| **Enseignant** | Appel numérique, emploi du temps |
| **Parent** | Portail complet, notifications, multi-enfants |
| **Surveillant Général** | Discipline, absences consolidées |
| **Comptable** | Comptabilité, paie |

---

## 11. Documents Connexes

- **[Overview](./00-overview.md)** : Vision globale et positionnement
- **[Business Context](./01-business-context.md)** : Marché cible et opportunités
- **[Success Metrics](./02-success-metrics.md)** : Métriques de satisfaction par persona
- **[PRD Modules](./index.md)** : User stories détaillées par module

---

**Maintenu par** : John (PM Agent)
**Dernière mise à jour** : 2026-03-16
