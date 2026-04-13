# Architecture de Haut Niveau

[← Retour à l'index](./index.md)

---

## 2. Analyse du Système Existant

### 2.1 État Actuel

**Architecture Globale** :
- **Type** : Polyrepo (backend Laravel + frontend Next.js séparés)
- **Backend** : Laravel 12, PHP 8.3.26 — Architecture modulaire (nwidart/laravel-modules)
- **Frontend** : Next.js 15, React 18 — Architecture modulaire miroir
- **Base de données** : MySQL avec architecture multi-tenant
  - Base centrale : `mysql` (users superadmin, tenants, domains)
  - Bases tenant : `tenant_{id}` (isolation complète par établissement)

### 2.2 Module Opérationnel : UsersGuard ✅

**Fonctionnalités** :
- Authentification multi-niveaux (SuperAdmin central, Admin tenant, Frontend tenant)
- Multi-tenancy fonctionnel avec isolation BD complète
- Permissions Spatie au niveau tenant (rôles et permissions)
- API Sanctum pour authentification par tokens Bearer
- Structure backend/frontend complète et opérationnelle

**Patterns Architecturaux Établis** :

**Backend** (`Modules/UsersGuard/`) :
- `Entities/` : Models Eloquent avec `$connection` explicite (mysql/tenant)
- `Http/Controllers/{Admin|Frontend|Superadmin}/` : Controllers par niveau d'accès
- `Http/Requests/` : Form Requests pour validation (array syntax)
- `Http/Resources/` : API Resources (jamais de models bruts en réponse)
- `Database/Migrations/` : Migrations centrales
- `Database/Migrations/tenant/` : Migrations tenant
- `Routes/{admin|frontend|superadmin}.php` : Routes par niveau avec middleware appropriés

**Frontend** (`src/modules/UsersGuard/`) :
- Structure miroir du backend
- 3 couches : `admin/`, `superadmin/`, `frontend/`
- Chaque couche : `components/`, `hooks/`, `services/`
- `types/` : TypeScript interfaces
- `index.ts` : Barrel exports pour API publique du module

### 2.3 Stack Technique Confirmée

**Backend** :
- Laravel 12 + nwidart/laravel-modules v12
- stancl/tenancy v3.9 (multi-tenant avec isolation BD)
- spatie/laravel-permission v6.24 (RBAC)
- laravel/sanctum v4.0 (auth API)

**Frontend** :
- Next.js 15 avec App Router
- React 18 + TypeScript 5.5
- Material-UI (MUI) 6.2
- Axios 1.13.2

### 2.4 Capacité Actuelle vs Besoins

**Forces** :
- ✅ Multi-tenancy opérationnel (peut supporter 20+ établissements)
- ✅ Authentification et permissions robustes
- ✅ Patterns de code bien établis et documentés
- ✅ Architecture modulaire scalable

**Gaps Identifiés** :
- ❌ Aucun module métier scolaire développé
- ❌ Génération de PDF non configurée (nécessite barryvdh/laravel-dompdf)
- ❌ Queues non configurées (nécessaire pour génération documents asynchrone)
- ❌ Cache Redis non configuré (recommandé pour performances)
- ❌ Aucun rôle spécifique secondaire (Enseignant, Élève, Parent, Surveillant Général, Comptable)

---

## 3. Périmètre de l'Amélioration

### 3.1 Vue d'Ensemble

**Type d'Amélioration** : Extension fonctionnelle majeure (Major Feature Extension)

**Scope** : Intégration de 12 modules métier transformant une base d'authentification en plateforme complète de gestion scolaire couvrant l'ensemble du cycle pédagogique, administratif, disciplinaire et financier des collèges et lycées.

**Niveau d'Impact** : **Élevé**

Cette amélioration :
- ✅ N'altère PAS l'architecture existante (respect des patterns établis)
- ✅ Étend significativement les capacités du système (×12 modules)
- ✅ Crée des dépendances entre nouveaux modules
- ✅ Nécessite des ajouts à la BD tenant (45+ nouvelles tables)
- ✅ Ajoute de nouveaux rôles utilisateurs (Enseignant, Élève, Parent, Surveillant Général, Comptable)
- ✅ Introduit de nouvelles fonctionnalités transverses (génération PDF, queues, notifications parents)
- ✅ Nécessite une protection renforcée des données de mineurs

---

## 4. Stratégie d'Intégration

### 4.1 Approche Globale

**Principe** : **Extension modulaire isolée avec réutilisation stricte des patterns établis**

### 4.2 Intégration du Code

**Backend (Laravel)** :
- ✅ Création de 12 nouveaux modules dans `Modules/` suivant exactement la structure UsersGuard
- ✅ **Aucune modification du module UsersGuard** existant
- ✅ Réutilisation des services existants :
  - Middleware tenant (`tenant`, `tenant.auth`)
  - Configuration multi-tenant (stancl/tenancy)
  - Système de permissions Spatie
  - Guards Sanctum (superadmin, tenant)
- ✅ Ajout de services transverses partagés :
  - `App\Services\PdfGeneratorService` : Génération bulletins, attestations, reçus
  - `Modules\Inscriptions\Services\MatriculeGeneratorService` : Génération matricules élèves
  - `Modules\Notes\Services\GradeCalculatorService` : Calculs moyennes semestrielles avec coefficients
  - `Modules\Notes\Services\RankingService` : Classement des élèves
  - `Modules\ConseilDeClasse\Services\ClassCouncilService` : Statistiques de classe, décisions
  - `Modules\Discipline\Services\DisciplineNotificationService` : Notifications parents

**Frontend (Next.js)** :
- ✅ Création de 12 nouveaux modules dans `src/modules/` avec structure miroir backend
- ✅ Réutilisation du client API existant : `createApiClient(tenantId)` avec header `X-Tenant-ID`
- ✅ Réutilisation des hooks existants : `useTenant()`, patterns d'authentification
- ✅ Extension des types TypeScript : Nouveaux types pour entités scolaires
- ✅ Cohérence UI : Réutilisation des composants Material-UI établis

**Principes Clés** :
1. **Isolation** : Chaque nouveau module est autonome avec ses propres Entities, Controllers, Routes
2. **Non-régression** : UsersGuard reste 100% fonctionnel et inchangé
3. **Cohérence** : Respect strict des patterns (Form Requests, API Resources, Type hints)
4. **Découplage** : Communication inter-modules via Eloquent relationships uniquement

### 4.3 Intégration de la Base de Données

**Approche** : **Extension des bases tenant avec nouvelles tables, AUCUNE modification des tables existantes**

**Base Centrale (`mysql`)** : ❌ **AUCUNE MODIFICATION**
- Tables existantes préservées (`users`, `tenants`, `domains`)

**Bases Tenant (`tenant_{id}`)** : ✅ **AJOUT DE 45+ NOUVELLES TABLES**

**Stratégie de Migration** :
1. Migrations tenant séparées pour chaque module (`Modules/{Module}/Database/Migrations/tenant/`)
2. Exécution via `php artisan tenants:migrate` (applique à tous les tenants)
3. Rollback possible par module
4. SoftDeletes sur toutes les tables métier (audit trail)

**Intégrité Référentielle** :
- Foreign keys avec `ON DELETE CASCADE` ou `ON DELETE RESTRICT` selon logique métier
- Indexes sur colonnes fréquemment requêtées
- Contraintes d'unicité (matricules élèves, codes matières, etc.)

### 4.4 Intégration API

**Approche** : **Extension de l'API REST existante avec nouveaux endpoints, respect des conventions**

**Convention URL** : `/api/{niveau}/{module}/{ressource}`

**Exemples de nouveaux endpoints** :

```
# Module Structure Académique
GET    /api/admin/academic-years
GET    /api/admin/classes
GET    /api/admin/subjects
POST   /api/admin/teacher-assignments

# Module Inscriptions
POST   /api/admin/students                # Inscription élève + création compte parent
POST   /api/admin/students/import         # Import CSV
GET    /api/admin/parents                  # Liste parents

# Module Notes (Enseignant)
GET    /api/frontend/my-classes            # Classes assignées
POST   /api/frontend/grades               # Saisie notes
POST   /api/frontend/appreciations        # Saisie appréciations

# Module Notes (Élève)
GET    /api/frontend/my-grades            # Mes notes
GET    /api/frontend/my-report-cards      # Mes bulletins

# Module Portail Parent
GET    /api/frontend/my-children          # Mes enfants
GET    /api/frontend/children/{id}/grades # Notes d'un enfant
GET    /api/frontend/children/{id}/absences # Absences d'un enfant

# Module Discipline
POST   /api/admin/disciplinary-incidents   # Enregistrer incident
GET    /api/admin/students/{id}/discipline # Dossier disciplinaire

# Module Conseil de Classe
GET    /api/admin/class-councils/{id}/summary  # Récapitulatif classe
POST   /api/admin/class-councils/{id}/decisions # Décisions
```

**Authentification** :
- ✅ Réutilisation du système Sanctum existant
- ✅ Middleware `['tenant', 'tenant.auth']` sur toutes les routes protégées
- ✅ Header `X-Tenant-ID` obligatoire pour routes tenant

**Format des Réponses** :
- Success : `{ "message": "...", "data": {...} }` (201 pour création)
- Liste : Pagination Laravel avec `data`, `links`, `meta`
- Erreur validation : `{ "message": "...", "errors": {...} }` (422)
- Erreur auth : 401 (non authentifié), 403 (permissions insuffisantes)

### 4.5 Intégration UI

**Approche** : **Extension de l'interface avec nouveaux modules frontend, cohérence stricte avec l'existant**

**Composants UI Réutilisés** :
- ✅ Layout principal (Header, Sidebar, Footer)
- ✅ Composants Material-UI (Tables, Forms, Modals, Buttons)
- ✅ Patterns de formulaires (validation inline, messages d'erreur)

**Nouveaux Composants UI** :
- `ClassStructureView` : Vue de la structure académique (Cycles → Classes)
- `TimetableGrid` : Grille hebdomadaire pour emplois du temps par classe
- `AttendanceSheet` : Feuille d'appel interactive par séance
- `GradeInputTable` : Table de saisie de notes avec calculs automatiques
- `ReportCardViewer` : Prévisualisation du bulletin semestriel
- `ClassCouncilDashboard` : Tableau de bord du conseil de classe
- `DisciplinaryRecord` : Dossier disciplinaire d'un élève
- `ParentDashboard` : Tableau de bord parent multi-enfants
- `StudentCard` : Carte d'identité scolaire
- `CsvImportWizard` : Wizard multi-étapes pour import CSV

**Cohérence Visuelle** :
- ✅ Même palette de couleurs (Bleu primaire, Vert succès, Orange warning, Rouge erreur)
- ✅ Même typographie et espacements (Material-UI theme)
- ✅ Responsive design maintenu (mobile first pour parents et élèves)

### 4.6 Niveaux d'Accès et Rôles

| # | Rôle | Niveau | Modules Accessibles |
|---|------|--------|---------------------|
| 1 | **SuperAdmin** | Central | Gestion tenants, monitoring global |
| 2 | **Admin / Directeur** | Tenant | Tous modules, configuration établissement |
| 3 | **Censeur** | Tenant | Notes, Conseil de classe, Discipline, Présences, Documents |
| 4 | **Surveillant Général** | Tenant | Présences, Discipline, consultation notes |
| 5 | **Enseignant** | Tenant | Saisie notes, appel, EDT, appréciations |
| 6 | **Comptable / Intendant** | Tenant | Comptabilité, Paie, consultation élèves |
| 7 | **Élève** | Tenant | Consultation notes, bulletins, EDT, absences |
| 8 | **Parent / Tuteur** | Tenant | Portail Parent (notes, absences, discipline, finances, bulletins) |

### 4.7 Exigences de Compatibilité

**Garanties** :

1. **Compatibilité API** : ✅ Aucune modification des endpoints UsersGuard existants
2. **Compatibilité BD** : ✅ Aucune modification des tables existantes
3. **Cohérence UI/UX** : ✅ Même expérience utilisateur pour nouveaux modules
4. **Impact Performance** : ⚠️ Impact modéré acceptable avec mitigations :
   - Indexes optimaux, eager loading, pagination stricte
   - Queues asynchrones pour génération PDF (bulletins en masse)
   - Cache Redis pour données rarement modifiées (structure académique, EDT)
   - Optimisation pour bande passante limitée (contexte Niger)

---

[Suivant : Stack Technique →](./tech-stack.md)
