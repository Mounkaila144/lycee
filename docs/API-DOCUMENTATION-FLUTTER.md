# CRM API - Documentation Complète pour Application Flutter
Endpoint api https://jandoo.ptrniger.com/api
> **Version:** 1.0
> **Base URL:** `https://{tenant-domain}/api`
> **Date:** 2026-02-16
> **Total Endpoints:** 992

---

## Table des Matières

1. [Architecture & Multi-Tenancy](#1-architecture--multi-tenancy)
2. [Authentification](#2-authentification)
3. [Headers Requis](#3-headers-requis)
4. [Gestion des Erreurs](#4-gestion-des-erreurs)
5. [Pagination](#5-pagination)
6. [Module UsersGuard (Utilisateurs)](#6-module-usersguard)
7. [Module StructureAcademique](#7-module-structureacademique)
8. [Module Enrollment (Inscriptions)](#8-module-enrollment)
9. [Module NotesEvaluations](#9-module-notesevaluations)
10. [Module Finance](#10-module-finance)
11. [Module Attendance (Présences)](#11-module-attendance)
12. [Module Exams](#12-module-exams)
13. [Module Timetable (Emploi du temps)](#13-module-timetable)
14. [Module Payroll (Paie)](#14-module-payroll)
15. [Module Documents](#15-module-documents)
16. [Modèles de Données](#16-modèles-de-données)
17. [Guide d'Intégration Flutter](#17-guide-dintégration-flutter)

---

## 1. Architecture & Multi-Tenancy

### Vue d'ensemble

Le CRM API est une application **Laravel 12 multi-tenant** utilisant le package `stancl/tenancy`.

```
┌─────────────────────────────────────────────┐
│              Base de Données Centrale         │
│  ┌──────────┐  ┌──────────┐  ┌───────────┐  │
│  │ tenants  │  │ domains  │  │ superadmin│  │
│  └──────────┘  └──────────┘  └───────────┘  │
└─────────────────────────────────────────────┘
         │
         ├── tenant_1 (Base de données isolée)
         │   └── users, students, grades, invoices...
         │
         ├── tenant_2 (Base de données isolée)
         │   └── users, students, grades, invoices...
         │
         └── tenant_N ...
```

### Identification du Tenant

**Méthode 1 - Par Domaine (Principal):**
```
GET https://university1.example.com/api/admin/users
```

**Méthode 2 - Par Header (Recommandé pour Mobile):**
```
GET https://api.example.com/api/admin/users
X-Tenant-ID: university1
```

---

## 2. Authentification

### 2.1 Login

```
POST /api/admin/auth/login
```

**Corps de la requête:**
```json
{
  "username": "string (requis, max:255)",
  "password": "string (requis, min:6)",
  "application": "string (requis, valeurs: 'admin' | 'frontend')"
}
```

**Réponse succès (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "firstname": "John",
      "lastname": "Doe",
      "full_name": "John Doe",
      "application": "admin",
      "avatar_url": "https://jandoo.ptrniger.com/api/storage/avatars/photo.jpg",
      "roles": ["Administrator"],
      "permissions": ["manage-users", "manage-grades"]
    },
    "token": "1|abc123def456...",
    "token_type": "Bearer",
    "tenant": {
      "id": "university1",
      "name": "Université Example"
    }
  }
}
```

**Réponse erreur (422):**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

### 2.2 Utilisateur Courant

```
GET /api/admin/auth/me
Authorization: Bearer {token}
```

**Réponse (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "firstname": "John",
      "lastname": "Doe",
      "full_name": "John Doe",
      "application": "admin",
      "is_active": true,
      "avatar_url": null,
      "lastlogin": "2026-02-16T10:30:00Z",
      "roles": ["Administrator"],
      "permissions": ["manage-users"]
    },
    "tenant": {
      "id": "university1",
      "name": "Université Example"
    }
  }
}
```

### 2.3 Rafraîchir le Token

```
POST /api/admin/auth/refresh
Authorization: Bearer {token}
```

**Réponse (200):**
```json
{
  "success": true,
  "data": {
    "token": "2|newtoken789...",
    "token_type": "Bearer"
  }
}
```

### 2.4 Déconnexion

```
POST /api/admin/auth/logout
Authorization: Bearer {token}
```

**Réponse (200):**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

### 2.5 Login Superadmin

```
POST /api/superadmin/auth/login
```

Mêmes paramètres que le login admin, mais sur la base de données centrale.

---

## 3. Headers Requis

### Pour toutes les requêtes API

| Header | Valeur | Obligatoire |
|--------|--------|-------------|
| `Content-Type` | `application/json` | Oui |
| `Accept` | `application/json` | Oui |
| `Authorization` | `Bearer {token}` | Oui (sauf login) |
| `X-Tenant-ID` | `{tenant_identifier}` | Oui (si pas de domaine) |

---

## 4. Gestion des Erreurs

### Codes de Statut HTTP

| Code | Signification |
|------|---------------|
| 200 | Succès |
| 201 | Ressource créée |
| 204 | Succès sans contenu |
| 207 | Multi-Status (succès partiel) |
| 400 | Requête invalide |
| 401 | Non authentifié |
| 403 | Accès interdit |
| 404 | Ressource non trouvée |
| 422 | Erreur de validation |
| 500 | Erreur serveur |

### Format des erreurs de validation (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "username": ["Le champ username est obligatoire."],
    "email": ["Le champ email doit être une adresse email valide."]
  }
}
```

### Format des erreurs d'authentification (401)

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

---

## 5. Pagination

La plupart des endpoints de liste retournent des résultats paginés.

**Paramètres de requête:**

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `per_page` | integer | 15 | Nombre d'éléments par page |
| `page` | integer | 1 | Numéro de page |

**Format de réponse paginée:**
```json
{
  "data": [...],
  "links": {
    "first": "https://jandoo.ptrniger.com/api/api/admin/users?page=1",
    "last": "https://jandoo.ptrniger.com/api/api/admin/users?page=5",
    "prev": null,
    "next": "https://jandoo.ptrniger.com/api/api/admin/users?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 73
  }
}
```

---

## 6. Module UsersGuard

### 6.1 Gestion des Utilisateurs

#### Lister les utilisateurs
```
GET /api/admin/users
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `per_page` | integer | Éléments par page (défaut: 15) |
| `search` | string | Recherche par username, email, nom |
| `application` | string | Filtrer: `admin` ou `frontend` |
| `is_active` | boolean | Filtrer par statut actif |

**Réponse:** Collection paginée de `UserResource`

```json
{
  "data": [
    {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "firstname": "John",
      "lastname": "Doe",
      "full_name": "John Doe",
      "application": "admin",
      "is_active": true,
      "sex": "M",
      "phone": "+22790000000",
      "mobile": "+22790000001",
      "avatar_url": null,
      "address": "123 Rue Example",
      "city": "Niamey",
      "country": "Niger",
      "postal_code": "8000",
      "lastlogin": "2026-02-16T10:30:00Z",
      "roles": ["Administrator"],
      "permissions": ["manage-users"],
      "created_at": "2026-01-01T00:00:00Z",
      "updated_at": "2026-02-16T10:30:00Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

#### Créer un utilisateur
```
POST /api/admin/users
```

```json
{
  "username": "string (requis, unique, max:255)",
  "email": "string (requis, unique, email, max:255)",
  "password": "string (requis, min:8)",
  "firstname": "string (requis, max:255)",
  "lastname": "string (requis, max:255)",
  "application": "string (requis, valeurs: 'admin' | 'frontend')",
  "sex": "string (optionnel, valeurs: 'M', 'F', 'Other')",
  "phone": "string (optionnel, max:20)",
  "mobile": "string (optionnel, max:20)",
  "address": "string (optionnel)",
  "city": "string (optionnel, max:255)",
  "country": "string (optionnel, max:255)",
  "postal_code": "string (optionnel, max:20)",
  "is_active": "boolean (optionnel, défaut: true)",
  "roles": ["array de noms de rôles (optionnel)"],
  "permissions": ["array de noms de permissions (optionnel)"]
}
```

**Réponse (201):**
```json
{
  "message": "Utilisateur créé avec succès.",
  "user": {...UserResource...}
}
```

#### Voir un utilisateur
```
GET /api/admin/users/{id}
```

#### Modifier un utilisateur
```
PUT /api/admin/users/{id}
```
Mêmes champs que la création, tous optionnels.

#### Supprimer un utilisateur (soft delete)
```
DELETE /api/admin/users/{id}
```

#### Restaurer un utilisateur
```
POST /api/admin/users/{id}/restore
```

#### Supprimer définitivement
```
DELETE /api/admin/users/{id}/force
```

#### Lister les enseignants
```
GET /api/admin/teachers
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `per_page` | integer | Éléments par page |
| `search` | string | Recherche |

#### Lister les étudiants (users)
```
GET /api/admin/students
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `per_page` | integer | Éléments par page |
| `search` | string | Recherche |
| `program_id` | integer | Filtrer par programme |
| `level_id` | integer | Filtrer par niveau |
| `status` | string | Filtrer par statut |

### 6.2 Gestion des Rôles et Permissions

#### Gérer les rôles d'un utilisateur
```
POST /api/admin/users/{id}/roles/add
POST /api/admin/users/{id}/roles/remove
POST /api/admin/users/{id}/roles/sync
```
```json
{ "roles": ["Administrator", "Professeur"] }
```

#### Gérer les permissions d'un utilisateur
```
POST /api/admin/users/{id}/permissions/add
POST /api/admin/users/{id}/permissions/remove
POST /api/admin/users/{id}/permissions/sync
```
```json
{ "permissions": ["manage-users", "manage-grades"] }
```

### 6.3 CRUD Rôles

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/roles` | Lister les rôles |
| POST | `/api/admin/roles` | Créer un rôle |
| GET | `/api/admin/roles/{id}` | Voir un rôle |
| PUT | `/api/admin/roles/{id}` | Modifier un rôle |
| DELETE | `/api/admin/roles/{id}` | Supprimer un rôle |

**Créer un rôle:**
```json
{
  "name": "string (requis, unique, max:255)",
  "display_name": "string (optionnel, max:255)",
  "description": "string (optionnel)",
  "permissions": ["array de noms de permissions (optionnel)"]
}
```

**Rôles système (non supprimables):** Administrator, Manager, User, Professeur, Étudiant, Caissier, Agent Comptable, Comptable

### 6.4 CRUD Permissions

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/permissions` | Lister les permissions |
| POST | `/api/admin/permissions` | Créer une permission |
| GET | `/api/admin/permissions/{id}` | Voir une permission |
| PUT | `/api/admin/permissions/{id}` | Modifier |
| DELETE | `/api/admin/permissions/{id}` | Supprimer |

### 6.5 Vérification des Permissions

```
GET  /api/api/auth/permissions
POST /api/api/auth/permissions/check
POST /api/api/auth/permissions/batch-check
```

### 6.6 Superadmin - Gestion des Tenants

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/superadmin/tenants` | Lister les tenants |
| POST | `/api/superadmin/tenants` | Créer un tenant |
| GET | `/api/superadmin/tenants/{id}` | Voir un tenant |
| PUT | `/api/superadmin/tenants/{id}` | Modifier |
| DELETE | `/api/superadmin/tenants/{id}` | Supprimer |
| POST | `/api/superadmin/tenants/{id}/toggle-active` | Activer/Désactiver |
| POST | `/api/superadmin/tenants/{id}/domains` | Ajouter un domaine |
| DELETE | `/api/superadmin/tenants/{id}/domains/{domainId}` | Retirer un domaine |

---

## 7. Module StructureAcademique

### 7.1 Programmes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/programmes` | Lister les programmes |
| POST | `/api/admin/programmes` | Créer un programme |
| GET | `/api/admin/programmes/statistics` | Statistiques |
| GET | `/api/admin/programmes/{id}` | Voir un programme |
| PUT | `/api/admin/programmes/{id}` | Modifier |
| DELETE | `/api/admin/programmes/{id}` | Supprimer |
| PATCH | `/api/admin/programmes/{id}/status` | Changer le statut |
| GET | `/api/admin/programmes/{id}/activation-status` | Statut d'activation |
| POST | `/api/admin/programmes/{id}/activate` | Activer |
| POST | `/api/admin/programmes/{id}/deactivate` | Désactiver |

**Filtres pour la liste:**

| Paramètre | Type | Valeurs |
|-----------|------|---------|
| `search` | string | Recherche code ou libellé |
| `type` | string | `Licence`, `Master`, `Doctorat` |
| `statut` | string | `Brouillon`, `Actif`, `Inactif`, `Archivé` |
| `per_page` | integer | Défaut: 15 |

**Créer un programme:**
```json
{
  "code": "string (requis, unique, max:50)",
  "libelle": "string (requis, max:255)",
  "type": "string (requis, valeurs: 'Licence', 'Master', 'Doctorat')",
  "duree_annees": "integer (requis, entre 1 et 8)",
  "description": "string (optionnel)",
  "responsable_id": "integer (optionnel, FK users)"
}
```

**Réponse ProgrammeResource:**
```json
{
  "id": 1,
  "code": "INFO-L",
  "libelle": "Licence Informatique",
  "type": "Licence",
  "duree_annees": 3,
  "description": "...",
  "statut": "Actif",
  "responsable": {"id": 1, "full_name": "Dr. Smith"},
  "levels": ["L1", "L2", "L3"],
  "can_be_modified": true,
  "can_be_deleted": false,
  "can_be_activated": true,
  "created_at": "2026-01-01T00:00:00Z",
  "updated_at": "2026-02-01T00:00:00Z"
}
```

**Statistiques:**
```json
{
  "total": 15,
  "by_type": {"licence": 8, "master": 5, "doctorat": 2},
  "by_statut": {"brouillon": 2, "actif": 10, "inactif": 2, "archive": 1}
}
```

#### Historique & Import/Export des Programmes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/programmes/{id}/history` | Historique des modifications |
| GET | `/api/admin/programmes/{id}/history/compare` | Comparer versions |
| POST | `/api/admin/programmes/{id}/restore/{historyId}` | Restaurer version |
| GET | `/api/admin/programmes/{id}/history/export` | Exporter historique |
| POST | `/api/admin/programmes/import` | Aperçu import |
| POST | `/api/admin/programmes/import/confirm` | Confirmer import |
| GET | `/api/admin/programmes/export/template` | Template d'import |
| GET | `/api/admin/programmes/export/excel` | Export Excel |
| GET | `/api/admin/programmes/export/csv` | Export CSV |

#### Niveaux de Programme

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/programmes/{id}/levels` | Lister les niveaux |
| POST | `/api/admin/programmes/{id}/levels` | Ajouter un niveau |
| DELETE | `/api/admin/programmes/{id}/levels/{level}` | Retirer un niveau |

#### Crédits par Niveau

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/programmes/{id}/credits` | Crédits du programme |
| POST | `/api/admin/programmes/{id}/credits` | Configurer crédits |
| DELETE | `/api/admin/programmes/{id}/credits/{level}` | Supprimer config |
| GET | `/api/admin/programmes/{id}/credits/validate` | Valider crédits |
| GET | `/api/admin/programmes/{id}/credits/effective` | Crédits effectifs |
| GET | `/api/admin/levels/credits` | Crédits globaux |
| POST | `/api/admin/levels/credits` | Configurer crédits globaux |

#### Modules du Programme

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/programmes/{id}/modules` | Modules du programme |
| POST | `/api/admin/programmes/{id}/modules` | Attacher module |
| DELETE | `/api/admin/programmes/{id}/modules/{moduleId}` | Détacher module |
| PUT | `/api/admin/programmes/{id}/modules/sync` | Synchroniser modules |

#### Maquette Pédagogique

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/admin/programmes/{id}/generate-maquette` | Générer maquette |
| POST | `/api/admin/programmes/{id}/preview-maquette` | Aperçu maquette |
| POST | `/api/admin/programmes/{id}/store-maquette` | Sauvegarder maquette |
| GET | `/api/admin/programmes/{id}/maquette/{filename}` | Télécharger |

### 7.2 Modules (UE)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/modules` | Lister les modules |
| POST | `/api/admin/modules` | Créer un module |
| GET | `/api/admin/modules/search` | Rechercher |
| GET | `/api/admin/modules/statistics` | Statistiques |
| GET | `/api/admin/modules/{id}` | Voir un module |
| PUT | `/api/admin/modules/{id}` | Modifier |
| DELETE | `/api/admin/modules/{id}` | Supprimer |

**Filtres:**

| Paramètre | Type | Valeurs |
|-----------|------|---------|
| `search` | string | Recherche code ou nom |
| `level` | string | `L1`, `L2`, `L3`, `M1`, `M2` |
| `semester` | integer | Numéro du semestre |
| `type` | string | `Obligatoire`, `Optionnel` |
| `is_eliminatory` | boolean | Module éliminatoire |

**Créer un module:**
```json
{
  "code": "string (requis, unique, max:50)",
  "name": "string (requis, max:255)",
  "credits_ects": "integer (requis, entre 1 et 30)",
  "coefficient": "decimal (optionnel, entre 0.25 et 10)",
  "type": "string (requis, valeurs: 'Obligatoire', 'Optionnel')",
  "semester": "integer (requis, entre 1 et 4)",
  "level": "string (requis, valeurs: 'L1', 'L2', 'L3', 'M1', 'M2')",
  "description": "string (optionnel)",
  "hours_cm": "integer (requis, min:0)",
  "hours_td": "integer (requis, min:0)",
  "hours_tp": "integer (requis, min:0)",
  "is_eliminatory": "boolean (défaut: false)"
}
```

**Réponse ModuleResource:**
```json
{
  "id": 1,
  "code": "INF101",
  "name": "Algorithmique",
  "credits_ects": 6,
  "coefficient": 3.0,
  "type": "Obligatoire",
  "semester": 1,
  "level": "L1",
  "description": "...",
  "hours_cm": 30,
  "hours_td": 15,
  "hours_tp": 15,
  "total_hours": 60,
  "is_eliminatory": false,
  "programmes": [...],
  "can_be_modified": true,
  "can_be_deleted": true,
  "created_at": "2026-01-01T00:00:00Z"
}
```

#### Prérequis de Modules

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/modules/{id}/prerequisites` | Lister prérequis |
| POST | `/api/admin/modules/{id}/prerequisites` | Ajouter prérequis |
| DELETE | `/api/admin/modules/{id}/prerequisites/{prereqId}` | Retirer |
| GET | `/api/admin/modules/{id}/prerequisites/available` | Prérequis disponibles |
| GET | `/api/admin/modules/{id}/dependency-graph` | Graphe de dépendances |
| POST | `/api/admin/modules/{id}/check-prerequisites` | Vérifier prérequis |

#### Configuration des Évaluations par Module

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/modules/{id}/semesters/{semId}/evaluation-config` | Lister configs |
| POST | `/api/admin/modules/{id}/semesters/{semId}/evaluation-config` | Créer config |
| PUT | `/api/admin/modules/{id}/semesters/{semId}/evaluation-config/{configId}` | Modifier |
| DELETE | `/api/admin/modules/{id}/semesters/{semId}/evaluation-config/{configId}` | Supprimer |
| POST | `/api/admin/modules/{id}/semesters/{semId}/evaluation-config/apply-template/{templateId}` | Appliquer template |
| GET | `/api/admin/modules/{id}/semesters/{semId}/evaluation-config/validate` | Valider config |
| POST | `/api/admin/modules/{id}/semesters/{semId}/evaluation-config/publish` | Publier config |

### 7.3 Années Académiques

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/academic-years` | Lister |
| POST | `/api/admin/academic-years` | Créer |
| GET | `/api/admin/academic-years/{id}` | Voir |
| PUT | `/api/admin/academic-years/{id}` | Modifier |
| DELETE | `/api/admin/academic-years/{id}` | Supprimer |
| PATCH | `/api/admin/academic-years/{id}/activate` | Activer |

### 7.4 Semestres

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/semesters` | Lister |
| POST | `/api/admin/semesters` | Créer |
| GET | `/api/admin/semesters/{id}` | Voir |
| PUT | `/api/admin/semesters/{id}` | Modifier |
| DELETE | `/api/admin/semesters/{id}` | Supprimer |
| POST | `/api/admin/semesters/{id}/close` | Clôturer |
| POST | `/api/admin/semesters/{id}/reopen` | Rouvrir |

#### Modules assignés au Semestre

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/semesters/{id}/modules` | Modules du semestre |
| POST | `/api/admin/semesters/{id}/modules` | Assigner module |
| POST | `/api/admin/semesters/{id}/modules/bulk-assign` | Assignation en masse |
| DELETE | `/api/admin/semesters/{id}/modules/{moduleId}` | Retirer |
| PATCH | `/api/admin/semesters/{id}/modules/{moduleId}/toggle-active` | Activer/Désactiver |

#### Périodes d'Évaluation

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/semesters/{id}/evaluation-periods` | Lister |
| POST | `/api/admin/semesters/{id}/evaluation-periods` | Créer |
| GET | `/api/admin/semesters/{id}/evaluation-periods/{periodId}` | Voir |
| PUT | `/api/admin/semesters/{id}/evaluation-periods/{periodId}` | Modifier |
| DELETE | `/api/admin/semesters/{id}/evaluation-periods/{periodId}` | Supprimer |
| GET | `/api/admin/evaluation-periods/active` | Périodes actives |
| GET | `/api/admin/evaluation-periods/upcoming` | Périodes à venir |
| GET | `/api/admin/evaluation-periods/calendar` | Calendrier |

### 7.5 Périodes Académiques

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/academic-periods` | Lister |
| POST | `/api/admin/academic-periods` | Créer |
| GET | `/api/admin/academic-periods/active` | Actives |
| GET | `/api/admin/academic-periods/upcoming` | À venir |
| GET | `/api/admin/academic-periods/calendar` | Calendrier |
| GET | `/api/admin/academic-periods/{id}` | Voir |
| PUT | `/api/admin/academic-periods/{id}` | Modifier |
| DELETE | `/api/admin/academic-periods/{id}` | Supprimer |

### 7.6 Affectation des Enseignants

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/teacher-assignments` | Lister affectations |
| POST | `/api/admin/teacher-assignments` | Créer |
| GET | `/api/admin/teacher-assignments/{id}` | Voir |
| PUT | `/api/admin/teacher-assignments/{id}` | Modifier |
| DELETE | `/api/admin/teacher-assignments/{id}` | Supprimer |
| POST | `/api/admin/teacher-assignments/{id}/replace` | Remplacer |
| POST | `/api/admin/teacher-assignments/{id}/cancel` | Annuler |
| GET | `/api/admin/teacher-assignments/coverage/report` | Rapport couverture |
| GET | `/api/admin/teachers/{teacherId}/workload/{semesterId}` | Charge horaire |
| GET | `/api/admin/teachers/{teacherId}/annual-workload` | Charge annuelle |
| GET | `/api/admin/modules/{moduleId}/teachers` | Enseignants du module |
| GET | `/api/admin/semesters/{semId}/overloaded-teachers` | Enseignants surchargés |

### 7.7 Templates d'Évaluation

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/evaluation-templates` | Lister |
| POST | `/api/admin/evaluation-templates` | Créer |
| GET | `/api/admin/evaluation-templates/{id}` | Voir |
| PUT | `/api/admin/evaluation-templates/{id}` | Modifier |
| DELETE | `/api/admin/evaluation-templates/{id}` | Supprimer |
| PATCH | `/api/admin/evaluation-templates/{id}/toggle-active` | Activer/Désactiver |

### 7.8 Règles de Progression

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/progression-rules` | Lister les règles |
| POST | `/api/admin/progression-rules` | Créer |
| GET | `/api/admin/progression-rules/{id}` | Voir |
| PUT | `/api/admin/progression-rules/{id}` | Modifier |
| DELETE | `/api/admin/progression-rules/{id}` | Supprimer |
| GET | `/api/admin/programmes/{id}/eliminatory-modules` | Modules éliminatoires |
| POST | `/api/admin/programmes/{id}/eliminatory-modules` | Ajouter éliminatoire |
| DELETE | `/api/admin/eliminatory-modules/{id}` | Retirer |
| POST | `/api/admin/validate-progression` | Valider progression |
| POST | `/api/admin/simulate-progression` | Simuler progression |
| GET | `/api/admin/programmes/{id}/progression-statistics` | Statistiques |

### 7.9 Spécialisations

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/specializations` | Lister |
| POST | `/api/admin/specializations` | Créer |
| GET | `/api/admin/specializations/{id}` | Voir |
| PUT | `/api/admin/specializations/{id}` | Modifier |
| DELETE | `/api/admin/specializations/{id}` | Supprimer |
| GET | `/api/admin/specializations/{id}/candidates` | Candidats |
| POST | `/api/admin/specializations/{id}/assign-students` | Affecter étudiants |
| POST | `/api/admin/specializations/{id}/promote-waitlist` | Promouvoir attente |
| POST | `/api/admin/specializations/{id}/apply` | Postuler |
| DELETE | `/api/admin/specializations/{id}/cancel-application` | Annuler candidature |
| GET | `/api/admin/specializations/{id}/modules` | Modules |
| POST | `/api/admin/specializations/{id}/modules` | Ajouter module |
| DELETE | `/api/admin/specializations/{id}/modules/{moduleId}` | Retirer module |
| GET | `/api/admin/specializations/{id}/electives` | Électifs disponibles |
| POST | `/api/admin/specializations/{id}/choose-electives` | Choisir électifs |
| POST | `/api/admin/specializations/{id}/confirm-electives` | Confirmer électifs |

### 7.10 Statistiques Structure

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/stats/structure/global` | Stats globales |
| GET | `/api/admin/stats/structure/volumes` | Volumes horaires |
| GET | `/api/admin/stats/structure/volumes/by-program` | Volumes par programme |
| GET | `/api/admin/stats/structure/programs/{id}` | Stats d'un programme |
| GET | `/api/admin/stats/structure/credits/by-level` | Crédits par niveau |
| GET | `/api/admin/stats/structure/export` | Export stats |
| POST | `/api/admin/stats/structure/cache/invalidate` | Invalider cache |

---

## 8. Module Enrollment

### 8.1 Gestion des Étudiants (Admin)

#### CRUD Étudiants

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/enrollment/students` | Lister |
| POST | `/api/admin/enrollment/students` | Créer |
| GET | `/api/admin/enrollment/students/{id}` | Voir |
| PUT | `/api/admin/enrollment/students/{id}` | Modifier |
| DELETE | `/api/admin/enrollment/students/{id}` | Supprimer |

**Filtres pour la liste:**

| Paramètre | Type | Valeurs |
|-----------|------|---------|
| `search` | string | Recherche nom, matricule, email |
| `status` | string | `Actif`, `Suspendu`, `Exclu`, `Diplômé` |
| `sex` | string | `M`, `F`, `O` |
| `nationality` | string | Nationalité |
| `sort_by` | string | Champ de tri (défaut: `created_at`) |
| `sort_order` | string | `asc` ou `desc` (défaut: `desc`) |

**Créer un étudiant:**
```json
{
  "firstname": "string (requis, max:255)",
  "lastname": "string (requis, max:255)",
  "birthdate": "date (requis, format: Y-m-d, âge entre 15 et 60 ans)",
  "birthplace": "string (optionnel, max:255)",
  "sex": "string (requis, valeurs: 'M', 'F', 'O')",
  "nationality": "string (requis, max:255)",
  "email": "email (requis, unique)",
  "phone": "string (optionnel, format: +XXXXXXXXXXX)",
  "mobile": "string (requis, format: +XXXXXXXXXXX)",
  "address": "string (optionnel)",
  "city": "string (optionnel, max:255)",
  "country": "string (requis, max:255)",
  "photo": "file (optionnel, image, max: 2048 KB)",
  "emergency_contact_name": "string (optionnel, max:255)",
  "emergency_contact_phone": "string (optionnel, format tel)",
  "programme_id": "integer (requis, existe dans programmes)"
}
```

**Réponse StudentResource:**
```json
{
  "id": 1,
  "matricule": "2026-INF-0001",
  "firstname": "Amadou",
  "lastname": "Diallo",
  "full_name": "Amadou Diallo",
  "birthdate": "2000-05-15",
  "birthplace": "Niamey",
  "age": 25,
  "sex": "M",
  "nationality": "Niger",
  "email": "amadou@example.com",
  "phone": "+22790000000",
  "mobile": "+22790000001",
  "address": "123 Rue...",
  "city": "Niamey",
  "country": "Niger",
  "photo": "students/photo.jpg",
  "photo_url": "https://jandoo.ptrniger.com/api/storage/students/photo.jpg",
  "status": "Actif",
  "is_active": true,
  "is_suspended": false,
  "is_excluded": false,
  "is_graduated": false,
  "emergency_contact_name": "Parent Diallo",
  "emergency_contact_phone": "+22790000002",
  "documents": [...],
  "documents_count": 3,
  "has_complete_documents": false,
  "completeness_percentage": 60,
  "missing_documents": ["diplome_bac", "certificat_medical"],
  "created_at": "2026-01-15T00:00:00Z",
  "updated_at": "2026-02-10T00:00:00Z"
}
```

#### Fonctionnalités spéciales Étudiants

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../students/search/autocomplete?q=ama` | Autocomplétion |
| GET | `.../students/export` | Export Excel |
| GET | `.../students/statistics/summary` | Stats résumé |
| POST | `.../students/check-duplicates` | Vérifier doublons |
| GET | `.../students/{id}/check-completeness` | Complétude documents |
| GET | `.../students/{id}/audit-log` | Journal d'audit |
| POST | `.../students/{id}/documents` | Upload document |
| GET | `.../students/{id}/documents/{docId}` | Télécharger document |
| POST | `.../students/{id}/status` | Changer statut |
| GET | `.../students/{id}/status/history` | Historique statuts |
| GET | `.../students/{id}/status/transitions` | Transitions possibles |
| GET | `.../students/status/statistics` | Stats par statut |

#### Import d'étudiants

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../students/import/template` | Télécharger template CSV |
| POST | `.../students/import/preview` | Aperçu import CSV |
| POST | `.../students/import/revalidate-row` | Revalider une ligne |
| POST | `.../students/import/confirm` | Confirmer import |

### 8.2 Inscriptions Pédagogiques

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/enrollment/enrollments` | Lister inscriptions |
| POST | `/api/admin/enrollment/enrollments` | Créer inscription |
| GET | `.../enrollments/{id}` | Voir |
| PUT | `.../enrollments/{id}` | Modifier |
| DELETE | `.../enrollments/{id}` | Supprimer |
| GET | `.../enrollments/available-modules` | Modules disponibles |
| GET | `.../enrollments/module-enrollments` | Inscriptions modules |
| GET | `.../enrollments/students-in-module` | Étudiants d'un module |
| GET | `.../enrollments/statistics` | Statistiques |
| POST | `.../enrollments/check-prerequisites` | Vérifier prérequis |
| POST | `.../enrollments/{id}/modules` | Ajouter modules |
| DELETE | `.../enrollments/{id}/modules` | Retirer modules |
| GET | `.../enrollments/{id}/sheet` | Fiche d'inscription PDF |
| PUT | `.../module-enrollments/{id}` | Modifier inscription module |

**Créer une inscription:**
```json
{
  "student_id": "integer (requis)",
  "programme_id": "integer (requis)",
  "semester_id": "integer (requis)",
  "level": "string (requis, valeurs: 'L1', 'L2', 'L3', 'M1', 'M2')",
  "group_id": "integer (optionnel)",
  "module_ids": [1, 2, 3],
  "auto_enroll_obligatory": "boolean (défaut: true)"
}
```

### 8.3 Groupes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/enrollment/groups` | Lister |
| POST | `/api/admin/enrollment/groups` | Créer |
| GET | `.../groups/{id}` | Voir |
| PUT | `.../groups/{id}` | Modifier |
| DELETE | `.../groups/{id}` | Supprimer |
| GET | `.../groups/unassigned-students` | Étudiants non assignés |
| POST | `.../groups/auto-assign/preview` | Aperçu auto-assignation |
| POST | `.../groups/auto-assign` | Auto-assigner |
| POST | `.../groups/{id}/assign-student` | Assigner étudiant |
| POST | `.../groups/{id}/move-student` | Déplacer étudiant |
| GET | `.../groups/{id}/students` | Étudiants du groupe |
| GET | `.../groups/{id}/students/export` | Exporter liste |
| GET | `.../groups/{id}/statistics` | Statistiques |
| DELETE | `.../group-assignments/{id}` | Retirer assignation |

### 8.4 Cartes Étudiantes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/enrollment/student-cards` | Lister |
| POST | `.../student-cards/generate/{studentId}` | Générer carte |
| POST | `.../student-cards/batch-generate` | Génération en masse |
| GET | `.../student-cards/{id}` | Voir |
| POST | `.../student-cards/{id}/duplicate` | Duplicata |
| PATCH | `.../student-cards/{id}/status` | Changer statut |
| PATCH | `.../student-cards/{id}/print-status` | Statut impression |
| POST | `.../student-cards/verify` | Vérifier QR code |
| GET | `.../student-cards/{id}/download` | Télécharger PDF |
| GET | `.../student-cards/statistics` | Statistiques |
| DELETE | `.../student-cards/{id}` | Supprimer |

### 8.5 Validation des Inscriptions

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../validation/pending` | En attente |
| GET | `.../validation/stats` | Statistiques |
| POST | `.../validation/batch-validate` | Validation en masse |
| GET | `.../validation/{id}` | Voir |
| GET | `.../validation/{id}/check` | Vérifier |
| POST | `.../validation/{id}/validate` | Valider |
| POST | `.../validation/{id}/reject` | Rejeter |
| GET | `.../validation/{id}/contract` | Contrat PDF |

### 8.6 Réinscriptions

#### Campagnes de réinscription (Admin)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../reenrollment-campaigns` | Lister campagnes |
| POST | `.../reenrollment-campaigns` | Créer |
| GET | `.../reenrollment-campaigns/{id}` | Voir |
| PUT | `.../reenrollment-campaigns/{id}` | Modifier |
| DELETE | `.../reenrollment-campaigns/{id}` | Supprimer |
| POST | `.../reenrollment-campaigns/{id}/activate` | Activer |
| POST | `.../reenrollment-campaigns/{id}/close` | Clôturer |
| GET | `.../reenrollment-campaigns/{id}/statistics` | Statistiques |
| GET | `.../reenrollment-campaigns/{id}/eligible-students` | Étudiants éligibles |

#### Réinscriptions (Admin)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../reenrollments` | Lister |
| POST | `.../reenrollments` | Créer |
| POST | `.../reenrollments/check-eligibility` | Vérifier éligibilité |
| POST | `.../reenrollments/batch-validate` | Validation en masse |
| GET | `.../reenrollments/statistics` | Statistiques |
| GET | `.../reenrollments/{id}` | Voir |
| POST | `.../reenrollments/{id}/validate` | Valider |
| POST | `.../reenrollments/{id}/reject` | Rejeter |
| GET | `.../reenrollments/{id}/confirmation` | Confirmation PDF |

### 8.7 Transferts

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../transfers` | Lister |
| POST | `.../transfers` | Créer |
| GET | `.../transfers/statistics` | Statistiques |
| GET | `.../transfers/{id}` | Voir |
| POST | `.../transfers/{id}/start-review` | Démarrer examen |
| POST | `.../transfers/{id}/analyze` | Analyser équivalences |
| POST | `.../transfers/{id}/validate` | Valider |
| POST | `.../transfers/{id}/integrate` | Intégrer |
| POST | `.../transfers/{id}/reject` | Rejeter |
| GET | `.../transfers/{id}/certificate` | Certificat PDF |

#### Équivalences

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../transfers/{id}/equivalences` | Lister |
| POST | `.../transfers/{id}/equivalences` | Créer |
| POST | `.../transfers/{id}/equivalences/batch-validate` | Validation masse |
| GET | `.../equivalences/{id}` | Voir |
| PUT | `.../equivalences/{id}` | Modifier |
| DELETE | `.../equivalences/{id}` | Supprimer |
| POST | `.../equivalences/{id}/validate` | Valider |
| POST | `.../equivalences/{id}/reject` | Rejeter |

### 8.8 Dispenses de Modules

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../exemptions` | Lister |
| POST | `.../exemptions` | Créer |
| GET | `.../exemptions/pending` | En attente |
| GET | `.../exemptions/statistics` | Statistiques |
| GET | `.../exemptions/{id}` | Voir |
| POST | `.../exemptions/{id}/teacher-review` | Avis enseignant |
| POST | `.../exemptions/{id}/validate` | Valider |
| POST | `.../exemptions/{id}/revoke` | Révoquer |
| GET | `.../exemptions/{id}/certificate` | Certificat PDF |

### 8.9 Statistiques Inscriptions

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../statistics/kpis` | KPIs |
| GET | `.../statistics/by-program` | Par programme |
| GET | `.../statistics/trends` | Tendances |
| GET | `.../statistics/demographics` | Démographie |
| GET | `.../statistics/pedagogical` | Pédagogique |
| GET | `.../statistics/monthly-trends` | Tendances mensuelles |
| GET | `.../statistics/status` | Par statut |
| GET | `.../statistics/comparison` | Comparaison |
| GET | `.../statistics/alerts` | Alertes |
| POST | `.../statistics/clear-cache` | Vider cache |
| POST | `.../reports/executive-summary` | Rapport exécutif |
| POST | `.../reports/dashboard` | Dashboard |
| GET | `.../reports/export/excel` | Export Excel |
| GET | `.../reports/download` | Télécharger rapport |

### 8.10 Exports de Groupes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../group-exports/templates` | Templates |
| POST | `.../group-exports/batch` | Export en masse |
| GET | `.../group-exports/{groupId}/pdf` | PDF |
| GET | `.../group-exports/{groupId}/excel` | Excel |
| GET | `.../group-exports/{groupId}/csv` | CSV |
| GET | `.../group-exports/{groupId}/attendance-sheet` | Feuille de présence |

### 8.11 Endpoints Frontend (Étudiant)

#### Mon inscription
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/frontend/enrollment/my-enrollment/status` | Mon statut |
| GET | `.../my-enrollment/history` | Mon historique |
| GET | `.../my-enrollment/contract` | Mon contrat PDF |

#### Mes groupes
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/frontend/enrollment/my-groups` | Mes groupes |
| GET | `.../my-groups/year/{academicYearId}` | Par année |

#### Ma carte étudiante
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/frontend/enrollment/my-card` | Ma carte |
| GET | `.../my-card/history` | Historique cartes |
| GET | `.../my-card/download` | Télécharger PDF |
| GET | `.../my-card/year/{yearId}` | Carte par année |
| GET | `.../my-card/qr-code` | QR Code |

#### Ma réinscription
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/frontend/enrollment/reenrollment/campaigns` | Campagnes |
| POST | `.../reenrollment/check-eligibility` | Éligibilité |
| POST | `.../reenrollment` | Demander réinscription |
| PUT | `.../reenrollment/{id}` | Modifier |
| POST | `.../reenrollment/{id}/submit` | Soumettre |
| GET | `.../reenrollment/my-status` | Mon statut |
| GET | `.../reenrollment/{id}` | Voir |
| GET | `.../reenrollment/{id}/confirmation` | Confirmation PDF |

#### Mon transfert
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/frontend/enrollment/transfer/programs` | Programmes |
| GET | `.../transfer/academic-year` | Année active |
| POST | `.../transfer` | Demander transfert |
| POST | `.../transfer/check-status` | Vérifier statut |
| GET | `.../transfer/my-requests` | Mes demandes |

#### Mes dispenses
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/frontend/enrollment/exemption/available-modules` | Modules |
| POST | `.../exemption` | Demander dispense |
| GET | `.../exemption/my-requests` | Mes demandes |
| GET | `.../exemption/{id}` | Voir |
| GET | `.../exemption/{id}/certificate` | Certificat PDF |

---

## 9. Module NotesEvaluations

### 9.1 Saisie des Notes (Enseignant - Frontend)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/frontend/teacher/my-modules` | Mes modules |
| GET | `.../modules/{moduleId}/evaluations` | Évaluations du module |
| GET | `.../evaluations/{evalId}/students` | Étudiants de l'évaluation |
| GET | `.../evaluations/{evalId}/statistics` | Statistiques |
| GET | `.../evaluations/{evalId}/export` | Exporter notes |
| GET | `.../evaluations/{evalId}/check-completeness` | Complétude |
| POST | `.../evaluations/{evalId}/publish` | Publier |
| POST | `/api/frontend/teacher/grades/batch` | Saisie en lot |
| POST | `/api/frontend/teacher/grades/auto-save` | Auto-sauvegarde |

**Saisie de notes en lot:**
```json
{
  "evaluation_id": "integer (requis)",
  "grades": [
    {
      "student_id": 1,
      "score": 15.5,
      "is_absent": false,
      "comment": "Bon travail"
    },
    {
      "student_id": 2,
      "score": null,
      "is_absent": true,
      "comment": "Absent non justifié"
    }
  ]
}
```

#### Saisie par lot avancée

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../evaluations/{evalId}/batch-grades` | Saisie batch |
| POST | `.../evaluations/{evalId}/validate-batch` | Valider batch |

#### Import/Export de notes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../grades/import/template` | Template import |
| POST | `.../grades/import/validate` | Valider fichier |
| POST | `.../grades/import/preview` | Aperçu import |
| POST | `.../grades/import/execute` | Exécuter import |
| GET | `.../grades/import/status/{jobId}` | Statut import |

#### Soumission des notes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/frontend/teacher/grades/submit` | Soumettre |
| GET | `/api/frontend/teacher/grades/submission-status` | Statut soumission |

#### Corrections de notes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../grades/{gradeId}/history` | Historique note |
| POST | `.../grades/{gradeId}/request-correction` | Demander correction |
| GET | `.../evaluations/{evalId}/export-history` | Export historique |
| GET | `.../modules/{moduleId}/export-history` | Export historique module |

#### Gestion des absences (Enseignant)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../evaluations/{evalId}/absences` | Absences |
| POST | `.../evaluations/{evalId}/absences/mark-absent` | Marquer absent |
| GET | `.../evaluations/{evalId}/absences/policy` | Politique |
| GET | `.../evaluations/{evalId}/absences/statistics` | Stats |
| GET | `.../evaluations/{evalId}/absences/replacements` | Rattrapages |
| POST | `.../evaluations/{evalId}/absences/schedule-replacement` | Planifier rattrapage |
| POST | `.../replacements/{id}/cancel` | Annuler |
| POST | `.../replacements/{id}/record-grade` | Saisir note rattrapage |

#### Notes de rattrapage (Enseignant)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../retake-modules` | Mes modules rattrapage |
| GET | `.../modules/{id}/retake-students` | Étudiants en rattrapage |
| GET | `.../modules/{id}/retake-statistics` | Stats rattrapage |
| GET | `.../modules/{id}/retake-template` | Template rattrapage |
| POST | `.../modules/{id}/submit-retake-grades` | Soumettre notes |
| POST | `.../retake-grades` | Saisir note |
| POST | `.../retake-grades/batch` | Saisir en lot |

### 9.2 Validation des Notes (Admin)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/grade-validations` | Lister validations |
| GET | `.../grade-validations/statistics` | Statistiques |
| GET | `.../grade-validations/{id}` | Voir |
| POST | `.../grade-validations/{id}/validate` | Valider |
| POST | `.../grade-validations/{id}/reject` | Rejeter |
| POST | `.../grade-validations/{id}/publish` | Publier |
| POST | `.../grade-validations/bulk-publish` | Publication masse |

### 9.3 Approbation des Corrections (Admin)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/correction-requests` | Lister demandes |
| GET | `.../correction-requests/{id}` | Voir |
| POST | `.../correction-requests/{id}/approve` | Approuver |
| POST | `.../correction-requests/{id}/reject` | Rejeter |

### 9.4 Justifications d'Absence (Admin)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/absence-justifications` | Lister |
| GET | `.../absence-justifications/statistics` | Stats |
| GET | `.../absence-justifications/{id}` | Voir |
| GET | `.../absence-justifications/{id}/download` | Télécharger |
| POST | `.../absence-justifications/{id}/approve` | Approuver |
| POST | `.../absence-justifications/{id}/reject` | Rejeter |
| POST | `.../absence-justifications/bulk-approve` | Approbation masse |
| POST | `.../absence-justifications/bulk-reject` | Rejet masse |

### 9.5 Moyennes de Modules

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/modules/{id}/averages` | Moyennes |
| GET | `.../modules/{id}/averages/statistics` | Stats |
| POST | `.../modules/{id}/averages/recalculate` | Recalculer |
| GET | `.../students/{id}/semesters/{semId}/module-grades` | Notes étudiant |

### 9.6 Résultats de Modules

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/modules/{id}/semesters/{semId}/results` | Résultats |
| POST | `.../results/generate` | Générer |
| POST | `.../results/publish` | Publier |
| GET | `.../results/students-by-status` | Par statut |
| GET | `.../results/export` | Exporter |

### 9.7 Résultats Semestriels

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/semesters/{id}/results` | Résultats |
| GET | `.../results/statistics` | Stats |
| GET | `.../results/students-by-status` | Par statut |
| POST | `.../results/recalculate` | Recalculer |
| POST | `.../results/publish` | Publier |
| GET | `.../results/blocked-by-eliminatory` | Bloqués |
| GET | `/api/admin/students/{id}/semesters/{semId}/result` | Résultat étudiant |

### 9.8 Coefficients

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/modules/{id}/coefficients` | Lister |
| PUT | `.../modules/{id}/coefficients/credits` | Modifier crédits |
| GET | `.../modules/{id}/coefficients/credits-history` | Historique crédits |
| POST | `.../modules/{id}/coefficients/apply-template` | Appliquer template |
| PUT | `/api/admin/evaluations/{id}/coefficient` | Modifier coefficient |
| POST | `.../evaluations/{id}/simulate-impact` | Simuler impact |
| GET | `.../evaluations/{id}/coefficient-history` | Historique |
| GET | `/api/admin/coefficient-templates` | Templates |
| POST | `/api/admin/coefficient-templates` | Créer template |

### 9.9 Modules Éliminatoires

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/semesters/{id}/eliminatory/modules` | Modules |
| GET | `.../eliminatory/blocked-students` | Étudiants bloqués |
| GET | `.../eliminatory/statistics` | Stats |
| POST | `/api/admin/modules/{id}/eliminatory/toggle` | Activer/Désactiver |
| PUT | `.../modules/{id}/eliminatory/threshold` | Seuil |
| GET | `.../students/{id}/modules/{modId}/semesters/{semId}/eliminatory-status` | Statut |
| GET | `.../students/{id}/semesters/{semId}/failed-eliminatory` | Modules échoués |

### 9.10 ECTS

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/students/{id}/ects/summary` | Résumé ECTS |
| GET | `.../ects/equivalences` | Équivalences |
| POST | `.../ects/equivalence` | Allouer équivalence |
| GET | `.../ects/progression/{level}` | Progression |
| GET | `.../students/{id}/semesters/{semId}/ects` | ECTS semestre |
| POST | `.../semesters/{semId}/ects/recalculate` | Recalculer |
| GET | `/api/admin/semesters/{semId}/ects/statistics` | Stats ECTS |

### 9.11 Compensation

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/compensation-rules` | Règles |
| PUT | `/api/admin/compensation-rules` | Modifier règles |
| POST | `.../semesters/{id}/compensation/simulate` | Simuler |
| POST | `.../semesters/{id}/compensation/apply` | Appliquer |
| GET | `.../semesters/{id}/compensation/statistics` | Stats |
| GET | `.../students/{id}/compensation-history` | Historique |
| GET | `.../students/{id}/semesters/{semId}/compensable-modules` | Modules compensables |
| POST | `.../students/{id}/semesters/{semId}/compensation/apply` | Appliquer pour étudiant |
| GET | `.../students/{id}/modules/{modId}/semesters/{semId}/can-compensate` | Peut compenser? |
| DELETE | `.../students/{id}/modules/{modId}/semesters/{semId}/compensation` | Révoquer |

### 9.12 Délibérations

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/deliberations` | Lister sessions |
| POST | `/api/admin/deliberations` | Créer session |
| GET | `.../deliberations/decisions-requiring-review` | Décisions à revoir |
| GET | `.../deliberations/sessions/{id}` | Voir session |
| POST | `.../deliberations/sessions/{id}/start` | Démarrer |
| POST | `.../deliberations/sessions/{id}/complete` | Terminer |
| POST | `.../deliberations/sessions/{id}/cancel` | Annuler |
| GET | `.../sessions/{id}/pending-students` | Étudiants en attente |
| GET | `.../sessions/{id}/deliberated-students` | Étudiants délibérés |
| POST | `.../sessions/{id}/decisions` | Enregistrer décision |
| POST | `.../sessions/{id}/bulk-decisions` | Décisions en masse |
| POST | `.../deliberations/decisions/{id}/review` | Revoir décision |
| GET | `/api/admin/students/{id}/deliberation-history` | Historique |

### 9.13 Publications

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/publications` | Lister |
| GET | `.../publications/{id}` | Voir |
| DELETE | `.../publications/{id}` | Dépublier |
| GET | `.../publications/{id}/export` | Exporter |
| GET | `.../semesters/{id}/publication/status` | Statut |
| GET | `.../semesters/{id}/publication/history` | Historique |
| GET | `.../semesters/{id}/publication/can-publish` | Peut publier? |
| POST | `.../semesters/{id}/publication/publish` | Publier |
| GET | `.../students/{id}/published-results` | Résultats publiés |

### 9.14 Rattrapages (Admin)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../semesters/{id}/retakes/identify` | Identifier |
| GET | `.../semesters/{id}/retakes/statistics` | Stats |
| GET | `.../semesters/{id}/retakes/students` | Étudiants |
| GET | `.../semesters/{id}/retakes/students/export` | Export |
| GET | `.../semesters/{id}/retakes/modules` | Modules |
| GET | `.../semesters/{id}/retakes/eligible` | Éligibles |
| GET | `.../modules/{id}/retake-students` | Étudiants module |
| GET | `.../students/{id}/retake-modules` | Modules étudiant |
| GET | `.../retake-enrollments/{id}` | Voir inscription |
| POST | `.../retake-enrollments/{id}/schedule` | Planifier |
| POST | `.../retake-enrollments/{id}/cancel` | Annuler |

### 9.15 Validation Notes Rattrapage (Admin)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../semesters/{id}/retake-grades/pending` | En attente |
| GET | `.../retake-grades/modules-pending` | Modules en attente |
| POST | `.../retake-grades/bulk-validate` | Validation masse |
| GET | `.../modules/{id}/semesters/{semId}/retake-grades/statistics` | Stats |
| POST | `.../retake-grades/validate` | Valider |
| POST | `.../retake-grades/publish` | Publier |
| POST | `/api/admin/retake-grades/{id}/reject` | Rejeter |

### 9.16 Résultats Finaux

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../semesters/{id}/final-results` | Résultats |
| GET | `.../final-results/can-publish` | Peut publier? |
| GET | `.../final-results/statistics` | Stats |
| POST | `.../semesters/{id}/publish-final-results` | Publier |
| POST | `.../semesters/{id}/lock-year` | Verrouiller année |
| GET | `.../semesters/{id}/is-locked` | Est verrouillé? |
| GET | `.../students/{id}/semesters/{semId}/final-result` | Résultat étudiant |
| GET | `.../students/{id}/semesters/{semId}/debts` | Dettes |
| GET | `.../students/{id}/semesters/{semId}/attestation` | Attestation PDF |

### 9.17 Classement

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../semesters/{id}/ranking` | Classement |
| POST | `.../semesters/{id}/ranking/calculate` | Calculer |
| GET | `.../ranking/top` | Top étudiants |
| GET | `.../ranking/mention-distribution` | Mentions |
| GET | `.../ranking/palmares` | Palmarès |
| GET | `.../ranking/improving-students` | En progression |
| GET | `.../ranking/export` | Exporter |
| GET | `.../students/{id}/semesters/{semId}/position` | Position |
| GET | `.../students/{id}/ranking-evolution` | Évolution |

### 9.18 Procès-Verbaux

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../deliberation-sessions/{id}/generate-pv` | Générer PV |
| POST | `.../deliberation-sessions/{id}/regenerate-pv` | Régénérer |
| GET | `.../deliberation-sessions/{id}/pv-preview` | Aperçu |
| GET | `/api/admin/pv/search` | Rechercher |
| GET | `/api/admin/pv/{id}` | Voir |
| GET | `/api/admin/pv/{id}/download` | Télécharger |
| DELETE | `/api/admin/pv/{id}` | Supprimer |
| GET | `.../semesters/{id}/pv-history` | Historique PV |
| GET | `.../academic-years/{id}/summary-report` | Rapport annuel |

### 9.19 Statistiques & Analytics

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../statistics/semesters/{id}/global` | Stats globales |
| GET | `.../statistics/semesters/{id}/modules` | Stats modules |
| GET | `.../statistics/semesters/{id}/programmes` | Stats programmes |
| GET | `.../statistics/semesters/{id}/distribution` | Distribution |
| GET | `.../statistics/semesters/{id}/top-performers` | Meilleurs |
| GET | `.../statistics/semesters/{id}/dashboard` | Dashboard |
| GET | `.../statistics/semesters/{id}/export` | Export |
| GET | `.../statistics/academic-years/{id}/comparison` | Comparaison |
| GET | `.../statistics/programmes/{id}/historical` | Historique |
| GET | `.../analytics/semesters/{id}/kpis` | KPIs |
| GET | `.../analytics/semesters/{id}/weak-modules` | Modules faibles |
| GET | `.../analytics/semesters/{id}/cohort-analysis` | Analyse cohorte |
| GET | `.../analytics/semesters/{id}/at-risk-students` | À risque |
| GET | `.../analytics/semesters/{id}/correlation-matrix` | Corrélations |
| GET | `.../analytics/semesters/{id}/dashboard` | Dashboard analytics |
| GET | `.../analytics/academic-years/{id}/historical-comparison` | Comparaison historique |

---

## 10. Module Finance

### 10.1 Types de Frais

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/finance/fee-types` | Lister |
| POST | `/api/admin/finance/fee-types` | Créer |
| PUT | `/api/admin/finance/fee-types/{id}` | Modifier |

### 10.2 Factures

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/finance/invoices` | Lister |
| POST | `/api/admin/finance/invoices` | Créer |
| POST | `.../invoices/generate-automated` | Génération auto |
| GET | `/api/admin/finance/invoices/{id}` | Voir |
| PUT | `/api/admin/finance/invoices/{id}` | Modifier |
| DELETE | `/api/admin/finance/invoices/{id}` | Supprimer |
| POST | `.../invoices/{id}/payment-schedule` | Échéancier |
| GET | `.../invoices/{id}/late-fees` | Pénalités retard |

### 10.3 Paiements

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/finance/payments` | Lister |
| POST | `/api/admin/finance/payments` | Enregistrer |
| POST | `.../payments/partial` | Paiement partiel |
| GET | `/api/admin/finance/payments/{id}` | Voir |
| GET | `.../payments/{id}/receipt` | Reçu |
| POST | `.../payments/{id}/refund` | Remboursement |
| GET | `.../payments/reconciliation/data` | Réconciliation |
| GET | `.../payments/summary/daily` | Résumé journalier |

### 10.4 Réductions

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/finance/discounts` | Lister |
| POST | `/api/admin/finance/discounts` | Appliquer |
| POST | `.../discounts/{id}/approve` | Approuver |
| DELETE | `.../discounts/{id}` | Révoquer |

### 10.5 Recouvrement

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../collection/reminders/generate` | Générer rappels |
| POST | `.../collection/reminders/send` | Envoyer rappels |
| GET | `.../collection/reminders` | Lister rappels |
| POST | `.../collection/blocks` | Bloquer services |
| POST | `.../collection/blocks/{id}/unblock` | Débloquer |
| GET | `.../collection/blocks` | Lister blocages |
| GET | `.../collection/blocks/check` | Vérifier blocages |
| POST | `.../collection/blocks/auto-process` | Blocage auto |
| POST | `.../collection/payment-plans` | Plan de paiement |
| POST | `.../collection/write-off/{id}` | Passer en perte |
| GET | `.../collection/statistics` | Statistiques |

### 10.6 Rapports Finance

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../reports/dashboard` | Dashboard |
| GET | `.../reports/payment-journal` | Journal paiements |
| GET | `.../reports/aging-balance` | Balance âgée |
| GET | `.../reports/unpaid-statements` | Impayés |
| GET | `.../reports/cash-flow-forecast` | Prévision trésorerie |
| GET | `.../reports/collection-statistics` | Stats recouvrement |
| GET | `.../reports/accounting-export` | Export comptable |
| GET | `.../reports/export/excel` | Export Excel |
| GET | `.../reports/export/pdf` | Export PDF |
| GET | `.../reports/summary` | Résumé |
| POST | `.../reports/clear-cache` | Vider cache |

---

## 11. Module Attendance

### 11.1 Sessions de Présence

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/attendance/sessions` | Lister sessions |
| POST | `/api/admin/attendance/sessions` | Créer session |
| GET | `.../sessions/{id}/sheet` | Feuille de présence |
| POST | `.../sessions/{id}/complete` | Compléter session |
| POST | `/api/admin/attendance/record` | Enregistrer présence |
| PUT | `.../attendance/records/{id}` | Modifier |
| POST | `/api/admin/attendance/record-qr` | Présence QR code |

### 11.2 Justifications

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/justifications` | Lister |
| POST | `/api/admin/justifications` | Soumettre |
| GET | `.../justifications/pending` | En attente |
| GET | `.../justifications/students/{studentId}` | Par étudiant |
| POST | `.../justifications/{id}/validate` | Valider |
| GET | `.../justifications/{id}/download` | Télécharger |

### 11.3 Monitoring & Alertes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../monitoring/check-thresholds` | Vérifier seuils |
| POST | `.../monitoring/trigger-alerts` | Déclencher alertes |
| GET | `.../monitoring/alerts` | Alertes actives |
| GET | `.../monitoring/students/{id}/history` | Historique étudiant |
| GET | `.../monitoring/students/{id}/stats` | Stats étudiant |

### 11.4 Rapports Présence

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../reports/rates` | Taux de présence |
| GET | `.../reports/absentees` | Liste absents |
| GET | `.../reports/statistics` | Stats détaillées |
| GET | `.../reports/export` | Export |

---

## 12. Module Exams

### 12.1 Sessions d'Examen

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/exams/sessions` | Lister |
| POST | `/api/admin/exams/sessions` | Créer |
| GET | `.../sessions/{id}` | Voir |
| PUT | `.../sessions/{id}` | Modifier |
| DELETE | `.../sessions/{id}` | Supprimer |
| POST | `.../sessions/{id}/publish` | Publier |
| POST | `.../sessions/{id}/cancel` | Annuler |
| POST | `.../sessions/{id}/duplicate` | Dupliquer |
| POST | `.../sessions/validate-schedule` | Valider horaire |
| GET | `.../sessions/available-rooms` | Salles disponibles |
| POST | `.../sessions/{id}/rooms` | Assigner salle |
| DELETE | `.../sessions/{id}/rooms/{assignId}` | Retirer salle |

### 12.2 Gestion d'Examen

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| PUT | `.../management/sessions/{id}/materials` | Matériels autorisés |
| PUT | `.../management/sessions/{id}/instructions` | Instructions |
| POST | `.../management/sessions/{id}/students/assign` | Assigner étudiants |
| POST | `.../management/sessions/{id}/students/auto-assign` | Auto-assignation |
| PUT | `.../management/attendance-sheets/{id}/reassign` | Réassigner |
| DELETE | `.../management/attendance-sheets/{id}` | Retirer étudiant |
| GET | `.../management/sessions/{id}/eligible-students` | Éligibles |
| GET | `.../management/sessions/{id}/preparation-checklist` | Checklist |

### 12.3 Surveillance

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../supervision/sessions/{id}/supervisors` | Assigner surveillants |
| PUT | `.../supervision/supervisors/{id}/present` | Marquer présent |
| POST | `.../supervision/supervisors/{id}/replace` | Remplacer |
| GET | `.../supervision/teachers/{id}/schedule` | Planning |
| PUT | `.../supervision/attendance-sheets/{id}/status` | Statut présence |
| POST | `.../supervision/attendance-sheets/{id}/submit` | Soumission copie |
| POST | `.../supervision/attendance-sheets/{id}/verify` | Vérifier feuille |
| GET | `.../supervision/sessions/{id}/attendance-stats` | Stats |

### 12.4 Incidents

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../supervision/incidents` | Signaler |
| PUT | `.../supervision/incidents/{id}` | Modifier |
| PUT | `.../supervision/incidents/{id}/status` | Changer statut |
| POST | `.../supervision/incidents/{id}/evidence` | Ajouter preuve |
| POST | `.../supervision/incidents/{id}/escalate` | Escalader |
| GET | `.../supervision/sessions/{id}/incidents` | Incidents session |
| GET | `.../supervision/sessions/{id}/incidents/summary` | Résumé |

### 12.5 Rapports Examens

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../reports/sessions/{id}/attendance` | Rapport présence |
| GET | `.../reports/sessions/{id}/attendance/export` | Export présence |
| GET | `.../reports/sessions/{id}/incidents` | Rapport incidents |
| GET | `.../reports/sessions/{id}/incidents/export` | Export incidents |
| GET | `.../reports/statistics` | Statistiques |
| GET | `.../reports/supervisor-workload` | Charge surveillants |
| GET | `.../reports/room-utilization` | Utilisation salles |

---

## 13. Module Timetable

### 13.1 Salles

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/rooms` | Lister |
| POST | `/api/admin/rooms` | Créer |
| GET | `.../rooms/buildings` | Bâtiments |
| GET | `.../rooms/available` | Disponibles |
| GET | `.../rooms/suggested` | Suggestions |
| GET | `.../rooms/stats` | Stats globales |
| GET | `/api/admin/rooms/{id}` | Voir |
| PUT | `/api/admin/rooms/{id}` | Modifier |
| DELETE | `/api/admin/rooms/{id}` | Supprimer |
| GET | `.../rooms/{id}/occupation` | Occupation |
| GET | `.../rooms/{id}/occupation-report` | Rapport |
| POST | `.../rooms/{id}/block` | Bloquer |
| POST | `.../rooms/{id}/unblock` | Débloquer |

### 13.2 Créneaux

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/timetable/slots` | Lister |
| POST | `/api/admin/timetable/slots` | Créer |
| GET | `.../slots/{id}` | Voir |
| PUT | `.../slots/{id}` | Modifier |
| DELETE | `.../slots/{id}` | Supprimer |
| GET | `.../slots/{id}/history` | Historique |
| POST | `.../timetable/check-conflicts` | Vérifier conflits |
| GET | `.../timetable/groups/{groupId}` | EDT groupe |
| GET | `.../timetable/teachers/{teacherId}` | EDT enseignant |
| GET | `.../timetable/rooms/{roomId}` | EDT salle |
| GET | `.../timetable/students/{studentId}` | EDT étudiant |

### 13.3 Génération Automatique

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../timetable/generate` | Générer |
| GET | `.../timetable/generation-result/{groupId}` | Résultat |
| POST | `.../timetable/accept-generated` | Accepter |
| GET | `.../teachers/{id}/preferences` | Préférences enseignant |
| PUT | `.../teachers/{id}/preferences` | Modifier préférences |

### 13.4 Duplication

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../timetable/duplication-preview` | Aperçu |
| POST | `.../timetable/duplicate` | Dupliquer |
| GET | `.../timetable/slots/{id}/suggestions` | Suggestions |
| PUT | `.../timetable/slots/{id}/quick-assign` | Assignation rapide |

### 13.5 Exceptions

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../timetable/exceptions` | Lister |
| POST | `.../timetable/exceptions` | Créer |
| GET | `.../exceptions/slots/{slotId}/history` | Historique |
| GET | `.../exceptions/upcoming/{semesterId}` | À venir |
| DELETE | `.../timetable/exceptions/{id}` | Supprimer |

### 13.6 Rapports EDT

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../reports/export-pdf` | Export PDF |
| GET | `.../reports/occupation-stats` | Stats occupation |
| GET | `.../reports/teacher-workload` | Charge enseignants |
| GET | `.../reports/room-utilization` | Utilisation salles |

### 13.7 Notifications EDT

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/notifications` | Lister |
| GET | `.../notifications/unread-count` | Non lues |
| POST | `.../notifications/{id}/read` | Marquer lue |
| POST | `.../notifications/read-all` | Tout lire |
| GET | `.../notifications/upcoming-changes` | Changements à venir |
| GET | `.../notifications/settings` | Paramètres |
| PUT | `.../notifications/settings` | Modifier paramètres |
| POST | `.../notifications/trigger-reminders` | Déclencher rappels |
| GET | `.../notifications/statistics` | Stats |
| DELETE | `.../notifications/cleanup` | Nettoyer |

---

## 14. Module Payroll

### 14.1 Employés

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/payroll/employees` | Lister |
| POST | `/api/admin/payroll/employees` | Créer |
| GET | `.../employees/{id}` | Voir |
| PUT | `.../employees/{id}` | Modifier |
| DELETE | `.../employees/{id}` | Supprimer |

### 14.2 Contrats

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../employees/{id}/contracts` | Créer contrat |
| POST | `.../contracts/{id}/activate` | Activer |
| POST | `.../contracts/{id}/amendments` | Avenant |
| POST | `.../amendments/{id}/approve` | Approuver avenant |
| POST | `.../contracts/{id}/terminate` | Résilier |

### 14.3 Grilles Salariales & Composants

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../salary-scales` | Grilles salariales |
| POST | `.../salary-scales` | Créer grille |
| GET | `.../components` | Composants paie |
| POST | `.../components` | Créer composant |

### 14.4 Avances sur Salaire

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../advances` | Lister |
| POST | `.../advances` | Demander |
| POST | `.../advances/{id}/approve` | Approuver |
| POST | `.../advances/{id}/disburse` | Décaisser |

### 14.5 Périodes de Paie

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../payroll-periods` | Lister |
| POST | `.../payroll-periods` | Créer |
| GET | `.../payroll-periods/{id}` | Voir |
| PUT | `.../payroll-periods/{id}` | Modifier |
| DELETE | `.../payroll-periods/{id}` | Supprimer |
| POST | `.../payroll-periods/{id}/calculate` | Calculer |
| POST | `.../payroll-periods/{id}/validate` | Valider |
| POST | `.../payroll-periods/{id}/generate-payslips` | Générer bulletins |
| GET | `.../payroll-periods/{id}/bank-transfers` | Virements bancaires |
| POST | `.../payroll-periods/{id}/mark-as-paid` | Marquer payé |

### 14.6 Déclarations Sociales

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../declarations` | Lister |
| GET | `.../declarations/{id}` | Voir |
| POST | `.../payroll-periods/{id}/declarations/cnss` | Générer CNSS |
| POST | `.../declarations/income-tax` | Impôt sur revenu |
| POST | `.../declarations/amo` | AMO |
| POST | `.../declarations/annual-summary` | Résumé annuel |
| POST | `.../declarations/{id}/validate` | Valider |
| POST | `.../declarations/{id}/submit` | Soumettre |
| POST | `.../declarations/{id}/payment` | Enregistrer paiement |

### 14.7 Rapports Paie

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../reports/dashboard` | Dashboard |
| GET | `.../reports/payroll-journal/{periodId}` | Journal de paie |
| GET | `.../reports/social-charges/{periodId}` | Charges sociales |
| GET | `.../reports/salary-statistics` | Stats salaires |

---

## 15. Module Documents

### 15.1 Relevés de Notes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/admin/documents/transcripts/semester` | Relevé semestriel |
| POST | `.../transcripts/global` | Relevé global |
| POST | `.../transcripts/batch` | Génération en masse |

### 15.2 Diplômes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/documents/diplomas` | Lister |
| POST | `.../diplomas` | Générer diplôme |
| POST | `.../diplomas/{id}/duplicate` | Duplicata |
| POST | `.../diplomas/{id}/supplement` | Supplément au diplôme |
| PATCH | `.../diplomas/{id}/deliver` | Marquer délivré |

### 15.3 Certificats

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../certificates/enrollment` | Certificat inscription |
| POST | `.../certificates/status` | Certificat de statut |
| POST | `.../certificates/achievement` | Certificat de réussite |
| POST | `.../certificates/attendance` | Certificat de présence |
| POST | `.../certificates/schooling` | Certificat scolarité |
| POST | `.../certificates/transfer` | Certificat de transfert |
| GET | `.../certificates/requests` | Lister demandes |
| POST | `.../certificates/requests` | Créer demande |
| POST | `.../certificates/requests/{id}/approve` | Approuver |
| POST | `.../certificates/requests/{id}/reject` | Rejeter |

### 15.4 Cartes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `.../cards` | Lister |
| POST | `.../cards/student-card` | Carte étudiante |
| POST | `.../cards/access-badge` | Badge d'accès |
| POST | `.../cards/batch` | Génération masse |
| POST | `.../cards/{id}/replace` | Remplacer |
| POST | `.../cards/{id}/print` | Imprimer |
| POST | `.../cards/batch-print` | Impression masse |
| PATCH | `.../cards/{id}/access-permissions` | Permissions d'accès |
| POST | `.../cards/{id}/suspend` | Suspendre |
| POST | `.../cards/{id}/activate` | Activer |

### 15.5 Vérification & Archive

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `.../verification/qr-code` | Vérifier par QR |
| POST | `.../verification/document-number` | Vérifier par numéro |
| GET | `.../verification/statistics` | Stats vérification |
| GET | `.../verification/register` | Registre |
| GET | `.../verification/documents/{id}` | Voir document |
| POST | `.../verification/documents/{id}/archive` | Archiver |
| GET | `.../verification/archives` | Lister archives |
| GET | `.../archives/{id}/verify-integrity` | Vérifier intégrité |
| POST | `.../archives/{id}/cold-storage` | Stockage froid |
| POST | `.../documents/{id}/signature` | Signature électronique |
| GET | `.../verification/signatures` | Lister signatures |
| GET | `.../signatures/{id}/verify` | Vérifier signature |
| POST | `.../signatures/{id}/invalidate` | Invalider |
| POST | `.../documents/{id}/cancel` | Annuler document |

---

## 16. Modèles de Données

### Modèles Principaux

#### User (Utilisateur Tenant)
```
id              integer (PK)
username        string (unique, max:255)
email           string (unique, email)
password        string (hashé)
firstname       string (max:255)
lastname        string (max:255)
application     enum: 'admin', 'frontend'
is_active       boolean (défaut: true)
sex             enum: 'M', 'F', 'Other'
phone           string (max:20)
mobile          string (max:20)
avatar          string (chemin fichier)
address         text
city            string (max:255)
country         string (max:255)
postal_code     string (max:20)
lastlogin       datetime
created_at      datetime
updated_at      datetime
deleted_at      datetime (soft delete)
```

#### Student (Étudiant)
```
id                       integer (PK)
matricule                string (unique, max:50, auto-généré)
firstname                string (max:255)
lastname                 string (max:255)
birthdate                date
birthplace               string (max:255)
sex                      enum: 'M', 'F', 'O'
nationality              string (max:255, défaut: 'Niger')
email                    string (unique, email)
phone                    string (max:20)
mobile                   string (max:20)
address                  text
city                     string (max:255)
country                  string (max:255, défaut: 'Niger')
photo                    string (chemin fichier)
status                   enum: 'Actif', 'Suspendu', 'Exclu', 'Diplômé'
emergency_contact_name   string (max:255)
emergency_contact_phone  string (max:20)
created_at               datetime
updated_at               datetime
deleted_at               datetime (soft delete)
```

#### Programme
```
id              integer (PK)
code            string (unique, max:50)
libelle         string (max:255)
type            enum: 'Licence', 'Master', 'Doctorat'
duree_annees    integer (1-8)
description     text
responsable_id  integer (FK → users)
statut          enum: 'Brouillon', 'Actif', 'Inactif', 'Archivé'
created_at      datetime
updated_at      datetime
deleted_at      datetime (soft delete)
```

#### Module (UE)
```
id                     integer (PK)
code                   string (unique, max:50)
name                   string (max:255)
credits_ects           integer (1-30)
coefficient            decimal(3,1) (0.25-10)
type                   enum: 'Obligatoire', 'Optionnel'
semester               integer (1-10)
level                  enum: 'L1', 'L2', 'L3', 'M1', 'M2'
description            text
hours_cm               integer (heures Cours Magistral)
hours_td               integer (heures Travaux Dirigés)
hours_tp               integer (heures Travaux Pratiques)
is_eliminatory         boolean (défaut: false)
eliminatory_threshold  decimal(5,2)
created_at             datetime
updated_at             datetime
deleted_at             datetime (soft delete)
```

#### AcademicYear (Année Académique)
```
id          integer (PK)
name        string (ex: '2025-2026')
start_date  date
end_date    date
is_active   boolean
status      enum: 'Active', 'Terminée', 'Archivée'
created_at  datetime
updated_at  datetime
deleted_at  datetime (soft delete)
```

#### Semester (Semestre)
```
id                  integer (PK)
academic_year_id    integer (FK → academic_years)
name                string (ex: 'S1', 'S2')
start_date          date
end_date            date
courses_start_date  date
courses_end_date    date
exams_start_date    date
exams_end_date      date
is_closed           boolean
closed_at           datetime
closed_by           integer (FK → users)
created_at          datetime
updated_at          datetime
deleted_at          datetime (soft delete)
```

#### Grade (Note)
```
id                      integer (PK)
student_id              integer (FK → students)
evaluation_id           integer (FK → module_evaluation_configs)
score                   decimal(5,2)
is_absent               boolean
comment                 string (max:200)
entered_by              integer (FK → users)
entered_at              datetime
status                  enum: 'Draft', 'Submitted', 'Validated', 'Published'
is_visible_to_students  boolean
published_at            datetime
created_at              datetime
updated_at              datetime
deleted_at              datetime (soft delete)
```

#### Invoice (Facture)
```
id                integer (PK)
student_id        integer (FK → students)
academic_year_id  integer (FK → academic_years)
invoice_number    string (unique)
invoice_date      date
due_date          date
total_amount      decimal(10,2)
paid_amount       decimal(10,2)
status            enum: 'Pending', 'Paid', 'Overdue', 'Cancelled'
notes             text
created_at        datetime
updated_at        datetime
deleted_at        datetime (soft delete)
```

#### Payment (Paiement)
```
id                integer (PK)
invoice_id        integer (FK → invoices)
student_id        integer (FK → students)
payment_date      date
amount            decimal(10,2)
payment_method    string
reference_number  string
receipt_number    string
notes             text
recorded_by       integer (FK → users)
created_at        datetime
updated_at        datetime
deleted_at        datetime (soft delete)
```

#### TimetableSlot (Créneau EDT)
```
id             integer (PK)
module_id      integer (FK → modules)
teacher_id     integer (FK → users)
group_id       integer (FK → groups)
room_id        integer (FK → rooms)
semester_id    integer (FK → semesters)
day_of_week    enum: 'Lundi'-'Samedi'
start_time     time
end_time       time
type           enum: 'CM', 'TD', 'TP'
is_recurring   boolean
specific_date  date
notes          text
created_at     datetime
updated_at     datetime
deleted_at     datetime (soft delete)
```

#### Employee (Employé)
```
id                              integer (PK)
employee_code                   string (unique)
first_name                      string
last_name                       string
email                           string (unique)
phone                           string
cin                             string
cnss_number                     string
date_of_birth                   date
gender                          string
marital_status                  string
number_of_dependents            integer
hire_date                       date
termination_date                date
department                      string
position                        string
job_title                       string
bank_name                       string
bank_account_number             string
rib                             string
status                          enum: 'active', 'inactive', 'terminated'
created_at                      datetime
updated_at                      datetime
deleted_at                      datetime (soft delete)
```

### Relations Principales

```
AcademicYear ──1:N──> Semester ──1:N──> ModuleEvaluationConfig ──1:N──> Grade
                                                                         │
Programme ──N:N──> Module ──1:N──> TeacherModuleAssignment               │
    │                                                                    │
    └──1:N──> StudentEnrollment ──1:N──> StudentModuleEnrollment         │
                    │                                                    │
                    └── Student ─────────────────────────────────────────┘
                         │
                         ├──1:N──> Invoice ──1:N──> Payment
                         ├──1:N──> StudentDocument
                         └──1:N──> StudentCard

Employee ──1:N──> EmploymentContract ──1:N──> PayrollRecord ──1:1──> Payslip
```

---

## 17. Guide d'Intégration Flutter

### 17.1 Configuration HTTP

```dart
// lib/core/api/api_client.dart
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiClient {
  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  ApiClient({required String baseUrl}) {
    _dio = Dio(BaseOptions(
      baseUrl: baseUrl,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.read(key: 'auth_token');
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }

        final tenantId = await _storage.read(key: 'tenant_id');
        if (tenantId != null) {
          options.headers['X-Tenant-ID'] = tenantId;
        }

        return handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          // Token expiré → tenter refresh ou rediriger vers login
          await _handleTokenExpiration();
        }
        return handler.next(error);
      },
    ));
  }

  Future<Response> get(String path, {Map<String, dynamic>? queryParams}) {
    return _dio.get(path, queryParameters: queryParams);
  }

  Future<Response> post(String path, {dynamic data}) {
    return _dio.post(path, data: data);
  }

  Future<Response> put(String path, {dynamic data}) {
    return _dio.put(path, data: data);
  }

  Future<Response> delete(String path) {
    return _dio.delete(path);
  }

  Future<Response> patch(String path, {dynamic data}) {
    return _dio.patch(path, data: data);
  }

  Future<void> _handleTokenExpiration() async {
    // Implémenter la logique de refresh token ou déconnexion
  }
}
```

### 17.2 Authentification

```dart
// lib/features/auth/data/auth_repository.dart
class AuthRepository {
  final ApiClient _api;

  AuthRepository(this._api);

  Future<AuthResponse> login({
    required String username,
    required String password,
    required String application,
  }) async {
    final response = await _api.post('/admin/auth/login', data: {
      'username': username,
      'password': password,
      'application': application,
    });

    final authResponse = AuthResponse.fromJson(response.data);

    // Stocker le token de manière sécurisée
    await _storage.write(key: 'auth_token', value: authResponse.data.token);
    await _storage.write(key: 'tenant_id', value: authResponse.data.tenant.id);

    return authResponse;
  }

  Future<void> logout() async {
    await _api.post('/admin/auth/logout');
    await _storage.deleteAll();
  }

  Future<AuthResponse> refreshToken() async {
    final response = await _api.post('/admin/auth/refresh');
    final newToken = response.data['data']['token'];
    await _storage.write(key: 'auth_token', value: newToken);
    return AuthResponse.fromJson(response.data);
  }

  Future<User> me() async {
    final response = await _api.get('/admin/auth/me');
    return User.fromJson(response.data['data']['user']);
  }
}
```

### 17.3 Gestion de la Pagination

```dart
// lib/core/models/paginated_response.dart
class PaginatedResponse<T> {
  final List<T> data;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  PaginatedResponse({
    required this.data,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  bool get hasNextPage => currentPage < lastPage;
  bool get hasPreviousPage => currentPage > 1;

  factory PaginatedResponse.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) fromJson,
  ) {
    return PaginatedResponse(
      data: (json['data'] as List).map((e) => fromJson(e)).toList(),
      currentPage: json['meta']['current_page'],
      lastPage: json['meta']['last_page'],
      perPage: json['meta']['per_page'],
      total: json['meta']['total'],
    );
  }
}
```

### 17.4 Gestion des Erreurs

```dart
// lib/core/errors/api_exception.dart
class ApiException implements Exception {
  final int statusCode;
  final String message;
  final Map<String, List<String>>? validationErrors;

  ApiException({
    required this.statusCode,
    required this.message,
    this.validationErrors,
  });

  factory ApiException.fromDioError(DioException error) {
    final response = error.response;
    if (response == null) {
      return ApiException(statusCode: 0, message: 'Erreur réseau');
    }

    final data = response.data;
    Map<String, List<String>>? errors;

    if (response.statusCode == 422 && data['errors'] != null) {
      errors = (data['errors'] as Map<String, dynamic>).map(
        (key, value) => MapEntry(key, List<String>.from(value)),
      );
    }

    return ApiException(
      statusCode: response.statusCode ?? 500,
      message: data['message'] ?? 'Erreur inconnue',
      validationErrors: errors,
    );
  }

  bool get isUnauthorized => statusCode == 401;
  bool get isForbidden => statusCode == 403;
  bool get isNotFound => statusCode == 404;
  bool get isValidationError => statusCode == 422;
  bool get isServerError => statusCode >= 500;
}
```

### 17.5 Téléchargement de Fichiers

```dart
// Pour les exports Excel/PDF
Future<void> downloadFile(String endpoint, String filename) async {
  final response = await _dio.get(
    endpoint,
    options: Options(responseType: ResponseType.bytes),
  );

  final directory = await getApplicationDocumentsDirectory();
  final file = File('${directory.path}/$filename');
  await file.writeAsBytes(response.data);
}

// Pour l'upload de fichiers (photos, documents)
Future<Response> uploadFile(String endpoint, File file, {
  Map<String, dynamic>? extraFields,
}) async {
  final formData = FormData.fromMap({
    'file': await MultipartFile.fromFile(file.path),
    ...?extraFields,
  });

  return _dio.post(endpoint, data: formData);
}
```

### 17.6 Format des Dates

```
Format API → Flutter:
- DateTime complet: "2026-02-16T10:30:00Z" → DateTime.parse()
- Date seule: "2026-02-16" → DateFormat('yyyy-MM-dd').parse()

Flutter → Format API:
- DateTime → DateFormat('yyyy-MM-dd').format(date)
- DateTime complet → date.toIso8601String()
```

### 17.7 Récapitulatif des Endpoints par Module

| Module | Admin | Frontend | Total |
|--------|-------|----------|-------|
| UsersGuard | 49 | 0 | 68 (+ 19 superadmin) |
| StructureAcademique | 188 | 0 | 188 |
| Enrollment | 139 | 30 | 199 |
| NotesEvaluations | 194 | 53 | 247 |
| Finance | 56 | 0 | 56 |
| Attendance | 21 | 0 | 21 |
| Exams | 45 | 0 | 45 |
| Timetable | 61 | 0 | 61 |
| Payroll | 54 | 0 | 54 |
| Documents | 49 | 0 | 49 |
| Core API | 4 | 0 | 4 |
| **TOTAL** | | | **992** |

---

> **Note:** Tous les endpoints préfixés `/api/admin/` nécessitent le middleware `['tenant', 'tenant.auth']` (authentification Bearer token + contexte tenant). Les endpoints `/api/frontend/` nécessitent le même type d'authentification mais sont destinés aux utilisateurs de l'application frontend (étudiants, enseignants).
