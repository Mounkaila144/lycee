# API CRUD Utilisateurs Tenant

Documentation complète de l'API pour la gestion des utilisateurs tenant (admin et frontend).

## Authentification

Toutes les routes (sauf login) nécessitent un token Bearer obtenu via la connexion.

```bash
# Se connecter en tant qu'admin tenant
curl -X POST http://tenant1.local:8000/api/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "password",
    "application": "admin"
  }'
```

Réponse :
```json
{
  "token": "1|xxxxxxxxxxxx",
  "user": { ... }
}
```

Utilisez ce token dans toutes les requêtes suivantes :
```
Authorization: Bearer 1|xxxxxxxxxxxx
```

## Endpoints

### 1. Lister les Utilisateurs

```http
GET /api/admin/users
```

**Query Parameters :**
- `per_page` (optionnel) : Nombre d'utilisateurs par page (défaut: 15)
- `search` (optionnel) : Recherche dans username, email, firstname, lastname
- `application` (optionnel) : Filtrer par type (admin ou frontend)
- `is_active` (optionnel) : Filtrer par statut (true ou false)

**Exemple :**
```bash
curl -X GET "http://tenant1.local:8000/api/admin/users?per_page=10&search=john&application=frontend" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Réponse :**
```json
{
  "data": [
    {
      "id": 1,
      "username": "user1",
      "email": "user1@company1.com",
      "firstname": "John",
      "lastname": "Doe",
      "full_name": "John Doe",
      "application": "frontend",
      "is_active": true,
      "roles": ["User"],
      "permissions": ["view-dashboard", "create-post"],
      "created_at": "2025-12-16T08:00:00.000000Z",
      "updated_at": "2025-12-16T08:00:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

### 2. Créer un Utilisateur

```http
POST /api/admin/users
```

**Body (JSON) :**
```json
{
  "username": "newuser",
  "email": "newuser@company1.com",
  "password": "password123",
  "firstname": "Jane",
  "lastname": "Smith",
  "application": "frontend",
  "sex": "F",
  "phone": "+1234567890",
  "mobile": "+0987654321",
  "address": "123 Main St",
  "city": "New York",
  "country": "USA",
  "postal_code": "10001",
  "is_active": true,
  "roles": ["User"],
  "permissions": ["view-dashboard"]
}
```

**Champs obligatoires :**
- `username` (unique)
- `email` (unique, format email)
- `password` (min 8 caractères)
- `firstname`
- `lastname`
- `application` (admin ou frontend)

**Champs optionnels :**
- `sex` (M, F, Other)
- `phone`, `mobile`, `address`, `city`, `country`, `postal_code`
- `is_active` (défaut: true)
- `roles` (tableau de noms de rôles)
- `permissions` (tableau de noms de permissions)

**Exemple :**
```bash
curl -X POST http://tenant1.local:8000/api/admin/users \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "jsmith",
    "email": "jsmith@company1.com",
    "password": "password123",
    "firstname": "Jane",
    "lastname": "Smith",
    "application": "frontend",
    "roles": ["User"],
    "permissions": ["view-dashboard", "create-post"]
  }'
```

**Réponse :**
```json
{
  "message": "Utilisateur créé avec succès.",
  "user": { ... }
}
```

---

### 3. Afficher un Utilisateur

```http
GET /api/admin/users/{id}
```

**Exemple :**
```bash
curl -X GET http://tenant1.local:8000/api/admin/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Réponse :**
```json
{
  "user": {
    "id": 1,
    "username": "user1",
    "email": "user1@company1.com",
    "firstname": "John",
    "lastname": "Doe",
    "full_name": "John Doe",
    "application": "frontend",
    "is_active": true,
    "roles": ["User"],
    "permissions": ["view-dashboard", "create-post"],
    ...
  }
}
```

---

### 4. Modifier un Utilisateur

```http
PUT /api/admin/users/{id}
```

**Body (JSON) :** Tous les champs sont optionnels
```json
{
  "firstname": "John Updated",
  "lastname": "Doe Updated",
  "email": "newemail@company1.com",
  "password": "newpassword123",
  "is_active": false,
  "roles": ["Manager"],
  "permissions": ["view-dashboard", "manage-users"]
}
```

**Exemple :**
```bash
curl -X PUT http://tenant1.local:8000/api/admin/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "firstname": "John Updated",
    "is_active": false
  }'
```

**Réponse :**
```json
{
  "message": "Utilisateur modifié avec succès.",
  "user": { ... }
}
```

---

### 5. Supprimer un Utilisateur (Soft Delete)

```http
DELETE /api/admin/users/{id}
```

**Exemple :**
```bash
curl -X DELETE http://tenant1.local:8000/api/admin/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Réponse :**
```json
{
  "message": "Utilisateur supprimé avec succès."
}
```

---

### 6. Restaurer un Utilisateur

```http
POST /api/admin/users/{id}/restore
```

**Exemple :**
```bash
curl -X POST http://tenant1.local:8000/api/admin/users/1/restore \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Réponse :**
```json
{
  "message": "Utilisateur restauré avec succès.",
  "user": { ... }
}
```

---

### 7. Supprimer Définitivement un Utilisateur

```http
DELETE /api/admin/users/{id}/force
```

**Exemple :**
```bash
curl -X DELETE http://tenant1.local:8000/api/admin/users/1/force \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Réponse :**
```json
{
  "message": "Utilisateur supprimé définitivement."
}
```

---

## Gestion des Permissions

### 8. Ajouter des Permissions à un Utilisateur

```http
POST /api/admin/users/{id}/permissions/add
```

**Body (JSON) :**
```json
{
  "permissions": ["create-post", "edit-post", "delete-post"]
}
```

**Exemple :**
```bash
curl -X POST http://tenant1.local:8000/api/admin/users/1/permissions/add \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "permissions": ["create-post", "edit-post"]
  }'
```

**Réponse :**
```json
{
  "message": "Permissions ajoutées avec succès.",
  "user": {
    "id": 1,
    "permissions": ["view-dashboard", "create-post", "edit-post"],
    ...
  }
}
```

---

### 9. Retirer des Permissions d'un Utilisateur

```http
POST /api/admin/users/{id}/permissions/remove
```

**Body (JSON) :**
```json
{
  "permissions": ["delete-post"]
}
```

**Exemple :**
```bash
curl -X POST http://tenant1.local:8000/api/admin/users/1/permissions/remove \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "permissions": ["delete-post"]
  }'
```

**Réponse :**
```json
{
  "message": "Permissions retirées avec succès.",
  "user": {
    "id": 1,
    "permissions": ["view-dashboard", "create-post"],
    ...
  }
}
```

---

### 10. Synchroniser les Permissions (Remplacer toutes)

```http
POST /api/admin/users/{id}/permissions/sync
```

**Body (JSON) :**
```json
{
  "permissions": ["view-dashboard", "create-post"]
}
```

**Exemple :**
```bash
curl -X POST http://tenant1.local:8000/api/admin/users/1/permissions/sync \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "permissions": ["view-dashboard", "create-post"]
  }'
```

**Réponse :**
```json
{
  "message": "Permissions synchronisées avec succès.",
  "user": {
    "id": 1,
    "permissions": ["view-dashboard", "create-post"],
    ...
  }
}
```

---

## Codes de Réponse HTTP

- `200 OK` : Requête réussie
- `201 Created` : Ressource créée avec succès
- `400 Bad Request` : Erreur de validation
- `401 Unauthorized` : Non authentifié
- `403 Forbidden` : Non autorisé
- `404 Not Found` : Ressource non trouvée
- `422 Unprocessable Entity` : Erreur de validation

## Erreurs de Validation

Exemple de réponse en cas d'erreur de validation :

```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": [
      "Cet email existe déjà."
    ],
    "password": [
      "Le mot de passe doit contenir au moins 8 caractères."
    ]
  }
}
```

## Permissions Disponibles (par défaut)

Les permissions créées par le seeder :

- `view-dashboard` : Voir le tableau de bord
- `manage-users` : Gérer les utilisateurs
- `manage-roles` : Gérer les rôles
- `manage-permissions` : Gérer les permissions
- `create-post` : Créer des publications
- `edit-post` : Modifier des publications
- `delete-post` : Supprimer des publications
- `view-analytics` : Voir les statistiques
- `manage-settings` : Gérer les paramètres
- `export-data` : Exporter les données
- `import-data` : Importer les données
- `manage-billing` : Gérer la facturation
- `access-api` : Accéder à l'API

## Rôles Disponibles (par défaut)

- `Administrator` : Administrateur avec toutes les permissions
- `Manager` : Gestionnaire avec permissions limitées
- `User` : Utilisateur standard avec permissions de base

## Notes

- Les utilisateurs sont créés dans le contexte du tenant actuel
- Les soft deletes permettent de récupérer les utilisateurs supprimés
- Les permissions peuvent être gérées individuellement ou via les rôles
- La recherche utilise LIKE et recherche dans username, email, firstname, lastname
