<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Attendance\Entities\AbsenceJustification;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Attendance\Entities\AttendanceSession;
use Modules\Attendance\Services\AttendanceMonitoringService;
use Modules\Attendance\Services\JustificationService;
use Modules\Documents\Entities\DocumentRequest;
use Modules\Documents\Services\CertificateService;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Entities\ReenrollmentIntention;
use Modules\Enrollment\Entities\Student;
use Modules\Exams\Entities\ExamAttendanceSheet;
use Modules\Exams\Entities\ExamIncident;
use Modules\Exams\Entities\ExamRoomAssignment;
use Modules\Exams\Entities\ExamSession;
use Modules\Exams\Entities\ExamSupervisor;
use Modules\Finance\Entities\CashierCloseRecord;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\Payment;
use Modules\Messaging\Entities\Message;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ClassEvaluationGrade;
use Modules\NotesEvaluations\Entities\ReportCardComment;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\Payroll\Entities\Employee;
use Modules\Payroll\Entities\EmploymentContract;
use Modules\Payroll\Entities\PayrollComponent;
use Modules\Payroll\Entities\PayrollPeriod;
use Modules\Payroll\Entities\Payslip;
use Modules\Payroll\Services\PayrollProcessingService;
use Modules\Payroll\Services\PayrollSettingsService;
use Modules\PortailParent\Entities\ParentModel;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;
use Modules\Timetable\Entities\Room;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\UsersGuard\Entities\Tenant;
use Modules\UsersGuard\Entities\TenantUser;
use Throwable;

/**
 * Données d'activité de l'école de démonstration (année active 2026-2027), à lancer APRÈS DemoSchoolSeeder.
 *
 * Idempotent : chaque enregistrement est retrouvé par une clé naturelle (firstOrCreate / updateOrCreate) ;
 * les données déjà présentes (éventuellement modifiées pendant une démo) ne sont pas écrasées.
 * Chaque bloc vérifie que ses tables existent sur le tenant et est sauté (et journalisé) sinon.
 *
 * Usage :
 *  - php artisan db:seed --class="Database\Seeders\DemoSchoolActivitySeeder" --force           (tenant lycee1)
 *  - php artisan tenants:seed --tenants=<id> --class="Database\Seeders\DemoSchoolActivitySeeder" --force
 */
class DemoSchoolActivitySeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const DEFAULT_TENANT_ID = 'lycee1';

    private const ACADEMIC_YEAR = '2026-2027';

    private const NEXT_ACADEMIC_YEAR = '2027-2028';

    /**
     * Professeur de référence par code matière.
     */
    private const SUBJECT_TEACHERS = [
        'MATH' => 'prof.maths',
        'FRAN' => 'prof.francais',
        'SVT' => 'prof.svt',
        'HG' => 'prof.hg',
        'EC' => 'prof.hg',
        'ANG' => 'prof.anglais',
        'PHYS' => 'prof.pc',
        'PHIL' => 'prof.philo',
        'EPS' => 'prof.eps',
    ];

    /**
     * Professeurs ajoutés par ce seeder : username => [prénom, nom, sexe].
     */
    private const NEW_TEACHERS = [
        'prof.anglais' => ['Aïchatou', 'Hamani', 'F'],
        'prof.pc' => ['Souleymane', 'Garba', 'M'],
        'prof.philo' => ['Mariama', 'Soumana', 'F'],
        'prof.eps' => ['Idrissa', 'Boubacar', 'M'],
    ];

    private const TEACHER_USERNAMES = [
        'prof.maths', 'prof.francais', 'prof.svt', 'prof.hg',
        'prof.anglais', 'prof.pc', 'prof.philo', 'prof.eps',
    ];

    /**
     * Créneaux horaires : [début, fin].
     */
    private const TIME_SLOTS = [
        ['08:00:00', '09:00:00'],
        ['09:00:00', '10:00:00'],
        ['10:15:00', '11:15:00'],
        ['11:15:00', '12:15:00'],
        ['15:00:00', '16:00:00'],
        ['16:00:00', '17:00:00'],
    ];

    /**
     * Jours ouvrés => nombre de créneaux (samedi : matinée seulement).
     */
    private const DAYS = [
        'Lundi' => 6,
        'Mardi' => 6,
        'Mercredi' => 6,
        'Jeudi' => 6,
        'Vendredi' => 6,
        'Samedi' => 4,
    ];

    private const FINANCE_CLOSE_DATE = '2026-11-12';

    /**
     * Identifiant du tenant à remplir quand la tenancy n'est pas déjà initialisée.
     */
    public string $tenantId = self::DEFAULT_TENANT_ID;

    private string $currentTenantId = '';

    private AcademicYear $year;

    private Semester $s1;

    private ?Semester $s2 = null;

    private TenantUser $admin;

    /**
     * @var Collection<int, Classe>
     */
    private Collection $classes;

    /**
     * @var array<string, TenantUser> username => professeur
     */
    private array $teachers = [];

    /**
     * @var array<string, Subject> code => matière
     */
    private array $subjects = [];

    /**
     * @var array<int, list<array{subject: Subject, coefficient: float, hours: int}>> classe_id => matières
     */
    private array $classSubjects = [];

    /**
     * @var array<int, Collection<int, Student>> classe_id => élèves actifs
     */
    private array $classStudents = [];

    /**
     * @var array<string, int> volumes créés par ce passage
     */
    private array $created = [];

    /**
     * @var list<string>
     */
    private array $skipped = [];

    public function run(): void
    {
        if (tenancy()->initialized) {
            $this->seedTenant((string) tenant()->getTenantKey());

            return;
        }

        if (app()->runningUnitTests()) {
            $tenant = Tenant::query()->find($this->tenantId) ?? Tenant::query()->first();
            $this->seedTenant((string) ($tenant?->id ?? $this->tenantId));

            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);

        if (! $tenant) {
            $this->command?->error("Tenant [{$this->tenantId}] introuvable.");

            return;
        }

        tenancy()->initialize($tenant);

        try {
            $this->seedTenant((string) $tenant->id);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Fichier JSON des comptes de démo (hors stockage suffixé par la tenancy ; jamais écrasé par les tests).
     */
    public static function accountsFilePath(): string
    {
        return app()->runningUnitTests()
            ? storage_path('framework/testing/demo-accounts.json')
            : base_path('storage/app/demo-accounts.json');
    }

    private function seedTenant(string $tenantId): void
    {
        $this->currentTenantId = $tenantId;
        $this->created = [];
        $this->skipped = [];

        if (! $this->loadContext()) {
            return;
        }

        $this->runBlock('Professeurs', ['users'], [], fn () => $this->seedTeachers());
        $this->runBlock('Affectations des professeurs', ['teacher_assignments'], [], fn () => $this->seedTeacherAssignments());
        $this->runBlock('Professeurs principaux', ['classes'], [], fn () => $this->seedHeadTeachers());
        $this->runBlock('Notes (évaluations, notes, appréciations)', ['class_evaluations', 'class_evaluation_grades', 'report_card_comments', 'teacher_assignments'], [], fn () => $this->seedGrades());
        $this->runBlock('Salles', ['rooms'], [], fn () => $this->seedRooms());
        $this->runBlock('Emploi du temps', ['timetable_slots', 'rooms'], ['timetable_slots' => ['classe_id', 'subject_id']], fn () => $this->seedTimetable());
        $this->runBlock('Présences', ['attendance_sessions', 'attendance_records', 'attendance_justifications', 'attendance_alerts'], ['attendance_sessions' => ['classe_id', 'subject_id']], fn () => $this->seedAttendance());
        $this->runBlock('Examens', ['exam_sessions', 'exam_room_assignments', 'exam_supervisors', 'exam_attendance_sheets', 'exam_incidents', 'rooms'], ['exam_sessions' => ['subject_id', 'classe_id', 'level_id', 'semester_id', 'class_evaluation_ids']], fn () => $this->seedExams());
        $this->runBlock('Finance (paiements, retards, clôture de caisse)', ['invoices', 'payments', 'cashier_close_records'], [], fn () => $this->seedFinance());
        $this->runBlock('Paie', ['employees', 'employment_contracts', 'payroll_components', 'payroll_periods', 'payroll_records', 'payslips'], [], fn () => $this->seedPayroll());
        $this->runBlock('Messagerie', ['messages'], [], fn () => $this->seedMessaging());
        $this->runBlock('Documents (attestations)', ['documents', 'document_requests', 'document_templates'], [], fn () => $this->seedDocuments());
        $this->runBlock('Réinscription (campagne)', ['reenrollment_campaigns'], [], fn () => $this->seedReenrollmentCampaign());

        $this->writeAccountsFile();

        $this->command?->info("Demo activity seeded for tenant [{$tenantId}].");

        foreach ($this->created as $label => $count) {
            $this->command?->line("  + {$label}: {$count}");
        }

        foreach ($this->skipped as $message) {
            $this->command?->warn("  - SAUTÉ : {$message}");
        }
    }

    private function loadContext(): bool
    {
        $year = AcademicYear::query()->where('name', self::ACADEMIC_YEAR)->first();
        $s1 = $year ? Semester::query()->where('academic_year_id', $year->id)->where('name', 'S1')->first() : null;
        $admin = TenantUser::query()->where('username', 'demo.admin')->first();

        if (! $year || ! $s1 || ! $admin) {
            $this->command?->error('Année '.self::ACADEMIC_YEAR.', semestre S1 ou compte demo.admin absent : lancez d’abord DemoSchoolSeeder.');

            return false;
        }

        $this->year = $year;
        $this->s1 = $s1;
        $this->s2 = Semester::query()->where('academic_year_id', $year->id)->where('name', 'S2')->first();
        $this->admin = $admin;
        $this->subjects = Subject::query()->get()->keyBy('code')->all();
        $this->classes = Classe::query()
            ->with(['level', 'series'])
            ->where('academic_year_id', $year->id)
            ->orderBy('id')
            ->get();

        foreach ($this->classes as $classe) {
            $this->classSubjects[$classe->id] = $this->subjectsForClasse($classe);
            $this->classStudents[$classe->id] = ClassEnrollment::query()
                ->with('student')
                ->where('classe_id', $classe->id)
                ->where('status', 'Actif')
                ->get()
                ->pluck('student')
                ->filter()
                ->sortBy('matricule')
                ->values();
        }

        return true;
    }

    /**
     * @param  list<string>  $tables
     * @param  array<string, list<string>>  $columns
     */
    private function runBlock(string $label, array $tables, array $columns, callable $callback): void
    {
        $schema = Schema::connection('tenant');

        foreach ($tables as $table) {
            if (! $schema->hasTable($table)) {
                $this->skipped[] = "{$label} — table `{$table}` absente (migration non exécutée)";

                return;
            }
        }

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                if (! $schema->hasColumn($table, $column)) {
                    $this->skipped[] = "{$label} — colonne `{$table}.{$column}` absente (migration non exécutée)";

                    return;
                }
            }
        }

        try {
            DB::connection('tenant')->transaction(fn () => $callback());
        } catch (Throwable $exception) {
            if (app()->runningUnitTests()) {
                throw $exception;
            }

            $this->skipped[] = "{$label} — erreur : ".$exception->getMessage();
        }
    }

    private function count(string $label, int $increment = 1): void
    {
        $this->created[$label] = ($this->created[$label] ?? 0) + $increment;
    }

    /**
     * Entier pseudo-aléatoire déterministe dans [0, $modulo[.
     */
    private function pick(string $key, int $modulo): int
    {
        return (int) (crc32($key) % $modulo);
    }

    // ---------------------------------------------------------------------
    // Professeurs, affectations, professeurs principaux
    // ---------------------------------------------------------------------

    private function seedTeachers(): void
    {
        foreach (self::NEW_TEACHERS as $username => [$firstname, $lastname, $sex]) {
            $user = TenantUser::updateOrCreate(
                ['username' => $username],
                [
                    'email' => $username.'@'.$this->currentTenantId.'.test',
                    'password' => Hash::make(self::PASSWORD),
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'application' => 'admin',
                    'is_active' => true,
                    'sex' => $sex,
                    'phone' => '+2279000'.str_pad((string) (crc32($username) % 10000), 4, '0', STR_PAD_LEFT),
                    'city' => 'Niamey',
                    'country' => 'Niger',
                ]
            );

            if ($user->wasRecentlyCreated) {
                $this->count('professeurs créés');
            }

            if (! $user->hasRole('Professeur')) {
                $user->assignRole('Professeur');
            }
        }

        $this->teachers = TenantUser::query()
            ->whereIn('username', self::TEACHER_USERNAMES)
            ->get()
            ->keyBy('username')
            ->all();
    }

    /**
     * @return list<array{subject: Subject, coefficient: float, hours: int}>
     */
    private function subjectsForClasse(Classe $classe): array
    {
        $rows = SubjectClassCoefficient::query()
            ->with('subject')
            ->where('level_id', $classe->level_id)
            ->where(fn ($query) => $query->whereNull('series_id')->orWhere('series_id', $classe->series_id))
            ->get()
            ->sortByDesc(fn (SubjectClassCoefficient $row): int => $row->series_id === null ? 0 : 1)
            ->unique('subject_id')
            ->filter(fn (SubjectClassCoefficient $row): bool => $row->subject !== null)
            ->sortBy('subject_id');

        return $rows->map(fn (SubjectClassCoefficient $row): array => [
            'subject' => $row->subject,
            'coefficient' => (float) $row->coefficient,
            'hours' => max(1, (int) ($row->hours_per_week ?? 2)),
        ])->values()->all();
    }

    private function defaultTeacherFor(string $subjectCode): ?TenantUser
    {
        $username = self::SUBJECT_TEACHERS[$subjectCode] ?? null;

        return $username ? ($this->teachers[$username] ?? null) : null;
    }

    /**
     * Professeur de la matière dans la classe : l'affectation existante, sinon le professeur de référence.
     */
    private function teacherFor(Classe $classe, Subject $subject): ?TenantUser
    {
        if (Schema::connection('tenant')->hasTable('teacher_assignments')) {
            $teacherId = TeacherAssignment::query()
                ->where('classe_id', $classe->id)
                ->where('subject_id', $subject->id)
                ->where('academic_year_id', $classe->academic_year_id)
                ->value('teacher_id');

            if ($teacherId) {
                return collect($this->teachers)->firstWhere('id', (int) $teacherId)
                    ?? TenantUser::query()->find($teacherId);
            }
        }

        return $this->defaultTeacherFor($subject->code);
    }

    private function seedTeacherAssignments(): void
    {
        foreach ($this->classes as $classe) {
            foreach ($this->classSubjects[$classe->id] as $item) {
                $teacher = $this->defaultTeacherFor($item['subject']->code)
                    ?? $this->teachers['prof.maths'] ?? null;

                if (! $teacher) {
                    continue;
                }

                $assignment = TeacherAssignment::query()->firstOrCreate(
                    [
                        'classe_id' => $classe->id,
                        'subject_id' => $item['subject']->id,
                        'academic_year_id' => $classe->academic_year_id,
                    ],
                    ['teacher_id' => $teacher->id]
                );

                if ($assignment->wasRecentlyCreated) {
                    $this->count('affectations professeur/matière');
                }
            }
        }
    }

    /**
     * Un professeur principal par classe, qui enseigne dans la classe. Les PP cohérents existants sont conservés.
     */
    private function seedHeadTeachers(): void
    {
        $headCounts = [];

        foreach ($this->classes as $classe) {
            if ($classe->head_teacher_id) {
                $headCounts[(int) $classe->head_teacher_id] = ($headCounts[(int) $classe->head_teacher_id] ?? 0) + 1;
            }
        }

        foreach ($this->classes as $classe) {
            $classTeacherIds = collect($this->classSubjects[$classe->id])
                ->map(fn (array $item): ?int => $this->teacherFor($classe, $item['subject'])?->id)
                ->filter()
                ->unique()
                ->values();

            if ($classTeacherIds->isEmpty() || ($classe->head_teacher_id && $classTeacherIds->contains((int) $classe->head_teacher_id))) {
                continue;
            }

            $newHeadId = $classTeacherIds->sortBy(fn (int $id): int => $headCounts[$id] ?? 0)->first();

            if ($classe->head_teacher_id) {
                $headCounts[(int) $classe->head_teacher_id]--;
            }

            $headCounts[$newHeadId] = ($headCounts[$newHeadId] ?? 0) + 1;
            $classe->update(['head_teacher_id' => $newHeadId]);
            $this->count('professeurs principaux (ré)attribués');
        }
    }

    // ---------------------------------------------------------------------
    // Notes
    // ---------------------------------------------------------------------

    private function seedGrades(): void
    {
        $definitions = [
            'devoir' => ['title' => 'Devoir n°1', 'date' => '2026-10-16', 'max' => 20, 'weight' => 1],
            'interrogation' => ['title' => 'Interrogation écrite n°1', 'date' => '2026-10-28', 'max' => 10, 'weight' => 1],
            'composition' => ['title' => 'Composition du 1er semestre', 'date' => '2026-11-10', 'max' => 20, 'weight' => 2],
        ];

        foreach ($this->classes->values() as $classIndex => $classe) {
            foreach ($this->classSubjects[$classe->id] as $subjectIndex => $item) {
                $subject = $item['subject'];
                $teacher = $this->teacherFor($classe, $subject);

                if (! $teacher) {
                    continue;
                }

                $types = $item['coefficient'] <= 1 ? ['devoir', 'composition'] : ['devoir', 'interrogation', 'composition'];

                foreach ($types as $type) {
                    $definition = $definitions[$type];
                    $status = $this->evaluationStatus($type, $classIndex, $subjectIndex, $subject->code);
                    $evaluationDate = Carbon::parse($definition['date']);

                    $evaluation = ClassEvaluation::query()->firstOrCreate(
                        [
                            'classe_id' => $classe->id,
                            'subject_id' => $subject->id,
                            'semester_id' => $this->s1->id,
                            'title' => $definition['title'],
                        ],
                        [
                            'teacher_id' => $teacher->id,
                            'type' => $type,
                            'evaluation_date' => $evaluationDate->toDateString(),
                            'max_score' => $definition['max'],
                            'weight' => $definition['weight'],
                            'status' => $status,
                            'submitted_at' => $status === ClassEvaluation::STATUS_DRAFT ? null : $evaluationDate->copy()->addDays(2)->setTime(18, 0),
                            'published_at' => $status === ClassEvaluation::STATUS_PUBLISHED ? $evaluationDate->copy()->addDays(3)->setTime(10, 0) : null,
                            'published_by' => $status === ClassEvaluation::STATUS_PUBLISHED ? $this->admin->id : null,
                            'created_by' => $teacher->id,
                        ]
                    );

                    if ($evaluation->wasRecentlyCreated) {
                        $this->count('évaluations S1 ('.$status.')');
                    }

                    $this->seedEvaluationGrades($evaluation, $classe, $subject, $teacher, (float) $definition['max']);
                }
            }
        }

        $this->seedReportCardComments();
        $this->seedSecondSemesterDrafts();
    }

    private function evaluationStatus(string $type, int $classIndex, int $subjectIndex, string $subjectCode): string
    {
        if ($type !== 'composition' || ($classIndex === 0 && $subjectCode === 'MATH')) {
            return ClassEvaluation::STATUS_PUBLISHED;
        }

        return match (($classIndex + $subjectIndex) % 6) {
            0 => ClassEvaluation::STATUS_SUBMITTED,
            3 => ClassEvaluation::STATUS_DRAFT,
            default => ClassEvaluation::STATUS_PUBLISHED,
        };
    }

    /**
     * Niveau de l'élève (6 à 16, moyenne ≈ 11).
     */
    private function studentAbility(Student $student): float
    {
        return 6 + $this->pick('ability|'.$student->matricule, 1001) / 100;
    }

    private function seedEvaluationGrades(ClassEvaluation $evaluation, Classe $classe, Subject $subject, TenantUser $teacher, float $maxScore): void
    {
        foreach ($this->classStudents[$classe->id] as $student) {
            $key = $student->matricule.'|'.$subject->code.'|'.$evaluation->title;
            $isAbsent = $this->pick('absent|'.$key, 100) < 4;
            $score = null;

            if (! $isAbsent) {
                $subjectBias = $this->pick('bias|'.$student->matricule.'|'.$subject->code, 41) / 10 - 2;
                $noise = $this->pick('noise|'.$key, 61) / 10 - 3;
                $onTwenty = max(0, min(20, $this->studentAbility($student) + $subjectBias + $noise));
                $score = round($onTwenty * $maxScore / 20 * 2) / 2;
            }

            $grade = ClassEvaluationGrade::query()->firstOrCreate(
                ['class_evaluation_id' => $evaluation->id, 'student_id' => $student->id],
                [
                    'score' => $score,
                    'is_absent' => $isAbsent,
                    'comment' => $isAbsent ? 'Absent(e)' : null,
                    'entered_by' => $teacher->id,
                ]
            );

            if ($grade->wasRecentlyCreated) {
                $this->count('notes saisies');
            }
        }
    }

    private function seedReportCardComments(): void
    {
        $targets = $this->classes->take(1)->merge($this->classes->filter(fn (Classe $classe): bool => $classe->level?->code === '3E')->take(1));

        foreach ($targets as $classe) {
            $headTeacherId = $classe->fresh()->head_teacher_id ?? $this->admin->id;
            $mathTeacher = isset($this->subjects['MATH']) ? $this->teacherFor($classe, $this->subjects['MATH']) : null;

            foreach ($this->classStudents[$classe->id] as $student) {
                $ability = $this->studentAbility($student);
                $general = match (true) {
                    $ability >= 14 => 'Excellent trimestre, élève sérieux et moteur de la classe. Félicitations.',
                    $ability >= 11.5 => 'Bon ensemble. Des efforts réguliers qui portent leurs fruits, continuez ainsi.',
                    $ability >= 9 => 'Résultats justes. Il faut davantage de travail personnel pour progresser.',
                    default => 'Trimestre insuffisant. Un sursaut est indispensable ; rencontre avec les parents souhaitée.',
                };

                $this->reportComment($student, null, $general, (int) $headTeacherId);

                if ($mathTeacher && isset($this->subjects['MATH'])) {
                    $mathComment = $ability >= 12 ? 'Bonne maîtrise des notions, participation active.' : 'Des lacunes en calcul ; revoir les exercices du cours.';
                    $this->reportComment($student, $this->subjects['MATH']->id, $mathComment, $mathTeacher->id);
                }
            }
        }
    }

    private function reportComment(Student $student, ?int $subjectId, string $comment, int $authorId): void
    {
        $existing = ReportCardComment::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $this->s1->id)
            ->when($subjectId === null, fn ($query) => $query->whereNull('subject_id'), fn ($query) => $query->where('subject_id', $subjectId))
            ->exists();

        if ($existing) {
            return;
        }

        ReportCardComment::query()->create([
            'student_id' => $student->id,
            'semester_id' => $this->s1->id,
            'subject_id' => $subjectId,
            'comment' => $comment,
            'author_id' => $authorId,
        ]);

        $this->count('appréciations de bulletin');
    }

    private function seedSecondSemesterDrafts(): void
    {
        if (! $this->s2) {
            $this->skipped[] = 'Évaluations S2 — semestre S2 absent';

            return;
        }

        $drafts = [
            'MATH' => ['Devoir n°1 (S2)', 'devoir', '2027-02-12', 20],
            'FRAN' => ['Interrogation écrite n°1 (S2)', 'interrogation', '2027-02-19', 10],
        ];

        foreach ($this->classes->take(3) as $classe) {
            foreach ($drafts as $code => [$title, $type, $date, $max]) {
                $subject = $this->subjects[$code] ?? null;
                $teacher = $subject ? $this->teacherFor($classe, $subject) : null;

                if (! $subject || ! $teacher) {
                    continue;
                }

                $evaluation = ClassEvaluation::query()->firstOrCreate(
                    ['classe_id' => $classe->id, 'subject_id' => $subject->id, 'semester_id' => $this->s2->id, 'title' => $title],
                    [
                        'teacher_id' => $teacher->id,
                        'type' => $type,
                        'evaluation_date' => $date,
                        'max_score' => $max,
                        'weight' => 1,
                        'status' => ClassEvaluation::STATUS_DRAFT,
                        'created_by' => $teacher->id,
                    ]
                );

                if ($evaluation->wasRecentlyCreated) {
                    $this->count('évaluations S2 (draft)');
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // Salles et emploi du temps
    // ---------------------------------------------------------------------

    private function roomCodeFor(Classe $classe): string
    {
        return Str::upper(Str::slug($classe->classroom ?: 'Salle '.$classe->name));
    }

    private function seedRooms(): void
    {
        foreach ($this->classes as $classe) {
            $this->room($this->roomCodeFor($classe), $classe->classroom ?: 'Salle '.$classe->name, 'Salle', 60, 'Salle de classe de la '.$classe->name, ['Tableau', 'Ventilateurs']);
        }

        $this->room('LABO-SCI', 'Laboratoire de sciences', 'Labo', 40, 'Laboratoire de Physique-Chimie et SVT', ['Paillasses', 'Microscopes', 'Hotte']);
        $this->room('SALLE-EXAM', 'Salle d’examen', 'Salle', 120, 'Grande salle pour compositions et examens blancs', ['Tables individuelles', 'Horloge']);
    }

    /**
     * @param  list<string>  $equipment
     */
    private function room(string $code, string $name, string $type, int $capacity, string $description, array $equipment): Room
    {
        $room = Room::query()->withTrashed()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'building' => $type === 'Salle' ? 'Bâtiment principal' : 'Bloc scientifique',
                'floor' => 'RDC',
                'capacity' => $capacity,
                'equipment' => $equipment,
                'is_active' => true,
                'description' => $description,
            ]
        );

        if ($room->wasRecentlyCreated) {
            $this->count('salles');
        }

        return $room;
    }

    /**
     * Classes de démonstration de l'emploi du temps (6E A en premier : semaine complète).
     *
     * @return Collection<int, Classe>
     */
    private function timetableClasses(): Collection
    {
        $wanted = [['6E', 'A'], ['TLE', null], ['6E', 'B'], ['5E', 'A'], ['3E', 'A'], ['2NDE', 'A']];
        $selected = collect();

        foreach ($wanted as [$levelCode, $section]) {
            $classe = $this->classes->first(fn (Classe $classe): bool => $classe->level?->code === $levelCode
                && ($section === null || $classe->section === $section)
                && ! $selected->contains('id', $classe->id)
                && ($section !== null || $classe->series?->code === 'C'));

            if ($classe) {
                $selected->push($classe);
            }
        }

        return $selected;
    }

    private function seedTimetable(): void
    {
        $semesterId = $this->s1->id;
        $teacherBusy = [];
        $roomBusy = [];

        TimetableSlot::query()
            ->where('semester_id', $semesterId)
            ->get(['teacher_id', 'room_id', 'day_of_week', 'start_time'])
            ->each(function (TimetableSlot $slot) use (&$teacherBusy, &$roomBusy): void {
                $teacherBusy[$slot->teacher_id.'|'.$slot->day_of_week.'|'.$slot->start_time] = true;

                if ($slot->room_id) {
                    $roomBusy[$slot->room_id.'|'.$slot->day_of_week.'|'.$slot->start_time] = true;
                }
            });

        $rooms = Room::query()->pluck('id', 'code');

        foreach ($this->timetableClasses() as $classe) {
            $hasSlots = TimetableSlot::query()->where('classe_id', $classe->id)->where('semester_id', $semesterId)->exists();

            if ($hasSlots) {
                continue;
            }

            $roomId = $rooms[$this->roomCodeFor($classe)] ?? null;
            $queue = $this->hourQueue($classe);
            $unplaced = 0;

            foreach (self::DAYS as $day => $slotCount) {
                $subjectsToday = [];

                foreach (array_slice(self::TIME_SLOTS, 0, $slotCount) as [$start, $end]) {
                    if ($queue === []) {
                        break 2;
                    }

                    if ($roomId && isset($roomBusy[$roomId.'|'.$day.'|'.$start])) {
                        continue;
                    }

                    $chosenIndex = $this->chooseQueueItem($queue, $subjectsToday, $teacherBusy, $day, $start);

                    if ($chosenIndex === null) {
                        continue;
                    }

                    $item = $queue[$chosenIndex];
                    array_splice($queue, $chosenIndex, 1);

                    TimetableSlot::query()->create([
                        'module_id' => null,
                        'subject_id' => $item['subject']->id,
                        'teacher_id' => $item['teacher']->id,
                        'group_id' => null,
                        'classe_id' => $classe->id,
                        'room_id' => $roomId,
                        'semester_id' => $semesterId,
                        'day_of_week' => $day,
                        'start_time' => $start,
                        'end_time' => $end,
                        'type' => 'CM',
                        'is_recurring' => true,
                        'notes' => null,
                    ]);

                    $teacherBusy[$item['teacher']->id.'|'.$day.'|'.$start] = true;

                    if ($roomId) {
                        $roomBusy[$roomId.'|'.$day.'|'.$start] = true;
                    }

                    $subjectsToday[$item['subject']->code] = ($subjectsToday[$item['subject']->code] ?? 0) + 1;
                    $this->count('créneaux d’emploi du temps');
                }
            }

            $unplaced = count($queue);

            if ($unplaced > 0) {
                $this->skipped[] = "Emploi du temps {$classe->name} — {$unplaced} heure(s) non placée(s) faute de créneau libre";
            }
        }
    }

    /**
     * Heures de cours à placer, entrelacées par matière (MATH, FRAN, … puis à nouveau MATH, …).
     *
     * @return list<array{subject: Subject, teacher: TenantUser}>
     */
    private function hourQueue(Classe $classe): array
    {
        $items = [];
        $maxHours = max(array_map(fn (array $item): int => $item['hours'], $this->classSubjects[$classe->id]) ?: [0]);

        for ($round = 0; $round < $maxHours; $round++) {
            foreach ($this->classSubjects[$classe->id] as $item) {
                $teacher = $this->teacherFor($classe, $item['subject']);

                if ($teacher && $item['hours'] > $round) {
                    $items[] = ['subject' => $item['subject'], 'teacher' => $teacher];
                }
            }
        }

        return $items;
    }

    /**
     * Premier cours dont le professeur est libre, en évitant plus de 2 heures de la même matière par jour.
     *
     * @param  list<array{subject: Subject, teacher: TenantUser}>  $queue
     * @param  array<string, int>  $subjectsToday
     * @param  array<string, bool>  $teacherBusy
     */
    private function chooseQueueItem(array $queue, array $subjectsToday, array $teacherBusy, string $day, string $start): ?int
    {
        $fallback = null;

        foreach ($queue as $index => $item) {
            if (isset($teacherBusy[$item['teacher']->id.'|'.$day.'|'.$start])) {
                continue;
            }

            $todayCount = $subjectsToday[$item['subject']->code] ?? 0;

            if ($todayCount === 0) {
                return $index;
            }

            if ($todayCount < 2 && $fallback === null) {
                $fallback = $index;
            }
        }

        return $fallback;
    }

    // ---------------------------------------------------------------------
    // Présences
    // ---------------------------------------------------------------------

    private function seedAttendance(): void
    {
        $dayNames = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'];
        $today = Carbon::today();
        $from = $today->copy()->subDays(21);
        $semester = Semester::query()
            ->where('academic_year_id', $this->year->id)
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->first() ?? $this->s1;
        $absentees = [];

        foreach ($this->timetableClasses()->take(3) as $classe) {
            $slots = TimetableSlot::query()
                ->where('classe_id', $classe->id)
                ->where('semester_id', $this->s1->id)
                ->get();

            if ($slots->isEmpty()) {
                $this->skipped[] = "Présences {$classe->name} — aucun créneau d’emploi du temps";

                continue;
            }

            $students = $this->classStudents[$classe->id]->filter(fn (Student $student): bool => $student->user_id !== null)->values();
            $absentee = $students->get(1);

            if ($absentee) {
                $absentees[$classe->id] = $absentee;
            }

            for ($date = $from->copy(); $date->lt($today); $date->addDay()) {
                $dayName = $dayNames[$date->dayOfWeekIso] ?? null;

                if (! $dayName || $date->lt($this->year->start_date)) {
                    continue;
                }

                foreach ($slots->where('day_of_week', $dayName) as $slot) {
                    $this->attendanceSession($classe, $slot, $date->copy(), $students, $absentee);
                }
            }
        }

        $this->seedJustifications($absentees);

        $alerts = app(AttendanceMonitoringService::class)->triggerAutomaticAlerts($semester->id);
        $this->created['alertes d’absentéisme actives (calculées)'] = $alerts->count();
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function attendanceSession(Classe $classe, TimetableSlot $slot, Carbon $date, Collection $students, ?Student $absentee): void
    {
        $startTime = Carbon::parse($slot->start_time)->format('H:i:s');
        $endTime = Carbon::parse($slot->end_time)->format('H:i:s');

        $session = AttendanceSession::query()->firstOrCreate(
            ['classe_id' => $classe->id, 'session_date' => $date->toDateString(), 'start_time' => $startTime],
            [
                'timetable_slot_id' => $slot->id,
                'subject_id' => $slot->subject_id,
                'end_time' => $endTime,
                'status' => 'completed',
                'method' => 'manual',
                'created_by' => $slot->teacher_id,
                'completed_by' => $slot->teacher_id,
                'completed_at' => $date->copy()->setTimeFromTimeString($endTime),
                'notes' => null,
            ]
        );

        if ($session->wasRecentlyCreated) {
            $this->count('séances d’appel');
        }

        foreach ($students as $student) {
            $roll = $this->pick('attendance|'.$student->matricule.'|'.$date->toDateString().'|'.$startTime, 100);
            $isAbsentee = $absentee && $absentee->id === $student->id;
            $status = match (true) {
                $isAbsentee && $roll < 30 => 'absent',
                ! $isAbsentee && $roll < 4 => 'absent',
                $roll >= 93 => 'late',
                default => 'present',
            };
            $delay = $status === 'late' ? 5 + $this->pick('delay|'.$student->matricule.'|'.$date->toDateString(), 16) : 0;

            $record = AttendanceRecord::query()->firstOrCreate(
                ['attendance_session_id' => $session->id, 'student_id' => $student->user_id],
                [
                    'status' => $status,
                    'arrival_time' => $status === 'late' ? Carbon::parse($startTime)->addMinutes($delay)->format('H:i:s') : null,
                    'delay_minutes' => $delay,
                    'notes' => null,
                    'recorded_by' => $slot->teacher_id,
                ]
            );

            if ($record->wasRecentlyCreated) {
                $this->count('lignes d’appel');
            }
        }
    }

    /**
     * Une justification validée (6E A) et deux en attente (autres classes), sur des absences réelles.
     *
     * @param  array<int, Student>  $absentees
     */
    private function seedJustifications(array $absentees): void
    {
        $justificationService = app(JustificationService::class);
        $position = 0;

        foreach ($absentees as $student) {
            $absentDates = AttendanceRecord::query()
                ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
                ->where('attendance_records.student_id', $student->user_id)
                ->whereIn('attendance_records.status', ['absent', 'excused'])
                ->orderBy('attendance_sessions.session_date')
                ->pluck('attendance_sessions.session_date')
                ->map(fn ($date): string => Carbon::parse($date)->toDateString())
                ->unique()
                ->values();

            if ($absentDates->isEmpty()) {
                continue;
            }

            $approve = $position === 0;
            $date = $approve ? $absentDates->first() : $absentDates->last();
            $parentUserId = $student->parents()->first()?->user_id ?? $student->user_id;
            [$type, $reason] = match ($position) {
                0 => ['medical', 'Consultation médicale au CSI (certificat médical joint).'],
                1 => ['family', 'Décès dans la famille, voyage à Zinder.'],
                default => ['other', 'Panne du transport scolaire.'],
            };

            $justification = AbsenceJustification::query()->firstOrCreate(
                ['student_id' => $student->user_id, 'absence_date_from' => $date, 'type' => $type],
                [
                    'absence_date_to' => $date,
                    'reason' => $reason,
                    'document_path' => null,
                    'status' => 'pending',
                    'submitted_by' => $parentUserId,
                ]
            );

            if ($justification->wasRecentlyCreated) {
                $this->count('justificatifs d’absence');

                if ($approve) {
                    $justificationService->validateJustification($justification, 'approved', 'Certificat médical vérifié.', $this->admin->id);
                }
            }

            $position++;
        }
    }

    // ---------------------------------------------------------------------
    // Examens
    // ---------------------------------------------------------------------

    private function seedExams(): void
    {
        ExamSession::flushLyceeColumnsCache();

        $examRoom = Room::query()->where('code', 'SALLE-EXAM')->first();
        $maths = $this->subjects['MATH'] ?? null;
        $firstClass = $this->classes->first();

        if (! $examRoom || ! $maths || ! $firstClass) {
            $this->skipped[] = 'Examens — salle d’examen, matière MATH ou classe absente';

            return;
        }

        $this->seedCompletedComposition($firstClass, $maths, $examRoom);
        $this->seedPlannedMockExam($maths, $examRoom);
    }

    private function seedCompletedComposition(Classe $classe, Subject $maths, Room $examRoom): void
    {
        $evaluation = Schema::connection('tenant')->hasTable('class_evaluations')
            ? ClassEvaluation::query()
                ->where('classe_id', $classe->id)
                ->where('subject_id', $maths->id)
                ->where('semester_id', $this->s1->id)
                ->where('type', 'composition')
                ->first()
            : null;

        $session = ExamSession::query()->firstOrCreate(
            ['title' => 'Composition de Mathématiques — '.$classe->name, 'academic_year_id' => $this->year->id],
            [
                'module_id' => null,
                'subject_id' => $maths->id,
                'classe_id' => $classe->id,
                'level_id' => $classe->level_id,
                'semester_id' => $this->s1->id,
                'description' => 'Composition du 1er semestre.',
                'type' => 'normal',
                'exam_date' => '2026-11-10',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'duration_minutes' => 120,
                'total_capacity' => $examRoom->capacity,
                'status' => 'completed',
                'class_evaluation_ids' => $evaluation ? [$evaluation->id] : null,
                'instructions' => 'Calculatrice non programmable autorisée. Aucune sortie avant 1 heure.',
                'allowed_materials' => ['Calculatrice non programmable', 'Règle', 'Compas'],
                'is_published' => true,
                'published_at' => '2026-11-03 09:00:00',
                'created_by' => $this->admin->id,
            ]
        );

        if ($session->wasRecentlyCreated) {
            $this->count('sessions d’examen');
        }

        $students = $this->classStudents[$classe->id];
        $assignment = $this->examRoomAssignment($session, $examRoom, $students->count());
        $supervisor = $this->teachers['prof.hg'] ?? $this->admin;

        $this->examSupervisor($session, $assignment, $supervisor, 'principal', 'present', '07:45', '10:05');

        $absentIds = $evaluation
            ? ClassEvaluationGrade::query()->where('class_evaluation_id', $evaluation->id)->where('is_absent', true)->pluck('student_id')->all()
            : [];

        foreach ($students->values() as $position => $student) {
            $isAbsent = in_array($student->id, $absentIds, false);

            $sheet = ExamAttendanceSheet::query()->firstOrCreate(
                ['exam_session_id' => $session->id, 'student_id' => $student->id],
                [
                    'exam_room_assignment_id' => $assignment->id,
                    'seat_number' => sprintf('A%02d', $position + 1),
                    'status' => $isAbsent ? 'absent' : 'present',
                    'arrival_time' => $isAbsent ? null : '07:50',
                    'submission_time' => $isAbsent ? null : '09:55',
                    'has_submitted' => ! $isAbsent,
                    'verified_by' => $supervisor->id,
                    'verified_at' => '2026-11-10 10:10:00',
                ]
            );

            if ($sheet->wasRecentlyCreated) {
                $this->count('élèves placés en salle d’examen');
            }
        }

        if ($students->isNotEmpty() && ! ExamIncident::query()->where('exam_session_id', $session->id)->exists()) {
            ExamIncident::query()->create([
                'exam_session_id' => $session->id,
                'student_id' => $students->last()->id,
                'type' => 'disturbance',
                'title' => 'Bavardage',
                'description' => 'Échange de paroles avec un voisin, rappel à l’ordre.',
                'severity' => 'low',
                'occurred_at_time' => '09:10',
                'status' => 'resolved',
                'action_taken' => 'Avertissement oral, changement de place.',
                'reported_by' => $supervisor->id,
                'reviewed_by' => $this->admin->id,
                'reviewed_at' => '2026-11-10 12:00:00',
                'resolution_notes' => 'Incident clos.',
            ]);
            $this->count('incidents d’examen');
        }
    }

    private function seedPlannedMockExam(Subject $maths, Room $examRoom): void
    {
        $level = Level::query()->where('code', '3E')->first();

        if (! $level) {
            $this->skipped[] = 'Examen blanc — niveau 3E absent';

            return;
        }

        $session = ExamSession::query()->firstOrCreate(
            ['title' => 'Examen blanc de Mathématiques — niveau 3ème', 'academic_year_id' => $this->year->id],
            [
                'module_id' => null,
                'subject_id' => $maths->id,
                'classe_id' => null,
                'level_id' => $level->id,
                'semester_id' => $this->s1->id,
                'description' => 'Examen blanc BEPC pour toutes les classes de 3ème.',
                'type' => 'special',
                'exam_date' => '2026-12-10',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'duration_minutes' => 120,
                'total_capacity' => $examRoom->capacity,
                'status' => 'planned',
                'instructions' => 'Se présenter 15 minutes avant le début de l’épreuve avec sa carte scolaire.',
                'allowed_materials' => ['Calculatrice non programmable', 'Règle', 'Équerre'],
                'is_published' => true,
                'published_at' => now(),
                'created_by' => $this->admin->id,
            ]
        );

        if ($session->wasRecentlyCreated) {
            $this->count('sessions d’examen');
        }

        $students = $this->classes
            ->filter(fn (Classe $classe): bool => $classe->level_id === $level->id)
            ->flatMap(fn (Classe $classe): Collection => $this->classStudents[$classe->id])
            ->values();
        $assignment = $this->examRoomAssignment($session, $examRoom, $students->count());

        if (isset($this->teachers['prof.svt'])) {
            $this->examSupervisor($session, $assignment, $this->teachers['prof.svt'], 'principal', 'assigned', null, null);
        }

        if (isset($this->teachers['prof.anglais'])) {
            $this->examSupervisor($session, $assignment, $this->teachers['prof.anglais'], 'assistant', 'assigned', null, null);
        }

        foreach ($students as $position => $student) {
            $sheet = ExamAttendanceSheet::query()->firstOrCreate(
                ['exam_session_id' => $session->id, 'student_id' => $student->id],
                [
                    'exam_room_assignment_id' => $assignment->id,
                    'seat_number' => sprintf('B%02d', $position + 1),
                    'status' => 'present',
                    'has_submitted' => false,
                ]
            );

            if ($sheet->wasRecentlyCreated) {
                $this->count('élèves placés en salle d’examen');
            }
        }
    }

    private function examRoomAssignment(ExamSession $session, Room $room, int $studentsCount): ExamRoomAssignment
    {
        $assignment = ExamRoomAssignment::query()->firstOrCreate(
            ['exam_session_id' => $session->id, 'room_id' => $room->id],
            [
                'capacity' => $room->capacity,
                'assigned_students' => $studentsCount,
                'seat_start_number' => 1,
                'seat_end_number' => max(1, $studentsCount),
            ]
        );

        if (! $assignment->wasRecentlyCreated && (int) $assignment->assigned_students !== $studentsCount) {
            $assignment->update(['assigned_students' => $studentsCount, 'seat_end_number' => max(1, $studentsCount)]);
        }

        return $assignment;
    }

    private function examSupervisor(ExamSession $session, ExamRoomAssignment $assignment, TenantUser $teacher, string $role, string $status, ?string $start, ?string $end): void
    {
        $supervisor = ExamSupervisor::query()->firstOrCreate(
            ['exam_session_id' => $session->id, 'teacher_id' => $teacher->id],
            [
                'exam_room_assignment_id' => $assignment->id,
                'role' => $role,
                'actual_start_time' => $start,
                'actual_end_time' => $end,
                'status' => $status,
                'is_notified' => true,
                'notified_at' => now(),
            ]
        );

        if ($supervisor->wasRecentlyCreated) {
            $this->count('surveillants d’examen');
        }
    }

    // ---------------------------------------------------------------------
    // Finance
    // ---------------------------------------------------------------------

    private function seedFinance(): void
    {
        $cashier = TenantUser::query()->where('username', 'demo.caissier')->first() ?? $this->admin;
        $yearCode = str_replace('-', '', $this->year->name);
        $invoices = Invoice::query()
            ->where('academic_year_id', $this->year->id)
            ->where('invoice_number', 'like', 'DEMO-INV-'.$yearCode.'-%')
            ->orderBy('invoice_number')
            ->get();

        foreach ($invoices as $invoice) {
            $index = (int) Str::afterLast($invoice->invoice_number, '-');
            $payment = $this->plannedPayment($index, (float) $invoice->total_amount, (float) $invoice->paid_amount);

            if ($payment !== null) {
                $created = Payment::query()->firstOrCreate(
                    ['receipt_number' => sprintf('DEMO-ACT-REC-%s-%03d', $yearCode, $index)],
                    [
                        'invoice_id' => $invoice->id,
                        'student_id' => $invoice->student_id,
                        'payment_date' => $payment['date'],
                        'amount' => $payment['amount'],
                        'payment_method' => $payment['method'],
                        'reference_number' => $payment['method'] === 'cash' ? null : sprintf('%s-%06d', Str::upper($payment['method']), 100000 + $index),
                        'notes' => 'Versement de démonstration.',
                        'recorded_by' => $cashier->id,
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $this->count('paiements ajoutés');
                }
            }

            $this->refreshInvoiceStatus($invoice, $index);
        }

        $this->seedCashierClose($cashier, self::FINANCE_CLOSE_DATE);
    }

    /**
     * Versement complémentaire prévu pour la facture n° $index (null = aucun).
     *
     * @return array{amount: float, method: string, date: string}|null
     */
    private function plannedPayment(int $index, float $total, float $alreadyPaid): ?array
    {
        $remaining = $total - $alreadyPaid;

        if ($remaining <= 0) {
            return null;
        }

        return match (true) {
            $index % 8 === 1 => ['amount' => min(20000, $remaining), 'method' => $index % 16 === 1 ? 'bank_transfer' : 'check', 'date' => self::FINANCE_CLOSE_DATE],
            $index % 4 === 2 && $index % 3 !== 0 => ['amount' => min(30000, $remaining), 'method' => $index % 12 === 2 ? 'cash' : 'mobile_money', 'date' => $index % 8 === 2 ? self::FINANCE_CLOSE_DATE : '2026-11-13'],
            $index % 4 === 3 && $index % 3 === 0 => ['amount' => $remaining, 'method' => 'cash', 'date' => self::FINANCE_CLOSE_DATE],
            default => null,
        };
    }

    /**
     * Montant payé = somme des paiements ; quelques factures impayées passent en retard.
     */
    private function refreshInvoiceStatus(Invoice $invoice, int $index): void
    {
        if ($invoice->status === 'cancelled') {
            return;
        }

        $paid = round((float) Payment::query()->where('invoice_id', $invoice->id)->sum('amount'), 2);
        $total = (float) $invoice->total_amount;
        $attributes = ['paid_amount' => $paid];

        if ($paid >= $total) {
            $attributes['status'] = 'paid';
        } elseif ($paid > 0) {
            $attributes['status'] = 'partial';
        } elseif ($index % 3 === 0 || $invoice->status === 'overdue') {
            $attributes['status'] = 'overdue';
            $attributes['due_date'] = $invoice->status === 'overdue' ? $invoice->due_date : '2026-10-31';
        }

        $invoice->fill($attributes);

        if ($invoice->isDirty()) {
            $invoice->save();
            $this->count('factures mises à jour (payée/partielle/en retard)');
        }
    }

    private function seedCashierClose(TenantUser $cashier, string $date): void
    {
        $payments = Payment::query()
            ->where('recorded_by', $cashier->id)
            ->whereDate('payment_date', $date)
            ->get(['payment_method', 'amount']);

        if ($payments->isEmpty()) {
            return;
        }

        $sum = fn (string $method): float => round((float) $payments->where('payment_method', $method)->sum(fn (Payment $payment): float => (float) $payment->amount), 2);
        $cash = $sum('cash');

        $record = CashierCloseRecord::query()->firstOrCreate(
            ['cashier_user_id' => $cashier->id, 'close_date' => $date],
            [
                'total_cash_declared' => $cash,
                'total_cash_system' => $cash,
                'total_cheque' => $sum('check'),
                'total_mobile_money' => $sum('mobile_money'),
                'total_card' => $sum('card'),
                'total_transfer' => $sum('bank_transfer'),
                'variance' => 0,
                'status' => 'closed',
                'notes' => 'Clôture de démonstration : caisse conforme.',
                'closed_at' => $date.' 18:00:00',
            ]
        );

        if ($record->wasRecentlyCreated) {
            $this->count('clôtures de caisse');
        }
    }

    // ---------------------------------------------------------------------
    // Paie
    // ---------------------------------------------------------------------

    private function seedPayroll(): void
    {
        $staff = [];

        foreach (self::TEACHER_USERNAMES as $username) {
            $staff[$username] = ['Enseignement', 'Professeur', 'Professeur de '.$this->teacherSubjectLabel($username), 250000, 18];
        }

        $staff['demo.caissier'] = ['Administration', 'Caissier', 'Caissier principal', 150000, 40];
        $staff['demo.comptable'] = ['Administration', 'Comptable', 'Comptable', 200000, 40];

        $users = TenantUser::query()->whereIn('username', array_keys($staff))->get()->keyBy('username');
        $employeeIds = [];

        foreach ($staff as $username => [$department, $position, $jobTitle, $salary, $weeklyHours]) {
            $user = $users->get($username);

            if (! $user) {
                continue;
            }

            $code = 'EMP-'.Str::upper(str_replace('.', '-', $username));
            $employee = Employee::query()->withTrashed()->firstOrCreate(
                ['employee_code' => $code],
                [
                    'first_name' => $user->firstname,
                    'last_name' => $user->lastname,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'gender' => $user->sex === 'F' ? 'female' : 'male',
                    'marital_status' => 'married',
                    'number_of_dependents' => 2,
                    'city' => 'Niamey',
                    'hire_date' => '2024-09-01',
                    'department' => $department,
                    'position' => $position,
                    'job_title' => $jobTitle,
                    'bank_name' => 'BIA Niger',
                    'bank_account_number' => sprintf('NE%010d', crc32($username) % 10000000000),
                    'status' => 'active',
                ]
            );

            if ($employee->wasRecentlyCreated) {
                $this->count('employés');
            }

            $contract = EmploymentContract::query()->withTrashed()->firstOrCreate(
                ['contract_number' => 'CTR-'.Str::after($code, 'EMP-')],
                [
                    'employee_id' => $employee->id,
                    'contract_type' => 'permanent',
                    'start_date' => '2024-09-01',
                    'weekly_hours' => $weeklyHours,
                    'work_schedule' => $weeklyHours < 40 ? 'part_time' : 'full_time',
                    'base_salary' => $salary,
                    'contract_terms' => 'Contrat à durée indéterminée (démonstration).',
                    'job_description' => $jobTitle,
                    'status' => 'active',
                    'signature_date' => '2024-08-25',
                    'signed_by' => $this->admin->id,
                    'is_renewable' => false,
                ]
            );

            if ($contract->wasRecentlyCreated) {
                $this->count('contrats actifs');
            }

            $component = PayrollComponent::query()->firstOrCreate(
                ['employee_id' => $employee->id, 'code' => 'IND-TRANSPORT'],
                [
                    'name' => 'Indemnité de transport',
                    'component_type' => 'allowance',
                    'category' => 'transport',
                    'calculation_type' => 'fixed',
                    'amount' => 15000,
                    'is_taxable' => false,
                    'is_subject_to_cnss' => false,
                    'frequency' => 'monthly',
                    'is_recurring' => true,
                    'valid_from' => '2024-09-01',
                    'status' => 'active',
                    'approved_by' => $this->admin->id,
                    'approved_at' => now(),
                ]
            );

            if ($component->wasRecentlyCreated) {
                $this->count('éléments de paie');
            }

            $employeeIds[] = $employee->id;
        }

        $this->seedPayrollSettings();
        $this->seedPayrollPeriod($employeeIds);
    }

    /**
     * Paramètres de paie par défaut (Niger, à vérifier par le comptable) si aucune version n'existe.
     * Les périodes déjà calculées ne sont jamais recalculées.
     */
    private function seedPayrollSettings(): void
    {
        if (app(PayrollSettingsService::class)->ensureDefaultVersion('2026-01-01', $this->admin->id)) {
            $this->count('paramètres de paie');
        }
    }

    private function teacherSubjectLabel(string $username): string
    {
        $code = array_search($username, self::SUBJECT_TEACHERS, true);

        return $code !== false && isset($this->subjects[$code]) ? $this->subjects[$code]->name : 'Lycée';
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function seedPayrollPeriod(array $employeeIds): void
    {
        if ($employeeIds === []) {
            return;
        }

        $service = app(PayrollProcessingService::class);
        $period = PayrollPeriod::query()->forYearMonth(2026, 10)->first();

        if (! $period) {
            $period = $service->createPayrollPeriod(2026, 10, Carbon::parse('2026-10-31'));
            $period->update(['name' => 'Octobre 2026']);
            $this->count('périodes de paie');
        }

        if ($period->canBeCalculated()) {
            $period = $service->calculatePayroll($period, Employee::query()->whereIn('id', $employeeIds)->get());
            $period->update(['calculated_by' => $this->admin->id]);
        }

        if ($period->canBeValidated()) {
            $period = $service->validatePayroll($period, $this->admin->id);
        }

        if ($period->isValidated() && ! Payslip::query()->where('payroll_period_id', $period->id)->exists()) {
            $this->count('bulletins de paie', $service->generatePayslips($period, $this->admin->id)->count());
        }
    }

    // ---------------------------------------------------------------------
    // Messagerie
    // ---------------------------------------------------------------------

    private function seedMessaging(): void
    {
        $users = TenantUser::query()
            ->whereIn('username', ['parent.001', 'prof.maths', 'prof.francais', 'demo.admin'])
            ->get()
            ->keyBy('username');
        $parent = $users->get('parent.001');
        $mathTeacher = $users->get('prof.maths');
        $frenchTeacher = $users->get('prof.francais');
        $admin = $users->get('demo.admin');
        $child = Student::query()->whereHas('user', fn ($query) => $query->where('username', 'eleve.001'))->first();

        if ($parent && $mathTeacher) {
            $subject = 'Résultats en mathématiques de '.($child?->firstname ?? 'mon enfant');
            $first = $this->message($parent, $mathTeacher, null, $child, $subject, 'Bonjour Monsieur, mon enfant a eu des difficultés au dernier devoir. Que nous conseillez-vous pour l’aider à la maison ?', now()->subDays(6), true);
            $this->message($mathTeacher, $parent, $first, $child, 'RE: '.$subject, 'Bonjour, merci pour votre message. Je conseille de refaire les exercices du chapitre sur les fractions et de venir au soutien du mercredi matin.', now()->subDays(5), true);
            $this->message($parent, $mathTeacher, $first, $child, 'RE: '.$subject, 'Merci beaucoup, il participera au soutien dès mercredi.', now()->subDays(4), false);
        }

        if ($admin && $mathTeacher) {
            $this->message($admin, $mathTeacher, null, null, 'Conseil de classe du 1er semestre', 'Bonjour, le conseil de classe de vos classes se tiendra fin janvier. Merci de saisir et soumettre toutes vos notes avant le 20 janvier.', now()->subDays(3), false);
        }

        if ($admin && $frenchTeacher) {
            $this->message($admin, $frenchTeacher, null, null, 'Composition de français', 'Bonjour, merci de soumettre la composition de français pour validation afin que je puisse la publier.', now()->subDays(2), false);
        }
    }

    private function message(TenantUser $sender, TenantUser $recipient, ?Message $thread, ?Student $child, string $subject, string $body, Carbon $sentAt, bool $read): Message
    {
        $message = Message::query()->firstOrCreate(
            ['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'subject' => $subject, 'body' => $body],
            [
                'thread_id' => $thread?->id,
                'student_context_id' => $child?->id,
                'read_at' => $read ? $sentAt->copy()->addHours(3) : null,
            ]
        );

        if ($message->wasRecentlyCreated) {
            $message->forceFill(['created_at' => $sentAt, 'updated_at' => $sentAt])->save();
            $this->count('messages');
        }

        return $message;
    }

    // ---------------------------------------------------------------------
    // Documents
    // ---------------------------------------------------------------------

    private function seedDocuments(): void
    {
        $service = app(CertificateService::class);
        $students = Student::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->whereIn('username', ['eleve.001', 'eleve.002', 'eleve.007', 'eleve.013']))
            ->get()
            ->keyBy(fn (Student $student): string => (string) $student->user?->username);

        foreach (['eleve.001', 'eleve.007', 'eleve.013'] as $username) {
            $student = $students->get($username);

            if (! $student || DocumentRequest::query()->where('student_id', $student->id)->where('document_type', 'certificate_schooling')->exists()) {
                continue;
            }

            $request = $service->createCertificateRequest($student, 'certificate_schooling', 'Constitution du dossier de bourse scolaire');
            $document = $service->approveAndGenerateCertificate($request, $this->admin->id, 'Délivrée (démonstration).');
            $document->update(['issued_by' => $this->admin->id]);
            $this->count('attestations de scolarité délivrées');
        }

        $pendingStudent = $students->get('eleve.002');

        if ($pendingStudent && ! DocumentRequest::query()->where('student_id', $pendingStudent->id)->where('document_type', 'certificate_enrollment')->exists()) {
            $service->createCertificateRequest($pendingStudent, 'certificate_enrollment', 'Dossier de transport scolaire');
            $this->count('demandes d’attestation en attente');
        }
    }

    // ---------------------------------------------------------------------
    // Réinscription
    // ---------------------------------------------------------------------

    private function seedReenrollmentCampaign(): void
    {
        $nextYear = AcademicYear::query()->firstOrCreate(
            ['name' => self::NEXT_ACADEMIC_YEAR],
            ['start_date' => '2027-10-01', 'end_date' => '2028-06-30', 'is_active' => false]
        );

        $campaign = ReenrollmentCampaign::query()->firstOrCreate(
            ['name' => 'Réinscriptions '.self::NEXT_ACADEMIC_YEAR],
            [
                'from_academic_year_id' => $this->year->id,
                'to_academic_year_id' => $nextYear->id,
                'start_date' => '2026-11-01',
                'end_date' => '2027-07-15',
                'required_documents' => ['Bulletin du 2nd semestre', 'Photo d’identité', 'Quitus financier'],
                'fees_config' => ['INSCRIPTION' => 10000],
                'min_ects_required' => 0,
                'check_financial_clearance' => true,
                'status' => 'Active',
                'description' => 'Campagne de réinscription pour l’année '.self::NEXT_ACADEMIC_YEAR.' : indiquez si votre enfant poursuit sa scolarité dans l’établissement.',
            ]
        );

        if ($campaign->wasRecentlyCreated) {
            $this->count('campagnes de réinscription');
        }

        if (! Schema::connection('tenant')->hasTable('reenrollment_intentions')) {
            $this->skipped[] = 'Intentions de réinscription — table `reenrollment_intentions` absente (migration 2026_11_14_120000 non exécutée)';

            return;
        }

        foreach ($this->classes->values() as $classIndex => $classe) {
            if ($classe->level?->code === 'TLE') {
                continue;
            }

            foreach ($this->classStudents[$classe->id]->take(2)->values() as $position => $student) {
                $declines = ($classIndex + $position) % 7 === 3;

                $intention = ReenrollmentIntention::query()->firstOrCreate(
                    ['campaign_id' => $campaign->id, 'student_id' => $student->id],
                    [
                        'wants_to_reenroll' => ! $declines,
                        'comment' => $declines ? 'Déménagement prévu à Maradi.' : null,
                        'responded_at' => now()->subDays(1 + ($classIndex % 10)),
                    ]
                );

                if ($intention->wasRecentlyCreated) {
                    $this->count('intentions de réinscription');
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // Fichier des comptes de démo
    // ---------------------------------------------------------------------

    private function writeAccountsFile(): void
    {
        $accounts = [];
        $staff = TenantUser::query()
            ->with('roles')
            ->whereIn('username', array_merge(['demo.admin', 'manager', 'demo.caissier', 'demo.comptable'], self::TEACHER_USERNAMES))
            ->get()
            ->sortBy(fn (TenantUser $user): string => $user->username);

        foreach ($staff as $user) {
            $accounts[] = [
                'username' => $user->username,
                'role' => $user->roles->pluck('name')->implode(', '),
                'password' => Hash::check(self::PASSWORD, (string) $user->password) ? self::PASSWORD : null,
                'full_name' => $user->full_name,
            ];
        }

        $students = Student::query()
            ->with(['user', 'parents.user', 'classEnrollments' => fn ($query) => $query->where('academic_year_id', $this->year->id)->with('classe')])
            ->whereHas('user', fn ($query) => $query->where('username', 'like', 'eleve.%'))
            ->get()
            ->sortBy(fn (Student $student): string => (string) $student->user?->username);

        $parents = [];

        foreach ($students as $student) {
            $classe = $student->classEnrollments->first()?->classe;
            $accounts[] = [
                'username' => $student->user->username,
                'role' => 'Élève',
                'password' => self::PASSWORD,
                'full_name' => $student->full_name,
                'classe' => $classe?->name,
                'student_matricule' => $student->matricule,
            ];

            foreach ($student->parents as $parent) {
                /** @var ParentModel $parent */
                if (! $parent->user || ! str_starts_with((string) $parent->user->username, 'parent.')) {
                    continue;
                }

                $parents[] = [
                    'username' => $parent->user->username,
                    'role' => 'Parent',
                    'password' => self::PASSWORD,
                    'full_name' => trim($parent->firstname.' '.$parent->lastname),
                    'child' => $student->user->username.' — '.$student->full_name.($classe ? ' ('.$classe->name.')' : ''),
                    'student_matricule' => $student->matricule,
                ];
            }
        }

        usort($parents, fn (array $a, array $b): int => strcmp($a['username'], $b['username']));

        $volumes = [];

        foreach ([
            'users', 'classes', 'students', 'teacher_assignments', 'class_evaluations', 'class_evaluation_grades', 'report_card_comments',
            'rooms', 'timetable_slots', 'attendance_sessions', 'attendance_records', 'attendance_justifications', 'attendance_alerts',
            'exam_sessions', 'exam_attendance_sheets', 'invoices', 'payments', 'cashier_close_records', 'employees', 'employment_contracts',
            'payroll_periods', 'payroll_records', 'payslips', 'messages', 'documents', 'document_requests', 'reenrollment_campaigns',
            'reenrollment_intentions',
        ] as $table) {
            $volumes[$table] = Schema::connection('tenant')->hasTable($table)
                ? DB::connection('tenant')->table($table)->count()
                : null;
        }

        $payload = [
            'tenant' => $this->currentTenantId,
            'academic_year' => $this->year->name,
            'generated_at' => now()->toIso8601String(),
            'login' => ['endpoint' => '/api/admin/auth/login', 'application' => 'admin'],
            'superadmin' => [
                'username' => DemoSuperAdminSeeder::USERNAME,
                'password' => DemoSuperAdminSeeder::PASSWORD,
                'login' => '/api/superadmin/auth/login',
                'note' => 'Base centrale : créé par DemoSuperAdminSeeder.',
            ],
            'accounts' => array_merge($accounts, $parents),
            'volumes' => $volumes,
            'created_this_run' => $this->created,
            'skipped' => $this->skipped,
        ];

        $path = self::accountsFilePath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->command?->info('Comptes de démo écrits dans '.$path);
    }
}
