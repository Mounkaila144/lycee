# PRD - Module Comptabilite & Finances

> **Projet** : Gestion Scolaire - Enseignement Secondaire Multi-Tenant
> **Module** : Comptabilite & Finances (Accounting & Finance)
> **Version** : v5
> **Date** : 2026-03-16
> **Phase** : Phase 3 - Gestion Financiere
> **Priorite** : MOYENNE 🟡

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2026-03-16 | 5.0 | Refonte complete - Passage du LMD (superieur) au secondaire (colleges/lycees Niger). Suppression des frais universitaires (examen, bibliotheque, filiere/niveau LMD). Ajout des frais secondaire (APE, cantine, tenue, transport, activites). Tarification par classe. Bourses et exonerations. Facturation automatique a l'inscription. | John (PM) |
| 2026-01-07 | 1.0 | Version initiale du PRD Module Comptabilite Etudiants (LMD/Superieur) | Claude (PM Agent) |

---

## 1. Goals and Background Context

### 1.1 Goals

- **Parametrer les frais de scolarite** : Permettre au Comptable/Intendant de configurer les differents types de frais (inscription, scolarite, APE, cantine, tenue, transport, activites) avec des montants par classe
- **Facturer automatiquement** : Generer automatiquement la facture de chaque eleve au moment de son inscription, incluant tous les frais applicables a sa classe
- **Enregistrer les paiements rapidement** : Permettre l'encaissement en moins de 2 minutes avec generation automatique de recus PDF conformes
- **Gerer les paiements partiels et echeanciers** : Supporter les paiements fractionnes et les plans de paiement personnalises pour les familles en difficulte
- **Gerer les bourses et exonerations** : Permettre l'application de reductions partielles ou totales sur les frais d'un eleve (boursiers, orphelins, cas sociaux)
- **Suivre les impayes en temps reel** : Fournir une visibilite complete sur les creances pour ameliorer le taux de recouvrement
- **Enregistrer les depenses** : Permettre le suivi des sorties d'argent avec justificatifs pour connaitre l'etat de caisse reel
- **Piloter la sante financiere** : Fournir des tableaux de bord financiers clairs (etat de caisse, revenus, impayes, bilan par periode)
- **Eliminer les registres papier** : Remplacer les cahiers de caisse et les recus manuels par un systeme numerique fiable

### 1.2 Background Context

La gestion financiere des colleges et lycees au Niger repose actuellement sur des registres papier et des fichiers Excel disperses. L'intendant ou le caissier enregistre les paiements dans un cahier, calcule manuellement les montants dus, et emet des recus manuscrits ou imprimes sur Word. Chaque type de frais (inscription, scolarite, APE, cantine, tenue) est souvent suivi dans un registre separe, sans consolidation. Ce processus est lent, source d'erreurs, et ne permet pas d'identifier facilement les eleves en impaye.

Les etablissements secondaires au Niger percoivent une diversite de frais :
- **Frais d'inscription** : Payes une fois a l'inscription
- **Frais de scolarite** : Frais principaux, souvent payes en plusieurs tranches
- **Cotisation APE** : Contribution a l'Association des Parents d'Eleves
- **Frais de cantine** : Pour les eleves demi-pensionnaires ou pensionnaires
- **Frais de tenue** : Uniforme scolaire
- **Frais de transport** : Navette scolaire (certains etablissements prives)
- **Frais d'activites** : Sport, excursions, sorties pedagogiques

Les montants varient par classe (un eleve de 6e ne paie pas le meme montant qu'un eleve de Terminale) et par etablissement (chaque tenant definit sa propre grille tarifaire).

Le Module Comptabilite & Finances numerise l'ensemble du processus financier lie aux eleves et a l'etablissement : de la configuration des frais a la generation de rapports financiers. L'integration avec le Module Inscriptions assure que chaque eleve inscrit recoit automatiquement sa facture. Les recus PDF generes automatiquement sont conformes et professionnels. Les tableaux de bord permettent a l'Intendant, a l'Admin et au Directeur de suivre en temps reel la sante financiere de l'etablissement. Les parents peuvent consulter la situation financiere de leurs enfants via le Portail Parent.

### 1.3 Utilisateurs Cles

| Role | Acces | Description |
|------|-------|-------------|
| **Comptable / Intendant** | Complet (lecture/ecriture) | Utilisateur principal : configure les frais, enregistre les paiements, gere les depenses, genere les rapports |
| **Admin / Directeur** | Complet (lecture/ecriture) | Supervise la gestion financiere, valide les bourses/exonerations, consulte les bilans |
| **Parent / Tuteur** | Consultation seule (via Portail Parent) | Consulte la situation financiere de ses enfants, telecharge les recus |

### 1.4 Dependances

- **Module Inscriptions** : Declenchement automatique de la facturation lors de l'inscription d'un eleve
- **Module Structure Academique** : Classes et annees scolaires pour la tarification par classe
- **Module Documents Officiels** : Template PDF pour les recus de paiement
- **Module Portail Parent** : Affichage de la situation financiere aux parents

---

## 2. Requirements

### 2.1 Functional Requirements (FR)

#### 2.1.1 Configuration des Frais

- **FR1** : Le systeme doit permettre de configurer differents types de frais avec les informations suivantes : type (inscription, scolarite, APE, cantine, tenue, transport, activites, autre), libelle, montant, annee scolaire, actif/inactif
- **FR2** : Les frais doivent etre configurables par classe (ex : 6e = 50 000 FCFA, Terminale = 75 000 FCFA), permettant une tarification differenciee selon le niveau
- **FR3** : Le systeme doit permettre de definir des frais obligatoires (inscription, scolarite) et des frais optionnels (cantine, transport)
- **FR4** : Le systeme doit permettre de dupliquer la grille tarifaire d'une annee scolaire precedente pour faciliter la configuration d'une nouvelle annee
- **FR5** : Le systeme doit permettre de desactiver un type de frais sans le supprimer (historique preserve)

#### 2.1.2 Facturation Automatique

- **FR6** : Le systeme doit generer automatiquement une facture pour chaque eleve lors de son inscription, incluant tous les frais obligatoires applicables a sa classe
- **FR7** : Chaque facture doit avoir un numero unique sequentiel (format : FAC-2026-0001) et inclure : detail des frais, montants unitaires, total, date d'emission, date d'echeance, statut de paiement
- **FR8** : Le systeme doit permettre la creation manuelle de factures pour des cas exceptionnels (frais supplementaires, frais d'activites ponctuelles)
- **FR9** : Le systeme doit calculer automatiquement le statut de chaque facture : Impaye (0% paye), Partiellement paye (1-99%), Paye (100%)

#### 2.1.3 Enregistrement des Paiements

- **FR10** : Le systeme doit permettre l'enregistrement des paiements avec les informations suivantes : montant, mode de paiement (especes, virement bancaire), date, recu par (nom du caissier)
- **FR11** : Le systeme doit generer automatiquement un recu PDF pour chaque paiement avec : numero unique (REC-2026-0001), details du paiement, informations de l'eleve, montant en chiffres et en lettres, solde restant
- **FR12** : Le systeme doit supporter les paiements partiels : si un eleve paie moins que le total du, la facture passe en statut "Partiellement paye" avec le solde restant affiche clairement
- **FR13** : Le systeme doit permettre la consultation de l'historique complet des paiements d'un eleve avec dates, montants, modes, et recus telechargeables

#### 2.1.4 Echeanciers de Paiement

- **FR14** : Le systeme doit permettre la creation d'echeanciers de paiement personnalises (ex : 3 versements de X FCFA chacun, avec dates d'echeance)
- **FR15** : Le systeme doit supporter la repartition egale ou personnalisee des versements
- **FR16** : Lors de l'enregistrement d'un paiement pour une facture ayant un echeancier, le systeme doit automatiquement marquer le versement correspondant comme paye
- **FR17** : Le systeme doit detecter les versements en retard et les signaler visuellement

#### 2.1.5 Bourses et Exonerations

- **FR18** : Le systeme doit permettre d'appliquer une bourse ou exoneration a un eleve avec : type (bourse d'Etat, bourse merite, exoneration orphelin, exoneration sociale, autre), pourcentage de reduction (0-100%) ou montant fixe de reduction, motif, date de debut, date de fin (optionnelle)
- **FR19** : Lorsqu'une bourse/exoneration est appliquee, le systeme doit recalculer automatiquement le montant du de la facture de l'eleve
- **FR20** : Le systeme doit maintenir un historique des bourses et exonerations accordees
- **FR21** : Le systeme doit permettre de revoquer une bourse/exoneration avec recalcul automatique

#### 2.1.6 Suivi des Impayes

- **FR22** : Le systeme doit generer une liste des impayes avec filtres (classe, annee scolaire, periode, montant minimum)
- **FR23** : Le systeme doit afficher des statistiques d'impayes : montant total, nombre d'eleves concernes, taux de recouvrement
- **FR24** : Le systeme doit permettre l'export de la liste des impayes en Excel pour exploitation externe

#### 2.1.7 Gestion des Depenses

- **FR25** : Le systeme doit permettre l'enregistrement des depenses de l'etablissement avec : categorie (fournitures, entretien, equipement, salaires, charges, autres), montant, description, date, justificatif uploade (PDF/JPG, max 5 MB), enregistre par
- **FR26** : Le systeme doit permettre la consultation des depenses avec filtres par categorie, periode, montant

#### 2.1.8 Tableaux de Bord et Rapports Financiers

- **FR27** : Le systeme doit fournir un tableau de bord financier avec indicateurs cles : revenus du mois, impayes totaux, etat de caisse (revenus - depenses), nombre de paiements du jour
- **FR28** : Le systeme doit permettre la generation de rapports financiers filtrables : journal des paiements, bilan par periode, etat des creances, repartition des revenus par type de frais
- **FR29** : Le systeme doit permettre l'export des rapports en PDF et Excel

#### 2.1.9 Consultation Parent (Portail)

- **FR30** : Les parents doivent pouvoir consulter la situation financiere de leurs enfants depuis le Portail Parent : factures, paiements effectues, solde restant
- **FR31** : Les parents doivent pouvoir telecharger les recus de paiement de leurs enfants

### 2.2 Non-Functional Requirements (NFR)

- **NFR1** : L'enregistrement d'un paiement (saisie + generation recu) doit prendre moins de 2 minutes du debut a la fin
- **NFR2** : La generation d'un recu PDF doit etre quasi-instantanee (< 3 secondes)
- **NFR3** : Le systeme doit supporter 5 caissiers/intendants enregistrant des paiements simultanement sans conflit
- **NFR4** : Les numeros de facture et de recu doivent etre strictement sequentiels et uniques (pas de trous, pas de doublons)
- **NFR5** : Le tableau de bord financier doit charger en moins de 2 secondes meme avec 10 000 transactions
- **NFR6** : Les donnees financieres doivent etre isolees par tenant (multi-tenant) avec securite renforcee
- **NFR7** : L'historique des transactions doit etre conserve indefiniment pour audit et conformite legale
- **NFR8** : Les uploads de justificatifs de depenses doivent etre limites a 5 MB par fichier et aux formats PDF, JPG, PNG
- **NFR9** : Le systeme doit etre utilisable sur tablette pour permettre l'enregistrement de paiements en mobilite (journees portes ouvertes, inscriptions en masse)

---

## 3. User Interface Design Goals

### 3.1 Overall UX Vision

L'interface de caisse doit etre ultra-rapide et simple, de type POS (Point of Sale) : recherche d'eleve -> affichage de la facture -> saisie montant -> validation -> impression recu. Le design privilegie la vitesse et la clarte : gros boutons, affichage grand format des montants, raccourcis clavier.

Pour les parents, la consultation via le Portail Parent doit etre rassurante et transparente : voir clairement ce qui est du, ce qui est paye, telecharger les recus facilement.

Pour l'Admin/Directeur, les tableaux de bord doivent etre visuels avec graphiques et indicateurs colores (vert = bon, rouge = impayes eleves).

### 3.2 Key Interaction Paradigms

- **Recherche rapide eleve** : Barre de recherche par nom ou matricule avec autocompletion
- **Enregistrement paiement en 3 etapes** : Montant -> Mode de paiement -> Validation
- **Impression recu immediate** : Bouton "Imprimer" ouvrant le PDF dans un nouvel onglet
- **Visualisation claire des soldes** : Affichage grand format du solde restant avec code couleur (vert si paye, rouge si impaye, orange si partiellement paye)
- **Filtrage dynamique** : Filtres sur les listes d'impayes pour ciblage rapide
- **Application de bourse** : Formulaire simple avec recalcul instantane du montant du

### 3.3 Core Screens and Views

1. **Page de Configuration des Frais (Comptable/Admin)** : Tableau avec types de frais, montants par classe, annee scolaire, boutons CRUD, duplication d'annee
2. **Page d'Enregistrement de Paiement (Comptable)** : Recherche eleve -> Affichage facture -> Formulaire de paiement -> Generation recu
3. **Page Historique de Paiements d'un Eleve (Comptable/Admin)** : Liste chronologique avec details, telechargement recus
4. **Page Gestion des Bourses et Exonerations (Admin)** : Liste des eleves beneficiaires, formulaire d'attribution, historique
5. **Page Liste des Impayes (Comptable/Admin)** : Tableau filtrable avec eleves en impaye, montants dus, export Excel
6. **Dashboard Financier (Admin/Directeur)** : KPIs, graphiques revenus/depenses, etat de caisse, top impayes
7. **Page de Gestion des Depenses (Comptable/Admin)** : Enregistrement de sorties d'argent avec justificatifs
8. **Page de Rapports Financiers (Admin/Directeur)** : Filtres + generation de rapports PDF/Excel (journal, bilan, recouvrement)
9. **Portail Parent - Situation Financiere** : Vue des factures, paiements, solde, telechargement recus (lecture seule)

### 3.4 Accessibility

- **WCAG AA** : Navigation clavier complete, contrastes suffisants
- Montants affiches en grand pour lisibilite
- Confirmation visuelle apres enregistrement (toast vert + son optionnel)
- Statuts visuels combinant couleur + icone pour les daltoniens

### 3.5 Branding

Coherence avec l'application Gestion Scolaire. Recus avec logo de l'etablissement et cachet/signature numerique. Palette de couleurs financieres : vert (paye/positif), rouge (impaye/negatif), orange (partiellement paye/avertissement).

### 3.6 Target Device and Platforms

- **Web Responsive** : Desktop pour caisse et administration, tablette pour mobilite, mobile pour consultation parents
- Recus PDF optimises pour impression A5 ou A6

---

## 4. Technical Assumptions

### 4.1 Repository Structure

**Polyrepo** : Backend Laravel dans `crm-api`, frontend Next.js dans `crm-frontend`.

### 4.2 Service Architecture

**Architecture modulaire Laravel (nwidart/laravel-modules)** :
- Module backend : `Modules/Comptabilite/` contenant Models (`Fee`, `FeeClass`, `Invoice`, `InvoiceItem`, `Payment`, `PaymentPlan`, `PaymentPlanInstallment`, `Scholarship`, `Expense`), Controllers, Services, Form Requests
- Frontend Next.js : Pages `/admin/comptabilite/...`, `/comptable/...`, `/parent/finances/...`
- API RESTful avec routes admin, comptable (cashier), et parent guards

### 4.3 Base de Donnees

**Connexion** : `tenant` (base de donnees tenant dynamique)

**Tables a creer** :
- `fees` : Configuration des types de frais (id, tenant_id, fee_type, label, description, academic_year_id, is_mandatory, is_active, timestamps, soft_deletes)
- `fee_classes` : Montants par classe (id, fee_id, class_id, amount, timestamps)
- `invoices` : Factures (id, tenant_id, invoice_number, student_id, academic_year_id, total_amount, discount_amount, net_amount, paid_amount, status, due_date, issued_at, timestamps, soft_deletes)
- `invoice_items` : Lignes de facture (id, invoice_id, fee_id, description, original_amount, discount_amount, net_amount, timestamps)
- `payments` : Paiements (id, tenant_id, payment_number, invoice_id, student_id, amount, payment_method, payment_date, reference, received_by, receipt_generated_at, notes, timestamps)
- `payment_plans` : Echeanciers (id, tenant_id, invoice_id, student_id, installment_count, status, created_by, timestamps)
- `payment_plan_installments` : Versements (id, payment_plan_id, installment_number, amount, due_date, status, payment_id, timestamps)
- `scholarships` : Bourses et exonerations (id, tenant_id, student_id, scholarship_type, discount_percentage, discount_amount, reason, start_date, end_date, is_active, granted_by, revoked_at, revoked_by, timestamps)
- `expenses` : Depenses (id, tenant_id, expense_number, category, amount, description, expense_date, justification_file_path, recorded_by, timestamps)

**Relations cles** :
- `fees` hasMany `fee_classes` (montants par classe)
- `fee_classes` belongsTo `fees`, belongsTo `classes`
- `invoices` belongsTo `students`, hasMany `invoice_items`, hasMany `payments`
- `invoice_items` belongsTo `invoices`, belongsTo `fees`
- `payments` belongsTo `invoices`, belongsTo `students`
- `payment_plans` belongsTo `invoices`, hasMany `payment_plan_installments`
- `scholarships` belongsTo `students`
- Utiliser **eager loading** pour eviter les N+1 queries

### 4.4 Testing Requirements

- **Tests unitaires** : Calcul de statuts de facture, generation de numeros sequentiels, calcul de bourses/exonerations, calcul etat de caisse
- **Tests feature** : API CRUD pour frais, factures, paiements, bourses, depenses
- **Tests de concurrence** : Verification de l'unicite des numeros de recu en cas de paiements simultanes
- **Tests frontend** : Composants React pour enregistrement de paiement
- **Tests E2E** : Playwright pour workflow complet d'enregistrement de paiement avec generation de recu

### 4.5 Additional Technical Assumptions

- **Numerotation sequentielle** : Service `SequenceNumberService` avec locking pour garantir unicite (utiliser DB transactions avec row locking)
- **Generation recus PDF** : Service `ReceiptGeneratorService` avec template Blade professionnel
- **Calcul de solde** : Service `InvoiceCalculationService` calculant le solde restant d'une facture en tenant compte des bourses/exonerations
- **Facturation automatique** : Service `InvoiceGeneratorService` declenche par le Module Inscriptions lors de l'inscription d'un eleve
- **Gestion bourses** : Service `ScholarshipService` gerant l'application et la revocation des bourses avec recalcul automatique
- **Permissions** : Admin (full access), Comptable/Intendant (enregistrer paiements, gerer depenses, consulter factures), Parent (read own children)
- **Audit trail** : Logging de toutes les transactions financieres avec `spatie/laravel-activitylog`
- **API Resources** : Retourner toujours des Resources, jamais de models bruts
- **SoftDeletes** : Utiliser sur les tables critiques (fees, invoices) pour preserver l'historique
- **Casts Laravel 12** : Utiliser `casts()` method sur les models
- **Format JSON** : Reponses standardisees avec `success`, `message`, `data`

---

## 5. Epic List

### Epic 1 : Configuration des Frais et Facturation Automatique
Permettre la configuration des frais de scolarite par classe et generer automatiquement les factures pour les eleves inscrits.

### Epic 2 : Enregistrement des Paiements et Generation de Recus
Permettre l'enregistrement rapide des paiements avec generation automatique de recus PDF professionnels.

### Epic 3 : Paiements Partiels, Echeanciers, Bourses et Exonerations
Supporter les paiements partiels, les echeanciers personnalises, et la gestion des bourses/exonerations avec recalcul automatique.

### Epic 4 : Suivi des Impayes
Fournir des outils de suivi des impayes avec filtres et export pour ameliorer le recouvrement.

### Epic 5 : Gestion des Depenses
Permettre l'enregistrement et le suivi des depenses de l'etablissement avec justificatifs.

### Epic 6 : Tableaux de Bord et Rapports Financiers
Creer des dashboards et rapports financiers pour piloter la sante financiere de l'etablissement.

---

## 6. Epic Details

### Epic 1 : Configuration des Frais et Facturation Automatique

**Objectif** : Permettre au Comptable/Admin de configurer les differents types de frais de scolarite avec tarification par classe, et generer automatiquement les factures lors de l'inscription des eleves.

#### Story 1.1 : Creer les Migrations et Models pour les Frais et Factures

**En tant qu'** architecte technique,
**Je veux** creer les tables et models pour les frais, factures et lignes de facture,
**Afin de** stocker les donnees financieres des eleves.

**Acceptance Criteria :**

1. Migration `create_fees_table` creee avec colonnes : `id`, `tenant_id`, `fee_type` (enum: inscription/scolarite/ape/cantine/tenue/transport/activites/autre), `label`, `description`, `academic_year_id`, `is_mandatory` (boolean), `is_active` (boolean), `timestamps`, `soft_deletes`
2. Migration `create_fee_classes_table` creee avec colonnes : `id`, `fee_id`, `class_id`, `amount` (decimal 10,2), `timestamps`
3. Migration `create_invoices_table` creee avec colonnes : `id`, `tenant_id`, `invoice_number`, `student_id`, `academic_year_id`, `total_amount`, `discount_amount` (default 0), `net_amount`, `paid_amount` (default 0), `status` (enum: unpaid/partially_paid/paid), `due_date`, `issued_at`, `timestamps`, `soft_deletes`
4. Migration `create_invoice_items_table` creee avec colonnes : `id`, `invoice_id`, `fee_id`, `description`, `original_amount`, `discount_amount` (default 0), `net_amount`, `timestamps`
5. Model `Fee` cree avec relations : `hasMany(FeeClass)`, `belongsTo(AcademicYear)`
6. Model `FeeClass` cree avec relations : `belongsTo(Fee)`, `belongsTo(SchoolClass)`
7. Model `Invoice` cree avec relations : `belongsTo(Student)`, `belongsTo(AcademicYear)`, `hasMany(InvoiceItem)`, `hasMany(Payment)`, `hasOne(PaymentPlan)`
8. Model `InvoiceItem` cree avec relations : `belongsTo(Invoice)`, `belongsTo(Fee)`
9. Enum `FeeType` cree : `Inscription`, `Scolarite`, `Ape`, `Cantine`, `Tenue`, `Transport`, `Activites`, `Autre`
10. Enum `InvoiceStatus` cree : `Unpaid`, `PartiallyPaid`, `Paid`
11. Models utilisant le trait `BelongsToTenant`
12. Factories creees pour generation de donnees de test
13. Tests unitaires verifiant les relations et les enums

---

#### Story 1.2 : Creer les API Endpoints pour la Configuration des Frais

**En tant que** developpeur backend,
**Je veux** creer les endpoints CRUD pour la configuration des frais,
**Afin de** permettre la gestion de la grille tarifaire via l'API.

**Acceptance Criteria :**

1. Route `GET /api/admin/fees` retournant la liste des frais avec filtres (academic_year_id, fee_type, class_id, is_mandatory, is_active)
2. Route `GET /api/admin/fees/{id}` retournant un frais avec ses montants par classe (eager loading fee_classes)
3. Route `POST /api/admin/fees` creant un nouveau frais avec ses montants par classe
4. `StoreFeeRequest` validant : `fee_type` (required, enum), `label` (required, string), `academic_year_id` (required, exists), `is_mandatory` (boolean), `classes` (array de {class_id, amount} avec class_id exists et amount numeric min:0)
5. Route `PUT /api/admin/fees/{id}` mettant a jour un frais et ses montants par classe
6. Route `DELETE /api/admin/fees/{id}` desactivant un frais (soft delete)
7. Route `POST /api/admin/fees/duplicate-year` dupliquant la grille tarifaire d'une annee scolaire vers une autre
8. `FeeResource` transformant les donnees avec relations (fee_classes avec montants par classe)
9. Middleware `auth:sanctum` et `ability:admin,comptable`
10. Tests feature couvrant tous les endpoints, y compris la duplication d'annee

---

#### Story 1.3 : Creer l'Interface de Configuration des Frais

**En tant que** Comptable ou Admin,
**Je veux** configurer les types de frais et leurs montants par classe,
**Afin de** parametrer la tarification de mon etablissement.

**Acceptance Criteria :**

1. Page Next.js `/admin/comptabilite/configuration-frais` creee avec authentification guard admin/comptable
2. Tableau affichant tous les frais avec colonnes : Type, Libelle, Obligatoire (badge), Annee scolaire, Actif, Actions
3. Clic sur un frais ouvre un panneau/modal affichant les montants par classe
4. Bouton "Ajouter un frais" ouvrant un formulaire avec : Type (select), Libelle, Description, Annee scolaire, Obligatoire (checkbox), et tableau des montants par classe (une ligne par classe avec champ montant)
5. Validation frontend : au moins un montant > 0 pour au moins une classe
6. Bouton "Dupliquer l'annee" permettant de copier toute la grille tarifaire d'une annee precedente vers l'annee active
7. Boutons "Modifier" et "Desactiver" sur chaque ligne
8. Tests E2E verifiant la creation complete d'un frais avec montants par classe

---

#### Story 1.4 : Creer le Service de Facturation Automatique

**En tant que** systeme,
**Je veux** generer automatiquement une facture pour chaque eleve lors de son inscription,
**Afin d'** automatiser la facturation.

**Acceptance Criteria :**

1. Service `InvoiceGeneratorService` cree dans `Modules/Comptabilite/Services/`
2. Methode `generateForStudent(student_id, academic_year_id)` generant une facture
3. Logique :
   - Recuperer l'eleve avec sa classe
   - Recuperer tous les frais obligatoires applicables (is_mandatory = true, is_active = true, academic_year_id match, fee_classes avec class_id de l'eleve)
   - Verifier si une bourse/exoneration est active pour l'eleve et appliquer les reductions
   - Creer une facture avec numero unique (format : FAC-2026-0001)
   - Creer des invoice_items pour chaque frais applicable avec original_amount, discount_amount, net_amount
   - Calculer total_amount, discount_amount, net_amount
   - Initialiser paid_amount = 0, status = "Unpaid"
4. Service `SequenceNumberService` pour generer le numero de facture unique avec DB locking
5. Appel automatique de ce service via un event listener sur l'evenement d'inscription (integration avec Module Inscriptions)
6. Le service doit etre idempotent : ne pas generer de doublon si l'eleve est deja facture pour l'annee en cours
7. Tests unitaires couvrant differents scenarios (eleve 6e sans bourse, eleve Terminale avec bourse 50%, eleve avec exoneration totale)

---

#### Story 1.5 : Creer les Endpoints pour les Factures

**En tant que** Comptable ou Admin,
**Je veux** consulter et gerer les factures via l'API,
**Afin de** suivre les factures des eleves.

**Acceptance Criteria :**

1. Route `GET /api/admin/invoices` retournant la liste des factures avec filtres (student_id, class_id, status, academic_year_id, due_date_from, due_date_to)
2. Route `GET /api/admin/invoices/{id}` retournant une facture avec ses items, paiements et echeancier en eager loading
3. Route `POST /api/admin/invoices` creant manuellement une facture (cas exceptionnels : frais d'activites, remplacement de materiel)
4. Route `PUT /api/admin/invoices/{id}` mettant a jour (ex : modifier due_date)
5. Route `DELETE /api/admin/invoices/{id}` supprimant une facture (soft delete, seulement si aucun paiement enregistre)
6. `InvoiceResource` incluant : invoice_number, student (nom, matricule, classe), items, total_amount, discount_amount, net_amount, paid_amount, balance (calculated: net_amount - paid_amount), status, due_date
7. Middleware `auth:sanctum` et `ability:admin,comptable`
8. Tests feature verifiant les filtres et les permissions

---

### Epic 2 : Enregistrement des Paiements et Generation de Recus

**Objectif** : Permettre aux Comptables/Intendants d'enregistrer rapidement les paiements avec generation automatique de recus PDF professionnels.

#### Story 2.1 : Creer les Migrations et Models pour les Paiements

**En tant qu'** architecte technique,
**Je veux** creer les tables et models pour les paiements,
**Afin de** enregistrer les transactions financieres.

**Acceptance Criteria :**

1. Migration `create_payments_table` creee avec colonnes : `id`, `tenant_id`, `payment_number`, `invoice_id`, `student_id`, `amount` (decimal 10,2), `payment_method` (enum: cash/bank_transfer), `payment_date`, `reference` (nullable, pour virement), `received_by` (user_id du comptable), `receipt_generated_at`, `notes`, `timestamps`
2. Model `Payment` cree avec relations : `belongsTo(Invoice)`, `belongsTo(Student)`, `belongsTo(User, 'received_by')`
3. Enum `PaymentMethod` cree : `Cash` (Especes), `BankTransfer` (Virement bancaire)
4. Model utilisant le trait `BelongsToTenant`
5. Factory creee pour generation de donnees de test
6. Tests unitaires verifiant les relations

---

#### Story 2.2 : Creer le Service d'Enregistrement de Paiements

**En tant que** developpeur backend,
**Je veux** creer un service gerant l'enregistrement de paiements,
**Afin de** centraliser la logique metier et mettre a jour les factures.

**Acceptance Criteria :**

1. Service `PaymentProcessingService` cree dans `Modules/Comptabilite/Services/`
2. Methode `recordPayment(invoice_id, amount, payment_method, ?reference, user_id)` enregistrant un paiement
3. Logique :
   - Creer un Payment avec numero unique (format : REC-2026-0001)
   - Mettre a jour invoice.paid_amount += amount
   - Recalculer invoice.status (si paid_amount == 0: Unpaid, si paid_amount < net_amount: PartiallyPaid, si paid_amount >= net_amount: Paid)
   - Tout dans une transaction DB (atomic)
4. Validation : montant > 0, montant <= solde restant (net_amount - paid_amount)
5. Service `SequenceNumberService` pour generer le numero de recu unique avec DB locking
6. Retour : objet Payment cree avec Invoice mise a jour
7. Tests unitaires couvrant paiement complet, partiel, multi-paiements successifs, et tentative de surpaiement

---

#### Story 2.3 : Creer les API Endpoints pour l'Enregistrement de Paiements

**En tant que** Comptable,
**Je veux** enregistrer un paiement via l'API,
**Afin d'** encaisser les frais des eleves.

**Acceptance Criteria :**

1. Route `POST /api/comptable/payments` creant un paiement avec validation (StorePaymentRequest)
2. `StorePaymentRequest` validant : `invoice_id` (exists, belongs to tenant), `amount` (numeric, min:1, max:balance), `payment_method` (enum), `payment_date` (date), `reference` (required si bank_transfer)
3. Utilisation du `PaymentProcessingService` pour enregistrement
4. Reponse incluant : payment cree + invoice mise a jour avec nouveau statut et solde
5. Route `GET /api/comptable/payments` listant les paiements avec filtres (date_from, date_to, payment_method, received_by, student_id)
6. Route `GET /api/comptable/payments/{id}` retournant un paiement avec relations
7. `PaymentResource` incluant toutes les infos + lien de telechargement du recu PDF
8. Middleware `auth:sanctum` et `ability:admin,comptable`
9. Tests feature verifiant la creation et la mise a jour de la facture

---

#### Story 2.4 : Creer le Template et Service de Generation de Recus PDF

**En tant que** developpeur backend,
**Je veux** creer un template PDF professionnel pour les recus,
**Afin que** les recus soient imprimables et conformes.

**Acceptance Criteria :**

1. Template Blade `payment-receipt.blade.php` cree avec :
   - En-tete : Logo etablissement, nom de l'etablissement, titre "RECU DE PAIEMENT", numero de recu
   - Infos eleve : Nom complet, matricule, classe
   - Infos paiement : Date, Montant paye (en chiffres et en lettres), Mode de paiement, Reference (si applicable)
   - Details facture : Numero facture, Total facture, Reductions (bourse/exoneration), Net a payer, Deja paye, Solde restant
   - Pied de page : "Recu par {Nom comptable}", Signature/cachet, Date de generation
2. CSS optimise pour impression A5 ou A6
3. Service `ReceiptGeneratorService` avec methode `generate(payment_id)` generant le PDF
4. Utilisation de `barryvdh/laravel-dompdf` (package existant dans le projet)
5. Stockage du PDF dans `storage/app/public/receipts/{tenant_id}/{year}/{receipt_number}.pdf`
6. Mise a jour de payment.receipt_generated_at apres generation
7. Tests verifiant la generation et le contenu du PDF

---

#### Story 2.5 : Creer l'Interface d'Enregistrement de Paiement

**En tant que** Comptable,
**Je veux** enregistrer un paiement via une interface rapide type POS,
**Afin d'** encaisser les eleves efficacement.

**Acceptance Criteria :**

1. Page Next.js `/comptable/enregistrer-paiement` creee avec authentification guard comptable
2. Etape 1 : Recherche eleve par nom ou matricule avec autocompletion
3. Selection de l'eleve affiche ses factures impayees/partiellement payees
4. Tableau des factures avec colonnes : Numero, Annee, Total, Reductions, Net, Paye, Solde, Action "Payer"
5. Clic sur "Payer" ouvre un formulaire :
   - Montant (input avec default = solde restant, modifiable pour paiement partiel)
   - Mode de paiement (select : Especes, Virement bancaire)
   - Reference (input conditionnel si virement)
   - Date de paiement (date picker, default = aujourd'hui)
   - Notes (textarea optionnel)
6. Validation frontend : montant > 0 et <= solde
7. Bouton "Enregistrer le paiement" bien visible
8. Apres succes : affichage d'un modal de confirmation avec bouton "Imprimer le recu" ouvrant le PDF dans un nouvel onglet
9. Toast de succes et rechargement de la liste des factures de l'eleve
10. Tests E2E verifiant le workflow complet

---

### Epic 3 : Paiements Partiels, Echeanciers, Bourses et Exonerations

**Objectif** : Supporter les paiements partiels, les echeanciers personnalises, et la gestion des bourses et exonerations pour s'adapter aux situations financieres des familles.

#### Story 3.1 : Implementer la Logique de Paiements Partiels

**En tant que** systeme,
**Je veux** gerer correctement les paiements partiels,
**Afin de** permettre aux familles de payer en plusieurs fois.

**Acceptance Criteria :**

1. Lors de l'enregistrement d'un paiement, si montant < solde restant, status passe a "PartiallyPaid"
2. Calcul du solde restant : `balance = net_amount - paid_amount`
3. Une facture peut avoir plusieurs paiements (relation hasMany)
4. Affichage du solde restant sur la page de recherche eleve
5. Indicateur visuel : badge orange "Partiellement paye" avec montant restant
6. Tests verifiant le calcul correct du solde apres plusieurs paiements partiels

---

#### Story 3.2 : Creer les Migrations et Models pour les Echeanciers

**En tant qu'** architecte technique,
**Je veux** creer les tables pour les echeanciers de paiement,
**Afin de** stocker les plans de paiement personnalises.

**Acceptance Criteria :**

1. Migration `create_payment_plans_table` creee avec colonnes : `id`, `tenant_id`, `invoice_id`, `student_id`, `installment_count`, `status` (enum: active/completed/cancelled), `created_by`, `timestamps`
2. Migration `create_payment_plan_installments_table` creee avec colonnes : `id`, `payment_plan_id`, `installment_number`, `amount` (decimal 10,2), `due_date`, `status` (enum: pending/paid/overdue), `payment_id` (nullable, FK vers payments), `timestamps`
3. Model `PaymentPlan` cree avec relations : `belongsTo(Invoice)`, `belongsTo(Student)`, `hasMany(PaymentPlanInstallment)`
4. Model `PaymentPlanInstallment` cree avec relations : `belongsTo(PaymentPlan)`, `belongsTo(Payment)`
5. Enum `PaymentPlanStatus` : `Active`, `Completed`, `Cancelled`
6. Enum `InstallmentStatus` : `Pending`, `Paid`, `Overdue`
7. Models utilisant le trait `BelongsToTenant`
8. Factories et tests unitaires

---

#### Story 3.3 : Creer les API Endpoints et Interface pour les Echeanciers

**En tant que** Comptable ou Admin,
**Je veux** creer un echeancier de paiement pour un eleve,
**Afin de** lui permettre de payer en plusieurs fois.

**Acceptance Criteria :**

1. Route `POST /api/admin/payment-plans` creant un echeancier avec validation
2. Validation : `invoice_id` (exists), `installments` (array avec amount + due_date), somme des installments == balance de la facture
3. Route `GET /api/admin/payment-plans/{id}` retournant un plan avec ses installments
4. Route `GET /api/admin/invoices/{invoice_id}/payment-plan` retournant l'echeancier d'une facture (si existe)
5. Route `PUT /api/admin/payment-plans/{id}` mettant a jour (ex : modifier dates d'echeance si pas encore paye)
6. Route `DELETE /api/admin/payment-plans/{id}` annulant un echeancier (status = cancelled)
7. Sur la page de detail d'une facture (frontend), bouton "Creer un echeancier" avec formulaire :
   - Nombre de versements (input number, min 2, max 12)
   - Repartition : Egale (montant divise egalement) ou Personnalisee (saisie manuelle)
   - Dates d'echeance pour chaque versement (date pickers)
8. Affichage de l'echeancier cree sur la page de la facture avec statuts colores (vert = paye, orange = en attente, rouge = en retard)
9. Lors de l'enregistrement d'un paiement, le systeme marque automatiquement le prochain versement "pending" comme "paid"
10. Lorsque tous les versements sont payes, payment_plan.status passe a "completed"
11. Tests feature verifiant la creation, la liaison automatique paiement-versement, et le changement de statut

---

#### Story 3.4 : Creer les Migrations, Models et Logique pour les Bourses et Exonerations

**En tant que** Admin ou Directeur,
**Je veux** gerer les bourses et exonerations des eleves,
**Afin de** reduire les frais pour les eleves beneficiaires.

**Acceptance Criteria :**

1. Migration `create_scholarships_table` creee avec colonnes : `id`, `tenant_id`, `student_id`, `scholarship_type` (enum: bourse_etat/bourse_merite/exoneration_orphelin/exoneration_sociale/autre), `discount_percentage` (nullable, 0-100), `discount_amount` (nullable, decimal 10,2), `reason`, `start_date`, `end_date` (nullable), `is_active` (boolean), `granted_by` (user_id), `revoked_at` (nullable), `revoked_by` (nullable, user_id), `timestamps`
2. Model `Scholarship` cree avec relations : `belongsTo(Student)`, `belongsTo(User, 'granted_by')`
3. Enum `ScholarshipType` : `BourseEtat`, `BourseMerite`, `ExonerationOrphelin`, `ExonerationSociale`, `Autre`
4. Service `ScholarshipService` avec methodes :
   - `grant(student_id, type, percentage_or_amount, reason, user_id)` : Attribuer une bourse
   - `revoke(scholarship_id, user_id)` : Revoquer une bourse
   - `applyToInvoice(invoice_id)` : Recalculer les montants d'une facture en fonction de la bourse active
5. Route `GET /api/admin/scholarships` listant les bourses avec filtres (student_id, type, is_active)
6. Route `POST /api/admin/scholarships` attribuant une bourse
7. Route `PUT /api/admin/scholarships/{id}/revoke` revoquant une bourse
8. Page Next.js `/admin/comptabilite/bourses` avec :
   - Liste des eleves beneficiaires avec type, pourcentage/montant, motif, statut
   - Bouton "Attribuer une bourse" avec formulaire
   - Bouton "Revoquer" sur chaque ligne
9. Lorsqu'une bourse est attribuee ou revoquee, les factures non payees de l'eleve sont automatiquement recalculees
10. Tests couvrant : attribution, revocation, recalcul facture, bourse en pourcentage, bourse en montant fixe

---

#### Story 3.5 : Creer une Commande de Detection des Versements en Retard

**En tant que** systeme,
**Je veux** detecter automatiquement les versements d'echeancier en retard,
**Afin de** mettre a jour les statuts et preparer les relances.

**Acceptance Criteria :**

1. Command Artisan `invoices:detect-overdue` cree
2. Logique : recuperer tous les installments avec status = "pending" ET due_date < aujourd'hui, les passer en status "overdue"
3. Egalement detecter les factures sans echeancier avec status != "Paid" ET due_date < aujourd'hui
4. Command schedule quotidiennement via Laravel Scheduler
5. Tests verifiant la detection correcte

---

### Epic 4 : Suivi des Impayes

**Objectif** : Fournir des outils de suivi des impayes avec filtres et export pour ameliorer le taux de recouvrement.

#### Story 4.1 : Creer le Endpoint et la Page de Liste des Impayes

**En tant que** Comptable ou Admin,
**Je veux** consulter la liste des eleves en impaye,
**Afin de** suivre les creances et ameliorer le recouvrement.

**Acceptance Criteria :**

1. Endpoint API `GET /api/admin/invoices/unpaid` retournant les factures avec status "Unpaid" ou "PartiallyPaid"
2. Filtres : `class_id`, `academic_year_id`, `due_date_before` (pour detecter retards), `min_balance` (montant minimum du)
3. Tri par defaut : montant du (decroissant, les plus gros impayes en premier)
4. Eager loading : student (avec classe), invoice_items
5. Statistiques retournees en meta : `total_unpaid_amount`, `student_count`, `recovery_rate` (paye / total facture)
6. Page Next.js `/admin/comptabilite/impayes` creee avec :
   - KPIs en haut de page : Montant total des impayes, Nombre d'eleves en impaye, Taux de recouvrement
   - Tableau avec colonnes : Eleve, Classe, Facture, Total, Paye, Solde, Date d'echeance, Retard (jours), Actions
   - Filtres : Classe, Annee scolaire, En retard uniquement (toggle)
   - Indicateur visuel : rouge si date d'echeance depassee
   - Bouton "Voir details" sur chaque ligne
7. Export Excel de la liste des impayes
8. Tests feature verifiant les filtres et les calculs statistiques

---

#### Story 4.2 : Creer un Rapport de Recouvrement

**En tant que** Directeur,
**Je veux** voir un rapport de recouvrement,
**Afin de** mesurer la performance financiere de l'etablissement.

**Acceptance Criteria :**

1. Page `/admin/comptabilite/rapport-recouvrement` creee
2. Filtres : Periode (mois, trimestre, semestre, annee scolaire)
3. Indicateurs :
   - Total facture (somme de toutes les factures de la periode)
   - Total recouvre (somme de tous les paiements de la periode)
   - Taux de recouvrement (%)
   - Impayes en cours
   - Bourses/exonerations accordees (total des reductions)
4. Graphique : Evolution du taux de recouvrement sur les 12 derniers mois (line chart)
5. Comparaison avec periode precedente (ex : +5% vs mois dernier)
6. Export PDF du rapport
7. Tests verifiant les calculs

---

### Epic 5 : Gestion des Depenses

**Objectif** : Permettre l'enregistrement et le suivi des depenses de l'etablissement avec justificatifs.

#### Story 5.1 : Creer les Migrations, Models et Endpoints pour les Depenses

**En tant que** Comptable ou Admin,
**Je veux** enregistrer et consulter les depenses de l'etablissement,
**Afin de** suivre l'etat de caisse reel.

**Acceptance Criteria :**

1. Migration `create_expenses_table` avec colonnes : `id`, `tenant_id`, `expense_number` (format : DEP-2026-0001), `category` (enum: fournitures/entretien/equipement/salaires/charges/autres), `amount` (decimal 10,2), `description`, `expense_date`, `justification_file_path` (nullable), `recorded_by` (user_id), `timestamps`
2. Model `Expense` avec relation `belongsTo(User, 'recorded_by')`
3. Enum `ExpenseCategory` : `Fournitures`, `Entretien`, `Equipement`, `Salaires`, `Charges`, `Autres`
4. Route `POST /api/admin/expenses` creant une depense avec upload de justificatif
5. Route `GET /api/admin/expenses` listant les depenses avec filtres (date_from, date_to, category)
6. Route `GET /api/admin/expenses/{id}` retournant une depense avec lien vers le justificatif
7. `ExpenseResource` incluant toutes les infos
8. `StoreExpenseRequest` validant : `category` (required, enum), `amount` (required, numeric, min:0.01), `description` (required, string), `expense_date` (required, date), `justification` (nullable, file, mimes:pdf,jpg,png, max:5120)
9. Page Next.js `/admin/comptabilite/depenses` creee avec :
   - Tableau avec colonnes : Date, Numero, Categorie, Montant, Description, Justificatif (lien), Actions
   - Bouton "Enregistrer une depense" ouvrant un formulaire avec upload de justificatif (PDF/JPG)
   - Filtres par categorie et periode
10. Tests feature verifiant la creation, l'upload, et les filtres

---

### Epic 6 : Tableaux de Bord et Rapports Financiers

**Objectif** : Creer des dashboards financiers et rapports pour piloter la sante financiere de l'etablissement.

#### Story 6.1 : Creer les Endpoints pour les Statistiques Financieres

**En tant que** developpeur backend,
**Je veux** creer les endpoints fournissant les statistiques financieres,
**Afin d'** alimenter les dashboards et rapports.

**Acceptance Criteria :**

1. Endpoint API `GET /api/admin/comptabilite/statistics` retournant :
   - `monthly_revenue` : revenus du mois en cours (somme des paiements du mois)
   - `total_unpaid` : impayes totaux (somme des soldes de toutes les factures)
   - `cash_balance` : etat de caisse (total revenus - total depenses pour l'annee scolaire en cours)
   - `payments_today` : nombre de paiements enregistres aujourd'hui
   - `total_scholarships` : total des reductions accordees (bourses/exonerations)
2. Endpoint API `GET /api/admin/comptabilite/revenue-by-month` retournant les revenus des 12 derniers mois
3. Endpoint API `GET /api/admin/comptabilite/revenue-by-fee-type` retournant la repartition par type de frais
4. Endpoint API `GET /api/admin/comptabilite/unpaid-by-class` retournant les impayes par classe
5. Middleware `auth:sanctum` et `ability:admin,directeur`
6. Caching des resultats pour 10 minutes pour performances
7. Tests verifiant les calculs et le caching

---

#### Story 6.2 : Creer le Dashboard Financier Principal

**En tant que** Admin ou Directeur,
**Je veux** avoir un dashboard financier avec KPIs et graphiques,
**Afin de** suivre la sante financiere en un coup d'oeil.

**Acceptance Criteria :**

1. Page Next.js `/admin/comptabilite/dashboard` creee
2. KPIs (grandes cartes visuelles) :
   - Revenus du mois en cours (somme des paiements du mois)
   - Impayes totaux (solde de toutes les factures)
   - Etat de caisse (revenus - depenses)
   - Nombre de paiements enregistres aujourd'hui
   - Total bourses/exonerations accordees
3. Graphique 1 : Evolution des revenus par mois sur l'annee scolaire (bar chart)
4. Graphique 2 : Repartition des revenus par type de frais (pie chart)
5. Graphique 3 : Impayes par classe (bar chart horizontal)
6. Section "Derniers paiements" : Liste des 10 derniers paiements avec details
7. Section "Top impayes" : Top 5 eleves avec les plus gros soldes dus
8. Rafraichissement automatique toutes les 5 minutes
9. Tests verifiant les calculs des KPIs

---

#### Story 6.3 : Creer le Journal Financier

**En tant que** Comptable ou Admin,
**Je veux** generer un journal financier complet,
**Afin d'** avoir un document recapitulatif pour comptabilite/audit.

**Acceptance Criteria :**

1. Page `/admin/comptabilite/journal` creee
2. Filtres : Periode (date debut - date fin)
3. Tableau combine :
   - Colonne Date
   - Colonne Type (Paiement / Depense)
   - Colonne Description (nom eleve + classe pour paiement, categorie pour depense)
   - Colonne Entree (montant paiement)
   - Colonne Sortie (montant depense)
   - Colonne Solde cumule
4. Calcul du solde cumule ligne par ligne
5. Resume en bas : Total entrees, Total sorties, Solde final
6. Export Excel et PDF du journal
7. Tests verifiant les calculs du solde cumule

---

#### Story 6.4 : Generer un Bilan Financier de Periode

**En tant que** Directeur,
**Je veux** generer un bilan financier pour une periode,
**Afin de** presenter la situation financiere aux parties prenantes (APE, conseil d'etablissement).

**Acceptance Criteria :**

1. Page `/admin/comptabilite/bilan` creee
2. Selection de periode (mois, trimestre, semestre, annee scolaire)
3. Endpoint API `GET /api/admin/comptabilite/balance-sheet` calculant :
   - Total revenus (somme paiements periode)
   - Total depenses (somme depenses periode)
   - Resultat (revenus - depenses)
   - Creances (impayes en cours)
   - Bourses/exonerations (total reductions accordees)
4. Affichage structure :
   - Section REVENUS : detail par type de frais (inscription, scolarite, APE, cantine, etc.)
   - Section DEPENSES : detail par categorie (fournitures, entretien, equipement, etc.)
   - Section BOURSES/EXONERATIONS : nombre de beneficiaires, total reductions
   - Section RESULTAT : benefice ou deficit
   - Section CREANCES : impayes avec taux de recouvrement
5. Graphiques visuels (revenus vs depenses en barres)
6. Export PDF professionnel du bilan pour presentation en conseil d'etablissement ou reunion APE
7. Tests verifiant les calculs

---

#### Story 6.5 : Consultation Financiere Parent (Portail)

**En tant que** Parent,
**Je veux** consulter la situation financiere de mes enfants via le Portail Parent,
**Afin de** savoir ce qui est du et ce qui a ete paye.

**Acceptance Criteria :**

1. Route `GET /api/parent/children/{child_id}/invoices` retournant toutes les factures de l'enfant
2. Route `GET /api/parent/children/{child_id}/payments` retournant l'historique de paiements
3. Route `GET /api/parent/children/{child_id}/payments/{payment_id}/receipt` telechargeant le recu PDF
4. Validation : le parent ne peut acceder qu'aux donnees de ses propres enfants
5. Section "Finances" dans le Portail Parent affichant par enfant :
   - Resume : Total a payer, Total paye, Solde restant (avec code couleur)
   - Bourse/exoneration active (si applicable) avec detail de la reduction
   - Liste des factures avec statut
   - Historique des paiements avec bouton "Telecharger le recu" pour chaque paiement
   - Echeancier (si existe) avec prochain versement mis en evidence
6. Middleware `auth:sanctum` et `ability:parent`
7. Tests verifiant que le parent ne voit QUE les donnees de ses propres enfants

---

## 7. Out of Scope

Les elements suivants ne sont PAS couverts dans cette version du module :

- **Paiement en ligne / Mobile Money** : Integration Orange Money, Moov Money (Phase 5 - Advanced Features)
- **Notifications SMS de relance** : Envoi automatique de SMS aux parents pour impayes (Phase 2 - Notifications)
- **Relances automatiques par email** : Envoi de relances automatiques aux parents (Phase 2)
- **Frais d'examen** : Non applicable au secondaire (les examens nationaux BEPC/Bac sont geres par l'Etat)
- **Frais de bibliotheque** : Non applicable dans le contexte secondaire Niger
- **Comptabilite analytique avancee** : Plan comptable, journaux comptables OHADA (hors scope MVP)
- **Gestion de la paie** : Couverte par le Module Paie Personnel (PRD separe)
- **Facturation par filiere/niveau LMD** : Remplace par facturation par classe (secondaire)

---

## 8. Success Metrics

### 8.1 Metriques d'Efficacite

- **Temps d'enregistrement d'un paiement** : < 2 minutes (vs 10+ minutes manuellement)
- **Temps de generation d'un recu** : < 3 secondes
- **Temps de generation d'un rapport financier** : < 10 minutes (vs 2+ heures manuellement)
- **Taux d'erreurs de calcul** : 0% (vs 5-10% manuellement)

### 8.2 Metriques Business

- **Taux de recouvrement** : Amelioration de 25% grace au suivi automatise des impayes
- **Visibilite financiere** : 100% des transactions tracees numeriquement (vs registres papier incomplets)
- **Satisfaction comptable (NPS)** : > 50 apres 2 mois d'utilisation

### 8.3 Metriques Systeme

- **Disponibilite** : > 99.5% pour les operations de caisse
- **Performance** : Dashboard charge en < 2 secondes meme avec 10 000 transactions
- **Concurrence** : Support de 5 caissiers simultanes sans conflit
- **Integrite** : 0 doublon de numero de facture/recu sur toute la duree de vie

---

## 9. Open Questions

### Produit

- Les montants des frais APE sont-ils standardises au niveau national ou propres a chaque etablissement ?
- Faut-il gerer les remboursements (eleve quittant l'etablissement en cours d'annee) ?
- Les recus doivent-ils etre numerotes par type de frais ou avec une numerotation unique globale ?
- Faut-il gerer des comptes financiers separes (caisse, banque) ou un compte unique suffit-il pour le MVP ?

### Technique

- `barryvdh/laravel-dompdf` est-il suffisant pour la generation de recus en masse (fin d'annee, remise de recus) ?
- Faut-il prevoir une API pour les futurs paiements mobile (Orange Money) des maintenant dans l'architecture ?
- Le stockage des justificatifs de depenses (PDF/images) doit-il etre en local ou sur un service cloud (S3) ?

### Business

- Les parents doivent-ils pouvoir telecharger un releve annuel complet de leurs paiements ?
- Faut-il prevoir un role "Caissier" distinct du role "Comptable/Intendant" avec des permissions differentes ?
- Comment gerer les frais supplementaires imprevus en cours d'annee (reparations, cotisations exceptionnelles) ?

---

## 10. Next Steps

### 10.1 Architect Prompt

Le PRD du Module Comptabilite & Finances est complet. Veuillez creer le document d'architecture technique detaille couvrant :
- Structure de base de donnees (tables fees, fee_classes, invoices, invoice_items, payments, payment_plans, payment_plan_installments, scholarships, expenses, indexes, relations)
- Architecture API (endpoints CRUD, permissions par guard : admin, comptable, parent)
- Services metier (InvoiceGeneratorService, PaymentProcessingService, ReceiptGeneratorService, SequenceNumberService, ScholarshipService, InvoiceCalculationService)
- Generation de numeros sequentiels uniques avec DB locking pour eviter doublons
- Generation PDF optimisee pour recus (template professionnel, performances)
- Integration avec Module Inscriptions (event listener pour facturation automatique)
- Strategie de caching pour dashboards financiers
- Plan de tests (unitaires, features, E2E, tests de concurrence)
- Securite : audit trail complet, isolation multi-tenant

### 10.2 UX Expert Prompt

Merci de creer les wireframes et maquettes pour les ecrans principaux du Module Comptabilite & Finances :
- Interface d'enregistrement de paiement (type POS, rapide et intuitive)
- Page de configuration des frais avec grille par classe
- Page de gestion des bourses/exonerations
- Dashboard financier avec KPIs et graphiques
- Page de suivi des impayes avec filtres
- Template PDF de recu de paiement professionnel
- Section Finances du Portail Parent
- Rapports financiers (journal, bilan)

Assurez-vous que le design soit coherent avec l'application Gestion Scolaire et que l'interface de caisse soit optimisee pour rapidite (gros boutons, montants bien visibles).

---

## 11. Checklist Results Report

**A completer apres implementation** : Executer le checklist PM pour valider :
- [ ] Tous les requirements fonctionnels sont implementes (FR1-FR31)
- [ ] La configuration des frais par classe fonctionne correctement
- [ ] La facturation automatique a l'inscription est operationnelle
- [ ] L'enregistrement des paiements est rapide (< 2 min)
- [ ] Les recus PDF sont generes correctement avec toutes les informations
- [ ] Les paiements partiels et echeanciers fonctionnent
- [ ] Les bourses/exonerations sont appliquees avec recalcul automatique
- [ ] Le suivi des impayes est complet avec statistiques
- [ ] Les depenses sont enregistrables avec justificatifs
- [ ] Le dashboard financier affiche des donnees correctes
- [ ] Les rapports (journal, bilan, recouvrement) sont generables en PDF/Excel
- [ ] Le Portail Parent affiche correctement la situation financiere
- [ ] Les permissions sont appliquees (admin, comptable, parent)
- [ ] Le multi-tenant isole correctement les donnees financieres
- [ ] L'interface est responsive et accessible (WCAG AA)

---

**Document cree par** : John (Product Manager)
**Derniere mise a jour** : 2026-03-16
**Version** : v5
**Statut** : Draft pour review
