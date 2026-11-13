<?php

namespace Tests\Feature\RoleCoverage\Etudiant;

use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Attendance\Entities\AttendanceSession;
use Modules\Documents\Entities\Document;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\InvoiceItem;
use Modules\Finance\Entities\Payment;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ClassEvaluationGrade;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\TestCase;

/**
 * Portail Élève — données réelles et isolation (un élève ne voit que ses propres données).
 */
class StudentPortalDataTest extends TestCase
{
    use RunsStudentPortalMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * @return array{0: TenantUser, 1: Student, 2: string}
     */
    private function studentWithProfile(): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Élève');
        $student = Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $student, $token];
    }

    private function enrollInActiveClass(Student $student): Classe
    {
        $year = AcademicYear::on('tenant')->create([
            'name' => '2040-2041',
            'start_date' => '2040-10-01',
            'end_date' => '2041-06-30',
            'is_active' => true,
        ]);
        $classe = Classe::factory()->create(['academic_year_id' => $year->id, 'name' => '6ème A']);

        ClassEnrollment::on('tenant')->create([
            'student_id' => $student->id,
            'classe_id' => $classe->id,
            'academic_year_id' => $year->id,
            'enrolled_at' => '2026-10-01',
            'assignment_type' => 'Nouvelle inscription',
            'status' => 'Actif',
        ]);

        return $classe;
    }

    #[Test]
    public function my_grades_returns_only_my_published_grades_with_average(): void
    {
        [, $student, $token] = $this->studentWithProfile();
        $classe = $this->enrollInActiveClass($student);
        $classmate = Student::factory()->create();
        ClassEnrollment::on('tenant')->create([
            'student_id' => $classmate->id,
            'classe_id' => $classe->id,
            'academic_year_id' => $classe->academic_year_id,
            'enrolled_at' => '2026-10-01',
            'assignment_type' => 'Nouvelle inscription',
            'status' => 'Actif',
        ]);
        $semester = Semester::factory()->create(['academic_year_id' => $classe->academic_year_id, 'name' => 'S1', 'is_active' => true]);
        $subject = Subject::factory()->create(['name' => 'Mathématiques']);
        $teacher = TenantUser::factory()->create();

        $published = ClassEvaluation::factory()->published()->create([
            'classe_id' => $classe->id, 'subject_id' => $subject->id, 'semester_id' => $semester->id,
            'teacher_id' => $teacher->id, 'title' => 'Devoir 1', 'evaluation_date' => '2026-10-15',
        ]);
        $draft = ClassEvaluation::factory()->create([
            'classe_id' => $classe->id, 'subject_id' => $subject->id, 'semester_id' => $semester->id, 'teacher_id' => $teacher->id,
        ]);
        ClassEvaluationGrade::factory()->create(['class_evaluation_id' => $published->id, 'student_id' => $student->id, 'score' => 14.5]);
        ClassEvaluationGrade::factory()->create(['class_evaluation_id' => $draft->id, 'student_id' => $student->id, 'score' => 2]);
        ClassEvaluationGrade::factory()->create(['class_evaluation_id' => $published->id, 'student_id' => $classmate->id, 'score' => 3]);

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-grades?student_id=999')
            ->assertOk()
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonCount(1, 'data.subjects')
            ->assertJsonPath('data.subjects.0.subject.name', 'Mathématiques')
            ->assertJsonCount(1, 'data.subjects.0.evaluations')
            ->assertJsonPath('data.subjects.0.evaluations.0.id', $published->id)
            ->assertJsonPath('data.subjects.0.evaluations.0.title', 'Devoir 1')
            ->assertJsonPath('data.subjects.0.evaluations.0.score', 14.5)
            ->assertJsonPath('data.subjects.0.evaluations.0.date', '2026-10-15')
            ->assertJsonPath('data.general_average', 14.5)
            ->assertJsonPath('data.rank', 1)
            ->assertJsonPath('meta.student_id', $student->id)
            ->assertJsonPath('meta.semester_id', $semester->id);
    }

    #[Test]
    public function my_attendance_uses_the_student_user_account_and_summarises(): void
    {
        [$user, $student, $token] = $this->studentWithProfile();
        $otherUser = TenantUser::factory()->create();
        $subject = Subject::factory()->create(['name' => 'Français']);

        $session = AttendanceSession::query()->create([
            'classe_id' => Classe::factory()->create()->id,
            'subject_id' => $subject->id,
            'session_date' => now()->subDays(2)->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => 'completed',
            'method' => 'manual',
            'created_by' => $otherUser->id,
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $user->id,
            'status' => 'absent',
            'recorded_by' => $otherUser->id,
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $otherUser->id,
            'status' => 'late',
            'recorded_by' => $otherUser->id,
        ]);

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-attendance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'absent')
            ->assertJsonPath('data.0.status_label', 'Absent')
            ->assertJsonPath('data.0.subject', 'Français')
            ->assertJsonPath('meta.student_id', $student->id)
            ->assertJsonPath('meta.summary.absent', 1)
            ->assertJsonPath('meta.summary.late', 0);

        $this->withToken($token)
            ->getJson('/api/frontend/student/dashboard')
            ->assertOk()
            ->assertJsonPath('data.recent_absences_count', 1);
    }

    #[Test]
    public function my_invoices_returns_only_my_invoices_with_payments_and_totals(): void
    {
        [, $student, $token] = $this->studentWithProfile();
        $academicYearId = AcademicYear::factory()->create()->id;

        $invoice = Invoice::query()->create([
            'student_id' => $student->id,
            'invoice_number' => 'INV-STU-001',
            'academic_year_id' => $academicYearId,
            'invoice_date' => '2026-10-01',
            'due_date' => '2026-11-30',
            'total_amount' => 72500,
            'paid_amount' => 25000,
            'status' => 'partial',
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Scolarité annuelle',
            'quantity' => 1,
            'unit_price' => 72500,
            'amount' => 72500,
        ]);
        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'payment_date' => '2026-10-10',
            'amount' => 25000,
            'payment_method' => 'mobile_money',
            'receipt_number' => 'REC-STU-001',
        ]);
        Invoice::query()->create([
            'student_id' => Student::factory()->create()->id,
            'invoice_number' => 'INV-OTHER-001',
            'academic_year_id' => $academicYearId,
            'invoice_date' => '2026-10-01',
            'due_date' => '2026-11-30',
            'total_amount' => 5000,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_number', 'INV-STU-001')
            ->assertJsonPath('data.0.balance', 47500)
            ->assertJsonPath('data.0.items.0.description', 'Scolarité annuelle')
            ->assertJsonPath('data.0.payments.0.receipt_number', 'REC-STU-001')
            ->assertJsonPath('meta.total_balance', 47500);

        $this->withToken($token)
            ->getJson('/api/frontend/student/dashboard')
            ->assertOk()
            ->assertJsonPath('data.pending_invoices_count', 1)
            ->assertJsonPath('data.balance_due', 47500);
    }

    #[Test]
    public function my_documents_returns_only_my_issued_documents(): void
    {
        [, $student, $token] = $this->studentWithProfile();
        $academicYearId = AcademicYear::factory()->create()->id;

        $issued = Document::factory()->create([
            'student_id' => $student->id,
            'document_type' => 'certificate_enrollment',
            'status' => 'issued',
            'academic_year_id' => $academicYearId,
        ]);
        Document::factory()->create([
            'student_id' => $student->id,
            'status' => 'cancelled',
            'academic_year_id' => $academicYearId,
        ]);
        Document::factory()->create([
            'student_id' => Student::factory()->create()->id,
            'status' => 'issued',
            'academic_year_id' => $academicYearId,
        ]);

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $issued->id)
            ->assertJsonPath('data.0.source', 'official')
            ->assertJsonPath('meta.student_id', $student->id);
    }

    #[Test]
    public function my_timetable_returns_slots_of_my_current_class_only(): void
    {
        [, $student, $token] = $this->studentWithProfile();
        $classe = $this->enrollInActiveClass($student);
        $subject = Subject::factory()->create(['name' => 'Physique']);
        $semester = Semester::factory()->create(['academic_year_id' => $classe->academic_year_id]);

        TimetableSlot::factory()->create([
            'classe_id' => $classe->id,
            'subject_id' => $subject->id,
            'semester_id' => $semester->id,
            'day_of_week' => 'Mardi',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
        ]);
        TimetableSlot::factory()->create([
            'classe_id' => $classe->id,
            'subject_id' => $subject->id,
            'semester_id' => $semester->id,
            'day_of_week' => 'Lundi',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);
        TimetableSlot::factory()->create([
            'classe_id' => Classe::factory()->create(['academic_year_id' => $classe->academic_year_id])->id,
            'semester_id' => $semester->id,
            'day_of_week' => 'Lundi',
        ]);

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-timetable')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.day_of_week', 'Lundi')
            ->assertJsonPath('data.0.start_time', '08:00')
            ->assertJsonPath('data.0.subject', 'Physique')
            ->assertJsonPath('data.1.day_of_week', 'Mardi')
            ->assertJsonPath('meta.classe.id', $classe->id)
            ->assertJsonPath('meta.classe.name', '6ème A');
    }

    #[Test]
    public function my_timetable_is_empty_without_current_class(): void
    {
        [, $student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-timetable')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.student_id', $student->id)
            ->assertJsonPath('meta.classe', null);
    }

    #[Test]
    public function dashboard_exposes_current_class_and_empty_kpis_for_new_student(): void
    {
        [, $student, $token] = $this->studentWithProfile();
        $classe = $this->enrollInActiveClass($student);

        $this->withToken($token)
            ->getJson('/api/frontend/student/dashboard')
            ->assertOk()
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.classe.id', $classe->id)
            ->assertJsonPath('data.moyenne_actuelle', null)
            ->assertJsonPath('data.next_class', null)
            ->assertJsonPath('data.recent_absences_count', 0)
            ->assertJsonPath('data.pending_invoices_count', 0)
            ->assertJsonPath('data.available_documents_count', 0);
    }

    #[Test]
    public function my_card_reports_not_issued_when_no_card_exists(): void
    {
        [, $student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-card')
            ->assertOk()
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.card_status', 'not_issued')
            ->assertJsonPath('data.card_number', null);
    }

    #[Test]
    public function parent_cannot_read_student_portal_invoices(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-invoices')
            ->assertForbidden();
    }
}
