# Architecture des Composants

[← Retour à l'index](./index.md)

---

## 7.1 Architecture Backend (Laravel - Modulaire)

### Structure Standard d'un Module

```
Modules/{ModuleName}/
├── Config/
│   └── config.php                      # Config module
├── Console/
│   └── Commands/                       # Artisan commands spécifiques
├── Database/
│   ├── Factories/                      # Factories pour tests
│   ├── Migrations/                     # Migrations centrales (rare)
│   ├── Migrations/tenant/              # Migrations tenant (majorité)
│   │   └── 2025_01_xx_create_xxx_table.php
│   └── Seeders/
│       └── {Module}Seeder.php
├── Entities/                           # Models Eloquent
│   ├── {EntityName}.php
│   └── ...
├── Http/
│   ├── Controllers/
│   │   ├── Admin/                      # Controllers Admin (tenant)
│   │   │   └── {Resource}Controller.php
│   │   ├── Frontend/                   # Controllers Frontend (tenant)
│   │   │   └── {Resource}Controller.php
│   │   └── Superadmin/                 # Controllers Superadmin (central)
│   │       └── ...
│   ├── Middleware/                     # Middleware spécifiques (optionnel)
│   ├── Requests/                       # Form Requests
│   │   ├── Store{Entity}Request.php
│   │   ├── Update{Entity}Request.php
│   │   └── ...
│   └── Resources/                      # API Resources
│       ├── {Entity}Resource.php
│       └── ...
├── Providers/
│   ├── {Module}ServiceProvider.php     # Service Provider principal
│   └── RouteServiceProvider.php        # Enregistrement routes
├── Routes/
│   ├── admin.php                       # Routes admin tenant
│   ├── frontend.php                    # Routes frontend tenant
│   └── superadmin.php                  # Routes superadmin central
├── Services/                           # Services métier
│   └── {Service}Service.php
├── Tests/
│   ├── Feature/
│   └── Unit/
└── module.json                         # Métadonnées module
```

### Les 12 Modules du Système

| # | Module | Entités principales |
|---|--------|-------------------|
| 1 | **StructureAcademique** | AcademicYear, Semester, Cycle, Level, Series, Classe, Subject, SubjectClassCoefficient, TeacherSubjectAssignment |
| 2 | **Inscriptions** | Student, Parent, StudentParent (pivot), ClassEnrollment, StudentStatusHistory |
| 3 | **Notes** | Evaluation, Grade, SubjectSemesterAverage, SemesterReportCard, GradingScale |
| 4 | **ConseilDeClasse** | ClassCouncil, CouncilDecision, CouncilAttendee |
| 5 | **Documents** | GeneratedDocument (BulletinSemestriel, Attestations, etc.) |
| 6 | **Presences** | Attendance, AbsenceAlertThreshold |
| 7 | **Discipline** | DisciplinaryIncident, DisciplinarySanction, DisciplinaryCouncil |
| 8 | **EmploisDuTemps** | Room, TimetableSlot (par classe) |
| 9 | **PortailParent** | Aucune entité propre -- agrège les données des autres modules |
| 10 | **Comptabilite** | FeeType, StudentFee, StudentPayment, Expense, PaymentSchedule |
| 11 | **Paie** | StaffContract, PayrollRecord |
| 12 | **UsersGuard** | User, Role, Permission (authentification & autorisation) |

### Exemple Concret : Module Inscriptions

```
Modules/Inscriptions/
├── Entities/
│   ├── Student.php
│   ├── Parent.php
│   ├── StudentParent.php
│   ├── ClassEnrollment.php
│   └── StudentStatusHistory.php
├── Http/
│   ├── Controllers/Admin/
│   │   ├── StudentController.php
│   │   ├── ParentController.php
│   │   ├── ClassEnrollmentController.php
│   │   └── StudentImportController.php
│   ├── Requests/
│   │   ├── StoreStudentRequest.php
│   │   ├── UpdateStudentRequest.php
│   │   ├── StoreParentRequest.php
│   │   ├── EnrollStudentRequest.php
│   │   └── ImportStudentsRequest.php
│   └── Resources/
│       ├── StudentResource.php
│       ├── ParentResource.php
│       └── ClassEnrollmentResource.php
├── Services/
│   ├── MatriculeGeneratorService.php
│   └── StudentImportService.php
└── Routes/
    └── admin.php
```

### Exemple Concret : Module Notes

```
Modules/Notes/
├── Entities/
│   ├── Evaluation.php
│   ├── Grade.php
│   ├── SubjectSemesterAverage.php
│   ├── SemesterReportCard.php
│   └── GradingScale.php
├── Http/
│   ├── Controllers/Admin/
│   │   ├── EvaluationController.php
│   │   ├── GradeController.php
│   │   └── ReportCardController.php
│   ├── Controllers/Frontend/
│   │   └── GradeConsultationController.php
│   ├── Requests/
│   │   ├── StoreEvaluationRequest.php
│   │   ├── StoreGradeRequest.php
│   │   └── BatchGradeRequest.php
│   └── Resources/
│       ├── EvaluationResource.php
│       ├── GradeResource.php
│       └── SemesterReportCardResource.php
├── Services/
│   ├── GradeCalculatorService.php
│   └── RankingService.php
└── Routes/
    ├── admin.php
    └── frontend.php
```

---

## 7.2 Architecture Frontend (Next.js - Modulaire)

### Stack Technique

- **Next.js 15** avec App Router
- **React 18**
- **TypeScript**
- **Material-UI (MUI) 6.2**

### Structure Standard d'un Module

```
src/modules/{ModuleName}/
├── index.ts                            # Barrel export (API publique)
├── admin/                              # Couche Admin
│   ├── components/
│   │   ├── {Entity}List.tsx
│   │   ├── {Entity}ListTable.tsx
│   │   ├── {Entity}AddModal.tsx
│   │   ├── {Entity}EditModal.tsx
│   │   └── ...
│   ├── hooks/
│   │   ├── use{Entities}.ts
│   │   ├── use{Entity}Mutations.ts
│   │   └── ...
│   ├── services/
│   │   └── {entity}Service.ts
│   └── utils/
│       └── ...
├── superadmin/                         # Couche Superadmin (optionnel)
│   └── ...
├── frontend/                           # Couche Frontend (Enseignant/Parent/Eleve)
│   ├── components/
│   ├── hooks/
│   └── services/
├── types/                              # Types TypeScript partagés
│   └── {entity}.types.ts
└── translations/                       # i18n (optionnel)
    └── fr.json
```

### Exemple Concret : Module Inscriptions

```
src/modules/Inscriptions/
├── index.ts
├── admin/
│   ├── components/
│   │   ├── StudentList.tsx
│   │   ├── StudentListTable.tsx
│   │   ├── StudentAddModal.tsx
│   │   ├── StudentEditModal.tsx
│   │   ├── ParentList.tsx
│   │   ├── ParentAddModal.tsx
│   │   ├── ClassEnrollmentForm.tsx
│   │   ├── StudentCard.tsx
│   │   └── CsvImportWizard.tsx
│   ├── hooks/
│   │   ├── useStudents.ts
│   │   ├── useStudentMutations.ts
│   │   ├── useParents.ts
│   │   └── useStudentImport.ts
│   └── services/
│       ├── studentService.ts
│       └── parentService.ts
├── frontend/
│   ├── components/
│   │   └── MyStudentProfile.tsx
│   ├── hooks/
│   │   └── useMyProfile.ts
│   └── services/
│       └── studentProfileService.ts
└── types/
    ├── student.types.ts
    ├── parent.types.ts
    └── enrollment.types.ts
```

### Exemple Concret : Module PortailParent

```
src/modules/PortailParent/
├── index.ts
├── frontend/
│   ├── components/
│   │   ├── ParentDashboard.tsx
│   │   ├── ChildSelector.tsx
│   │   ├── ChildGradesSummary.tsx
│   │   ├── ChildAttendanceSummary.tsx
│   │   ├── ChildDisciplinarySummary.tsx
│   │   └── ChildPaymentStatus.tsx
│   ├── hooks/
│   │   ├── useParentChildren.ts
│   │   ├── useChildGrades.ts
│   │   ├── useChildAttendance.ts
│   │   └── useChildPayments.ts
│   └── services/
│       └── parentPortalService.ts
└── types/
    └── parentPortal.types.ts
```

---

## 7.3 Services Transverses (Partages)

### 1. PDF Generation Service

**Emplacement** : `App\Services\PdfGeneratorService.php`

Genere les documents PDF pour l'ensemble du systeme : bulletins semestriels, attestations de scolarite, recus de paiement, bulletins de paie.

```php
namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfGeneratorService
{
    /**
     * Genere le bulletin semestriel d'un eleve.
     */
    public function generateBulletinSemestriel(Student $student, Semester $semester): string
    {
        $data = [
            'student' => $student->load('classe', 'classe.level', 'classe.series'),
            'semester' => $semester,
            'subjectAverages' => SubjectSemesterAverage::where('student_id', $student->id)
                ->where('semester_id', $semester->id)
                ->with('subject')
                ->get(),
            'reportCard' => SemesterReportCard::where('student_id', $student->id)
                ->where('semester_id', $semester->id)
                ->first(),
            'ranking' => app(RankingService::class)->getStudentRank($student, $semester),
        ];

        $pdf = Pdf::loadView('documents.bulletin-semestriel', $data);

        $filename = "bulletin_{$student->matricule}_{$semester->name}.pdf";
        $path = "documents/bulletins/{$filename}";

        Storage::disk('tenant')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Genere une attestation de scolarite.
     */
    public function generateAttestation(Student $student, AcademicYear $year): string
    {
        // Logique similaire avec vue 'documents.attestation'
    }

    /**
     * Genere un recu de paiement.
     */
    public function generateReceipt(StudentPayment $payment): string
    {
        // Logique similaire avec vue 'documents.receipt'
    }

    /**
     * Genere un bulletin de paie.
     */
    public function generateBulletinPaie(PayrollRecord $record): string
    {
        // Logique similaire avec vue 'documents.bulletin-paie'
    }
}
```

**Usage dans Job Asynchrone** :

```php
namespace Modules\Documents\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\PdfGeneratorService;

class GenerateBulletinJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public Semester $semester
    ) {}

    public function handle(PdfGeneratorService $pdfService): void
    {
        $path = $pdfService->generateBulletinSemestriel($this->student, $this->semester);

        GeneratedDocument::create([
            'student_id' => $this->student->id,
            'document_type' => 'BulletinSemestriel',
            'document_pdf_path' => $path,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);
    }
}
```

### 2. Grade Calculator Service

**Emplacement** : `Modules\Notes\Services\GradeCalculatorService.php`

Calcule les moyennes par matiere et par semestre en utilisant les coefficients (pas de credits ECTS -- systeme secondaire nigérien).

```php
namespace Modules\Notes\Services;

class GradeCalculatorService
{
    /**
     * Calcule la moyenne d'un eleve dans une matiere pour un semestre.
     * Prend en compte les differents types d'evaluations et leurs coefficients.
     */
    public function calculateSubjectAverage(
        Student $student,
        Subject $subject,
        Semester $semester
    ): float {
        $grades = Grade::where('student_id', $student->id)
            ->whereHas('evaluation', fn($q) =>
                $q->where('subject_id', $subject->id)
                  ->where('semester_id', $semester->id)
            )
            ->with('evaluation')
            ->get();

        $totalScore = 0;
        $totalCoefficient = 0;

        foreach ($grades as $grade) {
            $normalizedScore = ($grade->score / $grade->evaluation->max_score) * 20;
            $totalScore += $normalizedScore * $grade->evaluation->coefficient;
            $totalCoefficient += $grade->evaluation->coefficient;
        }

        return $totalCoefficient > 0 ? round($totalScore / $totalCoefficient, 2) : 0;
    }

    /**
     * Calcule la moyenne generale semestrielle d'un eleve.
     * Utilise les coefficients des matieres definis par classe (SubjectClassCoefficient).
     */
    public function calculateSemesterAverage(
        Student $student,
        Semester $semester
    ): float {
        $enrollment = ClassEnrollment::where('student_id', $student->id)
            ->where('academic_year_id', $semester->academicYear->id)
            ->first();

        $subjectCoefficients = SubjectClassCoefficient::where('classe_id', $enrollment->classe_id)
            ->get();

        $totalWeightedScore = 0;
        $totalCoefficients = 0;

        foreach ($subjectCoefficients as $sc) {
            $average = SubjectSemesterAverage::where('student_id', $student->id)
                ->where('subject_id', $sc->subject_id)
                ->where('semester_id', $semester->id)
                ->value('average');

            if ($average !== null) {
                $totalWeightedScore += $average * $sc->coefficient;
                $totalCoefficients += $sc->coefficient;
            }
        }

        return $totalCoefficients > 0 ? round($totalWeightedScore / $totalCoefficients, 2) : 0;
    }
}
```

### 3. Ranking Service

**Emplacement** : `Modules\Notes\Services\RankingService.php`

Calcule le classement des eleves au sein d'une classe pour un semestre donne.

```php
namespace Modules\Notes\Services;

class RankingService
{
    /**
     * Calcule et retourne le classement de tous les eleves d'une classe.
     *
     * @return array<int, array{student_id: int, average: float, rank: int}>
     */
    public function calculateClassRanking(Classe $classe, Semester $semester): array
    {
        $enrollments = ClassEnrollment::where('classe_id', $classe->id)
            ->where('academic_year_id', $semester->academicYear->id)
            ->with('student')
            ->get();

        $results = $enrollments->map(fn($enrollment) => [
            'student_id' => $enrollment->student_id,
            'average' => SemesterReportCard::where('student_id', $enrollment->student_id)
                ->where('semester_id', $semester->id)
                ->value('general_average') ?? 0,
        ])
        ->sortByDesc('average')
        ->values();

        $rank = 1;
        return $results->map(function ($item) use (&$rank) {
            $item['rank'] = $rank++;
            return $item;
        })->all();
    }

    /**
     * Retourne le rang d'un eleve specifique.
     */
    public function getStudentRank(Student $student, Semester $semester): int
    {
        $enrollment = ClassEnrollment::where('student_id', $student->id)
            ->where('academic_year_id', $semester->academicYear->id)
            ->first();

        $ranking = $this->calculateClassRanking($enrollment->classe, $semester);

        $entry = collect($ranking)->firstWhere('student_id', $student->id);

        return $entry ? $entry['rank'] : 0;
    }
}
```

### 4. Matricule Generator Service

**Emplacement** : `Modules\Inscriptions\Services\MatriculeGeneratorService.php`

Genere un matricule unique pour chaque eleve selon le format de l'etablissement.

```php
namespace Modules\Inscriptions\Services;

class MatriculeGeneratorService
{
    /**
     * Genere un matricule unique pour un eleve.
     * Format: {ANNEE}/{CODE_CYCLE}{SEQUENTIAL}
     * Exemple: 2025/C001 (college), 2025/L001 (lycee)
     */
    public function generate(Cycle $cycle, int $year = null): string
    {
        $year = $year ?? date('Y');
        $cycleCode = strtoupper(substr($cycle->name, 0, 1)); // C pour College, L pour Lycee

        $lastMatricule = Student::where('matricule', 'like', "{$year}/{$cycleCode}%")
            ->orderBy('matricule', 'desc')
            ->value('matricule');

        if ($lastMatricule) {
            $lastSequence = (int) substr($lastMatricule, -3);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return sprintf('%d/%s%03d', $year, $cycleCode, $newSequence);
    }
}
```

### 5. Class Council Service

**Emplacement** : `Modules\ConseilDeClasse\Services\ClassCouncilService.php`

Fournit les statistiques de classe et assiste les decisions du conseil de classe.

```php
namespace Modules\ConseilDeClasse\Services;

class ClassCouncilService
{
    /**
     * Calcule les statistiques globales d'une classe pour un semestre.
     *
     * @return array{class_average: float, highest_average: float, lowest_average: float, pass_rate: float, total_students: int}
     */
    public function calculateClassStatistics(Classe $classe, Semester $semester): array
    {
        $reportCards = SemesterReportCard::whereHas('student.classEnrollments', fn($q) =>
                $q->where('classe_id', $classe->id)
                  ->where('academic_year_id', $semester->academicYear->id)
            )
            ->where('semester_id', $semester->id)
            ->get();

        $averages = $reportCards->pluck('general_average');

        return [
            'class_average' => round($averages->avg(), 2),
            'highest_average' => round($averages->max(), 2),
            'lowest_average' => round($averages->min(), 2),
            'pass_rate' => $averages->count() > 0
                ? round(($averages->filter(fn($avg) => $avg >= 10)->count() / $averages->count()) * 100, 2)
                : 0,
            'total_students' => $reportCards->count(),
        ];
    }

    /**
     * Enregistre une decision du conseil pour un eleve.
     */
    public function recordDecision(
        ClassCouncil $council,
        Student $student,
        string $decision,
        ?string $observations = null
    ): CouncilDecision {
        return CouncilDecision::create([
            'class_council_id' => $council->id,
            'student_id' => $student->id,
            'decision' => $decision,
            'observations' => $observations,
        ]);
    }
}
```

### 6. Discipline Notification Service

**Emplacement** : `Modules\Discipline\Services\DisciplineNotificationService.php`

Notifie les parents en cas d'incidents disciplinaires concernant leurs enfants.

```php
namespace Modules\Discipline\Services;

class DisciplineNotificationService
{
    /**
     * Notifie les parents d'un incident disciplinaire.
     */
    public function notifyParents(DisciplinaryIncident $incident): void
    {
        $student = $incident->student->load('parents');

        foreach ($student->parents as $parent) {
            // Notification in-app
            $parent->user?->notify(new DisciplinaryIncidentNotification($incident));

            // Notification SMS/Email selon configuration tenant
        }
    }

    /**
     * Notifie les parents d'une convocation au conseil de discipline.
     */
    public function notifyDisciplinaryCouncil(DisciplinaryCouncil $council): void
    {
        $student = $council->student->load('parents');

        foreach ($student->parents as $parent) {
            $parent->user?->notify(new DisciplinaryCouncilNotification($council));
        }
    }
}
```

---

## 7.4 Nouveaux Composants UI Specialises

### 1. ClassStructureView

**Description** : Vue hierarchique de la structure academique (Cycles -> Niveaux -> Series -> Classes)

**Emplacement** : `src/modules/StructureAcademique/admin/components/ClassStructureView.tsx`

**Technologies** : Material-UI TreeView

**Fonctionnalites** :
- Arborescence depliable Cycle / Niveau / Serie / Classe
- Affichage du nombre d'eleves par classe
- Actions contextuelles (ajouter, modifier, supprimer)
- Filtrage par annee academique

### 2. TimetableGrid

**Description** : Grille hebdomadaire d'emploi du temps par classe (pas par groupe)

**Emplacement** : `src/modules/EmploisDuTemps/admin/components/TimetableGrid.tsx`

**Technologies** : Material-UI Table + custom drag-and-drop

**Fonctionnalites** :
- Grille jours (lundi-samedi) x creneaux horaires
- Affichage matiere, enseignant, salle par creneau
- Drag-and-drop pour reorganiser les creneaux
- Detection automatique des conflits (salle, enseignant)
- Vue par classe ou vue par enseignant

### 3. AttendanceSheet

**Description** : Feuille d'appel interactive pour la saisie des presences

**Emplacement** : `src/modules/Presences/frontend/components/AttendanceSheet.tsx`

**Technologies** : Material-UI Checkbox + DataGrid

**Fonctionnalites** :
- Liste des eleves de la classe avec photo
- Statuts : Present, Absent, Retard, Justifie
- Saisie rapide par clic ou clavier
- Indicateur visuel du seuil d'absences atteint
- Historique des absences par eleve

### 4. GradeInputTable

**Description** : Table de saisie de notes avec calculs automatiques des moyennes

**Emplacement** : `src/modules/Notes/frontend/components/GradeInputTable.tsx`

**Technologies** : MUI X DataGrid (editable)

**Fonctionnalites** :
- Colonnes : eleve, note/bareme, observation
- Calcul automatique de la moyenne de classe en temps reel
- Validation des notes (min 0, max = bareme de l'evaluation)
- Indicateurs visuels pour notes en dessous de la moyenne
- Export des notes en CSV

### 5. ReportCardViewer

**Description** : Apercu et impression du bulletin semestriel

**Emplacement** : `src/modules/Notes/admin/components/ReportCardViewer.tsx`

**Technologies** : Material-UI Card + PDF preview (react-pdf)

**Fonctionnalites** :
- Apercu du bulletin avant generation PDF
- Tableau des matieres avec moyennes, coefficients, moyennes ponderees
- Moyenne generale, rang, appreciation du conseil de classe
- Boutons d'impression et de telechargement PDF
- Navigation entre eleves de la meme classe

### 6. ClassCouncilDashboard

**Description** : Tableau de bord pour les sessions de conseil de classe

**Emplacement** : `src/modules/ConseilDeClasse/admin/components/ClassCouncilDashboard.tsx`

**Technologies** : Material-UI Cards + Charts (recharts)

**Fonctionnalites** :
- Statistiques de classe : moyenne, taux de reussite, repartition des notes
- Liste des eleves avec leur moyenne et decision proposee
- Graphiques de distribution des moyennes
- Saisie des decisions (Admis, Redoublant, Exclu, Avertissement)
- Saisie des observations par eleve
- Liste des participants au conseil

### 7. DisciplinaryRecord

**Description** : Dossier disciplinaire d'un eleve

**Emplacement** : `src/modules/Discipline/admin/components/DisciplinaryRecord.tsx`

**Technologies** : Material-UI Timeline + Card

**Fonctionnalites** :
- Chronologie des incidents disciplinaires
- Detail de chaque incident : date, type, description, sanction
- Statut des sanctions (en cours, terminee)
- Historique des conseils de discipline
- Bouton d'ajout d'un nouvel incident

### 8. ParentDashboard

**Description** : Tableau de bord parent multi-enfants

**Emplacement** : `src/modules/PortailParent/frontend/components/ParentDashboard.tsx`

**Technologies** : Material-UI Tabs + Cards

**Fonctionnalites** :
- Selecteur d'enfant (onglets) pour les parents avec plusieurs enfants
- Resume des notes et moyennes par matiere
- Resume des absences et retards
- Statut des paiements de frais de scolarite
- Incidents disciplinaires recents
- Telechargement des bulletins semestriels

### 9. StudentCard

**Description** : Carte d'identite scolaire de l'eleve

**Emplacement** : `src/modules/Inscriptions/admin/components/StudentCard.tsx`

**Technologies** : Material-UI Card

**Fonctionnalites** :
- Photo de l'eleve
- Informations : matricule, nom, prenom, date de naissance
- Classe actuelle, niveau, serie
- Informations du parent/tuteur
- QR code pour identification rapide

### 10. CsvImportWizard

**Description** : Assistant multi-etapes pour l'import CSV d'eleves et de notes

**Emplacement** : `src/modules/Inscriptions/admin/components/CsvImportWizard.tsx`

**Technologies** : Material-UI Stepper

**Fonctionnalites** :
- Etape 1 : Upload du fichier CSV
- Etape 2 : Mapping des colonnes (correspondance champs CSV / champs systeme)
- Etape 3 : Validation et apercu des donnees (erreurs surlignees en rouge)
- Etape 4 : Confirmation et import
- Rapport d'import avec nombre de lignes traitees, erreurs, doublons

---

[Suivant : APIs Externes →](./external-apis.md)
