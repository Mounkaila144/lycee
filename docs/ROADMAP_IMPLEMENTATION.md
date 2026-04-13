# Roadmap d'Implémentation des Stories

**Date** : 2026-01-10
**Module en cours** : Structure Académique
**Story actuelle** : ✅ CRUD Programmes (TERMINÉ)

---

## 🎯 Prochaines Stories Recommandées

### Option 1 : Continuer Structure Académique (Recommandé)

**Logique** : Construire la fondation académique complète avant de passer aux autres modules.

#### **Phase 1 : Fondations (Hiérarchie Académique)** - 2-3 semaines

| # | Story | Priorité | Dépendances | Complexité |
|---|-------|----------|-------------|------------|
| ✅ | `gestion-programmes.01-crud-programmes` | **FAIT** | - | ⭐⭐⭐ |
| 1️⃣ | `gestion-niveaux.01-association-niveaux-programmes` | 🔴 Haute | Programmes | ⭐⭐ |
| 2️⃣ | `gestion-niveaux.02-configuration-credits-ects` | 🔴 Haute | Niveaux | ⭐⭐ |
| 3️⃣ | `gestion-semestres.01-calendrier-academique` | 🔴 Haute | Niveaux | ⭐⭐⭐ |
| 4️⃣ | `gestion-modules.01-crud-modules-ue` | 🔴 Haute | Semestres | ⭐⭐⭐ |
| 5️⃣ | `gestion-semestres.02-rattachement-modules-semestres` | 🟡 Moyenne | Modules, Semestres | ⭐⭐ |

**Résultat** : Structure académique complète fonctionnelle (Programmes → Niveaux → Semestres → Modules)

---

#### **Phase 2 : Enrichissements Structure** - 1-2 semaines

| # | Story | Priorité | Dépendances | Complexité |
|---|-------|----------|-------------|------------|
| 6️⃣ | `gestion-specialites.01-creation-specialites` | 🟡 Moyenne | Programmes | ⭐⭐ |
| 7️⃣ | `gestion-specialites.02-tronc-commun-options` | 🟡 Moyenne | Spécialités | ⭐⭐ |
| 8️⃣ | `gestion-modules.02-gestion-prerequis` | 🟢 Basse | Modules | ⭐⭐ |
| 9️⃣ | `gestion-niveaux.03-validation-progression-pedagogique` | 🟡 Moyenne | Niveaux | ⭐⭐⭐ |

---

#### **Phase 3 : Affectations & Configuration** - 1 semaine

| # | Story | Priorité | Dépendances | Complexité |
|---|-------|----------|-------------|------------|
| 🔟 | `gestion-modules.03-affectation-enseignants` | 🟡 Moyenne | Modules, Employés | ⭐⭐ |
| 1️⃣1️⃣ | `gestion-modules.04-configuration-evaluations` | 🟡 Moyenne | Modules | ⭐⭐ |
| 1️⃣2️⃣ | `gestion-semestres.03-gestion-periodes-evaluations` | 🟡 Moyenne | Semestres | ⭐⭐ |

---

#### **Phase 4 : Fonctionnalités Avancées Programmes** - 1 semaine

| # | Story | Priorité | Dépendances | Complexité |
|---|-------|----------|-------------|------------|
| 1️⃣3️⃣ | `gestion-programmes.02-activation-desactivation` | 🟢 Basse | Programmes | ⭐ |
| 1️⃣4️⃣ | `gestion-programmes.03-historisation-modifications` | 🟢 Basse | Programmes | ⭐ |
| 1️⃣5️⃣ | `gestion-programmes.04-import-export-programmes` | 🟢 Basse | Programmes | ⭐⭐ |

---

#### **Phase 5 : Rapports** - 3 jours

| # | Story | Priorité | Dépendances | Complexité |
|---|-------|----------|-------------|------------|
| 1️⃣6️⃣ | `rapports.01-maquette-pedagogique-pdf` | 🟡 Moyenne | Tout | ⭐⭐⭐ |
| 1️⃣7️⃣ | `rapports.02-statistiques-structure` | 🟢 Basse | Tout | ⭐⭐ |

---

### Option 2 : Passer aux Inscriptions

**Logique** : Commencer le flux étudiant pour avoir rapidement un MVP utilisable.

| # | Story | Priorité | Dépendances | Complexité |
|---|-------|----------|-------------|------------|
| 1️⃣ | `inscriptions.inscription-administrative.01-creation-dossier-etudiant` | 🔴 Haute | Programmes | ⭐⭐⭐ |
| 2️⃣ | `inscriptions.inscription-administrative.02-modification-dossier` | 🔴 Haute | Dossier étudiant | ⭐⭐ |
| 3️⃣ | `inscriptions.inscription-pedagogique.06-inscription-aux-modules` | 🔴 Haute | Modules | ⭐⭐⭐ |

---

### Option 3 : Passer à la Paie Personnel

**Logique** : Implémenter un autre domaine métier complet (RH/Paie).

| # | Story | Priorité | Dépendances | Complexité |
|---|-------|----------|-------------|------------|
| 1️⃣ | `paie-personnel.gestion-contrats.01-creation-contrats` | 🔴 Haute | - | ⭐⭐⭐ |
| 2️⃣ | `paie-personnel.gestion-contrats.02-types-contrats` | 🔴 Haute | Contrats | ⭐⭐ |
| 3️⃣ | `paie-personnel.gestion-paie.01-calcul-paie-mensuelle` | 🔴 Haute | Contrats | ⭐⭐⭐⭐ |

---

## 🎯 Recommandation Finale

### ✨ **Je recommande : Option 1 - Phase 1**

**Raison** :
- Complète la **fondation académique** nécessaire pour tous les autres modules
- Les **Niveaux** et **Semestres** sont requis pour :
  - Inscriptions pédagogiques
  - Emplois du temps
  - Notes et évaluations
  - Examens
- **Approche incrémentale** : chaque story ajoute une brique solide

### 📋 **Ordre d'implémentation suggéré**

```
1. ✅ Programmes (FAIT)
2. 🔜 Niveaux (association + crédits ECTS)
3. 🔜 Semestres (calendrier académique)
4. 🔜 Modules/UE (CRUD + rattachement semestres)
5. 🔜 Spécialités (optionnel mais utile)
```

Après ces 5 stories, vous aurez une **structure académique complète** et vous pourrez :
- ✅ Implémenter les Inscriptions (étudiants peuvent s'inscrire à des modules)
- ✅ Implémenter les Notes (enseignants notent sur les modules)
- ✅ Implémenter les Emplois du temps (placer les modules dans le temps)

---

## 🚀 Story Suivante Immédiate

### **Story #1 : Association Niveaux-Programmes**

**Fichier** : `structure-academique.gestion-niveaux.01-association-niveaux-programmes.story.md`

**Pourquoi celle-ci ?**
- ✅ Dépend uniquement de Programmes (déjà fait)
- ✅ Nécessaire pour créer les Semestres
- ✅ Complexité moyenne (⭐⭐)
- ✅ Logique métier claire (L1, L2, L3, M1, M2)

**Estimation** : 2-3 heures

---

## 📊 Vue d'Ensemble des Modules

```
Structure Académique  ████████░░░░░░░░  18 stories (1/18 fait - 5%)
Inscriptions          ░░░░░░░░░░░░░░░░  15 stories (0/15 fait - 0%)
Notes & Évaluations   ░░░░░░░░░░░░░░░░  19 stories (0/19 fait - 0%)
Emplois du Temps      ░░░░░░░░░░░░░░░░  17 stories (0/17 fait - 0%)
Examens               ░░░░░░░░░░░░░░░░  13 stories (0/13 fait - 0%)
Présences/Absences    ░░░░░░░░░░░░░░░░  13 stories (0/13 fait - 0%)
Documents Officiels   ░░░░░░░░░░░░░░░░  24 stories (0/24 fait - 0%)
Comptabilité          ░░░░░░░░░░░░░░░░  24 stories (0/24 fait - 0%)
Paie Personnel        ░░░░░░░░░░░░░░░░  23 stories (0/23 fait - 0%)

TOTAL                 ░░░░░░░░░░░░░░░░  166 stories (1/166 fait - 0.6%)
```

---

**Voulez-vous que j'implémente la story suivante (Niveaux-Programmes) ?** 🚀
