# RALPH Integration - CRM-API

## Qu'est-ce que RALPH ?

RALPH (Ralph Wiggum Technique) est une méthodologie de développement autonome qui permet à Claude Code de travailler en boucle continue jusqu'à complétion des tâches définies.

## Structure

```
.ralph/
├── PROMPT.md              # Instructions principales pour Claude
├── @fix_plan.md           # Liste des tâches à exécuter
├── @AGENT.md              # Spécifications techniques du projet
├── start-ralph.cmd        # Activer le mode RALPH (Windows)
├── stop-ralph.cmd         # Désactiver le mode RALPH (Windows)
├── ralph-loop.sh          # Script de lancement (Bash/Git Bash)
├── ralph-loop.ps1         # Script de lancement (PowerShell)
├── hooks/                 # Hooks Claude Code
│   ├── stop-hook.cmd      # Wrapper Windows pour le hook
│   ├── stop-hook.ps1      # Hook PowerShell
│   └── stop-hook.sh       # Hook Bash
├── logs/                  # Logs des exécutions
├── templates/             # Templates pour nouveaux modules
│   └── MODULE_TEMPLATE.md
└── README.md              # Ce fichier
```

## Démarrage Rapide

### Option 1: Avec les Hooks Claude Code (Recommandé)

La méthode hooks utilise le système natif de hooks de Claude Code pour créer une boucle continue.

**Windows (CMD):**
```cmd
cd C:\laragon\www\crm-api

REM Activer le mode RALPH
.ralph\start-ralph.cmd

REM Lancer Claude Code avec le prompt
claude "Execute les instructions dans .ralph/PROMPT.md"

REM Pour arrêter manuellement (si nécessaire)
.ralph\stop-ralph.cmd
```

Le hook de stop vérifie automatiquement:
- S'il y a des tâches `[PENDING]` ou `[IN_PROGRESS]` dans `@fix_plan.md`
- Si le signal `EXIT_SIGNAL: true` a été émis
- Si toutes les tâches sont `[DONE]`

### Option 2: Avec les Scripts

**PowerShell (Windows):**
```powershell
cd C:\laragon\www\crm-api
.\.ralph\ralph-loop.ps1 -MaxIterations 20
```

**Bash (Git Bash / WSL):**
```bash
cd /c/laragon/www/crm-api
chmod +x .ralph/ralph-loop.sh
./.ralph/ralph-loop.sh 20
```

### Option 3: Manuellement dans Claude Code

```
Exécute les instructions dans .ralph/PROMPT.md
```

## Configuration

### Modifier le Plan de Tâches

Éditez `.ralph/@fix_plan.md` pour:
- Ajouter de nouvelles stories
- Changer les priorités
- Marquer des tâches comme complétées

### Personnaliser le Prompt

Éditez `.ralph/PROMPT.md` pour:
- Changer les instructions de développement
- Ajouter des règles spécifiques
- Modifier les critères de sortie

## Workflow B-MAD + RALPH

```
┌─────────────────────────────────────────────────────────────┐
│                    B-MAD Methodology                        │
├─────────────────────────────────────────────────────────────┤
│  docs/stories/*.story.md  →  Spécifications détaillées     │
│  docs/state.md            →  État global du projet          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    RALPH Automation                         │
├─────────────────────────────────────────────────────────────┤
│  .ralph/PROMPT.md         →  Instructions pour Claude       │
│  .ralph/@fix_plan.md      →  Liste ordonnée des tâches      │
│  .ralph/@AGENT.md         →  Specs techniques               │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Exécution Continue                       │
├─────────────────────────────────────────────────────────────┤
│  1. Claude lit @fix_plan.md                                 │
│  2. Trouve la prochaine tâche [PENDING]                     │
│  3. Lit la story B-MAD correspondante                       │
│  4. Implémente selon les specs                              │
│  5. Marque [DONE] et passe à la suivante                    │
│  6. Répète jusqu'à EXIT_SIGNAL                              │
└─────────────────────────────────────────────────────────────┘
```

## Nouveau Module

Pour configurer RALPH sur un nouveau module:

1. **Créer les stories B-MAD:**
   ```
   docs/stories/{module}.{epic}.{number}-{slug}.story.md
   ```

2. **Copier le template:**
   ```bash
   cp .ralph/templates/MODULE_TEMPLATE.md .ralph/@fix_plan.md
   ```

3. **Éditer `@fix_plan.md`:**
   - Remplacer les placeholders
   - Lister toutes les stories
   - Définir les dépendances

4. **Lancer RALPH:**
   ```bash
   /ralph-loop "Implémente le module {MODULE}" --max-iterations 30
   ```

## Bonnes Pratiques

### DO ✅
- Définir des critères de complétion clairs
- Utiliser des stories B-MAD bien structurées
- Limiter le nombre d'itérations (--max-iterations)
- Vérifier les logs régulièrement
- Faire des commits Git entre les sessions

### DON'T ❌
- Lancer sans limite d'itérations
- Ignorer les erreurs bloquantes
- Modifier les fichiers pendant l'exécution
- Utiliser pour des tâches subjectives/créatives

## Coûts Estimés

| Itérations | Coût Approximatif | Durée Estimée |
|------------|-------------------|---------------|
| 10         | $5-15             | 15-30 min     |
| 20         | $10-30            | 30-60 min     |
| 50         | $25-75            | 1-2 heures    |

*Les coûts varient selon la taille du contexte et la complexité des tâches.*

## Dépannage

### "EXIT_SIGNAL" non détecté
- Vérifiez que le prompt demande explicitement le signal
- Augmentez --max-iterations si les tâches sont nombreuses

### Boucle infinie
- Vérifiez que @fix_plan.md a des tâches [PENDING]
- Assurez-vous que les critères de complétion sont clairs

### Erreurs répétées
- Consultez les logs dans `.ralph/logs/`
- Corrigez manuellement l'erreur et relancez

## Ressources

- [Documentation RALPH](https://github.com/frankbria/ralph-claude-code)
- [Awesome Claude](https://awesomeclaude.ai/ralph-wiggum)
- [B-MAD Methodology](https://github.com/BMad/bmad-methodology)
