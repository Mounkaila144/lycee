# Arborescence du Code

[← Retour à l'index](./index.md)

---

## Backend - Structure Complète

```
C:\laragon\www\lycee\
├── app/
│   ├── Console/
│   ├── Exceptions/
│   ├── Http/
│   │   └── Middleware/
│   │       └── TenantSanctumAuth.php
│   ├── Models/
│   ├── Providers/
│   └── Services/                                # Services transverses
│       ├── PdfGeneratorService.php
│       └── ...
├── bootstrap/
│   ├── app.php                                  # Config Laravel 12
│   └── providers.php
├── config/
│   ├── tenancy.php                              # Config multi-tenant
│   ├── permission.php                           # Config Spatie
│   ├── dompdf.php                               # Config PDF
│   └── ...
├── database/
│   ├── migrations/                              # Migrations centrales
│   └── seeders/
├── Modules/                                     # Modules nwidart/laravel-modules
│   │
│   ├── UsersGuard/                              # ✅ Module existant
│   │   ├── Config/
│   │   ├── Database/
│   │   │   ├── Migrations/
│   │   │   ├── Migrations/tenant/
│   │   │   └── Seeders/
│   │   ├── Entities/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Admin/
│   │   │   │   ├── Frontend/
│   │   │   │   └── Superadmin/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Providers/
│   │   ├── Routes/
│   │   │   ├── admin.php
│   │   │   ├── frontend.php
│   │   │   └── superadmin.php
│   │   └── module.json
│   │
│   ├── StructureAcademique/                     # 🆕 Module 1 - Structure académique
│   │   ├── Config/
│   │   ├── Database/
│   │   │   ├── Migrations/tenant/
│   │   │   │   ├── 2025_01_01_create_academic_years_table.php
│   │   │   │   ├── 2025_01_02_create_semesters_table.php
│   │   │   │   ├── 2025_01_03_create_cycles_table.php
│   │   │   │   ├── 2025_01_04_create_levels_table.php
│   │   │   │   ├── 2025_01_05_create_series_table.php
│   │   │   │   ├── 2025_01_06_create_classes_table.php
│   │   │   │   ├── 2025_01_07_create_subjects_table.php
│   │   │   │   ├── 2025_01_08_create_subject_class_coefficients_table.php
│   │   │   │   └── 2025_01_09_create_teacher_subject_assignments_table.php
│   │   │   └── Seeders/
│   │   ├── Entities/
│   │   │   ├── AcademicYear.php
│   │   │   ├── Semester.php
│   │   │   ├── Cycle.php
│   │   │   ├── Level.php
│   │   │   ├── Series.php
│   │   │   ├── Classe.php
│   │   │   ├── Subject.php
│   │   │   ├── SubjectClassCoefficient.php
│   │   │   └── TeacherSubjectAssignment.php
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/
│   │   │   │   ├── AcademicYearController.php
│   │   │   │   ├── SemesterController.php
│   │   │   │   ├── CycleController.php
│   │   │   │   ├── LevelController.php
│   │   │   │   ├── SeriesController.php
│   │   │   │   ├── ClasseController.php
│   │   │   │   ├── SubjectController.php
│   │   │   │   ├── SubjectClassCoefficientController.php
│   │   │   │   └── TeacherSubjectAssignmentController.php
│   │   │   ├── Requests/
│   │   │   │   ├── StoreAcademicYearRequest.php
│   │   │   │   ├── UpdateAcademicYearRequest.php
│   │   │   │   ├── StoreClasseRequest.php
│   │   │   │   ├── UpdateClasseRequest.php
│   │   │   │   └── ...
│   │   │   └── Resources/
│   │   │       ├── AcademicYearResource.php
│   │   │       ├── ClasseResource.php
│   │   │       ├── SubjectResource.php
│   │   │       └── ...
│   │   ├── Providers/
│   │   ├── Routes/
│   │   │   └── admin.php
│   │   └── module.json
│   │
│   ├── Inscriptions/                            # 🆕 Module 2 - Inscriptions et élèves
│   │   ├── Config/
│   │   ├── Database/
│   │   │   ├── Migrations/tenant/
│   │   │   │   ├── 2025_01_10_create_students_table.php
│   │   │   │   ├── 2025_01_11_create_parents_table.php
│   │   │   │   ├── 2025_01_12_create_student_parents_table.php
│   │   │   │   ├── 2025_01_13_create_class_enrollments_table.php
│   │   │   │   └── 2025_01_14_create_student_status_history_table.php
│   │   │   └── Seeders/
│   │   ├── Entities/
│   │   │   ├── Student.php
│   │   │   ├── Parent_.php
│   │   │   ├── StudentParent.php
│   │   │   ├── ClassEnrollment.php
│   │   │   └── StudentStatusHistory.php
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/
│   │   │   │   ├── StudentController.php
│   │   │   │   ├── ParentController.php
│   │   │   │   ├── ClassEnrollmentController.php
│   │   │   │   └── StudentImportController.php
│   │   │   ├── Controllers/Frontend/
│   │   │   │   └── StudentProfileController.php
│   │   │   ├── Requests/
│   │   │   │   ├── StoreStudentRequest.php
│   │   │   │   ├── UpdateStudentRequest.php
│   │   │   │   ├── StoreParentRequest.php
│   │   │   │   └── ...
│   │   │   └── Resources/
│   │   │       ├── StudentResource.php
│   │   │       ├── ParentResource.php
│   │   │       └── ...
│   │   ├── Services/
│   │   │   ├── MatriculeGeneratorService.php
│   │   │   ├── StudentImportService.php
│   │   │   └── ClassAssignmentService.php
│   │   ├── Routes/
│   │   │   ├── admin.php
│   │   │   └── frontend.php
│   │   └── module.json
│   │
│   ├── Notes/                                   # 🆕 Module 3 - Notes et évaluations
│   │   ├── Config/
│   │   ├── Database/
│   │   │   ├── Migrations/tenant/
│   │   │   │   ├── 2025_01_15_create_evaluations_table.php
│   │   │   │   ├── 2025_01_16_create_grades_table.php
│   │   │   │   ├── 2025_01_17_create_subject_semester_averages_table.php
│   │   │   │   ├── 2025_01_18_create_semester_report_cards_table.php
│   │   │   │   └── 2025_01_19_create_grading_scales_table.php
│   │   │   └── Seeders/
│   │   ├── Entities/
│   │   │   ├── Evaluation.php
│   │   │   ├── Grade.php
│   │   │   ├── SubjectSemesterAverage.php
│   │   │   ├── SemesterReportCard.php
│   │   │   └── GradingScale.php
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/
│   │   │   │   ├── EvaluationController.php
│   │   │   │   ├── GradeController.php
│   │   │   │   └── GradingScaleController.php
│   │   │   ├── Controllers/Frontend/
│   │   │   │   ├── TeacherGradeController.php
│   │   │   │   └── StudentGradeController.php
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Services/
│   │   │   └── GradeCalculatorService.php
│   │   ├── Routes/
│   │   │   ├── admin.php
│   │   │   └── frontend.php
│   │   └── module.json
│   │
│   ├── ConseilDeClasse/                         # 🆕 Module 4 - Conseils de classe
│   │   ├── Config/
│   │   ├── Database/
│   │   │   ├── Migrations/tenant/
│   │   │   │   ├── 2025_01_20_create_class_councils_table.php
│   │   │   │   ├── 2025_01_21_create_council_decisions_table.php
│   │   │   │   └── 2025_01_22_create_council_attendees_table.php
│   │   │   └── Seeders/
│   │   ├── Entities/
│   │   │   ├── ClassCouncil.php
│   │   │   ├── CouncilDecision.php
│   │   │   └── CouncilAttendee.php
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/
│   │   │   │   ├── ClassCouncilController.php
│   │   │   │   ├── CouncilDecisionController.php
│   │   │   │   └── CouncilAttendeeController.php
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Routes/
│   │   │   └── admin.php
│   │   └── module.json
│   │
│   ├── Documents/                               # 🆕 Module 5 - Génération de documents
│   │   ├── Config/
│   │   ├── Database/
│   │   │   ├── Migrations/tenant/
│   │   │   │   └── 2025_01_23_create_generated_documents_table.php
│   │   │   └── Seeders/
│   │   ├── Entities/
│   │   │   └── GeneratedDocument.php
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/
│   │   │   │   ├── DocumentGeneratorController.php
│   │   │   │   └── BulletinBatchController.php
│   │   │   ├── Controllers/Frontend/
│   │   │   │   └── MyDocumentsController.php
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Services/
│   │   │   └── DocumentGenerationService.php
│   │   ├── Routes/
│   │   │   ├── admin.php
│   │   │   └── frontend.php
│   │   └── module.json
│   │
│   ├── Presences/                               # 🆕 Module 6 - Présences et absences
│   │   ├── Config/
│   │   ├── Database/
│   │   │   ├── Migrations/tenant/
│   │   │   │   ├── 2025_01_24_create_attendances_table.php
│   │   │   │   └── 2025_01_25_create_absence_alert_thresholds_table.php
│   │   │   └── Seeders/
│   │   ├── Entities/
│   │   │   ├── Attendance.php
│   │   │   └── AbsenceAlertThreshold.php
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/
│   │   │   │   ├── AttendanceReportController.php
│   │   │   │   └── AbsenceAlertThresholdController.php
│   │   │   ├── Controllers/Frontend/
│   │   │   │   ├── TeacherAttendanceController.php
│   │   │   │   └── StudentAttendanceController.php
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Routes/
│   │   │   ├── admin.php
│   │   │   └── frontend.php
│   │   └── module.json
│   │
│   ├── Discipline/                              # 🆕 Module 7 - Discipline
│   │   ├── Config/
│   │   ├── Database/
│   │   │   ├── Migrations/tenant/
│   │   │   │   ├── 2025_01_26_create_disciplinary_incidents_table.php
│   │   │   │   ├── 2025_01_27_create_disciplinary_sanctions_table.php
│   │   │   │   ├── 2025_01_28_create_disciplinary_councils_table.php
│   │   │   │   └── 2025_01_29_create_disciplinary_council_members_table.php
│   │   │   └── Seeders/
│   │   ├── Entities/
│   │   │   ├── DisciplinaryIncident.php
│   │   │   ├── DisciplinarySanction.php
│   │   │   ├── DisciplinaryCouncil.php
│   │   │   └── DisciplinaryCouncilMember.php
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/
│   │   │   │   ├── DisciplinaryIncidentController.php
│   │   │   │   ├── DisciplinarySanctionController.php
│   │   │   │   └── DisciplinaryCouncilController.php
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Routes/
│   │   │   └── admin.php
│   │   └── module.json
│   │
│   ├── EmploisDuTemps/                          # 🆕 Module 8 - Emplois du temps
│   │   ├── Config/
│   │   ├── Database/
│   │   │   ├── Migrations/tenant/
│   │   │   │   ├── 2025_01_30_create_rooms_table.php
│   │   │   │   └── 2025_01_31_create_timetable_slots_table.php
│   │   │   └── Seeders/
│   │   ├── Entities/
│   │   │   ├── Room.php
│   │   │   └── TimetableSlot.php
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/
│   │   │   │   ├── RoomController.php
│   │   │   │   └── TimetableSlotController.php
│   │   │   ├── Controllers/Frontend/
│   │   │   │   └── MyTimetableController.php
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Routes/
│   │   │   ├── admin.php
│   │   │   └── frontend.php
│   │   └── module.json
│   │
│   ├── PortailParent/                           # 🆕 Module 9 - Portail parent (agrégation)
│   │   ├── Config/
│   │   ├── Http/
│   │   │   ├── Controllers/Frontend/
│   │   │   │   └── ParentDashboardController.php
│   │   │   └── Resources/
│   │   ├── Routes/
│   │   │   └── frontend.php
│   │   └── module.json
│   │
│   ├── Comptabilite/                            # 🆕 Module 10 - Comptabilite
│   │   ├── Config/
│   │   ├── Database/
│   │   │   ├── Migrations/tenant/
│   │   │   │   ├── 2025_02_01_create_fee_types_table.php
│   │   │   │   ├── 2025_02_02_create_student_fees_table.php
│   │   │   │   ├── 2025_02_03_create_student_payments_table.php
│   │   │   │   ├── 2025_02_04_create_expenses_table.php
│   │   │   │   └── 2025_02_05_create_payment_schedules_table.php
│   │   │   └── Seeders/
│   │   ├── Entities/
│   │   │   ├── FeeType.php
│   │   │   ├── StudentFee.php
│   │   │   ├── StudentPayment.php
│   │   │   ├── Expense.php
│   │   │   └── PaymentSchedule.php
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/
│   │   │   │   ├── FeeTypeController.php
│   │   │   │   ├── StudentFeeController.php
│   │   │   │   ├── StudentPaymentController.php
│   │   │   │   ├── ExpenseController.php
│   │   │   │   └── FinancialDashboardController.php
│   │   │   ├── Controllers/Frontend/
│   │   │   │   └── MyFeesController.php
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Routes/
│   │   │   ├── admin.php
│   │   │   └── frontend.php
│   │   └── module.json
│   │
│   └── Paie/                                    # 🆕 Module 11 - Paie du personnel
│       ├── Config/
│       ├── Database/
│       │   ├── Migrations/tenant/
│       │   │   ├── 2025_02_06_create_staff_contracts_table.php
│       │   │   └── 2025_02_07_create_payroll_records_table.php
│       │   └── Seeders/
│       ├── Entities/
│       │   ├── StaffContract.php
│       │   └── PayrollRecord.php
│       ├── Http/
│       │   ├── Controllers/Admin/
│       │   │   ├── StaffContractController.php
│       │   │   └── PayrollRecordController.php
│       │   ├── Controllers/Frontend/
│       │   │   └── MyPayrollController.php
│       │   ├── Requests/
│       │   └── Resources/
│       ├── Routes/
│       │   ├── admin.php
│       │   └── frontend.php
│       └── module.json
│
├── public/
├── resources/
│   └── views/
│       └── documents/                           # Templates PDF
│           ├── bulletin-semestriel.blade.php     # Bulletin de notes semestriel
│           ├── bulletin-annuel.blade.php         # Bulletin de notes annuel
│           ├── attestation-scolarite.blade.php   # Attestation de scolarité
│           ├── carte-scolaire.blade.php          # Carte scolaire de l'élève
│           ├── recu-paiement.blade.php           # Reçu de paiement
│           ├── bulletin-paie.blade.php           # Bulletin de paie du personnel
│           └── pv-conseil.blade.php              # PV du conseil de classe
├── routes/
│   ├── api.php
│   ├── console.php
│   └── web.php
├── storage/
│   └── app/
│       └── tenants/                             # Stockage par tenant
│           └── tenant_{id}/
│               ├── documents/
│               │   ├── bulletins/               # Bulletins de notes générés
│               │   ├── attestations/            # Attestations de scolarité
│               │   ├── cartes-scolaires/        # Cartes scolaires
│               │   ├── recus/                   # Reçus de paiement
│               │   ├── bulletins-paie/          # Bulletins de paie
│               │   └── pv-conseils/             # PV des conseils de classe
│               ├── uploads/
│               │   ├── photos/                  # Photos d'identité élèves/personnel
│               │   └── justificatifs/           # Justificatifs d'absence
│               └── imports/
│                   └── csv/                     # Fichiers CSV d'import
├── tests/
│   ├── Feature/
│   │   ├── UsersGuard/
│   │   ├── StructureAcademique/
│   │   ├── Inscriptions/
│   │   ├── Notes/
│   │   ├── ConseilDeClasse/
│   │   ├── Documents/
│   │   ├── Presences/
│   │   ├── Discipline/
│   │   ├── EmploisDuTemps/
│   │   ├── PortailParent/
│   │   ├── Comptabilite/
│   │   └── Paie/
│   ├── Unit/
│   └── Concerns/
│       └── InteractsWithTenancy.php
├── .env
├── composer.json
└── artisan
```

---

## Frontend - Structure Complète

```
lycee-front/                                     # Polyrepo Next.js 15
├── src/
│   ├── app/                                     # Next.js App Router
│   │   ├── (admin)/                             # Routes administration
│   │   │   ├── dashboard/
│   │   │   ├── structure-academique/
│   │   │   ├── inscriptions/
│   │   │   ├── notes/
│   │   │   ├── conseil-de-classe/
│   │   │   ├── documents/
│   │   │   ├── presences/
│   │   │   ├── discipline/
│   │   │   ├── emplois-du-temps/
│   │   │   ├── comptabilite/
│   │   │   ├── paie/
│   │   │   └── ...
│   │   ├── (frontend)/                          # Routes élève / enseignant / parent
│   │   │   ├── dashboard/
│   │   │   ├── profile/
│   │   │   ├── notes/
│   │   │   ├── emploi-du-temps/
│   │   │   ├── presences/
│   │   │   ├── documents/
│   │   │   ├── portail-parent/
│   │   │   ├── mes-frais/
│   │   │   ├── ma-paie/
│   │   │   └── ...
│   │   ├── (superadmin)/                        # Routes superadmin
│   │   └── layout.tsx
│   │
│   ├── modules/                                 # Modules métier
│   │   ├── UsersGuard/                          # ✅ Module existant
│   │   │   ├── index.ts
│   │   │   ├── admin/
│   │   │   │   ├── components/
│   │   │   │   ├── hooks/
│   │   │   │   └── services/
│   │   │   ├── superadmin/
│   │   │   ├── frontend/
│   │   │   └── types/
│   │   │
│   │   ├── StructureAcademique/                 # 🆕 Module 1
│   │   │   ├── index.ts
│   │   │   ├── admin/
│   │   │   │   ├── components/
│   │   │   │   │   ├── ClassList.tsx
│   │   │   │   │   ├── SubjectList.tsx
│   │   │   │   │   ├── ClassStructureView.tsx
│   │   │   │   │   └── CoefficientManager.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   ├── useClasses.ts
│   │   │   │   │   ├── useSubjects.ts
│   │   │   │   │   ├── useLevels.ts
│   │   │   │   │   └── useSeries.ts
│   │   │   │   └── services/
│   │   │   │       ├── classeService.ts
│   │   │   │       ├── subjectService.ts
│   │   │   │       └── coefficientService.ts
│   │   │   └── types/
│   │   │       ├── classe.types.ts
│   │   │       ├── subject.types.ts
│   │   │       ├── level.types.ts
│   │   │       └── series.types.ts
│   │   │
│   │   ├── Inscriptions/                        # 🆕 Module 2
│   │   │   ├── index.ts
│   │   │   ├── admin/
│   │   │   │   ├── components/
│   │   │   │   │   ├── StudentList.tsx
│   │   │   │   │   ├── StudentAddModal.tsx
│   │   │   │   │   ├── ParentList.tsx
│   │   │   │   │   ├── CsvImportWizard.tsx
│   │   │   │   │   └── StudentCard.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   ├── useStudents.ts
│   │   │   │   │   ├── useStudentMutations.ts
│   │   │   │   │   ├── useParents.ts
│   │   │   │   │   └── useStudentImport.ts
│   │   │   │   └── services/
│   │   │   │       ├── studentService.ts
│   │   │   │       └── parentService.ts
│   │   │   ├── frontend/
│   │   │   │   ├── components/
│   │   │   │   │   └── MyStudentProfile.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   └── useMyProfile.ts
│   │   │   │   └── services/
│   │   │   │       └── studentProfileService.ts
│   │   │   └── types/
│   │   │       ├── student.types.ts
│   │   │       ├── parent.types.ts
│   │   │       └── enrollment.types.ts
│   │   │
│   │   ├── Notes/                               # 🆕 Module 3
│   │   │   ├── index.ts
│   │   │   ├── admin/
│   │   │   │   ├── components/
│   │   │   │   │   ├── EvaluationList.tsx
│   │   │   │   │   └── ClassGradeSummary.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   └── useEvaluations.ts
│   │   │   │   └── services/
│   │   │   │       └── evaluationService.ts
│   │   │   ├── frontend/
│   │   │   │   ├── components/
│   │   │   │   │   ├── GradeInputTable.tsx       # Enseignant
│   │   │   │   │   ├── AppreciationForm.tsx      # Enseignant
│   │   │   │   │   ├── MyGrades.tsx              # Élève
│   │   │   │   │   └── MyReportCards.tsx         # Élève
│   │   │   │   ├── hooks/
│   │   │   │   │   ├── useGradeInput.ts
│   │   │   │   │   └── useMyGrades.ts
│   │   │   │   └── services/
│   │   │   │       ├── gradeInputService.ts
│   │   │   │       └── myGradesService.ts
│   │   │   └── types/
│   │   │       ├── evaluation.types.ts
│   │   │       └── grade.types.ts
│   │   │
│   │   ├── ConseilDeClasse/                     # 🆕 Module 4
│   │   │   ├── index.ts
│   │   │   ├── admin/
│   │   │   │   ├── components/
│   │   │   │   │   ├── ClassCouncilDashboard.tsx
│   │   │   │   │   ├── DecisionForm.tsx
│   │   │   │   │   └── CouncilMinutes.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   └── useClassCouncils.ts
│   │   │   │   └── services/
│   │   │   │       └── classCouncilService.ts
│   │   │   └── types/
│   │   │       └── council.types.ts
│   │   │
│   │   ├── Documents/                           # 🆕 Module 5
│   │   │   ├── index.ts
│   │   │   ├── admin/
│   │   │   │   ├── components/
│   │   │   │   │   ├── DocumentGenerator.tsx
│   │   │   │   │   └── BulletinBatchGenerator.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   └── useDocuments.ts
│   │   │   │   └── services/
│   │   │   │       └── documentService.ts
│   │   │   ├── frontend/
│   │   │   │   ├── components/
│   │   │   │   │   └── MyDocuments.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   └── useMyDocuments.ts
│   │   │   │   └── services/
│   │   │   │       └── myDocumentsService.ts
│   │   │   └── types/
│   │   │       └── document.types.ts
│   │   │
│   │   ├── Presences/                           # 🆕 Module 6
│   │   │   ├── index.ts
│   │   │   ├── admin/
│   │   │   │   ├── components/
│   │   │   │   │   └── AttendanceReport.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   └── useAttendanceReport.ts
│   │   │   │   └── services/
│   │   │   │       └── attendanceReportService.ts
│   │   │   ├── frontend/
│   │   │   │   ├── components/
│   │   │   │   │   ├── AttendanceSheet.tsx       # Enseignant
│   │   │   │   │   └── MyAttendances.tsx         # Élève
│   │   │   │   ├── hooks/
│   │   │   │   │   ├── useAttendanceSheet.ts
│   │   │   │   │   └── useMyAttendances.ts
│   │   │   │   └── services/
│   │   │   │       ├── attendanceSheetService.ts
│   │   │   │       └── myAttendancesService.ts
│   │   │   └── types/
│   │   │       └── attendance.types.ts
│   │   │
│   │   ├── Discipline/                          # 🆕 Module 7
│   │   │   ├── index.ts
│   │   │   ├── admin/
│   │   │   │   ├── components/
│   │   │   │   │   ├── IncidentForm.tsx
│   │   │   │   │   ├── SanctionForm.tsx
│   │   │   │   │   ├── DisciplinaryRecord.tsx
│   │   │   │   │   └── DisciplinaryCouncilForm.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   ├── useIncidents.ts
│   │   │   │   │   └── useSanctions.ts
│   │   │   │   └── services/
│   │   │   │       └── disciplineService.ts
│   │   │   └── types/
│   │   │       ├── incident.types.ts
│   │   │       └── sanction.types.ts
│   │   │
│   │   ├── EmploisDuTemps/                      # 🆕 Module 8
│   │   │   ├── index.ts
│   │   │   ├── admin/
│   │   │   │   ├── components/
│   │   │   │   │   ├── TimetableGrid.tsx
│   │   │   │   │   └── RoomManager.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   ├── useTimetable.ts
│   │   │   │   │   └── useRooms.ts
│   │   │   │   └── services/
│   │   │   │       ├── timetableService.ts
│   │   │   │       └── roomService.ts
│   │   │   ├── frontend/
│   │   │   │   ├── components/
│   │   │   │   │   └── MyTimetable.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   └── useMyTimetable.ts
│   │   │   │   └── services/
│   │   │   │       └── myTimetableService.ts
│   │   │   └── types/
│   │   │       ├── timetableSlot.types.ts
│   │   │       └── room.types.ts
│   │   │
│   │   ├── PortailParent/                       # 🆕 Module 9
│   │   │   ├── index.ts
│   │   │   ├── frontend/
│   │   │   │   ├── components/
│   │   │   │   │   ├── ParentDashboard.tsx
│   │   │   │   │   ├── ChildGrades.tsx
│   │   │   │   │   ├── ChildAbsences.tsx
│   │   │   │   │   └── ChildDiscipline.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   ├── useParentDashboard.ts
│   │   │   │   │   └── useChildData.ts
│   │   │   │   └── services/
│   │   │   │       └── parentPortalService.ts
│   │   │   └── types/
│   │   │       └── parentPortal.types.ts
│   │   │
│   │   ├── Comptabilite/                        # 🆕 Module 10
│   │   │   ├── index.ts
│   │   │   ├── admin/
│   │   │   │   ├── components/
│   │   │   │   │   ├── FeeManager.tsx
│   │   │   │   │   ├── PaymentForm.tsx
│   │   │   │   │   └── FinancialDashboard.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   ├── useFees.ts
│   │   │   │   │   ├── usePayments.ts
│   │   │   │   │   └── useFinancialStats.ts
│   │   │   │   └── services/
│   │   │   │       ├── feeService.ts
│   │   │   │       └── paymentService.ts
│   │   │   ├── frontend/
│   │   │   │   ├── components/
│   │   │   │   │   └── MyFees.tsx
│   │   │   │   ├── hooks/
│   │   │   │   │   └── useMyFees.ts
│   │   │   │   └── services/
│   │   │   │       └── myFeesService.ts
│   │   │   └── types/
│   │   │       ├── fee.types.ts
│   │   │       └── payment.types.ts
│   │   │
│   │   └── Paie/                                # 🆕 Module 11
│   │       ├── index.ts
│   │       ├── admin/
│   │       │   ├── components/
│   │       │   │   ├── ContractManager.tsx
│   │       │   │   └── PayrollForm.tsx
│   │       │   ├── hooks/
│   │       │   │   ├── useContracts.ts
│   │       │   │   └── usePayroll.ts
│   │       │   └── services/
│   │       │       ├── contractService.ts
│   │       │       └── payrollService.ts
│   │       ├── frontend/
│   │       │   ├── components/
│   │       │   │   └── MyPayroll.tsx
│   │       │   ├── hooks/
│   │       │   │   └── useMyPayroll.ts
│   │       │   └── services/
│   │       │       └── myPayrollService.ts
│   │       └── types/
│   │           ├── contract.types.ts
│   │           └── payroll.types.ts
│   │
│   ├── lib/
│   │   ├── api/
│   │   │   ├── apiClient.ts                     # ✅ Client API existant
│   │   │   └── ...
│   │   └── utils/
│   │
│   ├── components/                              # Composants partagés
│   │   ├── common/
│   │   ├── layout/
│   │   └── ...
│   │
│   └── types/                                   # Types globaux
│
├── public/
├── .env.local
├── next.config.js
├── package.json
└── tsconfig.json
```

---

[Suivant : Infrastructure et Déploiement →](./infrastructure-and-deployment.md)
