<?php

namespace Tests\Feature\Enrollment;

use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentAuditLog;
use Modules\UsersGuard\Entities\TenantUser as User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentUpdateApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        // Disable tenancy middleware (required for tests)
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        // Create user and REAL token (TenantSanctumAuth requires Bearer token)
        $this->seedRolesAndPermissions();
        $this->user = User::factory()->create();
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Make an authenticated JSON GET request
     */
    private function authGetJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    /**
     * Make an authenticated JSON PUT request
     */
    private function authPutJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    #[Test]
    public function it_can_update_student_modifiable_fields(): void
    {
        $student = Student::factory()->create([
            'quarter' => 'Original Quarter',
            'address' => '123 Original St',
            'city' => 'Original City',
            'phone' => '+22712345678',
            'emergency_contact_name' => 'Original Contact',
            'emergency_contact_phone' => '+22787654321',
        ]);

        $response = $this->authPutJson("/api/admin/enrollment/students/{$student->id}", [
            'quarter' => 'Updated Quarter',
            'address' => '456 Updated Ave',
            'city' => 'Updated City',
            'phone' => '+22798765432',
            'emergency_contact_name' => 'Updated Contact',
            'emergency_contact_phone' => '+22711111111',
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => 'Dossier étudiant mis à jour avec succès',
        ]);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'quarter' => 'Updated Quarter',
            'address' => '456 Updated Ave',
            'city' => 'Updated City',
            'phone' => '+22798765432',
            'emergency_contact_name' => 'Updated Contact',
            'emergency_contact_phone' => '+22711111111',
        ], 'tenant');
    }

    #[Test]
    public function it_creates_audit_log_when_updating_student(): void
    {
        $student = Student::factory()->create([
            'quarter' => 'Original Quarter',
        ]);

        // Clear any existing audit logs from creation
        StudentAuditLog::on('tenant')->where('student_id', $student->id)->delete();

        $response = $this->authPutJson("/api/admin/enrollment/students/{$student->id}", [
            'quarter' => 'Updated Quarter',
        ]);

        $response->assertOk();

        // Verify audit log was created
        $this->assertDatabaseHas('student_audit_logs', [
            'student_id' => $student->id,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'field_name' => 'quarter',
            'old_value' => 'Original Quarter',
            'new_value' => 'Updated Quarter',
        ], 'tenant');
    }

    #[Test]
    public function it_prevents_updating_immutable_fields(): void
    {
        $student = Student::factory()->create([
            'firstname' => 'Original',
            'lastname' => 'Name',
            'birthdate' => '2000-01-01',
            'matricule' => '2024-TEST-001',
        ]);

        $response = $this->authPutJson("/api/admin/enrollment/students/{$student->id}", [
            'firstname' => 'Attempted',
            'lastname' => 'Change',
            'birthdate' => '2001-02-02',
            'matricule' => '2024-CHANGED-999',
            'quarter' => 'Allowed Quarter', // This should work
        ]);

        $response->assertOk();

        // Verify immutable fields were NOT changed
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'firstname' => 'Original',
            'lastname' => 'Name',
            'birthdate' => '2000-01-01',
            'matricule' => '2024-TEST-001',
            'quarter' => 'Allowed Quarter', // But this should be updated
        ], 'tenant');
    }

    #[Test]
    public function it_ignores_legacy_email_field(): void
    {
        // La colonne email a été retirée de `students` (migration align_students_to_secondaire).
        $student = Student::factory()->create();

        $response = $this->authPutJson("/api/admin/enrollment/students/{$student->id}", [
            'email' => 'existing@example.com',
            'mobile' => '+22790000000',
            'country' => 'Niger',
        ]);

        $response->assertOk();
    }

    #[Test]
    public function it_can_retrieve_audit_log_for_student(): void
    {
        $student = Student::factory()->create();

        // Create some audit logs
        StudentAuditLog::factory()->count(3)->create([
            'student_id' => $student->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/students/{$student->id}/audit-log");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'student_id',
                    'user_id',
                    'event',
                    'field_name',
                    'old_value',
                    'new_value',
                    'created_at',
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
    public function it_can_filter_audit_log_by_event(): void
    {
        $student = Student::factory()->create();

        // Create different event types
        StudentAuditLog::factory()->create([
            'student_id' => $student->id,
            'event' => 'created',
        ]);
        StudentAuditLog::factory()->create([
            'student_id' => $student->id,
            'event' => 'updated',
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/students/{$student->id}/audit-log?event=updated");

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('updated', $response->json('data.0.event'));
    }

    #[Test]
    public function it_tracks_multiple_field_changes_separately(): void
    {
        $student = Student::factory()->create([
            'quarter' => 'Old Quarter',
            'city' => 'Old City',
            'phone' => '+22712345678',
        ]);

        // Clear any existing audit logs
        StudentAuditLog::on('tenant')->where('student_id', $student->id)->delete();

        $response = $this->authPutJson("/api/admin/enrollment/students/{$student->id}", [
            'quarter' => 'New Quarter',
            'city' => 'New City',
            'phone' => '+22787654321',
        ]);

        $response->assertOk();

        // Verify each field change has its own audit log entry
        $auditLogs = StudentAuditLog::on('tenant')
            ->where('student_id', $student->id)
            ->where('event', 'updated')
            ->get();

        $this->assertCount(3, $auditLogs);

        $fieldNames = $auditLogs->pluck('field_name')->toArray();
        $this->assertContains('quarter', $fieldNames);
        $this->assertContains('city', $fieldNames);
        $this->assertContains('phone', $fieldNames);
    }

    #[Test]
    public function it_validates_blood_group(): void
    {
        $student = Student::factory()->create();

        $response = $this->authPutJson("/api/admin/enrollment/students/{$student->id}", [
            'blood_group' => 'Z+',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['blood_group']);
    }

    #[Test]
    public function it_validates_phone_format(): void
    {
        $student = Student::factory()->create();

        $response = $this->authPutJson("/api/admin/enrollment/students/{$student->id}", [
            'phone' => 'invalid-phone',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    #[Test]
    public function it_requires_authentication_to_update_student(): void
    {
        $student = Student::factory()->create();

        $response = $this->putJson("/api/admin/enrollment/students/{$student->id}", [
            'city' => 'Dosso',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_requires_authentication_to_view_audit_log(): void
    {
        $student = Student::factory()->create();

        $response = $this->getJson("/api/admin/enrollment/students/{$student->id}/audit-log");

        $response->assertStatus(401);
    }
}
