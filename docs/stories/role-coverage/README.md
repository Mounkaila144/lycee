# Epic global — Couverture des rôles (Role Coverage)

> **Type** : Epic transverse QA / Test design
> **Status** : Draft (README global uniquement — stories par rôle à venir)
> **Date** : 2026-05-12
> **Auteur** : SM Agent (Claude Opus 4.7)

## 1. Objectif

Produire une suite de stories qui décrivent, **par rôle utilisateur et par menu**, exactement ce que l'utilisateur **doit pouvoir faire** et ce qu'il **ne doit PAS pouvoir faire** dans l'application. Ces stories serviront de base pour :

- les tests E2E (Playwright) et les tests QA manuels,
- la complétion des middlewares `role:`/`permission:` manquants côté backend,
- la configuration de l'affichage conditionnel des menus côté frontend (`menu.config.ts` à créer),
- la conception du nouveau rôle **Parent** (qui n'existe pas encore en base).

## 2. Sources de vérité utilisées

| Source | Chemin | Usage |
|---|---|---|
| Hiérarchie des rôles + home_routes | `config/role-routes.php` | Ordre de priorité, page d'atterrissage par rôle |
| Liste des permissions Spatie + assignations | `Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php` | Source canonique des permissions par rôle (guard `tenant`) |
| Middlewares `role:`/`permission:` | `Modules/UsersGuard/Routes/admin.php` | Seul module qui applique réellement `middleware('role:...')` |
| Inventaire routes admin/teacher/frontend | `Modules/*/Routes/*.php` (10 modules) | Endpoints réels, libellés URL, méthodes contrôleur |
| Format BMad story | `docs/stories/1.1.story.md`, `docs/stories/7.1.story.md` | Modèle structurel à respecter |

> **⚠️ Limites de cartographie**
> 1. Le frontend Next.js est en **polyrepo séparé** (cf. `docs/architecture/source-tree.md`). Les fichiers `src/modules/*/menu.config.ts` et `src/config/route-guards.ts` mentionnés dans la mission **ne sont pas accessibles depuis ce dépôt**. Les libellés et IDs de menu sont donc inférés à partir des préfixes d'URL backend + des `home_routes`. À valider avec le frontend lead avant exécution QA.
> 2. Aucun dossier `docs/stories/role-based-interface/` n'existait — il n'y a donc pas de matrice héritée à enrichir ; cette README crée la matrice de zéro.

## 3. Rôles couverts

8 rôles (7 existants + 1 nouveau) :

| # | Rôle (slug exact) | Source | home_route | État |
|---|---|---|---|---|
| 1 | `Administrator` | seeder L84 | `/admin/dashboard` | ✅ Existant |
| 2 | `Manager` | seeder L90 | `/admin/dashboard` | ✅ Existant |
| 3 | `Professeur` | seeder L110 | `/admin/teacher/home` | ✅ Existant |
| 4 | `Étudiant` | seeder L121 | `/admin/student/home` | ✅ Existant |
| 5 | `Caissier` | seeder L169 | `/admin/finance/payments` | ✅ Existant |
| 6 | `Agent Comptable` | seeder L183 | `/admin/finance/invoices` | ✅ Existant |
| 7 | `Comptable` | seeder L200 | `/admin/finance/reports` | ✅ Existant |
| 8 | `Parent` | **À créer** | `/admin/parent/home` (proposé) | 🆕 À concevoir |

(Le rôle `User` du seeder L101 est conservé comme rôle « basique » et n'est pas couvert ici — il n'a que `view dashboard`.)

[Source : `config/role-routes.php#hierarchy`, `Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php#run()`]

## 4. Inventaire des modules backend (10 modules)

| Module | Routes admin | Routes teacher/frontend | Middlewares `role:` ? | Notes critiques |
|---|---|---|---|---|
| **UsersGuard** | `admin.php` (78 L) | `frontend.php` (22 L) | ✅ **Oui** — `role:Administrator\|Manager`, `role:Administrator\|Manager\|Professeur`, etc. | Seul module qui filtre réellement par rôle |
| **StructureAcademique** | `admin.php` (117 L) | — | ❌ Aucun | Toute action accessible à tout utilisateur authentifié |
| **Enrollment** | `admin.php` (438 L) | `frontend.php` (142 L) | ❌ Aucun | Idem ; routes étudiant `/frontend/enrollment/my-*` non gatées |
| **NotesEvaluations** | `admin.php` (336 L) | `teacher.php` (95 L) | ❌ Aucun | Routes prof `/api/frontend/teacher/...` accessibles à tout user authentifié |
| **Attendance** | `admin.php` (59 L) | — | ❌ Aucun | |
| **Timetable** | `admin.php` (115 L) | `frontend.php` (11 L vides) | ❌ Aucun | Routes frontend planifiées (stories 06-09) — pas encore en place |
| **Documents** | `admin.php` (85 L) | — | ⚠️ `auth:sanctum` seul, **pas `tenant.auth`** | Risque transverse-tenant : à corriger |
| **Finance** | `admin.php` (214 L) | — | ❌ Aucun | |
| **Payroll** | `admin.php` (54 L) | — | ⚠️ `auth:sanctum` seul, **pas `tenant.auth`** | Risque transverse-tenant : à corriger |
| **Exams** | `admin.php` (71 L) | — | ⚠️ `auth:sanctum` seul, **pas `tenant.auth`** | Risque transverse-tenant : à corriger |

> **⚠️ Constat sécurité critique** : Sauf `UsersGuard`, **aucun module backend n'enforce de `middleware('role:')` ou `middleware('permission:')` sur ses routes**. La majorité des modules s'appuie uniquement sur `['tenant', 'tenant.auth']` — tout utilisateur authentifié (peu importe son rôle) peut donc atteindre n'importe quel endpoint admin via cURL/Postman. Les stories de ce dossier vont **explicitement lister les middlewares à ajouter** pour bloquer ce que chaque rôle ne doit pas pouvoir faire. **Cette epic est aussi une epic de durcissement RBAC, pas seulement de documentation.**

## 5. Matrice Rôles × Menus (top-level)

Convention : ✅ accès complet • 👁️ lecture seule • ⚠️ restreint à ses propres données • ❌ interdit • 🆕 endpoint à créer.

**Note méthodologique** : « Visible aujourd'hui » = ce que les routes backend exposent réellement (tout user authentifié peut y accéder faute de `role:` middleware). « Attendu post-story » = la cible RBAC documentée dans la story du rôle.

### 5.1 Vue d'ensemble (état cible)

| Menu top-level | Admin | Manager | Professeur | Étudiant | Caissier | Agent Comptable | Comptable | Parent |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Dashboard | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Utilisateurs (Users) | ✅ | ✅ (no delete) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Rôles & Permissions | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Réglages tenant (Settings) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Structure Académique | ✅ | 👁️ | 👁️ (own) | ❌ | ❌ | ❌ | ❌ | ❌ |
| Inscriptions / Élèves | ✅ | ✅ | 👁️ (ses classes) | ⚠️ (self via `/frontend/...`) | 👁️ (lecture) | 👁️ | 👁️ | ⚠️ (ses enfants) 🆕 |
| Notes & Évaluations (admin) | ✅ | 👁️ | ❌ (passe par teacher.php) | ❌ | ❌ | ❌ | ❌ | ❌ |
| Saisie Notes (teacher) | ❌ | ❌ | ✅ (ses modules) | ❌ | ❌ | ❌ | ❌ | ❌ |
| Mes Notes / Bulletins | ❌ | ❌ | ❌ | ⚠️ (self) | ❌ | ❌ | ❌ | ⚠️ (ses enfants) 🆕 |
| Présences | ✅ | 👁️ | ✅ (ses cours) | ⚠️ (self) | ❌ | ❌ | ❌ | ⚠️ (ses enfants) 🆕 |
| Emploi du temps (admin) | ✅ | 👁️ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Mon Emploi du temps | ❌ | ❌ | ⚠️ (perso) | ⚠️ (perso) | ❌ | ❌ | ❌ | ⚠️ (ses enfants) 🆕 |
| Examens | ✅ | 👁️ | ⚠️ (surveillance) | ⚠️ (ses examens) | ❌ | ❌ | ❌ | ❌ |
| Documents (transcripts, diplômes, cartes…) | ✅ | ✅ | ❌ | ⚠️ (les siens) | ❌ | ❌ | ❌ | ⚠️ (de ses enfants) 🆕 |
| Finance — Factures | ✅ | 👁️ | ❌ | ⚠️ (les siennes) | 👁️ | ✅ | 👁️ | ⚠️ (de ses enfants) 🆕 |
| Finance — Encaissements | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | 🆕 (paiement en ligne) |
| Finance — Remises/Bourses | ✅ | ❌ | ❌ | ❌ | ❌ | ⚠️ (proposer) | ✅ (approuver) | ❌ |
| Finance — Recouvrement | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ |
| Finance — Rapports | ✅ | 👁️ | ❌ | ❌ | 👁️ | 👁️ | ✅ + export | ❌ |
| Finance — Rapprochement bancaire | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| Paie Personnel | ✅ | ❌ | ⚠️ (sa fiche) | ❌ | ❌ | ❌ | ⚠️ (lecture) | ❌ |
| Messages / Annonces | ✅ | ✅ | ✅ (vers parents/élèves) | 👁️ | ❌ | ❌ | ❌ | ✅ (vers profs) 🆕 |

> Cellules « 🆕 » : endpoint backend à créer. Cellules « ❌ » : on s'attend à un 403 backend **et** à l'absence de l'item dans la sidebar.

### 5.2 Lecture par rôle (résumé)

- **Administrator** — accès complet. Sera la story qui sert d'« étalon » : tout ce que les autres rôles voient/font apparaît au moins une fois ici.
- **Manager** — comme Admin mais sans Rôles/Permissions, sans Settings tenant, et **lecture seule** sur la Finance détaillée. Conserve la création d'utilisateurs (cf. `UsersGuard/admin.php:26`).
- **Professeur** — accès à ses propres classes/modules. Saisie via `Modules/NotesEvaluations/Routes/teacher.php`. Vues : home enseignant, mes classes, saisie notes, présences, emploi du temps personnel.
- **Étudiant** — accès portail self : ses notes, son emploi du temps, ses paiements, ses documents. Endpoints `/frontend/enrollment/my-*` déjà en place pour inscription/carte.
- **Caissier** — encaissement uniquement. Voit factures, ne les crée pas. Génère reçus.
- **Agent Comptable** — facturation et recouvrement. Pas d'encaissement direct, pas de rapprochement.
- **Comptable** — vue d'ensemble finance, rapports, exports, rapprochement bancaire, refunds. Pas de facturation.
- **Parent** 🆕 — voit ses enfants (notes, absences, EDT, factures, messages enseignants, annonces). **Paie en ligne**.

## 6. Plan d'écriture (démarrage progressif)

Per la consigne « Démarrage progressif », l'ordre d'exécution est strict :

1. ✅ **Étape 1 — Ce fichier** : `docs/stories/role-coverage/README.md` (global).
2. ⏸️ **Étape 2** : `README.md` epic dans chaque sous-dossier de rôle (8 fichiers). Pour le rôle Parent, le README contient la **conception** : slug, permissions Spatie, migration parents↔élèves, endpoints à créer.
3. ⏸️ **Étape 3** : Stories individuelles, **un rôle à la fois**, dans l'ordre :
   1. **Professeur** (rôle le plus complexe à QA — cible prioritaire)
   2. Étudiant
   3. Parent (le nouveau)
   4. Caissier
   5. Agent Comptable
   6. Comptable
   7. Manager
   8. Administrator (en dernier : sert de récapitulatif)
4. ⏸️ **Étape 4** : Validation croisée (matrice mise à jour si découvertes terrain).

**⏸ = J'attends ta confirmation explicite avant d'attaquer chaque étape suivante.** Tu peux dire « OK étape 2 » pour les 8 READMEs, ou « OK Professeur » pour générer les stories Professeur, etc.

## 7. Arborescence du livrable (cible)

```
docs/stories/role-coverage/
├── README.md                                ← ce fichier (Étape 1)
├── administrator/
│   ├── README.md                            ← epic Admin (Étape 2)
│   ├── 01-dashboard.story.md                ← Étape 3
│   ├── 02-users-management.story.md
│   ├── 03-roles-permissions.story.md
│   ├── 04-academic-structure.story.md
│   ├── 05-enrollments.story.md
│   ├── 06-grades-evaluations.story.md
│   ├── 07-attendance.story.md
│   ├── 08-timetable.story.md
│   ├── 09-exams.story.md
│   ├── 10-documents.story.md
│   ├── 11-finance.story.md
│   ├── 12-payroll.story.md
│   └── 13-settings.story.md
├── manager/
│   ├── README.md
│   ├── 01-dashboard.story.md
│   ├── 02-users-management.story.md         ← sans delete
│   ├── 03-academic-structure-readonly.story.md
│   ├── 04-enrollments.story.md
│   ├── 05-grades-readonly.story.md
│   ├── 06-attendance-readonly.story.md
│   ├── 07-timetable-readonly.story.md
│   ├── 08-documents.story.md
│   └── 09-finance-readonly.story.md
├── professeur/                              ← rédigé en premier
│   ├── README.md
│   ├── 01-home-mes-classes.story.md
│   ├── 02-saisie-notes.story.md
│   ├── 03-import-notes-batch.story.md
│   ├── 04-absences-evaluations.story.md
│   ├── 05-rattrapages.story.md
│   ├── 06-presences-cours.story.md
│   ├── 07-mon-emploi-du-temps.story.md
│   ├── 08-surveillance-examens.story.md
│   └── 09-eleves-readonly.story.md
├── etudiant/
│   ├── README.md
│   ├── 01-home-portail.story.md
│   ├── 02-mes-notes-bulletins.story.md
│   ├── 03-mon-emploi-du-temps.story.md
│   ├── 04-mes-presences.story.md
│   ├── 05-mes-factures-paiements.story.md
│   ├── 06-mes-documents.story.md
│   ├── 07-ma-carte-etudiante.story.md
│   ├── 08-reinscription.story.md
│   └── 09-transferts-equivalences.story.md
├── caissier/
│   ├── README.md
│   ├── 01-home-encaissements.story.md
│   ├── 02-saisie-paiement.story.md
│   ├── 03-recus.story.md
│   ├── 04-factures-lecture.story.md
│   └── 05-rapports-journaliers.story.md
├── agent-comptable/
│   ├── README.md
│   ├── 01-home-facturation.story.md
│   ├── 02-factures-crud.story.md
│   ├── 03-echeanciers.story.md
│   ├── 04-penalites-retard.story.md
│   ├── 05-recouvrement.story.md
│   └── 06-blocage-services.story.md
├── comptable/
│   ├── README.md
│   ├── 01-home-rapports.story.md
│   ├── 02-vue-ensemble-finance.story.md
│   ├── 03-rapprochement-bancaire.story.md
│   ├── 04-refunds.story.md
│   ├── 05-exports-comptables.story.md
│   └── 06-paie-lecture.story.md
└── parent/                                  ← rôle à créer
    ├── README.md                            ← conception du rôle (permissions, migration, endpoints)
    ├── 01-home-mes-enfants.story.md
    ├── 02-notes-enfant.story.md
    ├── 03-presences-enfant.story.md
    ├── 04-emploi-du-temps-enfant.story.md
    ├── 05-factures-enfant.story.md
    ├── 06-paiement-en-ligne.story.md
    ├── 07-messages-enseignants.story.md
    ├── 08-annonces-ecole.story.md
    └── 09-documents-enfant.story.md
```

## 8. Format strict d'une story (rappel)

Chaque story respectera le squelette ci-dessous. Toute déviation = story à réécrire.

```markdown
# Story: [Rôle] — [Nom du menu] - Coverage

**Module** : [nom du module backend]
**Rôle ciblé** : [Professeur | Étudiant | …]
**Menu(s) concerné(s)** : [route + id de menu]
**Status** : Draft

## User Story
En tant que [rôle], je veux [...], afin de [...].

## Couverture UI (sidebar)
| Item de menu | Visible ? | Pourquoi |
| --- | --- | --- |

## Actions autorisées dans ce menu
| Action | UI | API | Inputs | Réponse OK | Permission |
| --- | --- | --- | --- | --- | --- |

## Actions INTERDITES dans ce menu
| Action interdite | Blocage attendu |
| --- | --- |

## Cas limites (edge cases)
- État vide…
- Erreur réseau…
- Concurrent edit…
- Validation…

## Scenarios de test E2E
1. **Login → Atterrissage** …
2. **Menu visible** …
3. **Action heureuse 1** …
4. **Action interdite 1** …

## Definition of Done
- [ ] Tous les scénarios E2E passent
- [ ] Tous les endpoints autorisés renvoient 200/201
- [ ] Tous les endpoints interdits renvoient 403
- [ ] Les items de menu interdits ne sont pas dans le DOM
- [ ] Couverture documentée dans [role]/README.md
```

## 9. Règles strictes (non négociables)

1. **Une story = un menu top-level**. Pas de méga-story.
2. **Tables Markdown** pour actions autorisées/interdites. Jamais de paragraphes.
3. **Toujours référencer des fichiers réels** avec ligne (ex : `Modules/UsersGuard/Routes/admin.php:26`, `Modules/NotesEvaluations/Routes/teacher.php:42`).
4. **Pas d'invention** : si un endpoint n'existe pas, le marquer **« À implémenter »** dans une section _Dépendances backend_ et le lister dans le README du rôle.
5. **Toutes les stories en français**.
6. **Pas de code d'implémentation** dans les stories — sauf snippet JSON court (payload d'exemple).
7. **Auto-vérification avant remise** :
   - `grep -r "{endpoint}" Modules/` pour confirmer l'existence,
   - libellés cohérents (faute du frontend menu, on documente la valeur cible et on flag « À valider FE »),
   - rôles cités présents dans `config/role-routes.php` ou explicitement marqués 🆕 pour `Parent`.

## 10. Cas particulier : rôle Parent

`Parent` n'existe ni dans `config/role-routes.php` ni dans le seeder. Le README de `parent/` devra **précéder** ses stories et trancher :

1. Slug exact (`Parent`).
2. Liste des permissions Spatie à ajouter dans `RolesAndPermissionsSeeder.php` (proposition de départ : `view children grades`, `view children attendance`, `view children timetable`, `view children invoices`, `pay children invoices`, `view children documents`, `message teachers`, `view announcements`).
3. `home_route` : `/admin/parent/home`.
4. Modèle de données : table pivot `parents` (`id`, `user_id`, …) + `parent_student` (`parent_id`, `student_id`, `relationship`, `is_primary_contact`, `is_financial_responsible`) — à corréler avec `docs/architecture/data-models.md#2.2-parents` et la Story 7.6 (Inscriptions).
5. Endpoints API à créer (préfixe `/api/admin/parent/...` ou `/api/frontend/parent/...` — décision à prendre dans le README parent) : `GET /me/children`, `GET /children/{id}/grades`, `GET /children/{id}/attendance`, `GET /children/{id}/timetable`, `GET /children/{id}/invoices`, `POST /children/{id}/invoices/{invoiceId}/pay`, `GET /messages`, `POST /messages`, `GET /announcements`.
6. Conflit potentiel avec PRD module-inscriptions §5.3 (table dénormalisée `student_parents`) vs architecture `data-models.md` (table pivot normalisée). Résolution attendue avant Story Parent 1.

Tous ces points seront tranchés dans `docs/stories/role-coverage/parent/README.md`.

## 11. Risques et hypothèses

| # | Risque / Hypothèse | Impact | Mitigation |
|---|---|---|---|
| R1 | Frontend (menu.config.ts) non accessible → libellés inférés | Stories invalidées si libellés divergent | Marquer chaque libellé « À valider FE » ; passage de relecture frontend lead |
| R2 | Routes admin sans `role:` middleware → état actuel = 200 pour tout authentifié | Les stories décrivent un état cible non encore implémenté | Section « Dépendances backend » + tâches « Ajouter middleware » dans chaque story |
| R3 | Modules `Documents`, `Exams`, `Payroll` utilisent `auth:sanctum` sans `tenant.auth` | Risque cross-tenant | Story Admin référence le défaut ; story de durcissement transversal à planifier |
| R4 | `Parent` introduit une nouvelle dimension d'autorisation (ownership : « est parent de cet élève ») | Policies à créer | README parent statue sur l'usage de `Gate`/`Policy` (recommandé : `ParentPolicy` avec `viewChild`, `viewChildGrades`, etc.) |
| R5 | `Étudiant` actuel : routes `/frontend/...` sans filtre owner (un étudiant peut interroger les données d'un autre) | Sécurité | Story Étudiant 1 cible explicitement `auth()->user()->student_id` côté contrôleur |
| R6 | Permissions du seeder non utilisées dans les routes (ex : `manage grades`, `view students`) | Confusion sources de vérité | Choisir entre `role:` ET `permission:` middleware. Recommandation : `permission:` pour les actions, `role:` pour les vues |

## 12. Critères de fin d'epic

- [ ] 8 READMEs de rôle créés et validés
- [ ] ~60-70 stories individuelles rédigées (∼9 par rôle en moyenne)
- [ ] Matrice section 5 mise à jour si des découvertes terrain l'invalident
- [ ] Conception complète du rôle Parent (permissions + migrations + endpoints) tranchée
- [ ] Backlog des **middlewares manquants** côté backend extrait des stories (chaque ❌ qui n'est pas bloqué aujourd'hui = ligne de backlog)
- [ ] Backlog des **endpoints à créer** (toutes les cellules 🆕 et mentions « À implémenter ») agrégé

## 13. Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Création initiale — README global, matrice 8 rôles × 22 menus, plan d'écriture progressif | SM Agent (Claude Opus 4.7) |

---

**▶ Prochaine étape** : à ta confirmation, je crée les 8 `README.md` de rôle (Étape 2). Je commencerai par `parent/README.md` (conception) puis les 7 autres en parallèle. Dis simplement « OK étape 2 » ou indique un ordre différent.
