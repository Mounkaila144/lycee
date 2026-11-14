<?php

namespace Tests\Feature;

use Database\Seeders\DemoSchoolActivitySeeder;
use Database\Seeders\DemoSchoolSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class DemoSchoolActivitySeederTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as baseRunMigrationsOnce;
    }

    /**
     * Tables dont le nombre de lignes ne doit pas changer au second passage.
     */
    private const COUNTED_TABLES = [
        'users', 'teacher_assignments', 'class_evaluations', 'class_evaluation_grades', 'report_card_comments',
        'rooms', 'timetable_slots', 'attendance_sessions', 'attendance_records', 'attendance_justifications',
        'attendance_alerts', 'exam_sessions', 'exam_room_assignments', 'exam_supervisors', 'exam_attendance_sheets',
        'exam_incidents', 'invoices', 'payments', 'cashier_close_records', 'employees', 'employment_contracts',
        'payroll_components', 'payroll_periods', 'payroll_records', 'payslips', 'messages', 'documents',
        'document_requests', 'reenrollment_campaigns', 'reenrollment_intentions', 'academic_years',
    ];

    protected static bool $activityMigrationsRan = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$activityMigrationsRan = false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        Storage::fake('tenant');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function runMigrationsOnce(): void
    {
        $this->baseRunMigrationsOnce();

        if (self::$activityMigrationsRan) {
            return;
        }

        foreach ([
            'Modules/NotesEvaluations/Database/Migrations/tenant',
            'Modules/Timetable/Database/Migrations/tenant',
            'Modules/Attendance/Database/Migrations/tenant',
            'Modules/Documents/Database/Migrations/tenant',
            'Modules/Exams/Database/Migrations/tenant',
            'Modules/Payroll/Database/Migrations/tenant',
            'Modules/Enrollment/Database/Migrations/tenant',
        ] as $path) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        self::$activityMigrationsRan = true;
    }

    private function table(string $table): \Illuminate\Database\Query\Builder
    {
        return DB::connection('tenant')->table($table);
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        return collect(self::COUNTED_TABLES)->mapWithKeys(fn (string $table): array => [$table => $this->table($table)->count()])->all();
    }

    private function getAs(string $username, string $uri): TestResponse
    {
        $user = TenantUser::query()->where('username', $username)->firstOrFail();

        return $this->withToken($user->createToken('test-token')->plainTextToken)->getJson($uri);
    }

    private function firstClass(): Classe
    {
        return Classe::query()->orderBy('id')->firstOrFail();
    }

    private function s1(): Semester
    {
        return Semester::query()->where('name', 'S1')->whereHas('academicYear', fn ($query) => $query->where('name', '2026-2027'))->firstOrFail();
    }

    #[Test]
    public function it_seeds_the_demo_school_activity_idempotently(): void
    {
        $this->seed(DemoSchoolSeeder::class);
        $this->seed(DemoSchoolActivitySeeder::class);

        $firstRun = $this->tableCounts();

        $this->seed(DemoSchoolSeeder::class);
        $this->seed(DemoSchoolActivitySeeder::class);

        $this->assertSame($firstRun, $this->tableCounts(), 'Le second passage ne doit créer aucun doublon.');

        // Professeurs : 4 existants + 4 ajoutés, une affectation par matière de chaque classe.
        foreach (['prof.anglais', 'prof.pc', 'prof.philo', 'prof.eps'] as $username) {
            $this->assertTrue(TenantUser::query()->where('username', $username)->firstOrFail()->hasRole('Professeur'));
        }
        $this->assertSame(120, $firstRun['teacher_assignments']);
        $mathsTeacherId = TenantUser::query()->where('username', 'prof.maths')->value('id');
        $this->assertSame(16, $this->table('teacher_assignments')->where('teacher_id', $mathsTeacherId)->count());

        // Chaque classe a un professeur principal qui y enseigne.
        foreach (Classe::query()->get() as $classe) {
            $this->assertTrue(
                $this->table('teacher_assignments')->where('classe_id', $classe->id)->where('teacher_id', $classe->head_teacher_id)->exists(),
                "Le PP de {$classe->name} doit enseigner dans la classe."
            );
        }

        // Notes : les trois statuts sont représentés au S1, brouillons seulement au S2.
        $s1 = $this->s1();
        $statuses = $this->table('class_evaluations')->where('semester_id', $s1->id)->pluck('status')->countBy();
        $this->assertGreaterThan(0, $statuses['published'] ?? 0);
        $this->assertGreaterThan(0, $statuses['submitted'] ?? 0);
        $this->assertGreaterThan(0, $statuses['draft'] ?? 0);
        $this->assertGreaterThan($statuses['submitted'] + $statuses['draft'], $statuses['published']);
        $this->assertSame(['draft'], $this->table('class_evaluations')->where('semester_id', '!=', $s1->id)->distinct()->pluck('status')->all());
        $this->assertGreaterThan(0, $this->table('class_evaluation_grades')->where('is_absent', true)->count());
        $average = (float) $this->table('class_evaluation_grades')
            ->join('class_evaluations', 'class_evaluations.id', '=', 'class_evaluation_grades.class_evaluation_id')
            ->whereNotNull('score')
            ->selectRaw('AVG(score / max_score * 20) as average')
            ->value('average');
        $this->assertEqualsWithDelta(11, $average, 1.5);
        $this->assertGreaterThan(0, $firstRun['report_card_comments']);

        // Salles et emploi du temps : 6E A complète (22 h), aucun conflit de professeur ni de salle.
        $this->assertSame(18, $firstRun['rooms']);
        $this->assertSame(22, $this->table('timetable_slots')->where('classe_id', $this->firstClass()->id)->count());
        $this->assertGreaterThanOrEqual(6, $this->table('timetable_slots')->distinct()->count('classe_id'));
        $this->assertSame(0, $this->table('timetable_slots')->select('teacher_id', 'day_of_week', 'start_time')->groupBy('teacher_id', 'day_of_week', 'start_time')->havingRaw('COUNT(*) > 1')->get()->count());
        $this->assertSame(0, $this->table('timetable_slots')->select('room_id', 'day_of_week', 'start_time')->groupBy('room_id', 'day_of_week', 'start_time')->havingRaw('COUNT(*) > 1')->get()->count());

        // Présences : séances des 3 dernières semaines, une justification validée, deux en attente.
        $this->assertGreaterThan(0, $firstRun['attendance_sessions']);
        $this->assertGreaterThan(0, $this->table('attendance_records')->where('status', 'absent')->count());
        $this->assertSame(1, $this->table('attendance_justifications')->where('status', 'approved')->count());
        $this->assertSame(2, $this->table('attendance_justifications')->where('status', 'pending')->count());
        $this->assertGreaterThan(0, $this->table('attendance_records')->where('status', 'excused')->count());
        $this->assertGreaterThan(0, $firstRun['attendance_alerts']);

        // Examens : une composition terminée (6E A, Maths) et un examen blanc planifié (niveau 3E).
        $completed = $this->table('exam_sessions')->where('status', 'completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame($this->firstClass()->id, (int) $completed->classe_id);
        $this->assertNotEmpty(json_decode((string) $completed->class_evaluation_ids, true));
        $this->assertSame(6, $this->table('exam_attendance_sheets')->where('exam_session_id', $completed->id)->count());
        $this->assertSame(1, $this->table('exam_supervisors')->where('exam_session_id', $completed->id)->count());
        $planned = $this->table('exam_sessions')->where('status', 'planned')->first();
        $this->assertNotNull($planned);
        $this->assertNull($planned->classe_id);
        $this->assertSame(12, $this->table('exam_attendance_sheets')->where('exam_session_id', $planned->id)->count());

        // Finance : paiements variés, factures en retard, clôture de caisse du caissier de démo.
        $this->assertGreaterThan(48, $firstRun['payments']);
        $this->assertGreaterThanOrEqual(3, $this->table('payments')->distinct()->count('payment_method'));
        $this->assertGreaterThan(0, $this->table('invoices')->where('status', 'overdue')->count());
        $close = $this->table('cashier_close_records')->first();
        $this->assertSame(TenantUser::query()->where('username', 'demo.caissier')->value('id'), (int) $close->cashier_user_id);
        $this->assertGreaterThan(0, (float) $close->total_cash_system);
        $mismatched = $this->table('invoices')
            ->get()
            ->filter(fn ($invoice): bool => abs((float) $invoice->paid_amount - (float) $this->table('payments')->where('invoice_id', $invoice->id)->whereNull('deleted_at')->sum('amount')) > 0.01);
        $this->assertCount(0, $mismatched, 'paid_amount doit refléter la somme des paiements.');

        // Paie : 10 employés sous contrat actif, période d'octobre 2026 calculée et validée.
        $this->assertSame(10, $firstRun['employees']);
        $this->assertSame(10, $this->table('employment_contracts')->where('status', 'active')->count());
        $period = $this->table('payroll_periods')->where('year', 2026)->where('month', 10)->first();
        $this->assertSame('validated', $period->status);
        $this->assertSame(10, $this->table('payroll_records')->where('payroll_period_id', $period->id)->count());
        $this->assertGreaterThan(0, (float) $period->total_net_salary);

        // Messagerie, documents, réinscription.
        $this->assertSame(5, $firstRun['messages']);
        $this->assertSame(3, $this->table('documents')->where('document_type', 'certificate_schooling')->where('status', 'issued')->count());
        $this->assertSame(1, $this->table('document_requests')->where('status', 'pending')->count());
        $this->assertSame(1, $this->table('reenrollment_campaigns')->where('status', 'Active')->count());
        $this->assertGreaterThan(0, $firstRun['reenrollment_intentions']);

        // Fichier des comptes de démo.
        $payload = json_decode((string) file_get_contents(DemoSchoolActivitySeeder::accountsFilePath()), true);
        $accounts = collect($payload['accounts'])->keyBy('username');
        $this->assertSame('Professeur', $accounts['prof.pc']['role']);
        $this->assertSame('password', $accounts['prof.pc']['password']);
        $this->assertSame($this->firstClass()->name, $accounts['eleve.001']['classe']);
        $this->assertSame($accounts['eleve.001']['student_matricule'], $accounts['parent.001']['student_matricule']);
        $this->assertStringStartsWith('eleve.001', $accounts['parent.001']['child']);
        $this->assertSame(96, $accounts->where('role', 'Élève')->count());
        $this->assertSame($this->table('class_evaluations')->count(), $payload['volumes']['class_evaluations']);
        $this->assertSame([], $payload['skipped']);
    }

    #[Test]
    public function seeded_data_is_usable_by_admin_teacher_and_student(): void
    {
        $this->seed(DemoSchoolSeeder::class);
        $this->seed(DemoSchoolActivitySeeder::class);

        $classe = $this->firstClass();
        $s1 = $this->s1();

        $results = $this->getAs('demo.admin', "/api/admin/lycee-grades/results/classes/{$classe->id}?semester_id={$s1->id}")
            ->assertOk()
            ->assertJsonCount(6, 'data.students');
        foreach ($results->json('data.students') as $row) {
            $this->assertNotNull($row['general_average']);
            $this->assertNotNull($row['rank']);
        }
        $this->assertNotNull($results->json('data.stats.class_average'));

        $mine = $this->getAs('prof.maths', '/api/admin/lycee-grades/assignments/mine')->assertOk();
        $this->assertCount(16, $mine->json('data'));
        $this->assertSame(['MATH'], collect($mine->json('data'))->pluck('subject.code')->unique()->values()->all());

        $grades = $this->getAs('eleve.001', '/api/frontend/student/my-grades')->assertOk();
        $this->assertNotNull($grades->json('data.general_average'));
        $evaluationIds = collect($grades->json('data.subjects'))->flatMap(fn (array $subject): array => array_column($subject['evaluations'], 'id'));
        $this->assertNotEmpty($evaluationIds);
        $this->assertSame(
            $evaluationIds->count(),
            $this->table('class_evaluations')->whereIn('id', $evaluationIds->all())->where('status', 'published')->count(),
            'L’élève ne voit que des évaluations publiées.'
        );
    }
}
