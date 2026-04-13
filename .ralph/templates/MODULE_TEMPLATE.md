# Template RALPH - Nouveau Module

## Instructions

Copiez ce template pour configurer RALPH sur un nouveau module.
Remplacez `{MODULE_NAME}` par le nom du module (ex: Timetable, Attendance, etc.)

---

# Plan de Développement - Module {MODULE_NAME}

## Statut Global

- **Module**: {MODULE_NAME}
- **Phase actuelle**: 1
- **Progression**: 0/X stories (0%)

---

## Phase 1: [Nom de la Phase] 🚧 EN COURS

### [PENDING] Story 01 - [Titre]
- **Fichier**: `docs/stories/{module}.{epic}.01-{slug}.story.md`
- **Priorité**: Critique
- **Dépendances**: [Modules requis]
- **Livrables**:
  - Migration `create_{table}_table`
  - Entity `{EntityName}`
  - Service `{ServiceName}`
  - Controller `{ControllerName}`
  - Routes API

### [PENDING] Story 02 - [Titre]
- **Fichier**: `docs/stories/{module}.{epic}.02-{slug}.story.md`
- **Priorité**: Haute
- **Dépendances**: Story 01
- **Livrables**:
  - ...

---

## Phase 2: [Nom de la Phase] ⏳ À VENIR

### [PENDING] Story XX - [Titre]
...

---

## Notes d'Implémentation

### Points d'Attention
- [Spécificités du module]
- [Règles métier importantes]
- [Dépendances externes]

### Ordre Recommandé
1. [Story critiques d'abord]
2. [Stories dépendantes ensuite]
3. [Rapports/exports en dernier]

---

## Légende des Statuts

- `[PENDING]` - À faire
- `[IN_PROGRESS]` - En cours
- `[DONE]` - Terminé
- `[BLOCKED]` - Bloqué (nécessite intervention)
