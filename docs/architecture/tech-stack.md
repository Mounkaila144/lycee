# Stack Technique

[← Retour à l'index](./index.md)

---

## 5.1 Stack Existante (Opérationnelle) ✅

### Backend - Laravel 12

| Package | Version | Usage | Statut |
|---------|---------|-------|--------|
| `laravel/framework` | v12.0 | Framework principal | ✅ Opérationnel |
| `nwidart/laravel-modules` | v12.0 | Architecture modulaire | ✅ Opérationnel |
| `stancl/tenancy` | v3.9 | Multi-tenancy isolation BD | ✅ Opérationnel |
| `spatie/laravel-permission` | v6.24 | Rôles & permissions | ✅ Opérationnel |
| `laravel/sanctum` | v4.0 | Auth API tokens | ✅ Opérationnel |

### Frontend - Next.js 15

| Package | Version | Usage | Statut |
|---------|---------|-------|--------|
| `next` | 15.1.2 | Framework React SSR | ✅ Opérationnel |
| `react` | 18.3.1 | Library UI | ✅ Opérationnel |
| `typescript` | 5.5.4 | Typage statique | ✅ Opérationnel |
| `@mui/material` | 6.2.1 | Composants UI | ✅ Opérationnel |
| `axios` | 1.13.2 | Client HTTP | ✅ Opérationnel |

---

## 5.2 Nouvelles Dépendances Requises 🆕

### Backend

#### CRITIQUE - Génération PDF
```bash
composer require barryvdh/laravel-dompdf
```
**Usage** : Génération de bulletins semestriels, attestations de scolarité, reçus de paiement, bulletins de paie, cartes scolaires, PV conseil de classe

#### HAUTE PRIORITÉ - Export Excel/CSV
```bash
composer require maatwebsite/excel
```
**Usage** : Import/export CSV des élèves, export rapports financiers, listes de classe

#### MOYENNE PRIORITÉ - Manipulation Images
```bash
composer require intervention/image
```
**Usage** : Redimensionnement photos élèves, génération cartes scolaires avec photo

#### RECOMMANDÉ - Queue Redis
```bash
composer require predis/predis
```
**Usage** : Jobs asynchrones pour génération PDF en masse (bulletins de toute une classe), envoi notifications parents, traitements lourds

---

### Frontend

#### HAUTE PRIORITÉ - Date Picker
```bash
npm install @mui/x-date-pickers dayjs
```
**Usage** : Sélection dates (inscriptions, emplois du temps, dates d'évaluations, paiements)

#### HAUTE PRIORITÉ - Data Grid Avancé
```bash
npm install @mui/x-data-grid
```
**Usage** : Tables avec tri, filtres, pagination (listes élèves, saisie notes, paiements, appel)

#### MOYENNE PRIORITÉ - Validation Formulaires
```bash
npm install react-hook-form zod
```
**Usage** : Validation robuste des formulaires (inscriptions, saisie notes, paiements)

---

## 5.3 Configuration Requise

### Backend - Environment Variables

```env
# PDF Generation
PDF_ENABLE_REMOTE=true
PDF_ENABLE_PHP=true

# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# File Storage
FILESYSTEM_DISK=local
# Pour production : s3, digitalocean, etc.
```

### Frontend - Environment Variables

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
NEXT_PUBLIC_APP_NAME="Gestion Scolaire"
```

---

## 5.4 Versions et Compatibilité

### PHP
- **Version minimale** : PHP 8.3.26
- **Extensions requises** :
  - `ext-gd` (manipulation images)
  - `ext-zip` (export Excel)
  - `ext-dom` (génération PDF)
  - `ext-redis` (queues, cache)

### Node.js
- **Version minimale** : Node.js 18.x
- **Package manager** : npm ou yarn

### Base de Données
- **MySQL** : 8.0+
- **MariaDB** : 10.6+ (alternative compatible)

### Redis (Optionnel mais recommandé)
- **Version minimale** : Redis 6.0+
- **Usage** : Cache, queues, sessions

---

## 5.5 Commandes d'Installation

### Backend - Installation Complète
```bash
cd C:\laragon\www\lycee

# Nouvelles dépendances
composer require barryvdh/laravel-dompdf
composer require maatwebsite/excel
composer require intervention/image
composer require predis/predis

# Publier configs
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"
```

### Frontend - Installation Complète
```bash
cd C:\laragon\www\lycee-front

# Nouvelles dépendances
npm install @mui/x-date-pickers dayjs
npm install @mui/x-data-grid
npm install react-hook-form zod
```

---

[Suivant : Modèles de Données →](./data-models.md)
