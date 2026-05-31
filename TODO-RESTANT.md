# Jandoo — Passation & Travail restant (handoff)

> Document destiné à une **nouvelle session Claude Code**. Il contient : le contexte, les conventions/pièges **critiques** (à lire AVANT de coder), l'état actuel, puis la liste **détaillée et priorisée** des tâches restantes.

---

## 0. Contexte projet

- **Produit** : Jandoo — logiciel de gestion scolaire **secondaire (collèges & lycées) au Niger**. ⚠️ **PAS d'enseignement supérieur / LMD.** Quand tu vois du code LMD (modules/UE, groupes pédagogiques, ECTS, semestres LMD, programmes L1‑M2, rattrapage, compensation, délibération universitaire), il faut le **retirer ou le refondre en secondaire** (classe + matière + élève).
- **Stack** : Laravel 12 (backend) + Next.js 15 / MUI (frontend). **Polyrepo.**
  - Backend : `C:\laragon\www\lycee` (modules dans `Modules/<Nom>/`).
  - Frontend : `C:\Users\Mounkaila\PhpstormProjects\lycee-frontend` (modules dans `src/modules/<Nom>/`).
- **Multi-tenant** : stancl/tenancy. Base centrale = `lycee_api`. Bases tenant = `tenant_company1`, `tenant_company2`, `tenant_demo`. Le tenant de test `tenant1.local` = **company1**.
- **Accès / URL** : http://tenant1.local/en/login
- **Comptes de test (mot de passe `Password123!` sauf admin)** :
  - `admin` / `password` (Administrator)
  - `prof_test` / `Password123!` (Professeur)
  - `comptable_test` / `Password123!` (Comptable)
  - `eleve_test` / `Password123!` (Élève) — non lié à une fiche élève
  - `parent_test` / `Password123!` (Parent) — non lié à une fiche parent
- **Rôles (Spatie, guard `tenant`)** : Administrator, Manager, User, Professeur, **Élève**, **Parent**, Caissier, Comptable. (Le système `groups` legacy est inutilisé.)

---

## 1. ⚠️ Conventions & pièges CRITIQUES (lire avant de coder)

Ces pièges reviennent dans TOUT le projet — les ignorer fait perdre des heures :

1. **Guard tenant** : l'utilisateur authentifié est sur le guard `tenant` (middleware `TenantSanctumAuth`). `auth()->id()` renvoie **null** → utiliser **`auth('tenant')->id()`** (ou `$request->user()->id`) dans les contrôleurs/services tenant. Sinon colonnes `created_by`/etc. à null ou 500.
2. **Route-model-binding cassé** : dans les routes tenant, SubstituteBindings s'exécute **avant** l'init de tenancy → un paramètre typé `Model $x` provoque `SQLSTATE[3D000] No database selected`. **Toujours** accepter `int $id` et charger manuellement : `Model::on('tenant')->findOrFail($id)`.
3. **Préfixe API** : le frontend (`src/shared/lib/api-client.ts`) utilise `baseURL = '/api'` et les services appellent `/admin/...`. Donc les routes backend doivent résoudre sous **`api/admin/...`**. (Modules historiquement fautifs déjà corrigés : Attendance/Payroll/Exams/Documents/Timetable.)
4. **Réponses backend** souvent enveloppées `{ data: ... }` (ou paginator `{data:[...], current_page,...}`). Côté frontend, utiliser un helper `toArray(resp)` qui gère array / `.data` / `.data.data`.
5. **Tableaux de bord mock** : le pattern de correction = réécrire le composant `*Dashboard.tsx` avec **react-query** (`@tanstack/react-query`) + `createApiClient` de `@/shared/lib/api-client`, états loading/empty/error, **zéro donnée `DEMO_`**. Références déjà câblées à copier :
   - `src/modules/Finance/admin/components/FinanceReportsDashboard.tsx`
   - `src/modules/Payroll/admin/components/SecondaryPayrollEmployees.tsx`
   - `src/modules/Grades/admin/components/SecondaryGradeEntry.tsx`
6. **i18n frontend** : `useTranslation('<Module>')` doit correspondre **exactement** au dossier `src/modules/<Module>/translations/` qui doit contenir `en.json`, `fr.json`, `ar.json` (clé manquante → fallback sur le texte source).
7. **Après édition backend** : `php artisan optimize:clear` (OPcache `validate_timestamps` recharge les fichiers). **Pas de route cache.** Lancer **`vendor/bin/pint --dirty`** avant de finaliser (NE PAS faire `pint --test`).
8. **Migrations tenant** : `php artisan tenants:migrate --tenants=company1` (env `local` → **pas besoin de `--force`** ; `--force` est bloqué par le classifieur de sécurité). MySQL 8.4 : **pas de défaut littéral sur colonne JSON** (utiliser `DB::raw("(JSON_ARRAY(...))")`).
9. **Tests** : projet PHPUnit. Auth tenant en test = **vrais tokens Bearer** (PAS `Sanctum::actingAs`). Voir le template complet dans `CLAUDE.md` (trait `InteractsWithTenancy`, `setUpTenant()`, désactiver `InitializeTenancyByDomain`, helpers `authGetJson/authPostJson`).
10. **Frontend dev** : Next.js en dev avec HMR — **ne pas lancer `npm run build`**. Le warning console `WebSocket … webpack-hmr … 200` est un bruit de proxy inoffensif.
11. **Navigateur de test** : MCP chrome-devtools. Si « browser already running », tuer l'arbre chrome du profil `chrome-devtools-mcp` puis renaviguer.

---

## 2. Modèle de données secondaire (déjà en place)

- **Tables réelles utilisées** : `classes` (ex. « 6ème A », « 5ème A »), `subjects` (matières : MATH, FRAN, SVT…), `students` (avec `class_id`, `email`, `mobile`, `country` ajoutés), `subject_class_coefficients` (82 lignes), `semesters` (S1/S2 par année — servent de « période/trimestre »), `academic_years`.
- **Présences** : `attendance_sessions` (class_id + subject_id), `attendance_records` ; élèves = `students WHERE class_id`.
- **Notes** : table **`evaluations`** créée (class_id, subject_id, semester_id, type Devoir/Composition/Interrogation, max_score, coefficient) ; `grades` (score /20) repointée dessus.
- **Emploi du temps** : `timetable_slots` (class_id, subject_id, teacher_id, room_name, day, times ; colonnes LMD nullable).
- **Examens** : `exam_sessions` (class_id, subject_id, room_name ; module_id/academic_year_id nullable).
- **Documents** : `documents` (certificats générés). `document_templates` est **VIDE** (bloque la génération PDF basée template).

---

## 3. État actuel — FAIT (ne pas refaire)

Tout l'espace **admin** est en données réelles + boutons fonctionnels, vérifié dans le navigateur :
- ✅ Rôles nettoyés (fusion Agent Comptable→Comptable, Étudiant→Élève, +Parent) + redirection post-login par rôle (`config/role-routes.php` + `user.home_route`).
- ✅ **Présences** (appel par classe/matière), **Notes** (saisie /20 par classe/matière/évaluation, moyennes), **Inscriptions/Élèves** (création + affectation classe), **Emplois du Temps** (créneaux), **Examens** (planification).
- ✅ **Comptabilité** : Facturation, Rapports (4 onglets), Paiements, Recouvrement — tous réels.
- ✅ **Documents** : certificat de scolarité / attestation = **vrai PDF** (DomPDF inline) + registre + vérification.
- ✅ **Paie & RH** : Employés (création), Traitement, Éléments de paie, Rapports, Déclarations — câblés.
- ✅ Nettoyage LMD : menu Notes réduit à « Saisie des notes » ; page d'accueil réécrite (secondaire).
- ✅ Bugs systémiques corrigés : base centrale `crm_central`→`lycee_api`, préfixes API 404, migrations en attente, guard `auth('tenant')`, route-model-binding, colonnes manquantes.

Détails complets dans la mémoire projet : `C:\Users\Mounkaila\.claude\projects\C--laragon-www-lycee\memory\MEMORY.md` (auto-chargée dans toute session Claude Code de ce projet).

---

## 4. 🔧 TRAVAIL RESTANT (priorisé)

### P1 — Bulletins de notes (cœur métier manquant)
Le **bulletin trimestriel** n'existe pas encore. C'est LA fonctionnalité attendue d'un logiciel de lycée.
- **Backend** : créer un contrôleur secondaire (ex. `Modules/NotesEvaluations/Http/Controllers/Admin/BulletinController.php`) qui, pour une **classe + période (semester_id)** :
  - calcule par élève et par matière la **moyenne** = moyenne des notes `grades` des `evaluations` (pondérée par `evaluation.coefficient`) ;
  - calcule la **moyenne générale** pondérée par le coefficient matière (`subject_class_coefficients` : colonnes à vérifier) ;
  - calcule le **rang** de chaque élève dans la classe ;
  - expose `GET /api/admin/bulletins?class_id&semester_id` (tableau récap classe) et `GET /api/admin/bulletins/{studentId}/pdf?semester_id` (**bulletin PDF** via DomPDF inline, même technique que `SecondaryCertificateController`).
  - ⚠️ pièges : `auth('tenant')`, `int $id` manuel, réponse `{data:...}`.
- **Frontend** : nouveau composant + entrée de menu « Bulletins » dans `src/modules/Grades/menu.config.ts` ; page `app/[lang]/admin/grades/transcripts/page.tsx` (ou une nouvelle) : sélection classe+période → tableau des moyennes/rangs → bouton PDF par élève + « Générer toute la classe ».
- Réutiliser le modèle `Evaluation`/`Grade` déjà en place.

### P2 — Activer la génération PDF Documents (templates)
Transcripts / Diplômes / Cartes ont leurs boutons de génération **désactivés** car `document_templates` est vide et `CertificateService` lève une exception.
- Option recommandée (cohérente avec ce qui marche) : refondre ces générations comme `SecondaryCertificateController` (PDF **inline**, sans dépendre de `document_templates`). Surtout pour **cartes scolaires** (carte élève PDF) et **bulletins** (cf. P1).
- Sinon : seeder `document_templates` avec des templates HTML actifs (`certificate_enrollment`, `certificate_status`, `transcript_*`, `card_*`, `diploma_*`) puis réactiver les boutons.
- Fichiers : `Modules/Documents/Services/*`, contrôleurs `Documents/Http/Controllers/Admin/*`, composants `src/modules/Documents/admin/components/{Card,Transcript,Diploma}Dashboard.tsx` (boutons actuellement `disabled` avec tooltip « Indisponible (modèle non configuré) »).

### P3 — Portails Élève & Parent (données réelles)
Les portails affichent « Aucun profil … associé » car les comptes ne sont pas liés à une fiche.
- **Lier** : un `User` (rôle Élève) ↔ `students.user_id` ; un `User` (rôle Parent) ↔ `ParentModel` (+ pivot `parent_student`). Ajouter cette liaison à la **création d'utilisateur** (UsersGuard) ou un écran d'association.
- **PortailEtudiant** (`src/modules/PortailEtudiant`) : ne contient que `services/` + `menu.config` — **les composants des pages élève sont des `ScaffoldPage` génériques** (`app/[lang]/admin/student/*`). Les enrichir (Mes notes, Mon emploi du temps, Mes présences, Mes factures, Mes documents, Ma carte) avec un vrai rendu à partir des endpoints `/api/frontend/student/*` (déjà protégés par `role:Élève|Administrator`).
- **PortailParent** (`src/modules/PortailParent`) : idem pour `/admin/parent/*` (Mes enfants, etc.) via `/api/admin/parent/*`.
- Vérifier chaque portail connecté avec un compte réel lié.

### P4 — Coefficients par matière/classe
`subject_class_coefficients` (82 lignes) existe mais pas d'UI secondaire fonctionnelle. Câbler une page « Coefficients » (classe → matières → coefficient + matière éliminatoire) sur `GET/PUT /api/admin/subject-class-coefficients` (⚠️ `level_id` requis en query — vérifier la validation).

### P5 — Paie : vérifier le calcul de bout en bout
Câblé mais **non testé avec données** : créer une période de paie → `calculate` → vérifier le net calculé → générer bulletins (payslips PDF) → déclarations. Créer d'abord des grilles salariales + composantes (page Éléments de Paie) et des contrats employés (`POST employees/{id}/contracts`) pour que la masse salariale ≠ 0.

### P6 — Migrations & seed des autres tenants
Seules `company1` a reçu toutes les migrations en attente. Pour `company2` et `demo` : `php artisan tenants:migrate --tenants=company2` puis `company2,demo`. Le seeder des rôles a déjà tourné pour tous (vérifier).

### P7 — Nettoyage résiduel
- **Pages LMD de Notes** encore présentes comme routes (`app/[lang]/admin/grades/{deliberation,semester-results,retakes,retake-entry,retake-recalculation,final-results,pv-deliberation,ects,eliminatory,compensation,module-results,publications,validations,analytics,correction-requests}`) : supprimer les fichiers/pages + composants associés, OU les laisser orphelins (déjà retirés du menu). Idem côté backend : les contrôleurs LMD de `Modules/NotesEvaluations` (Deliberation, Ects, Retake, Compensation, Eliminatory, SemesterResult, ModuleResult, ProcesVerbal…) peuvent être retirés.
- **Image hero accueil** : `src/.../landing` utilise `img.png` (asset statique) affichant encore « ÉTUDIANTS / Par Programme ». Régénérer l'image ou la remplacer par une capture secondaire.
- **`financial.types.ts`** (frontend) contient encore `'Agent Comptable'` dans le type `FinancialRole` + `accountingClerkService.ts` (code mort) → nettoyer.
- Page d'accueil : revérifier qu'aucune occurrence « LMD/ECTS/supérieur/Étudiant » ne subsiste.

### P8 — Tests (exigence CLAUDE.md)
**Aucun test écrit** durant la refonte. Ajouter des tests Feature PHPUnit pour les nouveaux contrôleurs secondaires (EvaluationController, SecondaryTimetableController, SecondaryCertificateController, ExamSessionController secondaire, Attendance class-based, Student create avec class_id). Utiliser le template multi-tenant de `CLAUDE.md` (vrais tokens Bearer). Lancer avec `php artisan test --filter=...`.

### P9 — Finance/Paie compléments (optionnel)
- Reçu de paiement **PDF** (actuellement seulement données JSON via `/payments/{id}/receipt`).
- Plans de paiement (échéanciers) : pas d'endpoint GET de liste (seulement POST create) — ajouter si besoin.

---

## 5. Commandes utiles

```bash
# Backend (cwd = C:\laragon\www\lycee)
php artisan optimize:clear
vendor/bin/pint --dirty
php artisan tenants:migrate --tenants=company1        # local: pas de --force
php artisan db:seed --class="Modules\UsersGuard\Database\Seeders\RolesAndPermissionsSeeder"
php artisan test --filter=NomDuTest

# Frontend (cwd = C:\Users\Mounkaila\PhpstormProjects\lycee-frontend) — NE PAS faire npm run build (HMR)
```

## 6. Méthode de vérification (obligatoire)
Pour CHAQUE écran modifié : se connecter dans le navigateur (Chrome DevTools MCP) avec le rôle concerné, naviguer sur la page, vérifier **console sans erreur** (hors bruit HMR) + **données réelles affichées** + **chaque bouton déclenche un vrai appel API** (onglet réseau). Ne jamais laisser de donnée mock ni de bouton sans effet.
