<?php

namespace Tests\Feature\Enrollment;

use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class EnrollmentApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    private Student $student;

    private Programme $programme;

    private Semester $semester;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        // Create base entities
        $this->academicYear = AcademicYear::factory()->create(['is_active' => true]);
        $this->programme = Programme::factory()->create();
        $this->semester = Semester::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S1',
        ]);
        $this->student = Student::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authGetJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function authPutJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    private function authDeleteJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri, $data);
    }

    // ==================== ENROLLMENT CRUD TESTS ====================

    #[Test]
    public function it_can_list_enrollments(): void
    {
        // Create multiple enrollments with different students to avoid unique constraint
        $students = Student::factory()->count(3)->create();
        foreach ($students as $student) {
            StudentEnrollment::factory()->create([
                'student_id' => $student->id,
                'programme_id' => $this->programme->id,
                'semester_id' => $this->semester->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $response = $this->authGetJson('/api/admin/enrollment/enrollments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'student_id',
                        'programme_id',
                        'semester_id',
                        'level',
                        'status',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    #[Test]
    public function it_can_filter_enrollments_by_student(): void
    {
        $student2 = Student::factory()->create();

        StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        StudentEnrollment::factory()->create([
            'student_id' => $student2->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/enrollments?student_id={$this->student->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function it_can_create_enrollment(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/enrollments', [
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'module_ids' => [],
            'auto_enroll_obligatory' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'enrollment' => [
                        'id',
                        'student_id',
                        'programme_id',
                        'semester_id',
                        'level',
                        'status',
                    ],
                    'modules_enrolled_count',
                ],
            ]);

        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'level' => 'L1',
        ], 'tenant');
    }

    #[Test]
    public function it_creates_enrollment_with_auto_enrollment_to_obligatory_modules(): void
    {
        // Create obligatory modules for programme/level
        $obligatoryModule = Module::factory()->create([
            'type' => 'Obligatoire',
            'level' => 'L1',
            'semester' => 'S1',
        ]);
        $optionalModule = Module::factory()->create([
            'type' => 'Optionnel',
            'level' => 'L1',
            'semester' => 'S1',
        ]);

        // Associate modules with programme
        $obligatoryModule->programmes()->attach($this->programme->id);
        $optionalModule->programmes()->attach($this->programme->id);

        $response = $this->authPostJson('/api/admin/enrollment/enrollments', [
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'auto_enroll_obligatory' => true,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(1, $response->json('data.modules_enrolled_count'));

        // Verify obligatory module is enrolled
        $this->assertDatabaseHas('student_module_enrollments', [
            'student_id' => $this->student->id,
            'module_id' => $obligatoryModule->id,
            'is_optional' => false,
        ], 'tenant');

        // Verify optional module is NOT auto-enrolled
        $this->assertDatabaseMissing('student_module_enrollments', [
            'student_id' => $this->student->id,
            'module_id' => $optionalModule->id,
        ], 'tenant');
    }

    #[Test]
    public function it_prevents_duplicate_enrollment(): void
    {
        StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
            'level' => 'L1',
        ]);

        $response = $this->authPostJson('/api/admin/enrollment/enrollments', [
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_can_show_enrollment_details(): void
    {
        $enrollment = StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/enrollments/{$enrollment->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'student_id',
                    'student',
                    'programme_id',
                    'programme',
                    'semester_id',
                    'semester',
                    'academic_year_id',
                    'academic_year',
                    'level',
                    'status',
                ],
            ]);
    }

    #[Test]
    public function it_can_update_enrollment(): void
    {
        $enrollment = StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'Actif',
        ]);

        $response = $this->authPutJson("/api/admin/enrollment/enrollments/{$enrollment->id}", [
            'status' => 'Suspendu',
            'notes' => 'Test note',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('student_enrollments', [
            'id' => $enrollment->id,
            'status' => 'Suspendu',
            'notes' => 'Test note',
        ], 'tenant');
    }

    #[Test]
    public function it_can_delete_enrollment(): void
    {
        $enrollment = StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authDeleteJson("/api/admin/enrollment/enrollments/{$enrollment->id}");

        $response->assertOk();
        $this->assertSoftDeleted('student_enrollments', ['id' => $enrollment->id], 'tenant');
    }

    // ==================== MODULE ENROLLMENT TESTS ====================

    #[Test]
    public function it_can_get_available_modules_for_enrollment(): void
    {
        $module = Module::factory()->create([
            'level' => 'L1',
            'semester' => 'S1',
        ]);
        $module->programmes()->attach($this->programme->id);

        $response = $this->authGetJson("/api/admin/enrollment/enrollments/available-modules?programme_id={$this->programme->id}&level=L1&semester_id={$this->semester->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'credits_ects',
                        'type',
                        'is_obligatory',
                    ],
                ],
                'meta' => [
                    'total_modules',
                    'obligatory_modules',
                    'optional_modules',
                    'obligatory_credits',
                    'optional_credits',
                    'total_credits',
                ],
            ]);
    }

    #[Test]
    public function it_can_add_modules_to_enrollment(): void
    {
        $enrollment = StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $module = Module::factory()->create([
            'level' => 'L1',
            'semester' => 'S1',
        ]);
        $module->programmes()->attach($this->programme->id);

        $response = $this->authPostJson("/api/admin/enrollment/enrollments/{$enrollment->id}/modules", [
            'module_ids' => [$module->id],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'added_modules',
                    'total_credits',
                ],
            ]);

        $this->assertDatabaseHas('student_module_enrollments', [
            'student_id' => $this->student->id,
            'module_id' => $module->id,
            'student_enrollment_id' => $enrollment->id,
        ], 'tenant');
    }

    #[Test]
    public function it_can_remove_modules_from_enrollment(): void
    {
        $enrollment = StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $module = Module::factory()->create(['type' => 'Optionnel']);
        $moduleEnrollment = StudentModuleEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'student_enrollment_id' => $enrollment->id,
            'module_id' => $module->id,
            'semester_id' => $this->semester->id,
            'status' => 'Inscrit',
            'is_optional' => true,  // Must be optional to be removable
        ]);

        $response = $this->authDeleteJson("/api/admin/enrollment/enrollments/{$enrollment->id}/modules", [
            'module_ids' => [$module->id],
        ]);

        $response->assertOk();
        $this->assertSoftDeleted('student_module_enrollments', [
            'id' => $moduleEnrollment->id,
        ], 'tenant');
    }

    #[Test]
    public function it_cannot_remove_obligatory_modules(): void
    {
        $enrollment = StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $module = Module::factory()->create(['type' => 'Obligatoire']);
        StudentModuleEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'student_enrollment_id' => $enrollment->id,
            'module_id' => $module->id,
            'semester_id' => $this->semester->id,
            'status' => 'Inscrit',
            'is_optional' => false,  // Obligatory
        ]);

        $response = $this->authDeleteJson("/api/admin/enrollment/enrollments/{$enrollment->id}/modules", [
            'module_ids' => [$module->id],
        ]);

        $response->assertStatus(207); // Partial success with errors
        $this->assertStringContainsString('obligatoire', strtolower($response->json('data.errors.0') ?? ''));
    }

    #[Test]
    public function it_can_get_student_module_enrollments(): void
    {
        $enrollment = StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $module = Module::factory()->create();
        StudentModuleEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'student_enrollment_id' => $enrollment->id,
            'module_id' => $module->id,
            'semester_id' => $this->semester->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/enrollments/module-enrollments?student_id={$this->student->id}&semester_id={$this->semester->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'student_id',
                        'module_id',
                        'module',
                        'status',
                    ],
                ],
                'meta' => [
                    'total_modules',
                    'total_credits',
                    'by_status',
                ],
            ]);
    }

    #[Test]
    public function it_can_update_module_enrollment_status(): void
    {
        $enrollment = StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $module = Module::factory()->create();
        $moduleEnrollment = StudentModuleEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'student_enrollment_id' => $enrollment->id,
            'module_id' => $module->id,
            'semester_id' => $this->semester->id,
            'status' => 'Inscrit',
        ]);

        $response = $this->authPutJson("/api/admin/enrollment/module-enrollments/{$moduleEnrollment->id}", [
            'status' => 'Validé',
            'notes' => 'Validated by exam',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('student_module_enrollments', [
            'id' => $moduleEnrollment->id,
            'status' => 'Validé',
            'notes' => 'Validated by exam',
        ], 'tenant');
    }

    // ==================== STATISTICS TESTS ====================

    #[Test]
    public function it_can_get_enrollment_statistics(): void
    {
        // Create enrollments with different students to avoid unique constraint
        $students = Student::factory()->count(5)->create();
        foreach ($students as $student) {
            StudentEnrollment::factory()->create([
                'student_id' => $student->id,
                'programme_id' => $this->programme->id,
                'semester_id' => $this->semester->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $response = $this->authGetJson("/api/admin/enrollment/enrollments/statistics?programme_id={$this->programme->id}&semester_id={$this->semester->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_enrollments',
                    'by_level',
                    'by_status',
                ],
            ]);
    }

    #[Test]
    public function it_can_get_students_in_module(): void
    {
        $module = Module::factory()->create();
        $enrollment = StudentEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        StudentModuleEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'student_enrollment_id' => $enrollment->id,
            'module_id' => $module->id,
            'semester_id' => $this->semester->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/enrollments/students-in-module?module_id={$module->id}&semester_id={$this->semester->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'matricule',
                        'full_name',
                        'email',
                    ],
                ],
                'meta' => [
                    'total',
                ],
            ]);
    }

    // ==================== PREREQUISITES TESTS ====================

    #[Test]
    public function it_can_check_module_prerequisites(): void
    {
        $module = Module::factory()->create();

        $response = $this->authPostJson('/api/admin/enrollment/enrollments/check-prerequisites', [
            'student_id' => $this->student->id,
            'module_id' => $module->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'can_enroll',
                    'missing_prerequisites',
                    'already_enrolled',
                    'already_validated',
                ],
            ]);
    }

    // ==================== VALIDATION TESTS ====================

    #[Test]
    public function it_validates_required_fields_for_enrollment_creation(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/enrollments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['student_id', 'programme_id', 'semester_id', 'level']);
    }

    #[Test]
    public function it_validates_level_values(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/enrollments', [
            'student_id' => $this->student->id,
            'programme_id' => $this->programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'INVALID',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['level']);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/enrollment/enrollments');

        $response->assertStatus(401);
    }
}
