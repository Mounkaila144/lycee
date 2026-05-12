# Prompt complet pour l'agent dev — Implémentation des stories non implémentées

> **À COPIER-COLLER** intégralement à l'agent dev en début de session.
> **Périmètre couvert** : 66 stories `role-coverage/` + Story 7.1 (Inscriptions Création Élève) + durcissement RBAC transverse.

---

## 0. Contexte projet (lecture obligatoire avant de coder)

Tu interviens sur une application **multi-tenant école secondaire (collège/lycée)** :
- **Backend** : Laravel 12 (PHP 8.3.26) + `nwidart/laravel-modules` + `stancl/tenancy` v3.9 + `spatie/laravel-permission` v6 + Laravel Sanctum v4.
- **Frontend** : Next.js 15 + React 18 + MUI 6 + Axios — **polyrepo séparé** (ce dépôt = backend uniquement).
- **Base de données** : MySQL 8.0 (chaque tenant a sa propre DB via stancl/tenancy).
- **Tests** : PHPUnit v11. Pattern d'auth tenant **CRITIQUE** détaillé dans `CLAUDE.md` (utiliser Bearer token réel, **PAS** `Sanctum::actingAs`).

Lis avant de commencer (obligatoire) :
1. `CLAUDE.md` (racine) — conventions Laravel 12 + Multi-Tenant Authentication Testing
2. `docs/architecture/coding-standards.md`
3. `docs/architecture/test-strategy-and-standards.md`
4. `docs/architecture/security.md`
5. `docs/architecture/source-tree.md`
6. `docs/architecture/data-models.md`
7. `docs/architecture/error-handling-strategy.md`

## 1. Mission globale

Implémenter **67 stories** rédigées par le SM Agent et qui sont en status **Draft** :
- **Story 7.1** : `docs/stories/7.1.story.md` — Création d'un Élève (Inscriptions module brownfield cleanup)
- **66 stories role-coverage** : `docs/stories/role-coverage/<role>/*.story.md`

Pour chaque story, ton job est :
1. Lire la story de bout en bout.
2. Appliquer les changements **Dépendances backend** listés en fin de story.
3. Implémenter les actions **autorisées** (endpoints, controllers, validations, resources).
4. Garantir le blocage des actions **interdites** (middlewares `role:`, ownership checks, policies).
5. Écrire les **tests E2E Feature** correspondant aux "Scenarios de test E2E" de chaque story (PHPUnit + `InteractsWithTenancy` + Bearer token réel).
6. Cocher la `Definition of Done` story par story et passer `## Status: Draft` → `## Status: Ready for Review`.

## 2. Ordre d'attaque (impératif)

L'implémentation **n'est pas** dans l'ordre alphabétique des dossiers. Respecte la séquence ci-dessous : chaque étape débloque la suivante.

### Étape A — Prérequis transverses (avant toute story)

A.1. **Corriger les failles `auth:sanctum` sans `tenant.auth`** (3 modules) :
- `Modules/Documents/Routes/admin.php`
- `Modules/Exams/Routes/admin.php`
- `Modules/Payroll/Routes/admin.php`

Remplacer `middleware(['auth:sanctum'])` par `middleware(['tenant', 'tenant.auth'])`. Vérifier que les contrôleurs résolvent bien la DB tenant.

A.2. **Ajouter le rôle `Parent`** :
- Modifier `config/role-routes.php` : ajouter `Parent` dans `hierarchy` (entre Étudiant et User) et `home_routes['Parent'] => '/admin/parent/home'`.
- Modifier `Modules/UsersGuard/Database/Seeders/RolesAndPermissionsSeeder.php` : ajouter les 9 permissions Parent et le bloc rôle (cf. `docs/stories/role-coverage/parent/README.md` §2).
- Exécuter : `php artisan db:seed --class=Modules\\UsersGuard\\Database\\Seeders\\RolesAndPermissionsSeeder`.
- Test : `php artisan tinker` → `Role::where('name', 'Parent')->first()->permissions` retourne 9 permissions.

A.3. **Créer le module `Modules/PortailParent/`** :
- `php artisan module:make PortailParent`.
- Créer migrations `parents` et `parent_student` (cf. `parent/README.md` §3.2).
- Créer `Modules/PortailParent/Entities/ParentModel.php` avec relations.
- Créer `Modules/PortailParent/Policies/ChildPolicy.php` avec méthodes `view`, `viewGrades`, `viewInvoices`, `payInvoices` (cf. `parent/README.md` §4).
- Enregistrer la policy dans `AuthServiceProvider`.

A.4. **Décisions PO à acter avant impl** (bloquantes pour 3 stories) :
- **Module name** : conserver `Modules/Enrollment/` OU renommer en `Modules/Inscriptions/` ? Recommandation Story 7.1 : conserver `Enrollment`. **Statue avec PO avant Story 7.1.**
- **Gateway paiement** (Story Parent 06) : CinetPay Mobile Money / Stripe / hors ligne ? Recommandation : CinetPay.
- **Messaging interne** (Story Parent 07) : créer mini-module `Modules/Messaging/` ou notifications unidirectionnelles ?
- **Scope LMD-héritage** (Story Étudiant 09, Admin 05) : Options/Groupes/Transfers/Equivalences pertinents en secondaire ? Si non, dégager du périmètre des stories et masquer côté frontend.

### Étape B — Story 7.1 (Inscriptions brownfield)

Implémenter intégralement `docs/stories/7.1.story.md` :
- Nettoyage LMD du module `Modules/Enrollment/`
- Migration alignement `students` au PRD secondaire
- `StoreStudentRequest`, `StudentController@store`, `StudentResource`
- Tests `tests/Feature/Enrollment/StudentStoreApiTest.php`
- Format Pint : `vendor/bin/pint --dirty`

### Étape C — Durcissement RBAC transverse (avant les stories par rôle)

Avant d'implémenter une story d'un rôle, appliquer les middlewares `role:` listés en section "Dépendances backend" de cette story. **Ne pas attendre la fin pour tout patcher** : agir module par module, story par story.

Pattern à respecter :
```php
// Lecture lecture transverse
Route::middleware(['tenant', 'tenant.auth', 'role:Administrator|Manager|<autres_lecture>,tenant'])
    ->group(function () { /* GET routes */ });

// Écriture restreinte
Route::middleware(['tenant', 'tenant.auth', 'role:Administrator,tenant'])
    ->group(function () { /* POST/PUT/DELETE sensibles */ });
```

Pour les **ownership checks** (un Prof ne voit que ses modules, un Étudiant que ses notes, un Parent que ses enfants) : implémenter dans le **controller** via `WHERE teacher_id = auth()->id()` ou via une **Policy** Laravel. **Ne JAMAIS faire confiance** aux query parameters (un client peut envoyer `?student_id=X`, la valeur doit toujours être dérivée de `auth()->user()`).

### Étape D — Stories rôle par rôle (ordre suggéré)

**D.1. Professeur** (9 stories — rôle le plus complexe à QA)
- `professeur/01-home-mes-classes.story.md` à `09-eleves-readonly.story.md`
- Module principal : `Modules/NotesEvaluations/Routes/teacher.php` (95 L) + Attendance + Timetable + UsersGuard
- Action critique : owner check sur `teacher_id` dans `GradeEntryController` (currently absent)

**D.2. Étudiant** (9 stories)
- Endpoints `/api/frontend/student/*` **à créer** (la plupart n'existent pas)
- Owner check obligatoire : `auth()->user()->student_id` jamais via query param
- Story 09 (Transfers/Equivalences) en attente décision PO scope LMD

**D.3. Parent** (9 stories — nécessite Étape A.2 + A.3)
- Story 01 (Home Mes Enfants) doit être impl. en premier (crée le squelette)
- Story 06 (Paiement en ligne) bloquée tant que gateway non choisi
- Story 07 (Messages) bloquée tant que décision Messaging non prise

**D.4. Caissier** (5 stories)
- Module principal : `Modules/Finance/Routes/admin.php` (214 L)
- Middlewares à ajouter : `role:Administrator|Comptable|Caissier,tenant` sur payments — `role:Administrator|Comptable,tenant` sur refund (exclure Caissier)
- Story 05 (Clôture caisse) introduit nouvelle table `cashier_close_records`

**D.5. Agent Comptable** (6 stories)
- Mêmes routes Finance, autres permissions (création facture, échéanciers, recouvrement, blocages)
- Story 06 (Blocage services) intègre cross-module : Documents, Exams, Reenrollment doivent consulter `collection/blocks/check` avant de servir

**D.6. Comptable** (6 stories)
- Routes finance avancées (refund, reconciliation, accounting-export)
- Story 03 (Rapprochement bancaire) crée 4 nouvelles tables : `bank_accounts`, `bank_transactions`, `payment_bank_transaction_matches`, `reconciliation_periods`

**D.7. Manager** (9 stories)
- Lecture transverse + utilisateurs sans delete
- Middlewares pattern : `role:Administrator|Manager,tenant` sur GET, `role:Administrator,tenant` sur DELETE users / structure write

**D.8. Administrator** (13 stories)
- Story 13 (Settings) crée `Modules/Settings/` (à créer) ou extension UsersGuard
- Toutes les autres = vérifier que les middlewares ajoutés pour les autres rôles **n'empêchent pas** Admin
- Pattern : `role:Administrator|<autre>,tenant` (jamais `role:<autre>,tenant` seul)

## 3. Règles non négociables

### 3.1 Conventions code (Laravel 12)
- `protected $connection = 'tenant';` sur tous les models tenant.
- `casts()` method (pas `$casts` property).
- Form Requests obligatoires (jamais de `$request->validate()` inline dans controller).
- API Resources obligatoires (jamais de `return $model`).
- Type hints stricts + return types.
- Pas d'`env()` hors `config/`.
- `vendor/bin/pint --dirty` avant chaque commit.

### 3.2 Conventions tests (CLAUDE.md MULTI-TENANT)
- PHPUnit v11 (jamais Pest).
- Trait `Tests\Concerns\InteractsWithTenancy` + `$this->setUpTenant()` dans `setUp()`.
- Auth via Bearer token réel : `$this->token = $this->user->createToken('test')->plainTextToken;` puis `withToken($this->token)`.
- **INTERDIT** : `Sanctum::actingAs()` (provoque 401 avec TenantSanctumAuth).
- Helpers : `authGetJson()`, `authPostJson()`, `authPutJson()`, `authDeleteJson()` (à copier dans chaque test feature).
- Factories : `Student::factory()->create()` plutôt que `Student::create([...])`.
- Pour chaque story, **les scenarios E2E listés DOIVENT être implémentés** comme tests Feature distincts.

### 3.3 Sécurité
- **Jamais** de query param pour identifier l'utilisateur courant — toujours `auth()->user()`.
- Policies Laravel pour l'ownership (Parent ↔ Enfant, Prof ↔ Module, Élève ↔ Self).
- Audit log obligatoire sur : modification de notes, refund, write-off, blocage service, changement de rôle, suppression utilisateur, signature document.
- Upload fichier : valider `mimes` + `max` + stockage sur disque `tenant` (jamais `public`).
- Throttle sur endpoints sensibles (login, paiement, relances).

### 3.4 Workflow per story
Pour chaque story `<role>/NN-xxx.story.md` :
1. `git checkout -b feat/role-coverage-<role>-NN-xxx`.
2. Lire la story complète.
3. Implémenter les "Actions autorisées" — endpoints + controllers + requests + resources + policies.
4. Implémenter les "Actions interdites" — middlewares + policies + tests négatifs.
5. Écrire tests Feature pour chaque "Scénario E2E" de la story.
6. `vendor/bin/pint --dirty` + `php artisan test --filter=<TestClass>`.
7. Cocher la **Definition of Done** de la story.
8. Changer `## Status: Draft` → `## Status: Ready for Review`.
9. Ajouter ligne au Change Log de la story (date + version + description + auteur "Dev Agent").
10. Mettre à jour la section `File List` de la story (créer la section `## Dev Agent Record > File List` si absente).
11. Commit + PR.

### 3.5 Quand un endpoint listé "À créer" n'existe pas
Tu as 2 options :
- **A. Le créer immédiatement** dans le cadre de la story (cas le plus fréquent).
- **B. Le marquer "Hors scope V1"** si l'effort est disproportionné, et l'ajouter au backlog (cf. §5).

Ne **jamais** simuler / mocker un endpoint pour "passer le test" — si l'endpoint n'existe pas, soit tu le crées, soit tu sortes la story du scope.

### 3.6 Conflits avec les architectures docs
Les docs `docs/architecture/*.md` et `docs/prd/*.md` peuvent diverger sur certains points (cf. Story 7.1 schéma `students`, Story Parent README schéma `parents`). **En cas de doute** :
- PRD = source de vérité **fonctionnelle** (ce que veut l'utilisateur).
- Architecture = source de vérité **technique** (comment c'est structuré).
- Si conflit : déposer la décision dans le Change Log de la story et fixer l'incohérence dans le doc en cause.

## 4. Tests à exécuter — par rôle

Après implémentation complète d'un rôle, lancer la suite ciblée :

```bash
# Tests Feature par rôle
php artisan test --filter=Tests\\Feature\\RoleCoverage\\Professeur
php artisan test --filter=Tests\\Feature\\RoleCoverage\\Etudiant
php artisan test --filter=Tests\\Feature\\RoleCoverage\\Parent
php artisan test --filter=Tests\\Feature\\RoleCoverage\\Caissier
php artisan test --filter=Tests\\Feature\\RoleCoverage\\AgentComptable
php artisan test --filter=Tests\\Feature\\RoleCoverage\\Comptable
php artisan test --filter=Tests\\Feature\\RoleCoverage\\Manager
php artisan test --filter=Tests\\Feature\\RoleCoverage\\Administrator

# Tests Feature transverses (modules)
php artisan test tests/Feature/Enrollment
php artisan test tests/Feature/NotesEvaluations
php artisan test tests/Feature/Finance
# etc.

# Suite complète (à la toute fin)
php artisan test
```

Localisation suggérée : `tests/Feature/RoleCoverage/<Role>/<NN>_<MenuName>Test.php`. Exemple : `tests/Feature/RoleCoverage/Professeur/02_SaisieNotesTest.php`.

## 5. Backlog à remonter au PM en fin de mission

À la fin de l'implémentation, agréger :

5.1. **Liste des endpoints créés** (avec route + middleware + permission Spatie).
5.2. **Liste des middlewares `role:` ajoutés** (modulo par module).
5.3. **Liste des policies créées** (`ChildPolicy`, `StudentSelfPolicy`, `TeacherOwnerPolicy`, etc.).
5.4. **Liste des migrations exécutées** + dates.
5.5. **Liste des décisions PO actées** (gateway paiement, scope LMD, messaging, etc.).
5.6. **Liste des stories sortis du scope V1** (avec motif) — à reporter en V2.

Format de remontée : un PR final `docs: backlog implementation result of role-coverage` qui ajoute `docs/stories/role-coverage/IMPLEMENTATION-RESULT.md`.

## 6. Estimation effort (indicative)

Sur la base d'un dev expert Laravel à temps plein :

| Étape | Effort estimé |
|---|---|
| A. Prérequis transverses + 4 décisions PO | 3-5 jours |
| B. Story 7.1 (brownfield cleanup) | 2-3 jours |
| C. Durcissement RBAC transverse (au fil de l'eau) | inclus dans D |
| D.1. Professeur 9 stories | 5-7 jours |
| D.2. Étudiant 9 stories (beaucoup endpoints à créer) | 7-10 jours |
| D.3. Parent 9 stories (création module + gateway + messaging) | 10-15 jours |
| D.4. Caissier 5 stories | 3-4 jours |
| D.5. Agent Comptable 6 stories | 4-5 jours |
| D.6. Comptable 6 stories (incl. réconciliation bancaire) | 6-8 jours |
| D.7. Manager 9 stories (lecture transverse) | 3-5 jours |
| D.8. Administrator 13 stories (étalon + Settings module) | 5-8 jours |
| **Total** | **~50-70 jours homme** |

Si dev moins expert : ×1.5. Si dev nouveau projet : ×2.

## 7. Quick wins prioritaires si temps serré

Si l'enveloppe est < 30 jours, prioriser dans cet ordre :

1. Étape A + Story 7.1 (~5 jours) — base saine
2. **Professeur entier** — usage quotidien critique (5-7 jours)
3. **Étudiant** stories 1, 2, 4, 5, 6 — portail élève minimal (4-5 jours)
4. **Parent** stories 1, 2, 3, 5 — sans paiement en ligne ni messages (5 jours)
5. **Caissier + Agent Comptable** — finance opérationnelle (6 jours)
6. RBAC : middlewares appliqués partout (essentiel) (3 jours)

Sortir du scope V1 : Comptable rapprochement bancaire complet, Admin Settings, Parent paiement en ligne, Manager dashboard agrégateur, stories LMD-héritage Étudiant 09.

## 8. Communication avec le PO en cours d'impl

À chaque ambiguïté listée comme "(À VALIDER PO)" dans une story :
- **Ne pas bloquer** : choisir l'option recommandée + ouvrir une issue GitHub `pm-decision-needed` avec contexte.
- Le PO doit valider la décision dans les 48h, sinon ton choix devient l'implémentation finale.

À chaque divergence repérée pendant l'impl entre PRD et architecture :
- Documenter dans le Change Log de la story.
- Ne pas modifier silencieusement les docs.

## 9. Format de PR

Chaque PR doit contenir :
- Titre : `feat(role-coverage): <role> — <menu> (story <NN>)`
- Corps :
  - Lien vers la story (`docs/stories/role-coverage/<role>/NN-xxx.story.md`)
  - Liste des endpoints créés/modifiés
  - Liste des middlewares ajoutés
  - Liste des tests ajoutés
  - Liste des décisions tranchées (si applicable)
  - Capture d'écran UI si pertinent (optionnel)

## 10. Critères d'acceptation globaux (par rôle)

Une fois un rôle entièrement implémenté :
- [ ] Toutes les stories du rôle sont en `Status: Ready for Review`.
- [ ] `php artisan test --filter=RoleCoverage\\<Role>` → tout vert.
- [ ] Test cross-tenant : token Admin tenant A → ressource tenant B → 404 ou 403 garanti.
- [ ] Test escalade : tenter une action interdite → 403 systématique (jamais de 200).
- [ ] Sidebar Next.js (côté frontend repo séparé — coord requise) filtre les items selon le rôle.
- [ ] Audit log écrit pour toutes les actions sensibles du rôle.
- [ ] Performance : endpoint principal du rôle < 500ms en local.

## 11. Critères d'acceptation finaux (en fin de mission)

- [ ] 67 stories en `Status: Ready for Review` (66 role-coverage + 7.1).
- [ ] Aucun module backend n'a de `auth:sanctum` sans `tenant.auth`.
- [ ] Tous les modules ont au moins un `middleware('role:')` (sauf endpoints publics).
- [ ] `php artisan test` → tout vert (couverture ≥ 80% sur controllers API selon `test-strategy-and-standards.md`).
- [ ] `docs/stories/role-coverage/IMPLEMENTATION-RESULT.md` créé avec le backlog (cf. §5).
- [ ] Aucun secret commité (clés API, mots de passe).

---

## 12. Référence rapide — Stories à implémenter (cocher au fur et à mesure)

### Prérequis & Story 7.1
- [ ] A.1 — Corriger `auth:sanctum` (Documents, Exams, Payroll)
- [ ] A.2 — Créer rôle `Parent` (config + seeder)
- [ ] A.3 — Créer module `PortailParent` (entités + policy)
- [ ] A.4 — Décisions PO (gateway, messaging, module name, scope LMD)
- [ ] **Story 7.1** — Création d'un Élève

### Professeur (9)
- [ ] `01-home-mes-classes`
- [ ] `02-saisie-notes`
- [ ] `03-import-notes-batch`
- [ ] `04-absences-evaluations`
- [ ] `05-rattrapages`
- [ ] `06-presences-cours`
- [ ] `07-mon-emploi-du-temps`
- [ ] `08-surveillance-examens`
- [ ] `09-eleves-readonly`

### Étudiant (9)
- [ ] `01-home-portail`
- [ ] `02-mes-notes-bulletins`
- [ ] `03-mon-emploi-du-temps`
- [ ] `04-mes-presences`
- [ ] `05-mes-factures-paiements`
- [ ] `06-mes-documents`
- [ ] `07-ma-carte-etudiante`
- [ ] `08-reinscription`
- [ ] `09-transferts-equivalences` (selon décision PO)

### Parent (9)
- [ ] `01-home-mes-enfants`
- [ ] `02-notes-enfant`
- [ ] `03-presences-enfant`
- [ ] `04-emploi-du-temps-enfant`
- [ ] `05-factures-enfant`
- [ ] `06-paiement-en-ligne` (selon décision gateway)
- [ ] `07-messages-enseignants` (selon décision messaging)
- [ ] `08-annonces-ecole`
- [ ] `09-documents-enfant`

### Caissier (5)
- [ ] `01-home-encaissements`
- [ ] `02-saisie-paiement`
- [ ] `03-recus`
- [ ] `04-factures-lecture`
- [ ] `05-rapports-journaliers`

### Agent Comptable (6)
- [ ] `01-home-facturation`
- [ ] `02-factures-crud`
- [ ] `03-echeanciers`
- [ ] `04-penalites-retard`
- [ ] `05-recouvrement`
- [ ] `06-blocage-services`

### Comptable (6)
- [ ] `01-home-rapports`
- [ ] `02-vue-ensemble-finance`
- [ ] `03-rapprochement-bancaire`
- [ ] `04-refunds`
- [ ] `05-exports-comptables`
- [ ] `06-paie-lecture`

### Manager (9)
- [ ] `01-dashboard`
- [ ] `02-users-management`
- [ ] `03-academic-structure-readonly`
- [ ] `04-enrollments`
- [ ] `05-grades-readonly`
- [ ] `06-attendance-readonly`
- [ ] `07-timetable-readonly`
- [ ] `08-documents`
- [ ] `09-finance-readonly`

### Administrator (13)
- [ ] `01-dashboard`
- [ ] `02-users-management`
- [ ] `03-roles-permissions`
- [ ] `04-academic-structure`
- [ ] `05-enrollments`
- [ ] `06-grades-evaluations`
- [ ] `07-attendance`
- [ ] `08-timetable`
- [ ] `09-exams`
- [ ] `10-documents`
- [ ] `11-finance`
- [ ] `12-payroll`
- [ ] `13-settings`

---

**Total : 67 stories. Bon courage. Si tu bloques, créée une issue avec `pm-decision-needed`, ou viens me consulter.**
