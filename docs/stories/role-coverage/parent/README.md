# Epic Parent — Conception du rôle + Couverture des menus

> **Status** : Draft (design — précède toutes les stories Parent)
> **Type** : Epic role-coverage + design document
> **Date** : 2026-05-12
> **Précondition** : `Parent` n'existe nulle part en base ni dans `config/role-routes.php` ni dans `RolesAndPermissionsSeeder.php` — l'ensemble de la **structure RBAC + données + endpoints** est à créer. Aucune des stories de cet epic ne peut démarrer avant le merge de la story de conception ci-dessous.

## 1. Identité du rôle (proposition à valider)

| Champ | Valeur proposée | Justification |
|---|---|---|
| Slug interne (Spatie) | `Parent` | Cohérent avec les autres slugs francisés (`Professeur`, `Étudiant`, `Caissier`, `Agent Comptable`, `Comptable`) — cf. `RolesAndPermissionsSeeder.php:121` |
| Display name | `Parent / Tuteur` | Inclut les tuteurs légaux (frère aîné, oncle, ASE…) |
| `guard_name` Spatie | `tenant` | Aligné avec les autres rôles (cf. `RolesAndPermissionsSeeder.php:122`) |
| `home_route` | `/admin/parent/home` | Convention du projet : home par rôle dans `config/role-routes.php` |
| Position dans `hierarchy` | Entre `Étudiant` et `User` | Le parent agit en représentation d'un mineur — pas plus de droits qu'un étudiant majeur |

**Modification requise dans `config/role-routes.php`** :

```php
'hierarchy' => [
    'Administrator',
    'Manager',
    'Comptable',
    'Agent Comptable',
    'Caissier',
    'Professeur',
    'Étudiant',
    'Parent',          // ← À ajouter
    'User',
],

'home_routes' => [
    // …
    'Étudiant' => '/admin/student/home',
    'Parent' => '/admin/parent/home',   // ← À ajouter
    'User' => '/admin/dashboard',
],
```

## 2. Permissions Spatie à créer

À ajouter dans `RolesAndPermissionsSeeder.php` (bloc `$permissions` autour de L70) :

```php
// Parent Permissions
'view children',                  // Liste de SES enfants uniquement
'view children grades',           // Notes des SES enfants
'view children attendance',       // Absences/présences des SES enfants
'view children timetable',        // Emploi du temps des SES enfants
'view children invoices',         // Factures des SES enfants
'pay children invoices',          // Initier un paiement en ligne
'view children documents',        // Bulletins, certificats des SES enfants
'message teachers',               // Échanger avec les enseignants des SES enfants
'view announcements',             // Annonces de l'établissement
```

Et un bloc rôle dédié (à ajouter avant le `tenancy()->end();` autour de L218) :

```php
$parentRole = Role::updateOrCreate(
    ['name' => 'Parent', 'guard_name' => 'tenant'],
    ['display_name' => 'Parent / Tuteur', 'description' => 'Parent ou tuteur légal — suivi scolaire et paiement en ligne']
);
$parentRole->syncPermissions([
    'view dashboard',
    'view children',
    'view children grades',
    'view children attendance',
    'view children timetable',
    'view children invoices',
    'pay children invoices',
    'view children documents',
    'message teachers',
    'view announcements',
]);
```

> **Note CRITIQUE** : ces permissions sont des **vues thématiques**, pas des « bypass autorisation ». L'isolation Parent ↔ Enfant n'est **pas** une permission Spatie — c'est une `Policy` (cf. §4). Un parent ayant `view children grades` ne doit JAMAIS pouvoir voir les notes d'un enfant qui n'est pas le sien.

## 3. Modèle de données

### 3.1 Conflit à trancher AVANT migration

Deux schémas concurrents existent dans la documentation :

| Source | Forme | Position |
|---|---|---|
| `docs/architecture/data-models.md#2.2 parents` + `#2.3 student_parent` | **Normalisé** : table `parents` (entité propre, liée à `users` via `user_id`) + pivot `student_parent` avec `is_primary_contact`, `is_financial_responsible` | Recommandé |
| `docs/prd/module-inscriptions.md#5.3 Base de donnees` | **Dénormalisé** : table `student_parents` avec colonnes `father_*`, `mother_*`, `guardian_*` | À écarter |

**Décision recommandée pour cet epic** : suivre l'architecture (normalisé). La dénormalisation PRD bloque le rôle Parent — il faut une entité `parents` réutilisable pour un parent ayant plusieurs enfants (fratrie). Le PRD doit être mis à jour.

### 3.2 Migrations à créer (tenant)

```
Modules/PortailParent/Database/Migrations/tenant/
├── 2026_05_xx_create_parents_table.php
└── 2026_05_xx_create_parent_student_table.php
```

Schémas (extraits de `data-models.md` §2.2 et §2.3) :

```sql
-- parents
CREATE TABLE parents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,            -- FK -> users.id (compte portail Parent)
    firstname VARCHAR(255) NOT NULL,
    lastname VARCHAR(255) NOT NULL,
    relationship ENUM('Père','Mère','Tuteur','Tutrice','Autre') NOT NULL,
    phone VARCHAR(20) NOT NULL,
    phone_secondary VARCHAR(20) NULL,
    email VARCHAR(255) NULL,
    profession VARCHAR(255) NULL,
    address TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_phone (phone),
    INDEX idx_user (user_id)
);

-- parent_student (pivot M:N)
CREATE TABLE parent_student (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    is_primary_contact BOOLEAN DEFAULT FALSE,
    is_financial_responsible BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_parent_student (parent_id, student_id)
);
```

### 3.3 Models Eloquent

- `Modules/PortailParent/Entities/ParentModel.php` — `protected $connection = 'tenant';`, relations `user(): BelongsTo`, `students(): BelongsToMany`.
- Extension de `Modules/Enrollment/Entities/Student.php` : ajouter `parents(): BelongsToMany` (pivot `parent_student`, withPivot `is_primary_contact`, `is_financial_responsible`).

### 3.4 Où vit le code Parent ?

**Recommandation** : créer un module dédié `Modules/PortailParent/` (déjà prévu dans `docs/architecture/source-tree.md` ligne 300+ et `docs/architecture/components.md` ligne 75). Le module agrège les données d'autres modules (Notes, Attendance, Finance, Timetable, Documents) — il n'a pas d'entités métier propres en dehors de `Parent` lui-même.

Structure du module :

```
Modules/PortailParent/
├── Config/
├── Database/
│   ├── Factories/
│   │   ├── ParentFactory.php
│   │   └── ParentStudentFactory.php
│   └── Migrations/tenant/
│       ├── 2026_05_xx_create_parents_table.php
│       └── 2026_05_xx_create_parent_student_table.php
├── Entities/
│   └── ParentModel.php
├── Http/
│   ├── Controllers/Admin/
│   │   ├── ParentChildrenController.php
│   │   ├── ParentGradesController.php
│   │   ├── ParentAttendanceController.php
│   │   ├── ParentTimetableController.php
│   │   ├── ParentInvoiceController.php
│   │   ├── ParentDocumentsController.php
│   │   ├── ParentMessageController.php
│   │   └── ParentAnnouncementController.php
│   ├── Middleware/
│   │   └── EnsureChildOwnership.php   ← garde maison ou Policy (cf. §4)
│   ├── Requests/
│   └── Resources/
│       └── ChildResource.php
├── Policies/
│   └── ChildPolicy.php
├── Providers/
│   └── PortailParentServiceProvider.php
├── Routes/
│   └── admin.php
└── module.json
```

## 4. Stratégie d'autorisation (ownership Parent ↔ Enfant)

Spatie permissions = vues thématiques. **L'ownership doit passer par une Policy Laravel** :

```php
// Modules/PortailParent/Policies/ChildPolicy.php
namespace Modules\PortailParent\Policies;

use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\TenantUser;

class ChildPolicy
{
    public function view(TenantUser $user, Student $student): bool
    {
        if (! $user->hasRole('Parent')) {
            return false;
        }
        return $user->parent?->students()->where('students.id', $student->id)->exists() === true;
    }

    public function viewGrades(TenantUser $user, Student $student): bool
    {
        return $user->hasPermissionTo('view children grades')
            && $this->view($user, $student);
    }

    public function viewInvoices(TenantUser $user, Student $student): bool
    {
        return $user->hasPermissionTo('view children invoices')
            && $this->view($user, $student);
    }

    public function payInvoices(TenantUser $user, Student $student): bool
    {
        $relation = $user->parent?->students()
            ->where('students.id', $student->id)
            ->wherePivot('is_financial_responsible', true)
            ->exists();

        return $user->hasPermissionTo('pay children invoices') && $relation === true;
    }
}
```

Chaque endpoint Parent doit appeler `$this->authorize('viewGrades', $student)` AVANT de retourner les données. Sans ça, on retombe sur le défaut « tout authentifié peut tout faire » (R5 du README global).

## 5. Endpoints API à créer

Préfixe choisi : **`/api/admin/parent/...`** (cohérent avec `/admin/teacher/home` et `/admin/student/home` de `role-routes.php`). Tous sous `middleware(['tenant', 'tenant.auth', 'role:Parent,tenant'])`.

| Méthode | Endpoint | Controller@method | Permission Spatie | Policy |
|---|---|---|---|---|
| GET | `/api/admin/parent/me` | `ParentChildrenController@me` | `view children` | — |
| GET | `/api/admin/parent/me/children` | `ParentChildrenController@children` | `view children` | — (filtre owner natif) |
| GET | `/api/admin/parent/children/{student}` | `ParentChildrenController@show` | `view children` | `ChildPolicy::view` |
| GET | `/api/admin/parent/children/{student}/grades` | `ParentGradesController@index` | `view children grades` | `ChildPolicy::viewGrades` |
| GET | `/api/admin/parent/children/{student}/grades/semester/{semester}` | `ParentGradesController@semester` | `view children grades` | `ChildPolicy::viewGrades` |
| GET | `/api/admin/parent/children/{student}/attendance` | `ParentAttendanceController@index` | `view children attendance` | `ChildPolicy::view` |
| GET | `/api/admin/parent/children/{student}/timetable` | `ParentTimetableController@index` | `view children timetable` | `ChildPolicy::view` |
| GET | `/api/admin/parent/children/{student}/invoices` | `ParentInvoiceController@index` | `view children invoices` | `ChildPolicy::viewInvoices` |
| GET | `/api/admin/parent/children/{student}/invoices/{invoice}` | `ParentInvoiceController@show` | `view children invoices` | `ChildPolicy::viewInvoices` |
| POST | `/api/admin/parent/children/{student}/invoices/{invoice}/pay` | `ParentInvoiceController@initiatePayment` | `pay children invoices` | `ChildPolicy::payInvoices` |
| GET | `/api/admin/parent/payments/{payment}/status` | `ParentInvoiceController@paymentStatus` | `pay children invoices` | — (filtre owner) |
| GET | `/api/admin/parent/children/{student}/documents` | `ParentDocumentsController@index` | `view children documents` | `ChildPolicy::view` |
| GET | `/api/admin/parent/children/{student}/documents/{document}/download` | `ParentDocumentsController@download` | `view children documents` | `ChildPolicy::view` |
| GET | `/api/admin/parent/messages` | `ParentMessageController@index` | `message teachers` | — (filtre owner) |
| POST | `/api/admin/parent/messages` | `ParentMessageController@store` | `message teachers` | `ChildPolicy::view` (sur l'enfant lié au message) |
| GET | `/api/admin/parent/messages/{thread}` | `ParentMessageController@show` | `message teachers` | — |
| GET | `/api/admin/parent/announcements` | `ParentAnnouncementController@index` | `view announcements` | — |
| GET | `/api/admin/parent/announcements/{announcement}` | `ParentAnnouncementController@show` | `view announcements` | — |

**Tous ces endpoints sont à créer** — aucun n'existe dans `Modules/*/Routes/*.php` aujourd'hui (vérifié via `grep -r "parent" Modules/`).

## 6. Intégration paiement en ligne (CRITIQUE — décision produit attendue)

Le paiement en ligne suppose un **gateway**. Trois options :

| Option | Avantages | Inconvénients |
|---|---|---|
| **A. CinetPay / Orange Money / Moov Money** (locaux Niger/UEMOA) | Moyens de paiement utilisés par les parents nigériens (Mobile Money) | API à intégrer ; KYC ; gestion webhook |
| **B. Stripe** | Robuste, doc claire | Pas adapté Mobile Money local, frais en USD |
| **C. Marquer « hors ligne »** : générer un QR/bon de paiement à présenter en banque/caisse | Pas de gateway à intégrer | Pas de vrai paiement en ligne — défaut d'AC parent §06 |

**Recommandation** : option **A** (CinetPay multi-canal supporte Orange Money / Moov Money / cartes). Décision à valider par PO avant Story Parent 06 (`06-paiement-en-ligne.story.md`).

Côté backend (à ajouter à `Modules/Finance/`) :
- `Modules/Finance/Services/PaymentGatewayService.php`
- `Modules/Finance/Http/Controllers/Webhook/CinetPayWebhookController.php`
- Endpoint webhook public : `POST /api/webhooks/cinetpay` (sans `tenant.auth`, avec signature HMAC)
- Table `payments` augmentée : `gateway`, `gateway_transaction_id`, `gateway_status`, `webhook_received_at`

Cette intégration n'est **pas** dans le scope de l'epic role-coverage. Elle est référencée comme **dépendance bloquante** de la Story Parent 06.

## 7. Profil utilisateur

- Démographie : âgé(e) de 25-55 ans, alphabétisation variable (français), accès souvent via smartphone bas/milieu de gamme.
- Plateforme prioritaire : **Mobile-first** (WCAG AA, contraste élevé).
- Connaissances numériques : faibles à moyennes — l'UX doit minimiser les clics et favoriser les libellés explicites.
- Cas typique : 2-3 enfants dans l'établissement (fratrie). Doit pouvoir basculer rapidement d'un enfant à l'autre.

## 8. Backend coverage (modules touchés)

| Module | Lecture | Écriture | Notes |
|---|---|---|---|
| **PortailParent** (à créer) | ✅ | ✅ | Module dédié — 8 controllers + 1 policy |
| **Enrollment** | ✅ (lecture des données enfants) | ❌ | Via relations `parent_student` |
| **NotesEvaluations** | ✅ (notes des enfants) | ❌ | Vue read-only depuis `Modules/NotesEvaluations/...` |
| **Attendance** | ✅ (absences des enfants) | ❌ | |
| **Timetable** | ✅ (EDT des enfants) | ❌ | |
| **Finance** | ✅ (factures) | ⚠️ (initier un paiement) | Webhook gateway |
| **Documents** | ✅ (bulletins, certificats des enfants) | ❌ | |
| **Messages** (module à créer ?) | ✅ | ✅ | Messagerie interne — à scoper (peut-être hors epic) |

> **Question ouverte** : la messagerie interne enseignant↔parent existe-t-elle ? `grep` confirme absence dans `Modules/`. Story Parent 07 sera marquée bloquée par la création d'un mini-module `Modules/Messaging/` OU une simplification (notifications push unidirectionnelles).

## 9. Stories planifiées (9 stories)

| # | Fichier | Menu cible | Story |
|---|---|---|---|
| 01 | `01-home-mes-enfants.story.md` | Sidebar « Mes enfants » | Dashboard parent + sélecteur d'enfant (fratrie) |
| 02 | `02-notes-enfant.story.md` | « Notes » de l'enfant sélectionné | Bulletins semestriels, moyennes par matière |
| 03 | `03-presences-enfant.story.md` | « Présences » | Calendrier d'absences/retards, justificatifs |
| 04 | `04-emploi-du-temps-enfant.story.md` | « Emploi du temps » | Grille semaine + export PDF |
| 05 | `05-factures-enfant.story.md` | « Factures » (lecture) | Liste factures, statut, soldes |
| 06 | `06-paiement-en-ligne.story.md` | Bouton « Payer » | Tunnel paiement Mobile Money + webhook |
| 07 | `07-messages-enseignants.story.md` | « Messages » | Conversation par enfant avec enseignants |
| 08 | `08-annonces-ecole.story.md` | « Annonces » | Annonces de l'établissement (lecture) |
| 09 | `09-documents-enfant.story.md` | « Documents » | Téléchargement bulletins/certificats |

## 10. Dépendances backend (résumé)

1. **Bloc seeder à ajouter** dans `RolesAndPermissionsSeeder.php` (rôle + 9 permissions).
2. **Mise à jour `config/role-routes.php`** (hiérarchie + home_routes).
3. **Création du module `Modules/PortailParent/`** : entités, migrations, controllers, policies, routes (cf. §3.4).
4. **Création des 18 endpoints** listés en §5.
5. **Intégration gateway paiement** (Story 06 bloquée par décision PO option A/B/C — cf. §6).
6. **Création du mini-module Messaging** OU dégradation Story 07 en notifications unidirectionnelles.
7. **Ajout `middleware('role:Parent,tenant')`** sur toutes les routes `parent/`.
8. **Création d'au moins une `factory()` ParentFactory + states `withChildren()`** pour les tests.
9. **Story 7.6 du backlog Inscriptions** (Saisie Parent/Tuteur) devient prérequis : la création d'un compte Parent doit générer automatiquement le `users` + le `parents` + le pivot lors de l'inscription d'un élève.

## 11. Risques spécifiques au rôle Parent

| # | Risque | Mitigation |
|---|---|---|
| P1 | Conflit schéma `parents` (PRD dénormalisé vs architecture normalisée) | Décision en §3.1 ; mettre à jour PRD module-inscriptions §5.3 |
| P2 | Parent multi-tenant (un parent dont 2 enfants sont dans 2 écoles) | Hors scope V1 — Parent vit dans le tenant de son enfant ; un compte par tenant |
| P3 | Parent sans email mais avec téléphone uniquement | Auth par SMS OTP — hors scope V1, démarrer avec email obligatoire |
| P4 | Fraude paiement (parent paie pour l'enfant d'un autre) | Pivot `is_financial_responsible = true` requis ; check via `ChildPolicy::payInvoices` |
| P5 | Confidentialité notes (parent divorcé sans droit de visite) | Champ `is_primary_contact` + filtre `parent_student.allow_view_grades` (à ajouter — Phase 2) |
| P6 | Charge serveur sur paiement masse (fin de trimestre) | Queue Laravel pour les webhooks ; throttle 5/min sur l'endpoint `pay` |

## 12. Definition of Done de l'epic Parent

- [ ] Rôle `Parent` ajouté au seeder + permissions + `php artisan db:seed` testé sur tenant pilote
- [ ] `config/role-routes.php` mis à jour (hierarchy + home_routes)
- [ ] Module `Modules/PortailParent/` créé (au minimum entités, migrations, ParentChildrenController, ChildPolicy)
- [ ] Migrations `parents` + `parent_student` créées et exécutées
- [ ] Factory `ParentFactory` + state `::withChildren(int $count)` créée
- [ ] 9 stories Parent rédigées en Draft
- [ ] Bug PRD §5.3 (dénormalisation `student_parents`) tracké et corrigé
- [ ] Décision gateway paiement actée (option A/B/C) par PO

## 13. Change log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-05-12 | 1.0 | Conception initiale du rôle Parent — schéma, endpoints, policy, 9 stories planifiées | SM Agent (Claude Opus 4.7) |
