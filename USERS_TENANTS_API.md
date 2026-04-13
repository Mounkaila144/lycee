# API Documentation - Super Admin Users & Tenants

Cette documentation décrit tous les endpoints disponibles pour la gestion des utilisateurs super admin et des tenants.

---

## Table des matières

- [Super Admin Users API](#super-admin-users-api)
  - [Liste des utilisateurs](#1-liste-des-utilisateurs-super-admin)
  - [Créer un utilisateur](#2-créer-un-utilisateur-super-admin)
  - [Afficher un utilisateur](#3-afficher-un-utilisateur-super-admin)
  - [Mettre à jour un utilisateur](#4-mettre-à-jour-un-utilisateur-super-admin)
  - [Supprimer un utilisateur (soft delete)](#5-supprimer-un-utilisateur-soft-delete)
  - [Restaurer un utilisateur](#6-restaurer-un-utilisateur)
  - [Supprimer définitivement un utilisateur](#7-supprimer-définitivement-un-utilisateur)
  - [Activer/Désactiver un utilisateur](#8-activerdésactiver-un-utilisateur)
- [Tenants API](#tenants-api)
  - [Liste des tenants](#1-liste-des-tenants)
  - [Créer un tenant](#2-créer-un-tenant)
  - [Afficher un tenant](#3-afficher-un-tenant)
  - [Mettre à jour un tenant](#4-mettre-à-jour-un-tenant)
  - [Supprimer un tenant](#5-supprimer-un-tenant)
  - [Activer/Désactiver un tenant](#6-activerdésactiver-un-tenant)
  - [Ajouter un domaine à un tenant](#7-ajouter-un-domaine-à-un-tenant)
  - [Supprimer un domaine d'un tenant](#8-supprimer-un-domaine-dun-tenant)

---

## Super Admin Users API

Tous les endpoints nécessitent une authentification avec le middleware `auth:sanctum`.

Base URL: `/api/superadmin/users`

### 1. Liste des utilisateurs super admin

**Endpoint:** `GET /api/superadmin/users`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Query Parameters:**
| Paramètre | Type | Requis | Description |
|-----------|------|--------|-------------|
| per_page | integer | Non | Nombre d'éléments par page (défaut: 15) |
| search | string | Non | Recherche par username, email, firstname ou lastname |
| is_active | boolean | Non | Filtrer par statut actif (0 ou 1) |

**Exemple de requête:**
```bash
GET /api/superadmin/users?per_page=20&search=admin&is_active=1
```

**Réponse (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "username": "superadmin",
      "email": "superadmin@crm.com",
      "firstname": "Super",
      "lastname": "Admin",
      "full_name": "Super Admin",
      "application": "superadmin",
      "is_active": true,
      "sex": "M",
      "phone": "+1234567890",
      "mobile": "+1234567891",
      "lastlogin": null,
      "created_at": "2025-12-16T12:36:27.000000Z",
      "updated_at": "2025-12-16T12:36:27.000000Z",
      "deleted_at": null
    }
  ],
  "links": {
    "first": "http://localhost/api/superadmin/users?page=1",
    "last": "http://localhost/api/superadmin/users?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

---

### 2. Créer un utilisateur super admin

**Endpoint:** `POST /api/superadmin/users`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

**Body (JSON):**
```json
{
  "username": "newadmin",
  "email": "newadmin@example.com",
  "password": "SecurePassword123",
  "firstname": "John",
  "lastname": "Doe",
  "sex": "M",
  "phone": "+1234567890",
  "mobile": "+0987654321",
  "is_active": true
}
```

**Champs:**
| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| username | string | Oui | Nom d'utilisateur unique (max: 255) |
| email | string | Oui | Email unique et valide (max: 255) |
| password | string | Oui | Mot de passe (min: 8 caractères) |
| firstname | string | Non | Prénom (max: 255) |
| lastname | string | Non | Nom (max: 255) |
| sex | string | Non | Sexe (M, F, ou O) |
| phone | string | Non | Téléphone (max: 20) |
| mobile | string | Non | Mobile (max: 20) |
| is_active | boolean | Non | Statut actif (défaut: true) |

**Réponse (201 Created):**
```json
{
  "message": "Super administrateur créé avec succès.",
  "user": {
    "id": 5,
    "username": "newadmin",
    "email": "newadmin@example.com",
    "firstname": "John",
    "lastname": "Doe",
    "full_name": "John Doe",
    "application": "superadmin",
    "is_active": true,
    "sex": "M",
    "phone": "+1234567890",
    "mobile": "+0987654321",
    "lastlogin": null,
    "created_at": "2025-12-16T18:00:00.000000Z",
    "updated_at": "2025-12-16T18:00:00.000000Z",
    "deleted_at": null
  }
}
```

**Erreurs possibles:**
```json
{
  "message": "The username has already been taken.",
  "errors": {
    "username": ["Ce nom d'utilisateur existe déjà."]
  }
}
```

---

### 3. Afficher un utilisateur super admin

**Endpoint:** `GET /api/superadmin/users/{id}`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Exemple de requête:**
```bash
GET /api/superadmin/users/1
```

**Réponse (200 OK):**
```json
{
  "user": {
    "id": 1,
    "username": "superadmin",
    "email": "superadmin@crm.com",
    "firstname": "Super",
    "lastname": "Admin",
    "full_name": "Super Admin",
    "application": "superadmin",
    "is_active": true,
    "sex": "M",
    "phone": "+1234567890",
    "mobile": "+1234567891",
    "lastlogin": null,
    "created_at": "2025-12-16T12:36:27.000000Z",
    "updated_at": "2025-12-16T12:36:27.000000Z",
    "deleted_at": null
  }
}
```

**Erreurs possibles (404 Not Found):**
```json
{
  "message": "No query results for model [Modules\\UsersGuard\\Entities\\SuperAdmin] 999"
}
```

---

### 4. Mettre à jour un utilisateur super admin

**Endpoint:** `PUT /api/superadmin/users/{id}`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

**Body (JSON):**
```json
{
  "firstname": "John Updated",
  "lastname": "Doe Updated",
  "phone": "+9999999999",
  "password": "NewPassword123",
  "is_active": false
}
```

**Champs (tous optionnels):**
| Champ | Type | Description |
|-------|------|-------------|
| username | string | Nom d'utilisateur unique |
| email | string | Email unique et valide |
| password | string | Nouveau mot de passe (min: 8) |
| firstname | string | Prénom |
| lastname | string | Nom |
| sex | string | Sexe (M, F, ou O) |
| phone | string | Téléphone |
| mobile | string | Mobile |
| is_active | boolean | Statut actif |

**Réponse (200 OK):**
```json
{
  "message": "Super administrateur modifié avec succès.",
  "user": {
    "id": 1,
    "username": "superadmin",
    "email": "superadmin@crm.com",
    "firstname": "John Updated",
    "lastname": "Doe Updated",
    "full_name": "John Updated Doe Updated",
    "application": "superadmin",
    "is_active": false,
    "sex": "M",
    "phone": "+9999999999",
    "mobile": "+1234567891",
    "lastlogin": null,
    "created_at": "2025-12-16T12:36:27.000000Z",
    "updated_at": "2025-12-16T18:30:00.000000Z",
    "deleted_at": null
  }
}
```

---

### 5. Supprimer un utilisateur (soft delete)

**Endpoint:** `DELETE /api/superadmin/users/{id}`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Exemple de requête:**
```bash
DELETE /api/superadmin/users/5
```

**Réponse (200 OK):**
```json
{
  "message": "Super administrateur supprimé avec succès."
}
```

---

### 6. Restaurer un utilisateur

**Endpoint:** `POST /api/superadmin/users/{id}/restore`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Exemple de requête:**
```bash
POST /api/superadmin/users/5/restore
```

**Réponse (200 OK):**
```json
{
  "message": "Super administrateur restauré avec succès.",
  "user": {
    "id": 5,
    "username": "newadmin",
    "email": "newadmin@example.com",
    "firstname": "John",
    "lastname": "Doe",
    "full_name": "John Doe",
    "application": "superadmin",
    "is_active": true,
    "sex": "M",
    "phone": "+1234567890",
    "mobile": "+0987654321",
    "lastlogin": null,
    "created_at": "2025-12-16T18:00:00.000000Z",
    "updated_at": "2025-12-16T18:40:00.000000Z",
    "deleted_at": null
  }
}
```

---

### 7. Supprimer définitivement un utilisateur

**Endpoint:** `DELETE /api/superadmin/users/{id}/force`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Exemple de requête:**
```bash
DELETE /api/superadmin/users/5/force
```

**Réponse (200 OK):**
```json
{
  "message": "Super administrateur supprimé définitivement."
}
```

---

### 8. Activer/Désactiver un utilisateur

**Endpoint:** `POST /api/superadmin/users/{id}/toggle-active`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Exemple de requête:**
```bash
POST /api/superadmin/users/1/toggle-active
```

**Réponse (200 OK):**
```json
{
  "message": "Utilisateur désactivé avec succès.",
  "user": {
    "id": 1,
    "username": "superadmin",
    "email": "superadmin@crm.com",
    "firstname": "Super",
    "lastname": "Admin",
    "full_name": "Super Admin",
    "application": "superadmin",
    "is_active": false,
    "sex": "M",
    "phone": "+1234567890",
    "mobile": "+1234567891",
    "lastlogin": null,
    "created_at": "2025-12-16T12:36:27.000000Z",
    "updated_at": "2025-12-16T18:50:00.000000Z",
    "deleted_at": null
  }
}
```

---

## Tenants API

Base URL: `/api/superadmin/tenants`

### 1. Liste des tenants

**Endpoint:** `GET /api/superadmin/tenants`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Query Parameters:**
| Paramètre | Type | Requis | Description |
|-----------|------|--------|-------------|
| per_page | integer | Non | Nombre d'éléments par page (défaut: 15) |
| search | string | Non | Recherche par ID, nom d'entreprise ou email |

**Exemple de requête:**
```bash
GET /api/superadmin/tenants?per_page=20&search=company
```

**Réponse (200 OK):**
```json
{
  "data": [
    {
      "id": "company-xyz",
      "company_name": "XYZ Corporation",
      "company_email": "contact@xyz.com",
      "company_phone": "+1234567890",
      "company_address": "123 Main St, City",
      "company_logo": null,
      "is_active": true,
      "settings": null,
      "trial_ends_at": null,
      "subscription_ends_at": null,
      "domains": [
        {
          "id": 1,
          "domain": "xyz.localhost",
          "tenant_id": "company-xyz",
          "is_primary": true,
          "created_at": "2025-12-16T18:00:00.000000Z",
          "updated_at": "2025-12-16T18:00:00.000000Z"
        },
        {
          "id": 2,
          "domain": "www.xyz.localhost",
          "tenant_id": "company-xyz",
          "is_primary": false,
          "created_at": "2025-12-16T18:00:00.000000Z",
          "updated_at": "2025-12-16T18:00:00.000000Z"
        }
      ],
      "created_at": "2025-12-16T18:00:00.000000Z",
      "updated_at": "2025-12-16T18:00:00.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost/api/superadmin/tenants?page=1",
    "last": "http://localhost/api/superadmin/tenants?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

---

### 2. Créer un tenant

**Endpoint:** `POST /api/superadmin/tenants`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

**Body (JSON):**
```json
{
  "id": "company-abc",
  "company_name": "ABC Company",
  "company_email": "contact@abc.com",
  "company_phone": "+1234567890",
  "company_address": "456 Business Ave, City",
  "is_active": true,
  "domains": [
    {
      "domain": "abc.localhost"
    },
    {
      "domain": "www.abc.localhost"
    }
  ]
}
```

**Champs:**
| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| id | string | Oui | Identifiant unique du tenant (alpha_dash, max: 255) |
| company_name | string | Oui | Nom de l'entreprise (max: 255) |
| company_email | string | Non | Email de l'entreprise (valide) |
| company_phone | string | Non | Téléphone (max: 20) |
| company_address | string | Non | Adresse complète |
| is_active | boolean | Non | Statut actif (défaut: true) |
| domains | array | Oui | Liste des domaines (min: 1) |
| domains.*.domain | string | Oui | Nom de domaine unique (max: 255) |

**Notes importantes:**
- Le premier domaine de la liste sera automatiquement défini comme domaine principal
- L'ID ne peut contenir que des lettres, chiffres, tirets et underscores
- Tous les domaines doivent être uniques dans le système

**Réponse (201 Created):**
```json
{
  "message": "Tenant créé avec succès.",
  "tenant": {
    "id": "company-abc",
    "company_name": "ABC Company",
    "company_email": "contact@abc.com",
    "company_phone": "+1234567890",
    "company_address": "456 Business Ave, City",
    "company_logo": null,
    "is_active": true,
    "settings": null,
    "trial_ends_at": null,
    "subscription_ends_at": null,
    "domains": [
      {
        "id": 10,
        "domain": "abc.localhost",
        "tenant_id": "company-abc",
        "is_primary": true,
        "created_at": "2025-12-16T19:00:00.000000Z",
        "updated_at": "2025-12-16T19:00:00.000000Z"
      },
      {
        "id": 11,
        "domain": "www.abc.localhost",
        "tenant_id": "company-abc",
        "is_primary": false,
        "created_at": "2025-12-16T19:00:00.000000Z",
        "updated_at": "2025-12-16T19:00:00.000000Z"
      }
    ],
    "created_at": "2025-12-16T19:00:00.000000Z",
    "updated_at": "2025-12-16T19:00:00.000000Z"
  }
}
```

**Erreurs possibles:**
```json
{
  "message": "The id has already been taken.",
  "errors": {
    "id": ["Cet identifiant existe déjà."],
    "domains.0.domain": ["Ce domaine existe déjà."]
  }
}
```

---

### 3. Afficher un tenant

**Endpoint:** `GET /api/superadmin/tenants/{id}`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Exemple de requête:**
```bash
GET /api/superadmin/tenants/company-abc
```

**Réponse (200 OK):**
```json
{
  "tenant": {
    "id": "company-abc",
    "company_name": "ABC Company",
    "company_email": "contact@abc.com",
    "company_phone": "+1234567890",
    "company_address": "456 Business Ave, City",
    "company_logo": null,
    "is_active": true,
    "settings": null,
    "trial_ends_at": null,
    "subscription_ends_at": null,
    "domains": [
      {
        "id": 10,
        "domain": "abc.localhost",
        "tenant_id": "company-abc",
        "is_primary": true,
        "created_at": "2025-12-16T19:00:00.000000Z",
        "updated_at": "2025-12-16T19:00:00.000000Z"
      }
    ],
    "created_at": "2025-12-16T19:00:00.000000Z",
    "updated_at": "2025-12-16T19:00:00.000000Z"
  }
}
```

---

### 4. Mettre à jour un tenant

**Endpoint:** `PUT /api/superadmin/tenants/{id}`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

**Body (JSON):**
```json
{
  "company_name": "ABC Company Updated",
  "company_email": "newemail@abc.com",
  "company_phone": "+9999999999",
  "is_active": false,
  "domains": [
    {
      "domain": "abc.localhost"
    },
    {
      "domain": "new.abc.localhost"
    }
  ]
}
```

**Champs (tous optionnels):**
| Champ | Type | Description |
|-------|------|-------------|
| company_name | string | Nom de l'entreprise |
| company_email | string | Email de l'entreprise |
| company_phone | string | Téléphone |
| company_address | string | Adresse |
| is_active | boolean | Statut actif |
| domains | array | Nouvelle liste de domaines (remplace tous les domaines existants) |

**Note:** Si vous fournissez le champ `domains`, tous les domaines existants seront supprimés et remplacés par la nouvelle liste.

**Réponse (200 OK):**
```json
{
  "message": "Tenant modifié avec succès.",
  "tenant": {
    "id": "company-abc",
    "company_name": "ABC Company Updated",
    "company_email": "newemail@abc.com",
    "company_phone": "+9999999999",
    "company_address": "456 Business Ave, City",
    "company_logo": null,
    "is_active": false,
    "settings": null,
    "trial_ends_at": null,
    "subscription_ends_at": null,
    "domains": [
      {
        "id": 12,
        "domain": "abc.localhost",
        "tenant_id": "company-abc",
        "is_primary": true,
        "created_at": "2025-12-16T19:30:00.000000Z",
        "updated_at": "2025-12-16T19:30:00.000000Z"
      },
      {
        "id": 13,
        "domain": "new.abc.localhost",
        "tenant_id": "company-abc",
        "is_primary": false,
        "created_at": "2025-12-16T19:30:00.000000Z",
        "updated_at": "2025-12-16T19:30:00.000000Z"
      }
    ],
    "created_at": "2025-12-16T19:00:00.000000Z",
    "updated_at": "2025-12-16T19:30:00.000000Z"
  }
}
```

---

### 5. Supprimer un tenant

**Endpoint:** `DELETE /api/superadmin/tenants/{id}`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Exemple de requête:**
```bash
DELETE /api/superadmin/tenants/company-abc
```

**Note:** Cette action supprime définitivement le tenant et tous ses domaines associés.

**Réponse (200 OK):**
```json
{
  "message": "Tenant supprimé avec succès."
}
```

---

### 6. Activer/Désactiver un tenant

**Endpoint:** `POST /api/superadmin/tenants/{id}/toggle-active`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Exemple de requête:**
```bash
POST /api/superadmin/tenants/company-abc/toggle-active
```

**Réponse (200 OK):**
```json
{
  "message": "Tenant activé avec succès.",
  "tenant": {
    "id": "company-abc",
    "company_name": "ABC Company",
    "company_email": "contact@abc.com",
    "company_phone": "+1234567890",
    "company_address": "456 Business Ave, City",
    "company_logo": null,
    "is_active": true,
    "settings": null,
    "trial_ends_at": null,
    "subscription_ends_at": null,
    "domains": [
      {
        "id": 10,
        "domain": "abc.localhost",
        "tenant_id": "company-abc",
        "is_primary": true,
        "created_at": "2025-12-16T19:00:00.000000Z",
        "updated_at": "2025-12-16T19:00:00.000000Z"
      }
    ],
    "created_at": "2025-12-16T19:00:00.000000Z",
    "updated_at": "2025-12-16T19:45:00.000000Z"
  }
}
```

---

### 7. Ajouter un domaine à un tenant

**Endpoint:** `POST /api/superadmin/tenants/{id}/domains`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

**Body (JSON):**
```json
{
  "domain": "api.abc.localhost",
  "is_primary": false
}
```

**Champs:**
| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| domain | string | Oui | Nom de domaine unique |
| is_primary | boolean | Non | Définir comme domaine principal (défaut: false) |

**Note:** Si `is_primary` est défini à `true`, tous les autres domaines du tenant seront automatiquement définis comme non-principaux.

**Exemple de requête:**
```bash
POST /api/superadmin/tenants/company-abc/domains
Content-Type: application/json

{
  "domain": "api.abc.localhost",
  "is_primary": false
}
```

**Réponse (201 Created):**
```json
{
  "message": "Domaine ajouté avec succès.",
  "tenant": {
    "id": "company-abc",
    "company_name": "ABC Company",
    "company_email": "contact@abc.com",
    "company_phone": "+1234567890",
    "company_address": "456 Business Ave, City",
    "company_logo": null,
    "is_active": true,
    "settings": null,
    "trial_ends_at": null,
    "subscription_ends_at": null,
    "domains": [
      {
        "id": 10,
        "domain": "abc.localhost",
        "tenant_id": "company-abc",
        "is_primary": true,
        "created_at": "2025-12-16T19:00:00.000000Z",
        "updated_at": "2025-12-16T19:00:00.000000Z"
      },
      {
        "id": 14,
        "domain": "api.abc.localhost",
        "tenant_id": "company-abc",
        "is_primary": false,
        "created_at": "2025-12-16T20:00:00.000000Z",
        "updated_at": "2025-12-16T20:00:00.000000Z"
      }
    ],
    "created_at": "2025-12-16T19:00:00.000000Z",
    "updated_at": "2025-12-16T19:00:00.000000Z"
  }
}
```

**Erreurs possibles:**
```json
{
  "message": "The domain has already been taken.",
  "errors": {
    "domain": ["Ce domaine existe déjà."]
  }
}
```

---

### 8. Supprimer un domaine d'un tenant

**Endpoint:** `DELETE /api/superadmin/tenants/{tenantId}/domains/{domainId}`

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Exemple de requête:**
```bash
DELETE /api/superadmin/tenants/company-abc/domains/14
```

**Note importante:** Vous ne pouvez pas supprimer le domaine principal si le tenant a plusieurs domaines. Vous devez d'abord définir un autre domaine comme principal.

**Réponse (200 OK):**
```json
{
  "message": "Domaine supprimé avec succès.",
  "tenant": {
    "id": "company-abc",
    "company_name": "ABC Company",
    "company_email": "contact@abc.com",
    "company_phone": "+1234567890",
    "company_address": "456 Business Ave, City",
    "company_logo": null,
    "is_active": true,
    "settings": null,
    "trial_ends_at": null,
    "subscription_ends_at": null,
    "domains": [
      {
        "id": 10,
        "domain": "abc.localhost",
        "tenant_id": "company-abc",
        "is_primary": true,
        "created_at": "2025-12-16T19:00:00.000000Z",
        "updated_at": "2025-12-16T19:00:00.000000Z"
      }
    ],
    "created_at": "2025-12-16T19:00:00.000000Z",
    "updated_at": "2025-12-16T19:00:00.000000Z"
  }
}
```

**Erreur si tentative de supprimer le domaine principal (422 Unprocessable Entity):**
```json
{
  "message": "Impossible de supprimer le domaine principal. Veuillez d'abord définir un autre domaine comme principal."
}
```

---

## Codes d'erreur HTTP

| Code | Description |
|------|-------------|
| 200 | Succès |
| 201 | Créé avec succès |
| 401 | Non authentifié |
| 404 | Ressource non trouvée |
| 422 | Erreur de validation |
| 500 | Erreur serveur |

---

## Notes importantes

1. **Authentication:** Tous les endpoints nécessitent un token Bearer valide obtenu via l'endpoint `/api/superadmin/auth/login`.

2. **Pagination:** Les listes sont paginées par défaut avec 15 éléments par page. Utilisez le paramètre `per_page` pour modifier ce nombre.

3. **Soft Delete:** Les utilisateurs super admin utilisent le soft delete, ce qui signifie qu'ils peuvent être restaurés après suppression.

4. **Tenants et Domaines:** Chaque tenant doit avoir au moins un domaine. Le premier domaine est automatiquement défini comme principal.

5. **Validation:** Toutes les données envoyées sont validées. En cas d'erreur, vous recevrez un message détaillé avec les champs concernés.

6. **Base de données:** Toutes ces opérations utilisent la base de données centrale (mysql), pas les bases de données des tenants.

---

## Exemples de scripts

### Exemple cURL - Créer un tenant

```bash
curl -X POST http://localhost/api/superadmin/tenants \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "id": "my-company",
    "company_name": "My Company Ltd",
    "company_email": "contact@mycompany.com",
    "company_phone": "+1234567890",
    "company_address": "123 Business Street",
    "is_active": true,
    "domains": [
      {"domain": "mycompany.localhost"},
      {"domain": "www.mycompany.localhost"}
    ]
  }'
```

### Exemple JavaScript (Fetch API)

```javascript
const createTenant = async () => {
  const response = await fetch('http://localhost/api/superadmin/tenants', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer YOUR_TOKEN_HERE',
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      id: 'my-company',
      company_name: 'My Company Ltd',
      company_email: 'contact@mycompany.com',
      company_phone: '+1234567890',
      company_address: '123 Business Street',
      is_active: true,
      domains: [
        { domain: 'mycompany.localhost' },
        { domain: 'www.mycompany.localhost' }
      ]
    })
  });

  const data = await response.json();
  console.log(data);
};
```

### Exemple PHP (Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://localhost',
    'headers' => [
        'Authorization' => 'Bearer YOUR_TOKEN_HERE',
        'Accept' => 'application/json'
    ]
]);

$response = $client->post('/api/superadmin/tenants', [
    'json' => [
        'id' => 'my-company',
        'company_name' => 'My Company Ltd',
        'company_email' => 'contact@mycompany.com',
        'company_phone' => '+1234567890',
        'company_address' => '123 Business Street',
        'is_active' => true,
        'domains' => [
            ['domain' => 'mycompany.localhost'],
            ['domain' => 'www.mycompany.localhost']
        ]
    ]
]);

$data = json_decode($response->getBody(), true);
print_r($data);
```

---

## Support

Pour toute question ou problème, veuillez consulter la documentation principale ou contacter l'équipe de développement.
