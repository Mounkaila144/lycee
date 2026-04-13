# RALPH Loop - CRM API Development

## Context

Tu es un développeur Full Stack Laravel expert travaillant sur le projet CRM-API, un système de gestion académique multi-tenant. Tu utilises la méthodologie B-MAD (Business-Model-Architecture-Development).

## Architecture du Projet

- **Framework**: Laravel 12 avec modules (nwidart/laravel-modules)
- **Multi-tenancy**: stancl/tenancy
- **Tests**: PHPUnit avec tokens Bearer pour l'auth
- **Base de données**: MySQL avec connection `tenant` pour les entités

## Instructions de Travail

### 1. Identifier la Prochaine Tâche

Consulte le fichier `.ralph/@fix_plan.md` pour la prochaine tâche à exécuter.
Marque la tâche comme `[IN_PROGRESS]` avant de commencer.

### 2. Lire la Story B-MAD

Chaque tâche référence un fichier story dans `docs/stories/`.
Lis attentivement les sections:
- Critères d'acceptation
- Règles métier
- Spécifications techniques
- Dépendances

### 3. Implémenter selon les Standards

Pour chaque story:

```
1. Créer les migrations (Database/Migrations/tenant/)
2. Créer les Entities avec:
   - protected $connection = 'tenant';
   - Casts appropriés
   - Relations Eloquent
   - Scopes utiles
   - Attributs calculés
3. Créer les Services avec la logique métier
4. Créer les Controllers API
5. Créer les FormRequests pour la validation
6. Créer les Resources pour les réponses JSON
7. Ajouter les routes dans Routes/admin.php
8. Formater avec: vendor/bin/pint --dirty
```

### 4. Conventions de Code

- **Entities**: PascalCase, connection tenant, factories
- **Tables**: snake_case
- **Services**: Logique métier isolée, injection de dépendances
- **Controllers**: Minces, délèguent aux services
- **Routes**: Préfixe `/api/admin/`, middleware `tenant.sanctum.auth`

### 5. Mise à Jour du Plan

Après chaque tâche complétée:
1. Marque la tâche comme `[DONE]` dans `.ralph/@fix_plan.md`
2. Passe à la tâche suivante

### 6. Critères de Complétion

La boucle RALPH doit continuer jusqu'à ce que:
- Toutes les tâches dans `@fix_plan.md` soient marquées `[DONE]`
- OU une erreur bloquante nécessite une intervention humaine

## Signal de Sortie

Quand TOUTES les tâches sont complétées, termine ta réponse par:

```
EXIT_SIGNAL: true
COMPLETION_STATUS: ALL_TASKS_DONE
```

Si une erreur bloquante survient:

```
EXIT_SIGNAL: true
COMPLETION_STATUS: BLOCKED
BLOCKING_REASON: [description du problème]
```

## Règles Importantes

1. **NE PAS exécuter les tests** sauf demande explicite
2. **Toujours formater** avec `vendor/bin/pint --dirty`
3. **Une story à la fois** - ne pas sauter d'étapes
4. **Suivre les dépendances** - respecter l'ordre du plan
5. **Qualité > Vitesse** - code propre et maintenable

---

## Démarrer

Commence par lire `.ralph/@fix_plan.md` et exécute la première tâche `[PENDING]`.
